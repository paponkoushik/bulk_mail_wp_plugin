# WP Bulk Mail Tests

This plugin ships with a lightweight custom PHP test runner so the suite can run on local WordPress installs without requiring a modern PHPUnit package.

## Run

From the WordPress root:

```powershell
php wp-content/plugins/wp-bulk-mail/tests/run.php
```

## What It Covers

- SMTP password sanitization and preservation
- Bounce IMAP password sanitization
- Company info field coverage
- Template token coverage
- Template save and fetch workflow
- Compose draft sanitization
- Bulk send campaign queue creation with template linkage
- Queue status rules for immediate retries vs scheduled campaigns
- Import upload cleanup safety

## Notes

- The runner loads the active WordPress install through `wp-load.php`.
- Tests create and delete their own records using unique prefixes.
- Because the suite touches the local database, review before running on a site with live in-progress plugin activity.
