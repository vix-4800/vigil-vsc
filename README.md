# PHP Exception Inspector

![Version](https://img.shields.io/badge/version-0.1.6-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

A Visual Studio Code extension that analyzes PHP code for undocumented exception handling and missing `@throws` tags in docblocks.

## Features

- **Automatic Analysis**: Analyzes PHP files on open and save
- **Real-time Diagnostics**: Shows problems directly in the VS Code Problems panel
- **Quick Fix Support**: Automatically add missing `@throws` tags with one click
- **Manual Analysis**: Run analysis on-demand via command palette
- **Configurable**: Customize analysis behavior through VS Code settings

## Extension Settings

This extension contributes the following settings:

- `phpExceptionInspector.analyzeOnSave`: Automatically analyze PHP files on save (default: `true`)
- `phpExceptionInspector.analyzeOnOpen`: Automatically analyze PHP files when opened (default: `true`)
- `phpExceptionInspector.noProjectScan`: Disable automatic project-wide scanning for faster single file analysis
  (default: `false`)
- `phpExceptionInspector.disableCache`: Disable caching of analysis results (useful for testing or when cache causes issues)
  (default: `false`)
- `phpExceptionInspector.excludePatterns`: Array of regex patterns to exclude files and directories from analysis
  (default: `["/vendor/", "/node_modules/", "/\\.git/", "/\\.(?:idea|vscode|cache|config)/"]`)

## Requirements

This extension includes the PHP analyzer as part of the package. PHP 8.1 or higher is required on your system.

## Usage

### Automatic Analysis

By default, the extension automatically analyzes PHP files when you open or save them. Any detected issues will
appear in the Problems panel.

### Manual Analysis

You can manually trigger analysis using the Command Palette:

1. Open Command Palette (`Ctrl+Shift+P` or `Cmd+Shift+P`)
2. Type "PHP Exception Inspector: Analyze Current File"
3. Press Enter

## What Does It Check?

PHP Exception Inspector detects:

- Missing `@throws` tags in docblocks for thrown exceptions
- Undocumented exceptions that propagate from called functions
- Exception handling issues in your PHP code

## License

This extension is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
