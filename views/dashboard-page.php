<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats              = $dashboard_metrics['stats'];
$distribution       = $dashboard_metrics['distribution'];
$distribution_total = (int) $dashboard_metrics['distribution_total'];
$timeline           = $dashboard_metrics['timeline'];
$timeline_max       = max( 1, (int) $dashboard_metrics['timeline_max'] );
$latest_campaign    = $dashboard_metrics['latest_campaign'];
$segment_colors     = array(
	'sent_mail'                => '#1f9d55',
	'bounce_mail'              => '#d97706',
	'wrong_mail_address'       => '#dc2626',
	'import_invalid_addresses' => '#ea580c',
	'spam_mail'                => '#7c3aed',
	'mail_cant_send'           => '#2563eb',
);
$distribution_labels = array(
	'sent_mail'                => __( 'Sent Mail', 'wp-bulk-mail' ),
	'bounce_mail'              => __( 'Bounce Mail', 'wp-bulk-mail' ),
	'wrong_mail_address'       => __( 'Wrong Mail Address', 'wp-bulk-mail' ),
	'import_invalid_addresses' => __( 'Import Invalid', 'wp-bulk-mail' ),
	'spam_mail'                => __( 'Spam Mail', 'wp-bulk-mail' ),
	'mail_cant_send'           => __( "Mail Can't Send", 'wp-bulk-mail' ),
);
$gradient_parts = array();
$cursor         = 0;

foreach ( $distribution as $key => $value ) {
	$value = (int) $value;

	if ( $distribution_total < 1 || $value < 1 ) {
		continue;
	}

	$span            = max( 1, ( $value / $distribution_total ) * 360 );
	$gradient_parts[] = sprintf(
		'%1$s %2$.2fdeg %3$.2fdeg',
		$segment_colors[ $key ],
		$cursor,
		min( 360, $cursor + $span )
	);
	$cursor += $span;
}

$chart_gradient = ! empty( $gradient_parts ) ? implode( ', ', $gradient_parts ) : '#dfe7ef 0deg 360deg';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Bulk Mail Dashboard', 'wp-bulk-mail' ); ?></h1>
	<p>
		<?php esc_html_e( 'Monitor queue delivery health, import list quality, and recent send activity from one clean overview.', 'wp-bulk-mail' ); ?>
	</p>

	<style>
		.wp-bulk-mail-dashboard {
			--dash-bg: linear-gradient(135deg, #f7fbff 0%, #eef6ff 50%, #f8fcfa 100%);
			--dash-border: #d7e3f1;
			--dash-text: #102a43;
			--dash-muted: #52667a;
			--dash-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
			--dash-card-bg: rgba(255, 255, 255, 0.92);
			margin-top: 18px;
		}

		.wp-bulk-mail-dashboard-grid {
			display: grid;
			gap: 18px;
		}

		.wp-bulk-mail-dashboard-hero,
		.wp-bulk-mail-dashboard-card {
			background: var(--dash-card-bg);
			border: 1px solid var(--dash-border);
			border-radius: 22px;
			box-shadow: var(--dash-shadow);
			color: var(--dash-text);
		}

		.wp-bulk-mail-dashboard-hero {
			padding: 28px;
			background-image: var(--dash-bg);
		}

		.wp-bulk-mail-dashboard-hero h2 {
			margin: 0 0 10px;
			font-size: 26px;
			line-height: 1.2;
		}

		.wp-bulk-mail-dashboard-hero p {
			margin: 0;
			max-width: 760px;
			color: var(--dash-muted);
			font-size: 14px;
		}

		.wp-bulk-mail-dashboard-pills {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			margin-top: 18px;
		}

		.wp-bulk-mail-dashboard-pill {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 9px 14px;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.8);
			border: 1px solid rgba(147, 197, 253, 0.45);
			color: var(--dash-text);
			font-weight: 600;
		}

		.wp-bulk-mail-dashboard-pill strong {
			font-size: 16px;
		}

		.wp-bulk-mail-dashboard-stats {
			display: grid;
			grid-template-columns: repeat(7, minmax(0, 1fr));
			gap: 16px;
		}

		.wp-bulk-mail-dashboard-card {
			padding: 18px;
		}

		.wp-bulk-mail-dashboard-card h3,
		.wp-bulk-mail-dashboard-section-title {
			margin: 0 0 8px;
			font-size: 15px;
			color: var(--dash-muted);
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		.wp-bulk-mail-dashboard-card-value {
			font-size: 34px;
			font-weight: 800;
			line-height: 1;
			margin: 0 0 10px;
		}

		.wp-bulk-mail-dashboard-card p {
			margin: 0;
			color: var(--dash-muted);
			font-size: 13px;
			line-height: 1.5;
		}

		.wp-bulk-mail-dashboard-panels {
			display: grid;
			grid-template-columns: 1.1fr 1.4fr;
			gap: 18px;
		}

		.wp-bulk-mail-dashboard-panel {
			padding: 22px;
		}

		.wp-bulk-mail-dashboard-panel-header {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 16px;
		}

		.wp-bulk-mail-dashboard-panel-header p {
			margin: 0;
			color: var(--dash-muted);
			font-size: 13px;
			line-height: 1.5;
		}

		.wp-bulk-mail-dashboard-donut-wrap {
			display: grid;
			grid-template-columns: 220px 1fr;
			gap: 18px;
			align-items: center;
		}

		.wp-bulk-mail-dashboard-donut {
			position: relative;
			width: 220px;
			height: 220px;
			border-radius: 50%;
			background: conic-gradient(<?php echo esc_attr( $chart_gradient ); ?>);
			margin: 0 auto;
		}

		.wp-bulk-mail-dashboard-donut::after {
			content: "";
			position: absolute;
			inset: 26px;
			border-radius: 50%;
			background: #fff;
			box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.18);
		}

		.wp-bulk-mail-dashboard-donut-center {
			position: absolute;
			inset: 0;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			z-index: 1;
			text-align: center;
		}

		.wp-bulk-mail-dashboard-donut-center strong {
			font-size: 34px;
			line-height: 1;
		}

		.wp-bulk-mail-dashboard-donut-center span {
			color: var(--dash-muted);
			font-size: 13px;
			margin-top: 8px;
		}

		.wp-bulk-mail-dashboard-legend {
			display: grid;
			gap: 12px;
		}

		.wp-bulk-mail-dashboard-legend-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 14px;
			padding: 12px 14px;
			border: 1px solid #e4edf7;
			border-radius: 14px;
			background: #fcfeff;
		}

		.wp-bulk-mail-dashboard-legend-item-label {
			display: inline-flex;
			align-items: center;
			gap: 10px;
			font-weight: 600;
		}

		.wp-bulk-mail-dashboard-legend-swatch {
			width: 12px;
			height: 12px;
			border-radius: 999px;
			flex-shrink: 0;
		}

		.wp-bulk-mail-dashboard-legend-item span:last-child {
			color: var(--dash-muted);
			font-weight: 700;
		}

		.wp-bulk-mail-dashboard-bars {
			display: grid;
			grid-template-columns: repeat(7, minmax(0, 1fr));
			gap: 14px;
			align-items: end;
			min-height: 270px;
		}

		.wp-bulk-mail-dashboard-bar-col {
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 12px;
			height: 100%;
		}

		.wp-bulk-mail-dashboard-bar-stack {
			display: flex;
			align-items: end;
			justify-content: center;
			gap: 6px;
			height: 220px;
			width: 100%;
		}

		.wp-bulk-mail-dashboard-bar {
			width: 18px;
			min-height: 6px;
			border-radius: 999px 999px 4px 4px;
			background: #cbd5e1;
			position: relative;
		}

		.wp-bulk-mail-dashboard-bar--queued {
			background: linear-gradient(180deg, #7dd3fc 0%, #0ea5e9 100%);
		}

		.wp-bulk-mail-dashboard-bar--sent {
			background: linear-gradient(180deg, #86efac 0%, #16a34a 100%);
		}

		.wp-bulk-mail-dashboard-bar--failed {
			background: linear-gradient(180deg, #fda4af 0%, #e11d48 100%);
		}

		.wp-bulk-mail-dashboard-bar-label {
			font-size: 12px;
			color: var(--dash-muted);
			font-weight: 700;
		}

		.wp-bulk-mail-dashboard-chart-legend {
			display: flex;
			flex-wrap: wrap;
			gap: 14px;
			margin-top: 14px;
		}

		.wp-bulk-mail-dashboard-chart-legend-item {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			font-size: 13px;
			color: var(--dash-muted);
			font-weight: 600;
		}

		.wp-bulk-mail-dashboard-mini {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 18px;
		}

		.wp-bulk-mail-dashboard-mini-card {
			padding: 20px 22px;
		}

		.wp-bulk-mail-dashboard-mini-card ul {
			margin: 0;
			padding-left: 18px;
			color: var(--dash-muted);
		}

		.wp-bulk-mail-dashboard-mini-card li + li {
			margin-top: 8px;
		}

		.wp-bulk-mail-dashboard-mini-card strong {
			color: var(--dash-text);
		}

		@media screen and (max-width: 1400px) {
			.wp-bulk-mail-dashboard-stats {
				grid-template-columns: repeat(3, minmax(0, 1fr));
			}
		}

		@media screen and (max-width: 1100px) {
			.wp-bulk-mail-dashboard-panels,
			.wp-bulk-mail-dashboard-mini {
				grid-template-columns: 1fr;
			}

			.wp-bulk-mail-dashboard-donut-wrap {
				grid-template-columns: 1fr;
			}
		}

		@media screen and (max-width: 782px) {
			.wp-bulk-mail-dashboard-stats {
				grid-template-columns: 1fr;
			}

			.wp-bulk-mail-dashboard-hero,
			.wp-bulk-mail-dashboard-card,
			.wp-bulk-mail-dashboard-panel,
			.wp-bulk-mail-dashboard-mini-card {
				padding: 18px;
			}

			.wp-bulk-mail-dashboard-bars {
				gap: 10px;
			}
		}
	</style>

	<div class="wp-bulk-mail-dashboard wp-bulk-mail-dashboard-grid">
		<section class="wp-bulk-mail-dashboard-hero">
			<h2><?php esc_html_e( 'Delivery Monitoring At A Glance', 'wp-bulk-mail' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: background runner label */
						__( 'This overview is powered by your queue history and import logs. Background processing is currently handled by %s.', 'wp-bulk-mail' ),
						$dashboard_metrics['runner_label']
					)
				);
				?>
			</p>

			<div class="wp-bulk-mail-dashboard-pills">
				<div class="wp-bulk-mail-dashboard-pill">
					<?php esc_html_e( 'Queued Now', 'wp-bulk-mail' ); ?>
					<strong><?php echo esc_html( (string) $stats['queued_mail'] ); ?></strong>
				</div>
				<div class="wp-bulk-mail-dashboard-pill">
					<?php esc_html_e( 'Processing', 'wp-bulk-mail' ); ?>
					<strong><?php echo esc_html( (string) $stats['processing_mail'] ); ?></strong>
				</div>
				<div class="wp-bulk-mail-dashboard-pill">
					<?php esc_html_e( 'Active Campaigns', 'wp-bulk-mail' ); ?>
					<strong><?php echo esc_html( (string) $stats['active_campaigns'] ); ?></strong>
				</div>
				<div class="wp-bulk-mail-dashboard-pill">
					<?php esc_html_e( 'Completed Campaigns', 'wp-bulk-mail' ); ?>
					<strong><?php echo esc_html( (string) $stats['completed_campaigns'] ); ?></strong>
				</div>
			</div>
		</section>

		<section class="wp-bulk-mail-dashboard-stats">
			<div class="wp-bulk-mail-dashboard-card">
				<h3><?php esc_html_e( 'Bounce Mail', 'wp-bulk-mail' ); ?></h3>
				<div class="wp-bulk-mail-dashboard-card-value"><?php echo esc_html( (string) $stats['bounce_mail'] ); ?></div>
				<p><?php esc_html_e( 'Bounce-like failures detected from delivery logs and SMTP response text.', 'wp-bulk-mail' ); ?></p>
			</div>
			<div class="wp-bulk-mail-dashboard-card">
				<h3><?php esc_html_e( 'Sent Mail', 'wp-bulk-mail' ); ?></h3>
				<div class="wp-bulk-mail-dashboard-card-value"><?php echo esc_html( (string) $stats['sent_mail'] ); ?></div>
				<p><?php esc_html_e( 'Messages successfully delivered through the plugin queue so far.', 'wp-bulk-mail' ); ?></p>
			</div>
			<div class="wp-bulk-mail-dashboard-card">
				<h3><?php esc_html_e( "Mail Can't Send", 'wp-bulk-mail' ); ?></h3>
				<div class="wp-bulk-mail-dashboard-card-value"><?php echo esc_html( (string) $stats['mail_cant_send'] ); ?></div>
				<p><?php esc_html_e( 'Failures that were not recognized as bounce, spam, or wrong-address issues.', 'wp-bulk-mail' ); ?></p>
			</div>
			<div class="wp-bulk-mail-dashboard-card">
				<h3><?php esc_html_e( 'Wrong Mail Address', 'wp-bulk-mail' ); ?></h3>
				<div class="wp-bulk-mail-dashboard-card-value"><?php echo esc_html( (string) $stats['wrong_mail_address'] ); ?></div>
				<p><?php esc_html_e( 'Delivery failures caused by invalid-recipient style SMTP or provider responses.', 'wp-bulk-mail' ); ?></p>
			</div>
			<div class="wp-bulk-mail-dashboard-card">
				<h3><?php esc_html_e( 'Import Invalid', 'wp-bulk-mail' ); ?></h3>
				<div class="wp-bulk-mail-dashboard-card-value"><?php echo esc_html( (string) $stats['import_invalid_addresses'] ); ?></div>
				<p><?php esc_html_e( 'Addresses rejected during CSV or TXT import validation before they ever reached the queue.', 'wp-bulk-mail' ); ?></p>
			</div>
			<div class="wp-bulk-mail-dashboard-card">
				<h3><?php esc_html_e( 'Total Send Mail', 'wp-bulk-mail' ); ?></h3>
				<div class="wp-bulk-mail-dashboard-card-value"><?php echo esc_html( (string) $stats['total_send_mail'] ); ?></div>
				<p><?php esc_html_e( 'All queue records created by the plugin, including sent, failed, pending, and processing mail.', 'wp-bulk-mail' ); ?></p>
			</div>
			<div class="wp-bulk-mail-dashboard-card">
				<h3><?php esc_html_e( 'Spam Mail', 'wp-bulk-mail' ); ?></h3>
				<div class="wp-bulk-mail-dashboard-card-value"><?php echo esc_html( (string) $stats['spam_mail'] ); ?></div>
				<p><?php esc_html_e( 'Spam-like or blocked-message failures estimated from the provider error text.', 'wp-bulk-mail' ); ?></p>
			</div>
		</section>

		<section class="wp-bulk-mail-dashboard-panels">
			<div class="wp-bulk-mail-dashboard-card wp-bulk-mail-dashboard-panel">
				<div class="wp-bulk-mail-dashboard-panel-header">
					<div>
						<div class="wp-bulk-mail-dashboard-section-title"><?php esc_html_e( 'All-Time Delivery Split', 'wp-bulk-mail' ); ?></div>
						<p><?php esc_html_e( 'A cumulative view of successful delivery, final send failures, and invalid import addresses.', 'wp-bulk-mail' ); ?></p>
					</div>
				</div>

				<div class="wp-bulk-mail-dashboard-donut-wrap">
					<div class="wp-bulk-mail-dashboard-donut">
						<div class="wp-bulk-mail-dashboard-donut-center">
							<strong><?php echo esc_html( (string) $distribution_total ); ?></strong>
							<span><?php esc_html_e( 'tracked events', 'wp-bulk-mail' ); ?></span>
						</div>
					</div>

					<div class="wp-bulk-mail-dashboard-legend">
						<?php foreach ( $distribution as $key => $value ) : ?>
							<div class="wp-bulk-mail-dashboard-legend-item">
								<span class="wp-bulk-mail-dashboard-legend-item-label">
									<span class="wp-bulk-mail-dashboard-legend-swatch" style="background: <?php echo esc_attr( $segment_colors[ $key ] ); ?>;"></span>
									<?php echo esc_html( $distribution_labels[ $key ] ); ?>
								</span>
								<span><?php echo esc_html( (string) $value ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="wp-bulk-mail-dashboard-card wp-bulk-mail-dashboard-panel">
				<div class="wp-bulk-mail-dashboard-panel-header">
					<div>
						<div class="wp-bulk-mail-dashboard-section-title"><?php esc_html_e( 'Last 7 Days Queue Activity', 'wp-bulk-mail' ); ?></div>
						<p><?php esc_html_e( 'Daily queue creation, successful sends, and final send failures. Import validation issues are tracked separately above.', 'wp-bulk-mail' ); ?></p>
					</div>
				</div>

				<div class="wp-bulk-mail-dashboard-bars">
					<?php foreach ( $timeline['labels'] as $index => $label ) : ?>
						<?php
						$queued_height = max( 6, round( ( (int) $timeline['queued'][ $index ] / $timeline_max ) * 200 ) );
						$sent_height   = max( 6, round( ( (int) $timeline['sent'][ $index ] / $timeline_max ) * 200 ) );
						$failed_height = max( 6, round( ( (int) $timeline['failed'][ $index ] / $timeline_max ) * 200 ) );
						?>
						<div class="wp-bulk-mail-dashboard-bar-col">
							<div class="wp-bulk-mail-dashboard-bar-stack">
								<div class="wp-bulk-mail-dashboard-bar wp-bulk-mail-dashboard-bar--queued" style="height: <?php echo esc_attr( (string) $queued_height ); ?>px;" title="<?php echo esc_attr( sprintf( __( 'Queued: %d', 'wp-bulk-mail' ), (int) $timeline['queued'][ $index ] ) ); ?>"></div>
								<div class="wp-bulk-mail-dashboard-bar wp-bulk-mail-dashboard-bar--sent" style="height: <?php echo esc_attr( (string) $sent_height ); ?>px;" title="<?php echo esc_attr( sprintf( __( 'Sent: %d', 'wp-bulk-mail' ), (int) $timeline['sent'][ $index ] ) ); ?>"></div>
								<div class="wp-bulk-mail-dashboard-bar wp-bulk-mail-dashboard-bar--failed" style="height: <?php echo esc_attr( (string) $failed_height ); ?>px;" title="<?php echo esc_attr( sprintf( __( 'Failed: %d', 'wp-bulk-mail' ), (int) $timeline['failed'][ $index ] ) ); ?>"></div>
							</div>
							<div class="wp-bulk-mail-dashboard-bar-label"><?php echo esc_html( $label ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="wp-bulk-mail-dashboard-chart-legend">
					<span class="wp-bulk-mail-dashboard-chart-legend-item"><span class="wp-bulk-mail-dashboard-legend-swatch" style="background:#0ea5e9;"></span><?php esc_html_e( 'Queued', 'wp-bulk-mail' ); ?></span>
					<span class="wp-bulk-mail-dashboard-chart-legend-item"><span class="wp-bulk-mail-dashboard-legend-swatch" style="background:#16a34a;"></span><?php esc_html_e( 'Sent', 'wp-bulk-mail' ); ?></span>
					<span class="wp-bulk-mail-dashboard-chart-legend-item"><span class="wp-bulk-mail-dashboard-legend-swatch" style="background:#e11d48;"></span><?php esc_html_e( 'Failed', 'wp-bulk-mail' ); ?></span>
				</div>
			</div>
		</section>

		<section class="wp-bulk-mail-dashboard-mini">
			<div class="wp-bulk-mail-dashboard-card wp-bulk-mail-dashboard-mini-card">
				<div class="wp-bulk-mail-dashboard-section-title"><?php esc_html_e( 'Latest Campaign', 'wp-bulk-mail' ); ?></div>
				<?php if ( ! empty( $latest_campaign ) ) : ?>
					<ul>
						<li><strong><?php esc_html_e( 'Name:', 'wp-bulk-mail' ); ?></strong> <?php echo esc_html( ! empty( $latest_campaign['name'] ) ? $latest_campaign['name'] : $latest_campaign['subject'] ); ?></li>
						<li><strong><?php esc_html_e( 'Status:', 'wp-bulk-mail' ); ?></strong> <?php echo esc_html( ucfirst( (string) $latest_campaign['status'] ) ); ?></li>
						<li><strong><?php esc_html_e( 'Queued:', 'wp-bulk-mail' ); ?></strong> <?php echo esc_html( (string) $latest_campaign['total_recipients'] ); ?></li>
						<li><strong><?php esc_html_e( 'Sent:', 'wp-bulk-mail' ); ?></strong> <?php echo esc_html( (string) $latest_campaign['sent_count'] ); ?></li>
						<li><strong><?php esc_html_e( 'Failed:', 'wp-bulk-mail' ); ?></strong> <?php echo esc_html( (string) $latest_campaign['failed_count'] ); ?></li>
					</ul>
				<?php else : ?>
					<p><?php esc_html_e( 'No queued campaign activity found yet.', 'wp-bulk-mail' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="wp-bulk-mail-dashboard-card wp-bulk-mail-dashboard-mini-card">
				<div class="wp-bulk-mail-dashboard-section-title"><?php esc_html_e( 'Monitoring Note', 'wp-bulk-mail' ); ?></div>
				<ul>
					<li><?php esc_html_e( 'Bounce and spam counts are estimated from send-failure messages unless you later connect a provider API or webhook.', 'wp-bulk-mail' ); ?></li>
					<li><?php esc_html_e( 'Import Invalid counts bad addresses caught before queueing, while Wrong Mail Address shows send-time recipient failures only.', 'wp-bulk-mail' ); ?></li>
					<li><?php esc_html_e( 'For deeper delivery analytics later, we can add a dedicated Monitor page with per-campaign breakdowns.', 'wp-bulk-mail' ); ?></li>
				</ul>
			</div>
		</section>
	</div>
</div>
