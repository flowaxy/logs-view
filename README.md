# Logs Viewer Plugin

**Version:** 1.0.0  
**Author:** FlowAxy  
**Developer:** iTeffa (iteffa@flowaxy.com)  
**Studio:** flowaxy.com  
**License:** Proprietary

## Description

Logs Viewer is a plugin for Flowaxy CMS that provides a convenient web interface for viewing and managing system logs through the administrative panel. The plugin allows you to view, filter, export, and delete log files without requiring server access via SSH or FTP.

## Features

### Core Functionality

- 📋 **Log Viewing** — Convenient interface for viewing all log files
- 🔍 **Filtering** — Filter logs by level (DEBUG, INFO, WARNING, ERROR, CRITICAL), date, and text
- 📊 **Statistics** — Display count of entries by type
- 💾 **Export** — Export logs in TXT, CSV, and JSON formats
- 🗑️ **Deletion** — Delete individual files or all logs at once
- 📅 **Sorting** — Automatic sorting of files by modification date
- 🎨 **Modern UI** — Intuitive interface with responsive design support

### Technical Features

- Support for large log files with entry limit controls
- Secure file path validation (Path Traversal prevention)
- CSRF protection for all write operations
- Optimized architecture using services
- Integration with Flowaxy CMS access control system

## Requirements

- PHP >= 8.4.0
- Flowaxy CMS with plugin support
- `admin.logs.view` permission for viewing
- `admin.logs.delete` permission for deletion

## Installation

1. Copy the plugin directory to `plugins/logs-view/`
2. Activate the plugin through the admin panel (Settings → Plugins)
3. Verify user permissions

The plugin will automatically register the route and menu item after activation.

## Usage

### Accessing the Logs Page

1. Log in to the admin panel
2. Navigate to **System → Logs** in the menu
3. Or go directly to `/admin/logs-view`

### Viewing Logs

1. Select a log file from the dropdown list
2. View entries in real-time
3. Use filters to find specific entries

### Filtering Logs

The plugin supports several filtering methods:

- **By Level** — Filter buttons (All, Errors, Warnings, Information, etc.)
- **By Date** — Date range selection
- **By Text** — Search in log messages
- **Entry Count** — Select number of entries to display (50, 100, 200, 500, All)

### Exporting Logs

1. Apply necessary filters (optional)
2. Click the **Export** button
3. Select format: TXT, CSV, or JSON
4. File will download automatically

### Deleting Logs

- **Individual File** — Click the trash icon next to the selected file
- **All Files** — Click **Clear All Logs** button in the page header

⚠️ **Warning:** Log deletion is irreversible. Be sure to make a backup before deleting!

## Plugin Structure

```
logs-view/
├── assets/
│   ├── scripts/
│   │   └── logs-view.js      # JavaScript for filtering and interactivity
│   └── styles/
│       └── logs-view.css     # Styles for the log viewing page
├── src/
│   ├── admin/
│   │   └── pages/
│   │       └── LogsViewAdminPage.php  # Admin page for viewing logs
│   └── Services/
│       └── LogsService.php            # Service for working with logs
├── templates/
│   └── logs-view.php                  # Page viewing template
├── init.php                           # Plugin initialization
├── plugin.json                        # Plugin metadata
└── README.md                          # Documentation
```

## Technical Details

### Architecture

The plugin uses an architecture optimized for Flowaxy CMS standards:

- **LogsService** — Service for working with log files, uses `File` and `Directory` classes from `engine/infrastructure/filesystem`
- **LogsViewAdminPage** — Admin panel page that displays the user interface
- **Templates** — PHP templates for HTML rendering

### Log Format

The plugin supports the standard Flowaxy CMS log format:

```
[YYYY-MM-DD HH:MM:SS] LEVEL: message | IP: xxx.xxx.xxx.xxx | METHOD /path | Context: {...}
```

Example:
```
[2025-11-28 20:45:43] INFO: Database connected | IP: 172.23.160.1 | GET /admin/dashboard | Context: {"database":"mysql"}
```

### Security

- ✅ CSRF protection for all write operations
- ✅ Permission checks before executing operations
- ✅ File path validation (Path Traversal prevention)
- ✅ Output sanitization to prevent XSS
- ✅ File location verification within allowed directory

### Permissions

The plugin uses the Flowaxy CMS permission system:

- `admin.logs.view` — Permission to view logs (required)
- `admin.logs.delete` — Permission to delete logs (optional)

Users without permissions are automatically redirected to the admin panel home page.

### Engine Integration

The plugin is fully integrated with Flowaxy CMS Engine:

- Uses `BasePlugin` for basic functionality
- Registers through the hook system (`admin_register_routes`, `admin_menu`)
- Uses admin UI components (`AdminPage`, breadcrumbs, components)
- Uses helpers (`UrlHelper`, `SecurityHelper`, `Response`)

## Configuration

### Log Location

By default, the plugin looks for log files in:

```
storage/logs/
```

This can be changed by defining the `LOGS_DIR` constant in the system configuration:

```php
define('LOGS_DIR', '/custom/path/to/logs/');
```

### Entry Limiting

By default, 50 most recent entries are displayed. This can be changed via URL parameter:

```
/admin/logs-view?file=app-2025-11-28.log&limit=100
```

Available values: `50`, `100`, `200`, `500`, `0` (all entries)

## Development

### Dependencies

The plugin uses the following components from Engine:

- `engine/core/support/base/BasePlugin.php`
- `engine/infrastructure/filesystem/File.php`
- `engine/infrastructure/filesystem/Directory.php`
- `engine/interface/admin-ui/includes/AdminPage.php`
- `engine/core/support/helpers/UrlHelper.php`
- `engine/core/support/helpers/SecurityHelper.php`

### Extending Functionality

To extend plugin functionality:

1. **Adding New Filters** — Edit the `applyFilters()` method in `LogsService.php`
2. **Adding Export Formats** — Add a new method in `LogsViewAdminPage.php`
3. **UI Customization** — Edit the template `templates/logs-view.php` and styles `assets/styles/logs-view.css`

### Testing

To test the plugin:

1. Ensure you have log files in the `storage/logs/` directory
2. Check user permissions
3. Test CSRF protection by attempting an operation without a token
4. Test path validation by attempting to access a file outside the logs directory

## Support

If you found a bug or have questions:

1. Check log files for errors
2. Verify permissions for the logs directory
3. Ensure PHP has read permissions for log files

## License

Proprietary. All rights reserved.

## Changelog

### 1.0.0 (2025-11-28)

- ✨ Initial release
- ✅ Basic log viewing
- ✅ Filtering by level, date, and text
- ✅ Export to TXT, CSV, JSON
- ✅ Log file deletion
- ✅ Flowaxy CMS Engine integration
- ✅ Optimized architecture using services

## Author

**Developer:** iTeffa  
Email: iteffa@flowaxy.com

**Studio:** FlowAxy  
Website: https://flowaxy.com

---

*Made with ❤️ for Flowaxy CMS*
