# WP Bulk Mail

Bulk email tools for WordPress with:

- configurable mail drivers
- SMTP support
- recipient management
- recipient tags / segments
- bulk compose flow
- queue-based sending
- scheduled campaign sending
- templates
- campaigns
- background imports
- delivery dashboard
- failed-mail monitor and retry tools
- unsubscribe link support

## Plugin Structure

- `wp-bulk-mail.php`: plugin bootstrap
- `includes/`: plugin classes, drivers, and traits
- `views/`: WordPress admin screens
- `docs/`: project notes and progress summary
- `assets/import-samples/`: sample recipient import files

## Current Features

- WordPress default and SMTP driver support
- saved recipients with search, pagination, tags, and unsubscribe state
- CSV and TXT recipient import with background processing
- bulk send composer for one-to-many mail
- reusable templates with personalization tokens
- campaign builder with manual recipients or tag-based segment targeting
- scheduled sending through the queue layer
- monitoring dashboard for queue and delivery summary
- failed queue monitor with retry actions

## Local Scheduled Send Note

- On localhost or XAMPP, scheduled sending depends on WordPress cron execution.
- If a scheduled campaign does not start during testing, load the site/admin once or run `wp-cron.php` manually.

## Local Development

This repository is intended to track only the `wp-bulk-mail` plugin source, not the full WordPress install.
