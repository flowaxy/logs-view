<?php

/**
 * Сторінка перегляду логів
 */

declare(strict_types=1);

$rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 5);
$engineDir = $rootDir . '/engine';

require_once $engineDir . '/interface/admin-ui/includes/AdminPage.php';

class LogsViewAdminPage extends AdminPage
{
    private string $pluginDir;

    public function __construct()
    {
        parent::__construct();

        // Определяем путь к директории плагина
        // Файл находится в plugins/logs-view/src/admin/pages/LogsViewAdminPage.php
        // __DIR__ = plugins/logs-view/src/admin/pages
        // dirname(__DIR__, 3) = plugins/logs-view
        $this->pluginDir = dirname(__DIR__, 3);

        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            Response::redirectStatic(UrlHelper::admin('dashboard'));
            exit;
        }

        $this->pageTitle = 'Перегляд логів - Flowaxy CMS';
        $this->templateName = 'logs-view';

        $this->setPageHeader(
            'Перегляд логів',
            'Системні логи та події',
            'fas fa-file-alt'
        );

        // Додаємо хлібні крихти
        $this->setBreadcrumbs([
            ['title' => 'Головна', 'url' => UrlHelper::admin('dashboard')],
            ['title' => 'Перегляд логів'],
        ]);

        // Загружаем CSS как inline стили для гарантированной загрузки
        $cssFile = $this->pluginDir . '/assets/styles/logs-view.css';
        if (file_exists($cssFile)) {
            $this->additionalInlineCSS = file_get_contents($cssFile);
        }

        // Загружаем JS файл из плагина
        $this->additionalJS[] = $this->pluginAsset('scripts/logs-view.js');
    }

    /**
     * Получить путь к шаблонам плагина
     */
    protected function getTemplatePath()
    {
        return $this->pluginDir . '/templates/';
    }

    /**
     * Получить URL к ресурсам плагина (CSS, JS)
     */
    private function pluginAsset(string $path): string
    {
        $relativePath = 'plugins/logs-view/assets/' . ltrim($path, '/');
        $absolutePath = $this->pluginDir . '/assets/' . ltrim($path, '/');
        $version = file_exists($absolutePath) ? substr(md5_file($absolutePath), 0, 8) : substr((string)time(), -8);

        return UrlHelper::base($relativePath) . '?v=' . $version;
    }

    /**
     * Получить путь к директории логов
     */
    private function getLogsDir(): string
    {
        if (defined('LOGS_DIR')) {
            return rtrim(LOGS_DIR, '/\\') . '/';
        }
        
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 5);
        return $rootDir . '/storage/logs/';
    }

    public function handle()
    {
        // Обробка експорту логів
        if ($_GET && isset($_GET['export'])) {
            $this->exportLogs();

            return;
        }

        // Обробка очистки логів
        if ($_POST && isset($_POST['clear_logs'])) {
            $fileToDelete = $this->post('file', '');
            $this->clearLogs();
            // Після видалення конкретного файла відбувається редирект з exit, далі не виконуємо
            // Якщо видалення всіх файлів - продовжуємо виконання для відображення повідомлення
            if ($fileToDelete !== 'all') {
                return;
            }
        }

        // Отримання списку логів
        $logFiles = $this->getLogFiles();
        $logContent = $this->getLogContent();

        // Кнопка очистки всіх логів у заголовку (додаємо після отримання логів)
        $headerButtons = '';
        if (! empty($logFiles)) {
            $headerButtons = $this->createButton('Очистити всі логи', 'danger', [
                'icon' => 'trash',
                'attributes' => [
                    'class' => 'btn-sm',
                    'data-bs-toggle' => 'modal',
                    'data-bs-target' => '#clearAllLogsModal',
                    'onclick' => 'return false;',
                ],
            ]);
        }

        // Оновлюємо заголовок з кнопкою
        $this->setPageHeader(
            'Перегляд логів',
            'Системні логи та події',
            'fas fa-file-alt',
            $headerButtons
        );

        // Фільтри
        $filters = [
            'level' => $_GET['level'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'module' => $_GET['module'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];

        // Застосовуємо фільтри до контенту
        if (! empty($logContent['lines']) && ! empty(array_filter($filters))) {
            $logContent['lines'] = $this->applyFilters($logContent['lines'], $filters);
        }

        // Рендеримо сторінку
        $this->render([
            'logFiles' => $logFiles,
            'logContent' => $logContent,
            'selectedFile' => $_GET['file'] ?? null,
            'filters' => $filters,
        ]);
    }

    /**
     * Отримання списку файлів логів
     *
     * @return array<int, array<string, mixed>>
     */
    private function getLogFiles(): array
    {
        $logsDir = $this->getLogsDir();

        if (! is_dir($logsDir)) {
            return [];
        }

        $files = [];
        $pattern = $logsDir . '*.log';
        $fileList = glob($pattern);

        if ($fileList === false) {
            return [];
        }

        foreach ($fileList as $file) {
            if (! is_file($file) || ! is_readable($file)) {
                continue;
            }

            $filename = basename($file);
            $fileSize = filesize($file);
            $modified = filemtime($file);

            $files[] = [
                'name' => $filename,
                'path' => $file,
                'size' => $fileSize,
                'size_formatted' => $this->formatBytes($fileSize),
                'modified' => date('d.m.Y H:i:s', $modified),
                'modified_timestamp' => $modified,
            ];
        }

        // Сортуємо за датою модифікації (нові спочатку)
        usort($files, function ($a, $b) {
            return $b['modified_timestamp'] - $a['modified_timestamp'];
        });

        return $files;
    }

    /**
     * Отримання вмісту лог-файлу
     *
     * @return array<string, mixed>
     */
    private function getLogContent(): array
    {
        $selectedFile = $_GET['file'] ?? null;

        if (empty($selectedFile)) {
            return [
                'lines' => [],
                'total_lines' => 0,
                'file' => null,
            ];
        }

        $logsDir = $this->getLogsDir();
        $filePath = $logsDir . basename($selectedFile);

        // Безпека: перевіряємо, що файл знаходиться в директорії логів
        if (! file_exists($filePath) ||
            ! is_file($filePath) ||
            ! is_readable($filePath) ||
            ! str_starts_with(realpath($filePath), realpath($logsDir))) {
            return [
                'lines' => [],
                'total_lines' => 0,
                'file' => null,
                'error' => 'Файл не знайдено або недоступний',
            ];
        }

        // Получаем лимит из GET параметра
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        if ($limit <= 0) {
            $limit = PHP_INT_MAX; // Все записи
        }

        // Читаємо рядки
        $lines = [];
        $file = fopen($filePath, 'r');

        if ($file) {
            $lineCount = 0;
            $totalLines = 0;

            // Підраховуємо загальну кількість рядків
            while (fgets($file) !== false) {
                $totalLines++;
            }
            rewind($file);

            // Читаємо все файл для парсинга записей (не строк)
            // Если лимит небольшой, можно оптимизировать, но для простоты читаем весь файл
            $currentLogEntry = '';
            $allEntries = [];

            // Читаем все записи из файла
            while (($line = fgets($file)) !== false) {
                $trimmedLine = rtrim($line);

                // Проверяем, начинается ли строка с timestamp [YYYY-MM-DD HH:MM:SS]
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $trimmedLine)) {
                    // Если есть незавершенная запись, сохраняем её
                    if (! empty($currentLogEntry)) {
                        $parsedLog = $this->parseLogEntry(trim($currentLogEntry));
                        if ($parsedLog) {
                            $allEntries[] = $parsedLog;
                        }
                    }
                    // Начинаем новую запись
                    $currentLogEntry = $trimmedLine;
                } else {
                    // Продолжение предыдущей записи (многострочный контекст)
                    $currentLogEntry .= "\n" . $trimmedLine;
                }
            }

            // Сохраняем последнюю запись
            if (! empty($currentLogEntry)) {
                $parsedLog = $this->parseLogEntry(trim($currentLogEntry));
                if ($parsedLog) {
                    $allEntries[] = $parsedLog;
                }
            }

            // Берем последние N записей и переворачиваем порядок (новые сначала)
            if ($limit === PHP_INT_MAX) {
                $lines = array_reverse($allEntries);
            } else {
                $lastEntries = array_slice($allEntries, -$limit);
                $lines = array_reverse($lastEntries); // Новые записи сначала
            }

            fclose($file);
        }

        return [
            'lines' => $lines,
            'total_lines' => $totalLines ?? 0,
            'file' => basename($selectedFile),
        ];
    }

    /**
     * Очистка логів (удаление файлов)
     */
    private function clearLogs()
    {
        if (! $this->verifyCsrf()) {
            $this->setMessage('Помилка безпеки: невірний CSRF токен', 'danger');

            return;
        }

        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            $this->setMessage('У вас немає прав на видалення логів', 'danger');

            return;
        }

        $file = $this->post('file', null);
        $logsDir = $this->getLogsDir();

        // Проверяем, что директория существует
        if (! is_dir($logsDir)) {
            $this->setMessage('Помилка: директорія логів не знайдена', 'danger');

            return;
        }

        if ($file === 'all') {
            // Удаляем все файлы логов
            $pattern = $logsDir . '*.log';
            $files = glob($pattern);
            $deleted = 0;

            if ($files !== false) {
                foreach ($files as $logFile) {
                    if (is_file($logFile) && is_writable($logFile)) {
                        if (@unlink($logFile)) {
                            $deleted++;
                        }
                    }
                }
            }

            logger()->logInfo('Видалено всі файли логів', ['deleted_count' => $deleted]);
            $this->setMessage("Видалено {$deleted} файлів логів", 'success');
            // Редирект после удаления всех логов для предотвращения повторного выполнения
            Response::redirectStatic(UrlHelper::admin('logs-view'));
            exit;
        } elseif (! empty($file)) {
            // Удаляем конкретный файл
            $filePath = $logsDir . basename($file);

            // Нормализуем пути для безопасной проверки
            $realFilePath = realpath($filePath);
            $realLogsDir = realpath($logsDir);

            if ($realFilePath === false || $realLogsDir === false) {
                $this->setMessage('Помилка: не вдалося визначити шлях до файлу', 'danger');

                return;
            }

            if (file_exists($filePath) &&
                is_file($filePath) &&
                is_writable($filePath)) {
                // Проверяем, что файл находится в директории логов
                if (str_starts_with($realFilePath, $realLogsDir)) {
                    if (@unlink($filePath)) {
                        logger()->logInfo('Файл логу видалено', ['file' => basename($file)]);
                        $this->setMessage('Файл логу успішно видалено', 'success');
                        // Перенаправляем на страницу без выбранного файла
                        Response::redirectStatic(UrlHelper::admin('logs-view'));
                        exit; // Обязательно выходим после редиректа
                    } else {
                        logger()->logWarning('Помилка видалення файлу логу', ['file' => basename($file)]);
                        $this->setMessage('Помилка при видаленні файлу логу. Перевірте права доступу.', 'danger');
                    }
                } else {
                    logger()->logWarning('Спроба видалення файлу поза директорією логів', ['file' => $file]);
                    $this->setMessage('Помилка безпеки: файл знаходиться поза дозволеною директорією', 'danger');
                }
            } else {
                if (! file_exists($filePath)) {
                    $this->setMessage('Файл не знайдено: ' . basename($file), 'danger');
                } elseif (! is_writable($filePath)) {
                    $this->setMessage('Файл недоступний для запису. Перевірте права доступу.', 'danger');
                } else {
                    $this->setMessage('Файл не знайдено або недоступний', 'danger');
                }
            }
        }
    }

    /**
     * Парсинг лог-записи
     *
     * @return array<string, mixed>|null
     */
    private function parseLogEntry(string $logLine): ?array
    {
        // Формат: [timestamp] LEVEL: message | IP: ... | Context: {...}
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(ERROR|WARNING|INFO|DEBUG|NOTICE):\s+(.+?)(?:\s+\|\s+IP:\s+([^\|]+))?(?:\s+\|\s+GET\s+([^\|]+))?(?:\s+\|\s+Context:\s+(.+))?$/s';

        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(ERROR|WARNING|INFO|DEBUG|NOTICE):\s+(.+)$/s', $logLine, $matches)) {
            $timestamp = $matches[1] ?? '';
            $level = $matches[2] ?? 'INFO';
            $messageAndContext = $matches[3] ?? '';

            // Парсим дополнительные данные
            $ip = null;
            $url = null;
            $context = null;

            if (preg_match('/\|\s+IP:\s+([^\|]+)/', $logLine, $ipMatch)) {
                $ip = trim($ipMatch[1]);
            }

            if (preg_match('/\|\s+GET\s+([^\|]+)/', $logLine, $urlMatch)) {
                $url = trim($urlMatch[1]);
            }

            if (preg_match('/\|\s+Context:\s+(.+)$/s', $logLine, $contextMatch)) {
                $contextJson = trim($contextMatch[1]);
                // Пробуем распарсить JSON
                $decoded = json_decode($contextJson, true);
                $context = $decoded !== null ? $decoded : $contextJson;
            }

            // Убираем из сообщения уже распарсенные части
            $message = $messageAndContext;

            // Сначала убираем Context (самое длинное, может содержать пробелы)
            if ($context) {
                $message = preg_replace('/\s*\|\s+Context:\s+.+$/s', '', $message);
            }

            // Убираем IP и URL
            if ($ip) {
                $message = preg_replace('/\s*\|\s+IP:\s+[^\|]+/', '', $message);
            }
            if ($url) {
                $message = preg_replace('/\s*\|\s+GET\s+[^\|]+/', '', $message);
            }

            // Тщательная очистка от всех лишних пробелов
            $message = trim($message); // Убираем пробелы в начале и конце
            $message = preg_replace('/\s+/u', ' ', $message); // Заменяем множественные пробелы, табы, переносы на один пробел
            $message = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $message); // Заменяем все переносы и табы на пробелы
            $message = preg_replace('/\s+/u', ' ', $message); // Еще раз заменяем множественные пробелы на один
            $message = trim($message); // Финальная очистка

            return [
                'timestamp' => $timestamp,
                'level' => $level,
                'message' => $message,
                'ip' => $ip,
                'url' => $url,
                'context' => $context,
                'raw' => $logLine,
            ];
        }

        // Если не удалось распарсить, возвращаем как есть
        return [
            'timestamp' => '',
            'level' => 'INFO',
            'message' => $logLine,
            'ip' => null,
            'url' => null,
            'context' => null,
            'raw' => $logLine,
        ];
    }

    /**
     * Форматування байтів
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Застосування фільтрів до логів
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function applyFilters(array $lines, array $filters): array
    {
        $filtered = $lines;

        // Фільтр по рівню
        if (! empty($filters['level'])) {
            $level = strtoupper(trim($filters['level']));
            $filtered = array_filter($filtered, function ($line) use ($level) {
                return isset($line['level']) && strtoupper($line['level']) === $level;
            });
        }

        // Фільтр по даті (від)
        if (! empty($filters['date_from'])) {
            $dateFrom = strtotime($filters['date_from']);
            if ($dateFrom !== false) {
                $filtered = array_filter($filtered, function ($line) use ($dateFrom) {
                    if (empty($line['timestamp'])) {
                        return false;
                    }
                    $lineDate = strtotime($line['timestamp']);

                    return $lineDate !== false && $lineDate >= $dateFrom;
                });
            }
        }

        // Фільтр по даті (до)
        if (! empty($filters['date_to'])) {
            $dateTo = strtotime($filters['date_to'] . ' 23:59:59');
            if ($dateTo !== false) {
                $filtered = array_filter($filtered, function ($line) use ($dateTo) {
                    if (empty($line['timestamp'])) {
                        return false;
                    }
                    $lineDate = strtotime($line['timestamp']);

                    return $lineDate !== false && $lineDate <= $dateTo;
                });
            }
        }

        // Фільтр по тексту (пошук)
        if (! empty($filters['search'])) {
            $search = mb_strtolower(trim($filters['search']));
            $filtered = array_filter($filtered, function ($line) use ($search) {
                $message = mb_strtolower($line['message'] ?? '');
                $raw = mb_strtolower($line['raw'] ?? '');

                return str_contains($message, $search) || str_contains($raw, $search);
            });
        }

        // Повертаємо масив з правильними індексами
        return array_values($filtered);
    }

    /**
     * Експорт логів
     */
    private function exportLogs(): void
    {
        // Перевірка прав доступу
        if (! function_exists('current_user_can') || ! current_user_can('admin.access')) {
            http_response_code(403);
            echo 'У вас немає прав на експорт логів';
            exit;
        }

        $format = $_GET['format'] ?? 'txt';
        $selectedFile = $_GET['file'] ?? null;

        if (empty($selectedFile)) {
            http_response_code(400);
            echo 'Файл не вказано';
            exit;
        }

        $logsDir = $this->getLogsDir();
        $filePath = $logsDir . basename($selectedFile);

        // Безпека: перевіряємо, що файл знаходиться в директорії логів
        $realFilePath = realpath($filePath);
        $realLogsDir = realpath($logsDir);
        
        if (! file_exists($filePath) ||
            ! is_file($filePath) ||
            ! is_readable($filePath) ||
            $realFilePath === false ||
            $realLogsDir === false ||
            ! str_starts_with($realFilePath, $realLogsDir)) {
            http_response_code(404);
            echo 'Файл не знайдено';
            exit;
        }

        // Отримуємо контент з фільтрами
        $logContent = $this->getLogContent();

        // Застосовуємо фільтри
        $filters = [
            'level' => $_GET['level'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'module' => $_GET['module'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];

        if (! empty($logContent['lines']) && ! empty(array_filter($filters))) {
            $logContent['lines'] = $this->applyFilters($logContent['lines'], $filters);
        }

        $lines = $logContent['lines'] ?? [];

        // Генеруємо ім'я файлу для експорту
        $exportFilename = 'logs-export-' . date('Y-m-d_H-i-s') . '.' . $format;

        logger()->logDebug('Експорт логів', [
            'format' => $format,
            'file' => $selectedFile,
            'lines_count' => count($lines),
        ]);

        // Встановлюємо заголовки
        header('Content-Type: ' . match($format) {
            'csv' => 'text/csv; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            default => 'text/plain; charset=utf-8'
        });
        header('Content-Disposition: attachment; filename="' . $exportFilename . '"');

        // Експортуємо в залежності від формату
        switch ($format) {
            case 'csv':
                $this->exportAsCsv($lines);

                break;

            case 'json':
                $this->exportAsJson($lines);

                break;

            default:
                $this->exportAsTxt($lines);

                break;
        }

        exit;
    }

    /**
     * Експорт у форматі TXT
     *
     * @param array<int, array<string, mixed>> $lines
     */
    private function exportAsTxt(array $lines): void
    {
        foreach ($lines as $line) {
            echo $line['raw'] ?? '';
            echo "\n";
        }
    }

    /**
     * Експорт у форматі CSV
     *
     * @param array<int, array<string, mixed>> $lines
     */
    private function exportAsCsv(array $lines): void
    {
        // BOM для правильного відображення кирилиці в Excel
        echo "\xEF\xBB\xBF";

        // Заголовки
        echo "Дата/Час,Рівень,Повідомлення,IP,URL\n";

        foreach ($lines as $line) {
            $timestamp = $line['timestamp'] ?? '';
            $level = $line['level'] ?? '';
            $message = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $line['message'] ?? '');
            $ip = $line['ip'] ?? '';
            $url = $line['url'] ?? '';

            echo sprintf(
                '"%s","%s","%s","%s","%s"' . "\n",
                $timestamp,
                $level,
                $message,
                $ip,
                $url
            );
        }
    }

    /**
     * Експорт у форматі JSON
     *
     * @param array<int, array<string, mixed>> $lines
     */
    private function exportAsJson(array $lines): void
    {
        $export = [
            'export_date' => date('Y-m-d H:i:s'),
            'file' => $_GET['file'] ?? null,
            'total_lines' => count($lines),
            'logs' => $lines,
        ];

        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
