# Changelog

All notable changes to this project will be documented in this file.

## [1.6.1] - 2026-08-27

### Fixed
- Fixed `RuntimeException: Column "id" already exists.` when updating from an earlier version: `install.php` used `addColumn()`/`addIndex()`, which throw when run against a table that already has that column/index. Since `update.php` re-runs `install.php` on every update, this broke the update process for every existing installation. Switched to the idempotent `ensureColumn()`/`ensureIndex()`.

## [1.6.0] - 2026-08-27

### Security
- Fixed a SQL injection via the unvalidated `type` request parameter on the upload page
- Added CSRF protection (`rex_csrf_token`) to all state-changing actions: settings, repositories, modules, templates, classes, cache, upload
- Fixed reflected XSS caused by unescaped exception messages in several backend pages
- Added strict validation of class names before they are used in filesystem paths (defense against path traversal from repository content)
- GitHub token is no longer rendered into the settings page HTML; added an explicit "remove token" option, leaving the field empty now keeps the existing token unchanged

### Changed
- Modules/templates without their own `key` now show a clear "placeholder" badge instead of looking like a real, portable key
- The addon used to install classes into is now configurable (`class_install_addon` setting, default `project`) instead of being hardcoded
- Reduced GitHub API calls for the module/template list by replacing exists-then-fetch with fetch-and-catch and removing a redundant duplicate README check (noticeably faster on a cold cache)
- GitHub API errors now distinguish rate limiting, invalid/expired tokens, and network failures instead of one generic message
- Cache files are now written atomically to avoid readers seeing a partially written file

### Fixed
- Removed dead/unused code (`InstallManager.php`, `UploadManager.php`, `upload_new.php`) that duplicated the live install/update logic
- Fixed an undefined-variable warning and broken form title when uploading a class
- Deduplicated the module/template/class existence-lookup logic that was copy-pasted across `RepositoryManager`, `NewInstallManager` and `UpdateManager` into a shared `TableHelper`
- Fixed a silent failure path where a failed backup-restore during a class update could lose both the update and the backup
- Translated remaining hardcoded German UI strings so the English locale displays correctly

## [1.2.0] - 2025-09-10

### Added
- **Bidirectional Synchronization**: Upload functionality for modules and templates to GitHub
- **Asset Support**: Automatic copying of CSS/JS files during installation and upload
- **Settings Integration**: Backend configuration for upload repositories
- **Smart Folder Naming**: Uses module keys (e.g., "gblock") instead of IDs
- **Complete Upload**: Automatic generation of config.yml and README.md files
- **Overwrite Support**: Updates existing modules/templates in repositories

### Enhanced
- Improved UI with asset indicators
- Better error handling for upload operations
- Modern date formatting with IntlDateFormatter
- Comprehensive validation for repository access

### Fixed
- SQL queries now use proper rex_sql methods
- Resolved undefined array key warnings
- Corrected asset path resolution for recursive uploads

## [1.1.0] - 2025-09-09

### Added
- **Asset Installation**: CSS/JS files are automatically copied to `/assets/modules/{key}/` and `/assets/templates/{key}/`
- **Enhanced UI**: Better display of modules with asset indicators
- **Cache Optimization**: Faster repository browsing

### Enhanced
- Improved installation workflow
- Better asset file detection and copying
- Recursive directory handling for complex asset structures

## [1.0.0] - 2025-09-03

### Added
- **Base Installation**: Install modules and templates from GitHub repositories
- **Repository Management**: Add and manage GitHub repositories
- **Multi-Language Support**: German and English interface
- **File-based Caching**: Improved performance for repository browsing
- **Private Repository Support**: GitHub token integration
- **Clean UI**: Native REDAXO backend integration

### Features
- Browse GitHub repositories for modules and templates
- Install modules and templates with one click
- Support for public and private repositories
- Comprehensive error handling
- Repository validation and testing
