<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_BULK_MAIL_ADMIN_SHELL_STYLES_LOADED' ) ) {
	return;
}

define( 'WP_BULK_MAIL_ADMIN_SHELL_STYLES_LOADED', true );
?>
<style>
	.wp-bulk-mail-admin-shell {
		--wbm-bg: linear-gradient(135deg, #f7fbff 0%, #eef6ff 50%, #f8fcfa 100%);
		--wbm-border: #d7e3f1;
		--wbm-border-strong: #bfd4eb;
		--wbm-text: #102a43;
		--wbm-muted: #52667a;
		--wbm-soft: #7b8da1;
		--wbm-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
		--wbm-shadow-soft: 0 10px 24px rgba(15, 23, 42, 0.06);
		--wbm-card-bg: rgba(255, 255, 255, 0.94);
		--wbm-surface: #fcfeff;
		--wbm-blue: #2563eb;
		--wbm-blue-soft: #eaf2ff;
		--wbm-green: #1f9d55;
		--wbm-green-soft: #eaf9f1;
		--wbm-amber: #d97706;
		--wbm-amber-soft: #fff6e8;
		--wbm-red: #dc2626;
		--wbm-red-soft: #fff0f0;
		--wbm-purple: #7c3aed;
		--wbm-purple-soft: #f4ecff;
		margin-top: 18px;
		color: var(--wbm-text);
	}

	.wp-bulk-mail-admin-grid,
	.wp-bulk-mail-admin-stack {
		display: grid;
		gap: 18px;
	}

	.wp-bulk-mail-admin-columns {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 18px;
	}

	.wp-bulk-mail-admin-columns--3 {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}

	.wp-bulk-mail-admin-columns--sidebar {
		grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.9fr);
	}

	.wp-bulk-mail-admin-hero,
	.wp-bulk-mail-admin-card,
	.wp-bulk-mail-admin-metric {
		background: var(--wbm-card-bg);
		border: 1px solid var(--wbm-border);
		border-radius: 22px;
		box-shadow: var(--wbm-shadow);
		color: var(--wbm-text);
	}

	.wp-bulk-mail-admin-hero {
		padding: 28px;
		background-image: var(--wbm-bg);
	}

	.wp-bulk-mail-admin-kicker {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 8px 14px;
		border-radius: 999px;
		background: rgba(255, 255, 255, 0.78);
		border: 1px solid rgba(147, 197, 253, 0.45);
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: var(--wbm-blue);
	}

	.wp-bulk-mail-admin-hero h2 {
		margin: 14px 0 10px;
		font-size: 26px;
		line-height: 1.2;
	}

	.wp-bulk-mail-admin-hero p {
		margin: 0;
		max-width: 760px;
		color: var(--wbm-muted);
		font-size: 14px;
		line-height: 1.6;
	}

	.wp-bulk-mail-admin-pills {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		margin-top: 18px;
	}

	.wp-bulk-mail-admin-pill {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 9px 14px;
		border-radius: 999px;
		background: rgba(255, 255, 255, 0.82);
		border: 1px solid rgba(147, 197, 253, 0.4);
		color: var(--wbm-text);
		font-weight: 600;
	}

	.wp-bulk-mail-admin-pill strong {
		font-size: 16px;
	}

	.wp-bulk-mail-admin-card {
		padding: 22px;
	}

	.wp-bulk-mail-admin-card-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 16px;
		margin-bottom: 18px;
	}

	.wp-bulk-mail-admin-card-header h2,
	.wp-bulk-mail-admin-card-header h3,
	.wp-bulk-mail-admin-card-title {
		margin: 0;
		font-size: 20px;
		line-height: 1.25;
		color: var(--wbm-text);
	}

	.wp-bulk-mail-admin-card-title {
		font-size: 18px;
	}

	.wp-bulk-mail-admin-eyebrow {
		margin: 0 0 8px;
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: var(--wbm-soft);
	}

	.wp-bulk-mail-admin-copy,
	.wp-bulk-mail-admin-card-header p,
	.wp-bulk-mail-admin-shell p.description {
		color: var(--wbm-muted);
		line-height: 1.6;
	}

	.wp-bulk-mail-admin-card-header p,
	.wp-bulk-mail-admin-copy {
		margin: 8px 0 0;
		font-size: 13px;
	}

	.wp-bulk-mail-admin-shell p.description {
		font-size: 13px;
	}

	.wp-bulk-mail-admin-metrics {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
		gap: 14px;
	}

	.wp-bulk-mail-admin-metric {
		padding: 18px;
		box-shadow: var(--wbm-shadow-soft);
	}

	.wp-bulk-mail-admin-metric .label {
		margin: 0 0 10px;
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: var(--wbm-soft);
	}

	.wp-bulk-mail-admin-metric strong {
		display: block;
		font-size: 30px;
		line-height: 1;
		margin-bottom: 8px;
	}

	.wp-bulk-mail-admin-metric span {
		display: block;
		font-size: 13px;
		line-height: 1.5;
		color: var(--wbm-muted);
	}

	.wp-bulk-mail-admin-badge {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 6px 12px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 700;
		line-height: 1;
		background: var(--wbm-blue-soft);
		color: var(--wbm-blue);
	}

	.wp-bulk-mail-admin-badge.is-success {
		background: var(--wbm-green-soft);
		color: var(--wbm-green);
	}

	.wp-bulk-mail-admin-badge.is-warning {
		background: var(--wbm-amber-soft);
		color: var(--wbm-amber);
	}

	.wp-bulk-mail-admin-badge.is-danger {
		background: var(--wbm-red-soft);
		color: var(--wbm-red);
	}

	.wp-bulk-mail-admin-badge.is-accent {
		background: var(--wbm-purple-soft);
		color: var(--wbm-purple);
	}

	.wp-bulk-mail-admin-badge.is-neutral {
		background: #eef4f8;
		color: var(--wbm-muted);
	}

	.wp-bulk-mail-admin-shell .form-table {
		margin-top: 6px;
		margin-bottom: 8px;
	}

	.wp-bulk-mail-admin-shell .form-table th {
		width: 220px;
		padding: 18px 16px 18px 0;
		color: var(--wbm-muted);
		font-size: 13px;
		font-weight: 700;
	}

	.wp-bulk-mail-admin-shell .form-table td {
		padding: 18px 0;
	}

	.wp-bulk-mail-admin-shell .form-table td > input:not([type="checkbox"]):not([type="radio"]),
	.wp-bulk-mail-admin-shell .form-table td > select,
	.wp-bulk-mail-admin-shell .form-table td > textarea,
	.wp-bulk-mail-admin-shell .form-table td > .wp-editor-wrap,
	.wp-bulk-mail-admin-shell .form-table td > .wp-bulk-mail-recipient-picker {
		margin-bottom: 8px;
	}

	.wp-bulk-mail-admin-shell .form-table tr:first-child th,
	.wp-bulk-mail-admin-shell .form-table tr:first-child td {
		padding-top: 8px;
	}

	.wp-bulk-mail-admin-shell .form-table tr:last-child th,
	.wp-bulk-mail-admin-shell .form-table tr:last-child td {
		padding-bottom: 8px;
	}

	.wp-bulk-mail-admin-shell input[type="text"],
	.wp-bulk-mail-admin-shell input[type="email"],
	.wp-bulk-mail-admin-shell input[type="password"],
	.wp-bulk-mail-admin-shell input[type="number"],
	.wp-bulk-mail-admin-shell input[type="search"],
	.wp-bulk-mail-admin-shell input[type="file"],
	.wp-bulk-mail-admin-shell select,
	.wp-bulk-mail-admin-shell textarea {
		border: 1px solid var(--wbm-border-strong);
		border-radius: 12px;
		padding: 10px 12px;
		box-shadow: none;
		background: #fff;
		color: var(--wbm-text);
	}

	.wp-bulk-mail-admin-shell input[type="text"]:focus,
	.wp-bulk-mail-admin-shell input[type="email"]:focus,
	.wp-bulk-mail-admin-shell input[type="password"]:focus,
	.wp-bulk-mail-admin-shell input[type="number"]:focus,
	.wp-bulk-mail-admin-shell input[type="search"]:focus,
	.wp-bulk-mail-admin-shell select:focus,
	.wp-bulk-mail-admin-shell textarea:focus {
		border-color: #7aa8e8;
		box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
		outline: none;
	}

	.wp-bulk-mail-admin-shell select {
		min-height: 44px;
		padding-right: 32px;
	}

	.wp-bulk-mail-admin-shell textarea.large-text,
	.wp-bulk-mail-admin-shell input.large-text {
		width: 100%;
	}

	.wp-bulk-mail-admin-shell input.regular-text,
	.wp-bulk-mail-admin-shell select.regular-text {
		max-width: 460px;
		width: 100%;
	}

	.wp-bulk-mail-admin-shell .button {
		min-height: 40px;
		padding: 0 16px;
		border-radius: 12px;
		box-shadow: none;
	}

	.wp-bulk-mail-admin-shell .button.button-primary {
		border-color: #1d4ed8;
		background: #2563eb;
	}

	.wp-bulk-mail-admin-shell .button.button-secondary {
		border-color: var(--wbm-border-strong);
		background: #fff;
		color: var(--wbm-text);
	}

	.wp-bulk-mail-admin-shell .button-link,
	.wp-bulk-mail-admin-shell .button-link-delete,
	.wp-bulk-mail-admin-shell a {
		text-decoration: none;
	}

	.wp-bulk-mail-admin-shell .submit,
	.wp-bulk-mail-admin-button-row {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 12px;
		margin: 0;
		padding: 0;
	}

	.wp-bulk-mail-admin-shell .wp-editor-wrap {
		border: 1px solid var(--wbm-border);
		border-radius: 18px;
		overflow: hidden;
		box-shadow: var(--wbm-shadow-soft);
	}

	.wp-bulk-mail-admin-shell .mce-toolbar-grp,
	.wp-bulk-mail-admin-shell .quicktags-toolbar,
	.wp-bulk-mail-admin-shell .wp-editor-container {
		border-color: var(--wbm-border);
	}

	.wp-bulk-mail-admin-shell .widefat {
		border: 1px solid var(--wbm-border);
		border-radius: 18px;
		overflow: hidden;
		box-shadow: none;
		margin-top: 14px;
	}

	.wp-bulk-mail-admin-shell .widefat thead th {
		background: #f5f9fe;
		color: var(--wbm-muted);
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		border-bottom: 1px solid var(--wbm-border);
	}

	.wp-bulk-mail-admin-shell .widefat td,
	.wp-bulk-mail-admin-shell .widefat th {
		padding: 14px 16px;
		vertical-align: top;
	}

	.wp-bulk-mail-admin-shell .widefat tbody tr:nth-child(odd) td,
	.wp-bulk-mail-admin-shell .widefat.striped tbody tr:nth-child(odd) td {
		background: #fcfeff;
	}

	.wp-bulk-mail-admin-shell .widefat tbody tr:hover td {
		background: #f6fbff;
	}

	.wp-bulk-mail-admin-list {
		display: grid;
		gap: 12px;
		margin: 0;
		padding: 0;
		list-style: none;
	}

	.wp-bulk-mail-admin-list li {
		padding: 14px 16px;
		border: 1px solid #e4edf7;
		border-radius: 16px;
		background: var(--wbm-surface);
	}

	.wp-bulk-mail-admin-list li strong {
		display: block;
		margin-bottom: 4px;
	}

	.wp-bulk-mail-admin-note {
		padding: 14px 16px;
		border-radius: 16px;
		background: #f8fbfe;
		border: 1px dashed var(--wbm-border-strong);
		color: var(--wbm-muted);
	}

	.wp-bulk-mail-admin-empty {
		padding: 20px;
		border: 1px dashed var(--wbm-border-strong);
		border-radius: 18px;
		background: #fbfdff;
		color: var(--wbm-muted);
	}

	.wp-bulk-mail-admin-toolbar,
	.wp-bulk-mail-admin-inline-actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
	}

	.wp-bulk-mail-admin-search-form {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 12px;
		margin: 12px 0 18px;
	}

	.wp-bulk-mail-admin-pagination {
		margin-top: 18px;
	}

	.wp-bulk-mail-admin-shell .tablenav-pages {
		float: none;
		margin: 0;
	}

	.wp-bulk-mail-admin-shell .page-numbers {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 38px;
		height: 38px;
		padding: 0 12px;
		border: 1px solid var(--wbm-border);
		border-radius: 12px;
		background: #fff;
		color: var(--wbm-text);
		margin-right: 8px;
	}

	.wp-bulk-mail-admin-shell .page-numbers.current {
		background: #2563eb;
		border-color: #2563eb;
		color: #fff;
	}

	.wp-bulk-mail-recipient-picker {
		position: relative;
		max-width: 720px;
	}

	.wp-bulk-mail-recipient-trigger {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 14px;
		width: 100%;
		min-height: 58px;
		padding: 14px 16px;
		border: 1px solid var(--wbm-border-strong);
		border-radius: 16px;
		background: #fff;
		cursor: pointer;
		text-align: left;
		transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
	}

	.wp-bulk-mail-recipient-picker.is-open .wp-bulk-mail-recipient-trigger,
	.wp-bulk-mail-recipient-trigger:hover,
	.wp-bulk-mail-recipient-trigger:focus {
		border-color: #7aa8e8;
		box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
		outline: none;
		transform: translateY(-1px);
	}

	.wp-bulk-mail-recipient-trigger-main {
		display: flex;
		flex-direction: column;
		gap: 4px;
		min-width: 0;
	}

	.wp-bulk-mail-recipient-trigger-label {
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: var(--wbm-soft);
	}

	.wp-bulk-mail-recipient-trigger-value {
		font-size: 14px;
		color: var(--wbm-text);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.wp-bulk-mail-recipient-trigger-value.is-placeholder {
		color: var(--wbm-muted);
	}

	.wp-bulk-mail-recipient-trigger-side {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		flex-shrink: 0;
	}

	.wp-bulk-mail-recipient-badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 30px;
		height: 24px;
		padding: 0 10px;
		border-radius: 999px;
		background: #2563eb;
		color: #fff;
		font-size: 12px;
		font-weight: 700;
		line-height: 1;
	}

	.wp-bulk-mail-recipient-chevron {
		font-size: 18px;
		color: var(--wbm-muted);
		transition: transform 0.15s ease;
	}

	.wp-bulk-mail-recipient-picker.is-open .wp-bulk-mail-recipient-chevron {
		transform: rotate(180deg);
	}

	.wp-bulk-mail-recipient-panel {
		position: absolute;
		top: calc(100% + 10px);
		left: 0;
		right: 0;
		z-index: 20;
		border: 1px solid var(--wbm-border);
		border-radius: 18px;
		background: #fff;
		box-shadow: var(--wbm-shadow);
		overflow: hidden;
	}

	.wp-bulk-mail-recipient-panel[hidden],
	.wp-bulk-mail-recipient-empty[hidden] {
		display: none;
	}

	.wp-bulk-mail-recipient-search-wrap,
	.wp-bulk-mail-recipient-tools {
		padding: 12px 14px;
		background: #fff;
	}

	.wp-bulk-mail-recipient-search-wrap {
		border-bottom: 1px solid #e5edf6;
	}

	.wp-bulk-mail-recipient-tools {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		border-bottom: 1px solid #e5edf6;
		background: #f7fbff;
	}

	.wp-bulk-mail-recipient-tools label {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		font-weight: 600;
		color: var(--wbm-text);
	}

	.wp-bulk-mail-recipient-options {
		display: flex;
		flex-direction: column;
		max-height: 320px;
		overflow-y: auto;
		background: #fff;
	}

	.wp-bulk-mail-recipient-option {
		display: flex;
		align-items: flex-start;
		gap: 12px;
		padding: 13px 14px;
		border-bottom: 1px solid #eef4f8;
		cursor: pointer;
		transition: background 0.15s ease;
	}

	.wp-bulk-mail-recipient-option[hidden] {
		display: none;
	}

	.wp-bulk-mail-recipient-option:hover {
		background: #f8fbff;
	}

	.wp-bulk-mail-recipient-option[data-selected="true"] {
		order: -1;
		background: #eef5ff;
	}

	.wp-bulk-mail-recipient-option:last-child {
		border-bottom: 0;
	}

	.wp-bulk-mail-recipient-option input[type="checkbox"] {
		margin-top: 2px;
		accent-color: #2563eb;
	}

	.wp-bulk-mail-recipient-option-copy {
		display: flex;
		flex-direction: column;
		gap: 4px;
		min-width: 0;
	}

	.wp-bulk-mail-recipient-option-name {
		font-size: 14px;
		font-weight: 600;
		color: var(--wbm-text);
		word-break: break-word;
	}

	.wp-bulk-mail-recipient-option-email {
		font-size: 13px;
		color: var(--wbm-muted);
		word-break: break-word;
	}

	.wp-bulk-mail-recipient-empty {
		padding: 18px 14px;
		color: var(--wbm-muted);
		border-top: 1px solid #eef4f8;
	}

	.wp-bulk-mail-recipient-hint {
		margin-top: 12px;
	}

	.wp-bulk-mail-admin-token-list {
		display: grid;
		gap: 12px;
	}

	.wp-bulk-mail-admin-token-item {
		padding: 14px 16px;
		border: 1px solid #e4edf7;
		border-radius: 16px;
		background: var(--wbm-surface);
	}

	.wp-bulk-mail-admin-token-item code {
		display: inline-block;
		margin-bottom: 6px;
		font-weight: 700;
	}

	.wp-bulk-mail-admin-shell .notice {
		margin: 0 0 18px;
	}

	@media screen and (max-width: 960px) {
		.wp-bulk-mail-admin-columns,
		.wp-bulk-mail-admin-columns--3,
		.wp-bulk-mail-admin-columns--sidebar {
			grid-template-columns: 1fr;
		}
	}

	@media screen and (max-width: 782px) {
		.wp-bulk-mail-admin-hero,
		.wp-bulk-mail-admin-card,
		.wp-bulk-mail-admin-metric {
			border-radius: 18px;
		}

		.wp-bulk-mail-admin-shell .form-table th,
		.wp-bulk-mail-admin-shell .form-table td {
			display: block;
			width: 100%;
			padding-right: 0;
		}

		.wp-bulk-mail-admin-toolbar,
		.wp-bulk-mail-admin-inline-actions,
		.wp-bulk-mail-admin-search-form,
		.wp-bulk-mail-recipient-tools,
		.wp-bulk-mail-admin-button-row,
		.wp-bulk-mail-admin-shell .submit {
			flex-direction: column;
			align-items: stretch;
		}

		.wp-bulk-mail-recipient-panel {
			position: static;
			margin-top: 10px;
		}
	}
</style>
