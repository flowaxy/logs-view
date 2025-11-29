<?php
/**
 * Logs Viewer plugin bootstrap.
 */

declare(strict_types=1);

$rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
require_once $rootDir . '/engine/core/support/base/BasePlugin.php';
require_once $rootDir . '/engine/core/support/helpers/UrlHelper.php';

if (! function_exists('addHook')) {
    require_once $rootDir . '/engine/includes/functions.php';
}

// Загружаем ClassAutoloader для регистрации классов
if (file_exists($rootDir . '/engine/core/system/ClassAutoloader.php')) {
    require_once $rootDir . '/engine/core/system/ClassAutoloader.php';
}

// Загружаем сервис для работы с логами
$logsServiceFile = dirname(__FILE__) . '/src/Services/LogsService.php';
if (file_exists($logsServiceFile)) {
    require_once $logsServiceFile;
}

class LogsViewPlugin extends BasePlugin
{
    private string $pluginDir;

    public function __construct()
    {
        parent::__construct();
        $reflection = new ReflectionClass($this);
        $this->pluginDir = dirname($reflection->getFileName());
    }

    public function init(): void
    {
        addHook('admin_register_routes', [$this, 'registerAdminRoute'], 10, 1);
        addFilter('admin_menu', [$this, 'registerAdminMenu'], 15);
        addFilter('settings_categories', [$this, 'registerSettingsCategory'], 10);
    }

    public function registerAdminRoute($router): void
    {
        $pageFile = $this->pluginDir . '/src/admin/pages/LogsViewAdminPage.php';
        if (file_exists($pageFile)) {
            // Регистрируем класс в автозагрузчике
            if (isset($GLOBALS['engineAutoloader'])) {
                $autoloader = $GLOBALS['engineAutoloader'];
                if ($autoloader instanceof ClassAutoloader || 
                    (is_object($autoloader) && method_exists($autoloader, 'addClassMap'))) {
                    $autoloader->addClassMap([
                        'LogsViewAdminPage' => $pageFile
                    ]);
                }
            }
            
            require_once $pageFile;
            if (class_exists('LogsViewAdminPage')) {
                $router->add(['GET', 'POST'], 'logs-view', 'LogsViewAdminPage');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $menu
     */
    public function registerAdminMenu(array $menu): array
    {
        // Ищем существующее меню "Система"
        $found = false;
        foreach ($menu as &$item) {
            if (isset($item['page']) && $item['page'] === 'system') {
                if (!isset($item['submenu'])) {
                    $item['submenu'] = [];
                }
                $item['submenu'][] = [
                    'text' => 'Логи',
                    'icon' => 'fas fa-file-alt',
                    'href' => UrlHelper::admin('logs-view'),
                    'page' => 'logs-view',
                    'order' => 1,
                    'permission' => 'admin.logs.view',
                ];
                $found = true;
                break;
            }
        }
        
        // Если меню "Система" не найдено, создаем его
        if (!$found) {
            $menu[] = [
                'text' => 'Система',
                'icon' => 'fas fa-server',
                'href' => '#',
                'page' => 'system',
                'order' => 60,
                'permission' => null,
                'submenu' => [
                    [
                        'text' => 'Логи',
                        'icon' => 'fas fa-file-alt',
                        'href' => UrlHelper::admin('logs-view'),
                        'page' => 'logs-view',
                        'order' => 1,
                        'permission' => 'admin.logs.view',
                    ],
                ],
            ];
        }

        return $menu;
    }

    /**
     * Реєстрація в категоріях налаштувань
     * 
     * Додає плагін до категорії "Система" на сторінці /admin/settings
     * 
     * @param array<string, mixed> $categories Поточні категорії
     * @return array<string, mixed> Оновлені категорії
     */
    public function registerSettingsCategory(array $categories): array
    {
        // Перевіряємо, чи плагін активний
        $pluginManager = function_exists('pluginManager') ? pluginManager() : null;
        if (!$pluginManager || !method_exists($pluginManager, 'isPluginActive') || !$pluginManager->isPluginActive('logs-view')) {
            return $categories;
        }

        // Додаємо до категорії "Система"
        if (isset($categories['system'])) {
            $categories['system']['items'][] = [
                'title' => 'Логи',
                'description' => 'Перегляд системних логів',
                'url' => UrlHelper::admin('logs-view'),
                'icon' => 'fas fa-file-alt',
                'permission' => 'admin.logs.view',
            ];
        }

        return $categories;
    }
}

return new LogsViewPlugin();

