# Changelog

All notable changes to this project will be documented in this file.

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
