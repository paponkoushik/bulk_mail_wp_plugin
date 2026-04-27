# WP Bulk Mail Test Case Matrix

This document maps the current regression test coverage for the plugin so future feature work, UI changes, and refactors can be checked against the existing workflows.

## Purpose

- Protect core mail workflows from regression
- Make expected behavior explicit before refactoring
- Give developers a quick checklist for manual and automated verification

## Automated Runner

Run from the WordPress root:

```powershell
php wp-content/plugins/wp-bulk-mail/tests/run.php
```

Related files:

- `tests/run.php`
- `tests/TestCase.php`
- `tests/README.md`

## Test Case Matrix

### Settings Workflow

Source: `tests/SettingsWorkflowTest.php`

1. `testSmtpPasswordKeepsCurrentValueWhenSubmittedBlank`
Expected:
- SMTP password field can be left blank during settings save
- existing stored password must remain unchanged

2. `testSmtpPasswordWhitespaceIsRemovedBeforeSave`
Expected:
- Google-style spaced app passwords are normalized before save
- stored SMTP password should not keep spaces

3. `testBounceImapPasswordWhitespaceIsRemovedBeforeSave`
Expected:
- IMAP password field also strips spaces before save
- bounce mailbox auth stays consistent with Gmail app password formatting

4. `testCompanyInfoFieldsExposeReusableBrandInputs`
Expected:
- company info screen exposes all required reusable branding fields
- required keys:
  - `company_logo_url`
  - `site_name`
  - `site_url`
  - `company_address`
  - `company_phone`

### Template Workflow

Source: `tests/TemplatesWorkflowTest.php`

1. `testTemplateTokensIncludeRecipientSiteAndBrandingData`
Expected:
- all supported personalization and branding tokens remain available

2. `testTemplateRecordCanBeSavedAndReadBack`
Expected:
- custom template save must persist
- saved subject/body must match input
- fetched template must match stored values

### Bulk Send / Compose Workflow

Source: `tests/ComposeWorkflowTest.php`

1. `testComposeDraftSanitizationPreservesTemplateAndRecipientSelection`
Expected:
- duplicate/invalid recipient IDs are removed
- selected template ID stays attached to the compose draft
- subject and body are preserved

2. `testQueueBulkCampaignCarriesTemplateIdIntoCampaignRecord`
Expected:
- when Bulk Send starts from a saved template, the created campaign must keep that template linkage
- campaign subject/body should still persist correctly

### Queue Workflow

Source: `tests/QueueWorkflowTest.php`

1. `testImmediateRetryCampaignShowsProcessingInsteadOfScheduled`
Expected:
- immediate campaigns waiting for retry should not look like future scheduled campaigns
- status should resolve to `processing`

2. `testScheduledFutureCampaignRemainsScheduledBeforeFirstAttempt`
Expected:
- true future scheduled campaigns should keep `scheduled` status until first run window

3. `testCampaignProgressSnapshotMarksOpenImmediateRetryAsProcessing`
Expected:
- live progress snapshot should calculate:
  - total recipients
  - sent count
  - processing count
  - percent complete
- immediate retry campaigns should surface as `processing`

4. `testCampaignProgressSnapshotMarksAllSentCampaignAsCompleted`
Expected:
- progress snapshot must return `completed`
- progress percent must be `100`
- `is_finished` must be true

### Import Workflow

Source: `tests/ImportWorkflowTest.php`

1. `testCleanupImportFileDeletesFilesInsideUploadsOnly`
Expected:
- imported CSV/TXT files inside uploads should be deleted after cleanup
- files outside uploads must not be deleted

## Manual UI Checks

These are not yet covered by the lightweight runner and should still be checked manually after UI-heavy changes.

### Mail Driver

- driver selection toggles the correct settings panel
- sender identity section saves independently
- bounce tracking section saves independently
- company info lives on its own page

### Company Info

- media uploader opens correctly
- selected logo preview updates instantly
- clear button removes preview and URL

### Templates

- clicking a token inserts it into the focused field
- if no field is focused, token inserts into editor body
- default templates still render expected placeholders

### Bulk Send

- saved template can populate subject/body
- custom edits after template load are preserved
- live queue progress appears without page reload
- progress bar updates until completion

### Monitor

- failed queue items show accurate failure messages
- retry actions move failed rows back into processing flow

## Recommended Use During Refactors

Before refactor:

1. Run `tests/run.php`
2. Read the relevant section in this matrix
3. Identify which workflow is being touched

After refactor:

1. Run `tests/run.php` again
2. Re-check the affected manual UI checklist
3. Add a new automated case for every bug that was fixed during the refactor

## Gaps To Cover Later

- recipient add/edit/delete workflow
- campaign create/edit/queue flow end-to-end
- bounce parsing and bounce matching edge cases
- AJAX progress endpoint response validation
- queue retry button behavior from Monitor page
