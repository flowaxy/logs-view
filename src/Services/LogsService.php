<?php

/**
 * Сервис для работы с логами
 * Использует классы File и Directory из engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../engine/infrastructure/filesystem/File.php';
require_once __DIR__ . '/../../../../engine/infrastructure/filesystem/Directory.php';

class LogsService
{
    private string $logsDir;
    private Directory $directory;

    public function __construct(?string $logsDir = null)
    {
        if ($logsDir === null) {
            $this->logsDir = defined('LOGS_DIR') 
                ? rtrim(LOGS_DIR, '/\\') . '/' 
                : (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 4)) . '/storage/logs/';
        } else {
            $this->logsDir = rtrim($logsDir, '/\\') . '/';
        }

        $this->directory = new \Engine\Classes\Files\Directory($this->logsDir);
    }

    /**
     * Получить директорию логов
     */
    public function getLogsDir(): string
    {
        return $this->logsDir;
    }

    /**
     * Получить список файлов логов
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLogFiles(): array
    {
        if (! $this->directory->exists()) {
            return [];
        }

        $files = [];
        $filePaths = $this->directory->getFiles(false, '*.log');
        
        foreach ($filePaths as $filePath) {
            $file = new File($filePath);
            
            if (! $file->exists() || ! $file->isReadable()) {
                continue;
            }

            $modifiedTime = $file->getMTime();
            $files[] = [
                'name' => basename($filePath),
                'size' => $file->getSize(),
                'size_formatted' => $this->formatFileSize($file->getSize()),
                'modified' => $modifiedTime ? date('Y-m-d H:i:s', $modifiedTime) : '',
                'modified_timestamp' => $modifiedTime ?: 0,
            ];
        }

        // Сортируем по дате модификации (новые сначала)
        usort($files, function ($a, $b) {
            return $b['modified_timestamp'] - $a['modified_timestamp'];
        });

        return $files;
    }

    /**
     * Получить содержимое лог-файла
     *
     * @param string $fileName Имя файла
     * @param array<string, mixed> $filters Фильтры
     * @param int $limit Лимит записей
     * @return array<string, mixed>
     */
    public function getLogContent(string $fileName, array $filters = [], int $limit = 50): array
    {
        $filePath = $this->logsDir . basename($fileName);

        // Проверка безопасности
        if (! $this->isValidLogFile($filePath)) {
            return [
                'lines' => [],
                'total_lines' => 0,
                'file' => null,
                'error' => 'Файл не знайдено або недоступний',
            ];
        }

        $file = new File($filePath);
        
        if (! $file->exists() || ! $file->isReadable()) {
            return [
                'lines' => [],
                'total_lines' => 0,
                'file' => null,
                'error' => 'Файл недоступний для читання',
            ];
        }

        try {
            $content = $file->read();
            $entries = $this->parseLogFile($content);
            
            // Применяем фильтры
            $entries = $this->applyFilters($entries, $filters);
            
            // Применяем лимит
            if ($limit > 0 && $limit < PHP_INT_MAX) {
                $entries = array_slice($entries, -$limit);
            }
            
            // Переворачиваем, чтобы новые записи были первыми
            $entries = array_reverse($entries);

            return [
                'lines' => $entries,
                'total_lines' => count($this->parseLogFile($content)),
                'file' => basename($fileName),
            ];
        } catch (\Exception $e) {
            return [
                'lines' => [],
                'total_lines' => 0,
                'file' => null,
                'error' => 'Помилка читання файлу: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Удалить лог-файл
     *
     * @param string $fileName Имя файла или 'all' для удаления всех
     * @return array<string, mixed> Результат операции
     */
    public function deleteLogFile(string $fileName): array
    {
        if ($fileName === 'all') {
            return $this->deleteAllLogFiles();
        }

        $filePath = $this->logsDir . basename($fileName);

        if (! $this->isValidLogFile($filePath)) {
            return [
                'success' => false,
                'message' => 'Файл не знайдено або недоступний',
            ];
        }

        $file = new File($filePath);
        
        if (! $file->exists()) {
            return [
                'success' => false,
                'message' => 'Файл не існує',
            ];
        }

        try {
            if ($file->delete()) {
                return [
                    'success' => true,
                    'message' => 'Файл успішно видалено',
                ];
            }

            return [
                'success' => false,
                'message' => 'Не вдалося видалити файл',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Помилка: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Удалить все лог-файлы
     *
     * @return array<string, mixed>
     */
    private function deleteAllLogFiles(): array
    {
        $files = $this->getLogFiles();
        $deleted = 0;
        $errors = [];

        foreach ($files as $fileInfo) {
            $filePath = $this->logsDir . $fileInfo['name'];
            $file = new File($filePath);
            
            try {
                if ($file->delete()) {
                    $deleted++;
                } else {
                    $errors[] = $fileInfo['name'];
                }
            } catch (\Exception $e) {
                $errors[] = $fileInfo['name'];
            }
        }

        return [
            'success' => $deleted > 0,
            'deleted' => $deleted,
            'total' => count($files),
            'errors' => $errors,
            'message' => $deleted > 0 
                ? "Видалено {$deleted} з " . count($files) . " файлів"
                : 'Не вдалося видалити файли',
        ];
    }

    /**
     * Проверка валидности файла лога
     */
    private function isValidLogFile(string $filePath): bool
    {
        if (! file_exists($filePath) || ! is_file($filePath)) {
            return false;
        }

        $realPath = realpath($filePath);
        $realLogsDir = realpath($this->logsDir);

        if ($realPath === false || $realLogsDir === false) {
            return false;
        }

        return str_starts_with($realPath, $realLogsDir);
    }

    /**
     * Парсинг лог-файла
     *
     * @param string $content Содержимое файла
     * @return array<int, array<string, mixed>>
     */
    private function parseLogFile(string $content): array
    {
        $lines = explode("\n", $content);
        $entries = [];
        $currentEntry = '';

        foreach ($lines as $line) {
            $trimmedLine = rtrim($line);

            // Проверяем, начинается ли строка с timestamp
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $trimmedLine)) {
                if (! empty($currentEntry)) {
                    $parsed = $this->parseLogEntry(trim($currentEntry));
                    if ($parsed) {
                        $entries[] = $parsed;
                    }
                }
                $currentEntry = $trimmedLine;
            } else {
                $currentEntry .= "\n" . $trimmedLine;
            }
        }

        // Сохраняем последнюю запись
        if (! empty($currentEntry)) {
            $parsed = $this->parseLogEntry(trim($currentEntry));
            if ($parsed) {
                $entries[] = $parsed;
            }
        }

        return $entries;
    }

    /**
     * Парсинг одной записи лога
     *
     * @param string $entry Запись лога
     * @return array<string, mixed>|null
     */
    private function parseLogEntry(string $entry): ?array
    {
        // Формат: [2025-11-28 20:45:43] LEVEL: message | IP: 172.23.160.1 | GET /path | Context: {...}
        if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+):\s+(.+?)(?:\s*\|\s*IP:\s*([^\|]+))?(?:\s*\|\s*([A-Z]+)\s+(.+?))?(?:\s*\|\s*Context:\s*(.+))?$/s', $entry, $matches)) {
            return null;
        }

        $timestamp = $matches[1] ?? '';
        $level = $matches[2] ?? 'INFO';
        $message = trim($matches[3] ?? '');
        $ip = isset($matches[4]) ? trim($matches[4]) : null;
        $method = isset($matches[5]) ? trim($matches[5]) : null;
        $url = isset($matches[6]) ? trim($matches[6]) : null;
        $contextStr = isset($matches[7]) ? trim($matches[7]) : null;

        $context = null;
        if ($contextStr) {
            $decoded = json_decode($contextStr, true);
            $context = $decoded !== null ? $decoded : $contextStr;
        }

        return [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'ip' => $ip,
            'method' => $method,
            'url' => $url,
            'context' => $context,
        ];
    }

    /**
     * Применить фильтры к записям
     *
     * @param array<int, array<string, mixed>> $entries Записи
     * @param array<string, mixed> $filters Фильтры
     * @return array<int, array<string, mixed>>
     */
    private function applyFilters(array $entries, array $filters): array
    {
        if (empty($filters)) {
            return $entries;
        }

        return array_filter($entries, function ($entry) use ($filters) {
            // Фильтр по уровню
            if (isset($filters['level']) && ! empty($filters['level'])) {
                if (strtoupper($entry['level']) !== strtoupper($filters['level'])) {
                    return false;
                }
            }

            // Фильтр по дате
            if (isset($filters['date_from']) && ! empty($filters['date_from'])) {
                $entryDate = substr($entry['timestamp'], 0, 10);
                if ($entryDate < $filters['date_from']) {
                    return false;
                }
            }

            if (isset($filters['date_to']) && ! empty($filters['date_to'])) {
                $entryDate = substr($entry['timestamp'], 0, 10);
                if ($entryDate > $filters['date_to']) {
                    return false;
                }
            }

            // Фильтр по поиску
            if (isset($filters['search']) && ! empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $message = strtolower($entry['message'] ?? '');
                
                if (strpos($message, $search) === false) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Форматирование размера файла
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

