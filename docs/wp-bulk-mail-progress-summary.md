# WP Bulk Mail Progress Summary

- Document date: April 20, 2026
- Plugin version: 0.7.0
- Local plugin path: `c:\xampp\htdocs\wp-mail\wp-content\plugins\wp-bulk-mail`
- GitHub repository: `git@github.com:paponkoushik/bulk_mail_wp_plugin.git`

## Project Scope

- Built a WordPress bulk mail plugin instead of storing the full WordPress site in Git.
- Kept the plugin isolated inside the `wp-bulk-mail` folder.
- Created a clean local Git repository only for the plugin source.

## Completed Feature 1 - Mail Driver Configuration

- Added a mail driver architecture with a driver contract and registry.
- Implemented WordPress default mail sending support.
- Implemented SMTP configuration support.
- Added sender identity settings for `From Email` and `From Name`.
- Prepared placeholder drivers for Amazon SES, SendGrid, Mailgun, Postmark, Brevo, and Resend.
- Verified Gmail SMTP sending with a real send test.

## Completed Recipient Management

- Added a dedicated `Recipients` admin page.
- Added one-by-one recipient save flow with `Name` and `Email Address`.
- Added recipient tags / segment labels so campaigns can target saved groups.
- Added recipient search, pagination, edit, and delete actions.
- Added status filters for active and unsubscribed recipients.
- Stored recipients in the custom database table `wp_bulk_mail_recipients`.
- Removed the unused legacy table variant when identified.
- Added CSV and TXT import support.
- Added sample CSV and TXT files for import guidance.
- Moved large recipient import processing to a background import job system.
- Added unsubscribe token storage and public unsubscribe handling.

## Completed Bulk Send Flow

- Added a dedicated `Bulk Send` page separated from settings.
- Added searchable multi-select recipient selection from saved recipients.
- Added draft save behavior.
- Added shared `Subject` and `Mail Body` fields for one-to-many sending.
- Ensured each recipient gets an individual email so recipient addresses stay private.
- Prevented auto-selected first recipient by clearing saved selection behavior after send.

## Completed Queue and Background Processing

- Replaced direct synchronous sending with database-backed queue processing.
- Added custom tables for campaigns, campaign recipients, queue items, import jobs, and templates.
- Added queue statuses such as `pending`, `processing`, `sent`, and `failed`.
- Added retry and lock timing support in the queue layer.
- Added Action Scheduler detection.
- Added WP-Cron fallback when Action Scheduler is not installed.
- Kept sent queue rows for monitoring and history instead of deleting them immediately.
- Added scheduled send support by storing per-item `scheduled_at` values in the queue.
- Fixed queue rescheduling so an older future cron event does not block a newly due scheduled campaign.

## Completed Templates

- Added a `Templates` page for reusable email content.
- Added a builder-style template editor.
- Added default templates.
- Added template tokens: `{{recipient_name}}`, `{{recipient_email}}`, `{{site_name}}`, `{{site_url}}`, and `{{unsubscribe_url}}`.
- Added template save, edit, and delete flows.

## Completed Campaigns

- Added a `Campaigns` page.
- Added create and edit campaign flows.
- Added template selection inside a campaign.
- Added recipient assignment inside a campaign.
- Added segment-based recipient targeting from saved tags.
- Added scheduled campaign sending with `Queue immediately` and `Schedule for later` modes.
- Added save campaign and queue campaign actions.
- Stored campaign and campaign recipient relationships in custom tables.

## Completed Dashboard and Monitoring Summary

- Added a top-level dashboard as the main `Bulk Mail` entry screen.
- Added summary cards for `Bounce Mail`, `Sent Mail`, `Mail Can't Send`, `Wrong Mail Address`, `Total Send Mail`, and `Spam Mail`.
- Added queue and campaign summary metrics.
- Added chart-based visual summaries including delivery split and a last 7 days view.
- Classified failure messages into estimated buckets for bounce, spam, wrong address, and cannot send.
- Added a dedicated `Monitor` page for failed queue rows.
- Added retry actions for one failed row, one campaign, or all failed rows.

## Refactor and Design Improvements

- Refactored the oversized main plugin class into smaller traits.
- Split logic into traits for admin, settings, storage, queue, import, recipients, compose, templates, campaigns, dashboard, and monitor.
- Added a shared admin design system for visual consistency.
- Redesigned the remaining admin screens to match the dashboard style.
- Improved spacing and layout consistency across forms, cards, tables, and pickers.

## Database and Storage Summary

- Core WordPress site database was already created and installed.
- Plugin data is stored in the same WordPress database, not in a separate database.
- Active plugin-related tables now include `wp_bulk_mail_recipients`, `wp_bulk_mail_campaigns`, `wp_bulk_mail_campaign_recipients`, `wp_bulk_mail_queue`, `wp_bulk_mail_import_jobs`, and `wp_bulk_mail_templates`.
- Recipient rows now store tags, unsubscribe tokens, unsubscribe status, and unsubscribe timestamps.
- Campaign rows now store send mode, segment tag, and scheduled datetime values.

## Important Current Notes

- Bounce and spam reporting are estimated from failure message classification until provider webhooks or APIs are integrated.
- Action Scheduler is supported automatically, but the current site can fall back to WP-Cron.
- On localhost / XAMPP, scheduled jobs depend on WordPress cron execution. A page hit or a manual `wp-cron.php` run may be needed during testing.
- `Save Campaign` keeps a campaign in draft mode. `Queue Campaign` is required to activate immediate or scheduled sending.
- The plugin source is tracked in Git, while the full WordPress install is intentionally not part of the repository.
- Temporary helper files still exist in the WordPress root outside the plugin repo and are not part of the plugin Git history.

## Suggested Next Steps

- Add provider-specific integrations such as Amazon SES or other API-based drivers.
- Add webhook-based bounce, complaint, and delivery event syncing.
- Add export tools for recipients, failures, and campaign results.
- Add a manual `Run Queue Now` control for local development and troubleshooting.
- Add licensing / SaaS connection features when the commercial rollout starts.
