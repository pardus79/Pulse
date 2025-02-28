# Pulse - WordPress Lightning Network Affiliate Plugin

## Development Commands
- No formal build system (PHP WordPress plugin)
- Manual testing in WordPress environment required
- Debug: Add `error_log('Debug message');` for logging
- Plugin testing: Activate in WordPress admin and check logs

## Code Style Guidelines
- Follow WordPress Coding Standards
- Class names: Snake_Case with capitalized words (Pulse_Loader)
- Use PHP namespaces (namespace Pulse)
- File naming: class-{classname}.php for class files
- Documentation: PHPDoc style comments for classes/methods
- Error handling: Log errors with error_log() and return false
- Security: Sanitize inputs, use nonces for AJAX, validate permissions

## Project Structure
- Main plugin file: pulse.php
- Class files in /includes/ directory
- Frontend assets in /css/ and /js/ directories
- Templates in /templates/ directory
- Supports both BTCPay Server 1.x and 2.0

## Dependencies
- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.2+
- BTCPay Server (1.x or 2.0)