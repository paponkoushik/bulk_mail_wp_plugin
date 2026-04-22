<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Bounces_Trait {

	/**
	 * Store one bounce-sync notice for the current user.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_bounce_notice( $type, $message ) {
		set_transient(
			'wp_bulk_mail_bounce_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Get and clear the bounce-sync notice.
	 *
	 * @return array|null
	 */
	public function get_bounce_notice() {
		$key    = 'wp_bulk_mail_bounce_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( false === $notice ) {
			return null;
		}

		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Check whether mailbox sync support is available.
	 *
	 * @return bool
	 */
	public function is_imap_available() {
		return function_exists( 'imap_open' ) && function_exists( 'imap_search' ) && function_exists( 'imap_body' ) && function_exists( 'imap_fetchheader' ) && function_exists( 'imap_close' );
	}

	/**
	 * Return the option key used for bounce-sync state.
	 *
	 * @return string
	 */
	private function get_bounce_state_option_key() {
		return 'wp_bulk_mail_bounce_state';
	}

	/**
	 * Get normalized bounce-sync state.
	 *
	 * @return array
	 */
	private function get_bounce_state() {
		$state = get_option( $this->get_bounce_state_option_key(), array() );

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		$state = wp_parse_args(
			$state,
			array(
				'processed_keys'   => array(),
				'last_synced_at'   => '',
				'last_error'       => '',
				'last_scan_count'  => 0,
				'last_match_count' => 0,
			)
		);

		$state['processed_keys'] = array_values(
			array_filter(
				array_map( 'sanitize_text_field', (array) $state['processed_keys'] )
			)
		);

		return $state;
	}

	/**
	 * Persist the bounce-sync state.
	 *
	 * @param array $state State values.
	 * @return void
	 */
	private function update_bounce_state( $state ) {
		$current = $this->get_bounce_state();
		$next    = wp_parse_args( is_array( $state ) ? $state : array(), $current );

		$next['processed_keys'] = array_slice(
			array_values(
				array_unique(
					array_filter(
						array_map( 'sanitize_text_field', (array) $next['processed_keys'] )
					)
				)
			),
			-300
		);
		$next['last_synced_at']   = sanitize_text_field( (string) $next['last_synced_at'] );
		$next['last_error']       = sanitize_text_field( (string) $next['last_error'] );
		$next['last_scan_count']  = absint( $next['last_scan_count'] );
		$next['last_match_count'] = absint( $next['last_match_count'] );

		update_option( $this->get_bounce_state_option_key(), $next, false );
	}

	/**
	 * Get the settings fields used for later bounce tracking.
	 *
	 * @return array[]
	 */
	public function get_bounce_tracking_fields() {
		return array(
			array(
				'key'            => 'bounce_tracking_enabled',
				'type'           => 'checkbox',
				'label'          => __( 'Later Bounce Tracking', 'wp-bulk-mail' ),
				'checkbox_label' => __( 'Read bounce messages from IMAP and convert late delivery failures into queue failures', 'wp-bulk-mail' ),
			),
			array(
				'key'         => 'bounce_imap_host',
				'type'        => 'text',
				'label'       => __( 'IMAP Host', 'wp-bulk-mail' ),
				'class'       => 'regular-text',
				'placeholder' => 'imap.gmail.com',
				'description' => __( 'Example: imap.gmail.com for Gmail, outlook.office365.com for Microsoft 365, or your domain IMAP host.', 'wp-bulk-mail' ),
			),
			array(
				'key'   => 'bounce_imap_port',
				'type'  => 'number',
				'label' => __( 'IMAP Port', 'wp-bulk-mail' ),
				'class' => 'small-text',
				'min'   => 1,
				'max'   => 65535,
			),
			array(
				'key'     => 'bounce_imap_encryption',
				'type'    => 'select',
				'label'   => __( 'IMAP Encryption', 'wp-bulk-mail' ),
				'options' => array(
					'ssl'  => __( 'SSL', 'wp-bulk-mail' ),
					'tls'  => __( 'TLS', 'wp-bulk-mail' ),
					'none' => __( 'None', 'wp-bulk-mail' ),
				),
			),
			array(
				'key'         => 'bounce_imap_folder',
				'type'        => 'text',
				'label'       => __( 'Mailbox Folder', 'wp-bulk-mail' ),
				'class'       => 'regular-text',
				'placeholder' => 'INBOX',
				'description' => __( 'Use the folder where bounce messages arrive. For most setups this is INBOX.', 'wp-bulk-mail' ),
			),
			array(
				'key'         => 'bounce_imap_username',
				'type'        => 'text',
				'label'       => __( 'IMAP Username', 'wp-bulk-mail' ),
				'class'       => 'regular-text',
				'placeholder' => __( 'Leave blank to reuse SMTP username', 'wp-bulk-mail' ),
			),
			array(
				'key'         => 'bounce_imap_password',
				'type'        => 'password',
				'label'       => __( 'IMAP Password', 'wp-bulk-mail' ),
				'class'       => 'regular-text',
				'placeholder' => __( 'Leave blank to keep current password or reuse SMTP password', 'wp-bulk-mail' ),
			),
		);
	}

	/**
	 * Sanitize bounce tracking settings.
	 *
	 * @param array $input Submitted settings.
	 * @param array $settings Working settings.
	 * @param array $current Current saved settings.
	 * @return array
	 */
	public function sanitize_bounce_settings( $input, $settings, $current ) {
		$settings['bounce_tracking_enabled'] = ! empty( $input['bounce_tracking_enabled'] ) ? 1 : 0;

		if ( isset( $input['bounce_imap_host'] ) ) {
			$settings['bounce_imap_host'] = sanitize_text_field( wp_unslash( $input['bounce_imap_host'] ) );
		}

		if ( isset( $input['bounce_imap_port'] ) ) {
			$port = absint( $input['bounce_imap_port'] );

			if ( $port >= 1 && $port <= 65535 ) {
				$settings['bounce_imap_port'] = $port;
			}
		}

		if ( isset( $input['bounce_imap_encryption'] ) && in_array( $input['bounce_imap_encryption'], array( 'none', 'ssl', 'tls' ), true ) ) {
			$settings['bounce_imap_encryption'] = sanitize_key( $input['bounce_imap_encryption'] );
		}

		if ( isset( $input['bounce_imap_folder'] ) ) {
			$settings['bounce_imap_folder'] = sanitize_text_field( wp_unslash( $input['bounce_imap_folder'] ) );
		}

		if ( isset( $input['bounce_imap_username'] ) ) {
			$settings['bounce_imap_username'] = sanitize_text_field( wp_unslash( $input['bounce_imap_username'] ) );
		}

		if ( isset( $input['bounce_imap_password'] ) ) {
			$submitted_password              = preg_replace( '/\s+/', '', trim( (string) wp_unslash( $input['bounce_imap_password'] ) ) );
			$settings['bounce_imap_password'] = '' !== $submitted_password ? $submitted_password : $current['bounce_imap_password'];
		}

		if ( empty( $settings['bounce_imap_folder'] ) ) {
			$settings['bounce_imap_folder'] = 'INBOX';
		}

		if ( ! empty( $settings['bounce_tracking_enabled'] ) ) {
			if ( ! $this->is_imap_available() ) {
				add_settings_error(
					self::OPTION_KEY,
					'imap_extension_missing',
					__( 'Later bounce tracking needs mailbox sync support, but the required PHP networking functions are not available on this server.', 'wp-bulk-mail' ),
					'error'
				);
			}

			if ( empty( $settings['bounce_imap_host'] ) ) {
				add_settings_error(
					self::OPTION_KEY,
					'bounce_imap_host_required',
					__( 'Later bounce tracking is enabled, but the IMAP host is empty.', 'wp-bulk-mail' ),
					'warning'
				);
			}
		}

		return $settings;
	}

	/**
	 * Get the normalized mailbox configuration for bounce tracking.
	 *
	 * @return array
	 */
	private function get_bounce_mailbox_config() {
		$settings   = $this->get_settings();
		$username   = ! empty( $settings['bounce_imap_username'] ) ? $settings['bounce_imap_username'] : ( ! empty( $settings['smtp_username'] ) ? $settings['smtp_username'] : $settings['from_email'] );
		$password   = ! empty( $settings['bounce_imap_password'] ) ? $settings['bounce_imap_password'] : ( isset( $settings['smtp_password'] ) ? $settings['smtp_password'] : '' );
		$host       = ! empty( $settings['bounce_imap_host'] ) ? $settings['bounce_imap_host'] : '';
		$port       = ! empty( $settings['bounce_imap_port'] ) ? absint( $settings['bounce_imap_port'] ) : 993;
		$encryption = ! empty( $settings['bounce_imap_encryption'] ) ? $settings['bounce_imap_encryption'] : 'ssl';
		$folder     = ! empty( $settings['bounce_imap_folder'] ) ? $settings['bounce_imap_folder'] : 'INBOX';

		return array(
			'host'       => $host,
			'port'       => $port,
			'encryption' => in_array( $encryption, array( 'none', 'ssl', 'tls' ), true ) ? $encryption : 'ssl',
			'folder'     => $folder,
			'username'   => $username,
			'password'   => $password,
		);
	}

	/**
	 * Build the IMAP mailbox connection string.
	 *
	 * @param array $config Mailbox config.
	 * @return string
	 */
	private function build_bounce_imap_mailbox_string( $config ) {
		$flags = '/imap';

		if ( 'ssl' === $config['encryption'] ) {
			$flags .= '/ssl/validate-cert';
		} elseif ( 'tls' === $config['encryption'] ) {
			$flags .= '/tls/validate-cert';
		} else {
			$flags .= '/notls';
		}

		return sprintf(
			'{%1$s:%2$d%3$s}%4$s',
			$config['host'],
			(int) $config['port'],
			$flags,
			$config['folder']
		);
	}

	/**
	 * Open the configured IMAP mailbox with certificate validation enabled.
	 *
	 * @param array $config Mailbox config.
	 * @return resource|WP_Error
	 */
	private function open_bounce_imap_stream( $config ) {
		$mailbox = $this->build_bounce_imap_mailbox_string( $config );
		$stream  = @imap_open( $mailbox, $config['username'], $config['password'], OP_READONLY );

		if ( false === $stream ) {
			$error = function_exists( 'imap_last_error' ) ? imap_last_error() : '';

			if ( function_exists( 'imap_errors' ) ) {
				imap_errors();
			}

			return new WP_Error( 'bounce_imap_open_failed', ! empty( $error ) ? wp_strip_all_tags( (string) $error ) : __( 'Could not open the IMAP mailbox connection.', 'wp-bulk-mail' ) );
		}

		return $stream;
	}

	/**
	 * Fetch one IMAP message as parsed header/body data.
	 *
	 * @param resource $imap_stream Open IMAP stream.
	 * @param int      $message_number IMAP message number.
	 * @return array|WP_Error
	 */
	private function fetch_imap_message( $imap_stream, $message_number ) {
		$raw_headers = imap_fetchheader( $imap_stream, absint( $message_number ), FT_PREFETCHTEXT );
		$body        = imap_body( $imap_stream, absint( $message_number ), FT_PEEK );

		if ( false === $raw_headers || false === $body ) {
			return new WP_Error( 'bounce_imap_fetch_failed', __( 'Could not fetch one mailbox message during bounce sync.', 'wp-bulk-mail' ) );
		}

		return $this->raw_imap_parse_message( $raw_headers . "\r\n" . $body );
	}

	/**
	 * Get human-readable status for the bounce tracker.
	 *
	 * @return array
	 */
	public function get_bounce_tracker_status() {
		$settings = $this->get_settings();
		$config   = $this->get_bounce_mailbox_config();
		$state    = $this->get_bounce_state();

		return array(
			'enabled'          => ! empty( $settings['bounce_tracking_enabled'] ),
			'imap_available'   => $this->is_imap_available(),
			'host'             => $config['host'],
			'port'             => $config['port'],
			'folder'           => $config['folder'],
			'username'         => $config['username'],
			'last_synced_at'   => $state['last_synced_at'],
			'last_error'       => $state['last_error'],
			'last_scan_count'  => $state['last_scan_count'],
			'last_match_count' => $state['last_match_count'],
		);
	}

	/**
	 * Determine whether later bounce tracking is turned on.
	 *
	 * @return bool
	 */
	private function is_bounce_tracking_enabled() {
		$settings = $this->get_settings();

		return ! empty( $settings['bounce_tracking_enabled'] );
	}

	/**
	 * Schedule bounce processing when enabled.
	 *
	 * @return void
	 */
	public function maybe_schedule_bounce_processing() {
		if ( ! $this->is_bounce_tracking_enabled() || ! $this->is_imap_available() ) {
			return;
		}

		$this->ensure_background_action_schedule( self::BOUNCE_PROCESS_HOOK, self::BOUNCE_SYNC_INTERVAL );
	}

	/**
	 * Extract a short failure line from one bounce message.
	 *
	 * @param string $subject Message subject.
	 * @param string $body Message body.
	 * @return string
	 */
	private function extract_bounce_error_message( $subject, $body ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $body );

		foreach ( (array) $lines as $line ) {
			$line = trim( wp_strip_all_tags( (string) $line ) );

			if ( '' === $line ) {
				continue;
			}

			if ( preg_match( '/\b(550|551|552|553|554|5\.\d\.\d|address not found|mailbox unavailable|mailbox full|inbox full|quota exceeded|user unknown|unknown user|no such user|invalid address|delivery failed|undeliverable|blocked|spam|complaint)\b/i', $line ) ) {
				return $line;
			}
		}

		return '' !== trim( (string) $subject ) ? trim( (string) $subject ) : __( 'Bounce message detected from mailbox sync.', 'wp-bulk-mail' );
	}

	/**
	 * Decode one mailbox header value when possible.
	 *
	 * @param string $value Raw header value.
	 * @return string
	 */
	private function decode_bounce_header_value( $value ) {
		$value = (string) $value;

		if ( function_exists( 'iconv_mime_decode' ) ) {
			$decoded = @iconv_mime_decode( $value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, get_bloginfo( 'charset' ) );

			if ( false !== $decoded ) {
				return (string) $decoded;
			}
		}

		if ( function_exists( 'imap_utf8' ) ) {
			return (string) imap_utf8( $value );
		}

		return $value;
	}

	/**
	 * Parse one bounce message into queue matching data.
	 *
	 * @param object $header IMAP header info.
	 * @param string $body Message body.
	 * @return array
	 */
	private function parse_bounce_message( $header, $body ) {
		$subject          = isset( $header->subject ) ? $this->decode_bounce_header_value( (string) $header->subject ) : '';
		$from_address     = isset( $header->fromaddress ) ? strtolower( (string) $header->fromaddress ) : '';
		$body             = quoted_printable_decode( (string) $body );
		$normalized_body  = trim( wp_strip_all_tags( html_entity_decode( $body, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		$is_bounce        = false;
		$candidate_emails = array();
		$queue_id         = 0;
		$campaign_id      = 0;
		$recipient_id     = 0;

		if ( false !== strpos( $from_address, 'mailer-daemon' ) || false !== strpos( $from_address, 'postmaster' ) ) {
			$is_bounce = true;
		}

		if ( preg_match( '/delivery status notification|undeliver|address not found|mail delivery subsystem|delivery failure|returned mail|mail delivery failed/i', $subject . "\n" . $normalized_body ) ) {
			$is_bounce = true;
		}

		if ( preg_match( '/X-WBM-Queue-ID:\s*(\d+)/i', $body, $matches ) ) {
			$queue_id   = absint( $matches[1] );
			$is_bounce  = true;
		}

		if ( preg_match( '/X-WBM-Campaign-ID:\s*(\d+)/i', $body, $matches ) ) {
			$campaign_id = absint( $matches[1] );
		}

		if ( preg_match( '/X-WBM-Recipient-ID:\s*(\d+)/i', $body, $matches ) ) {
			$recipient_id = absint( $matches[1] );
		}

		if ( preg_match( '/X-WBM-Recipient-Email:\s*([^\s<>]+@[^\s<>]+)/i', $body, $matches ) ) {
			$candidate_emails[] = sanitize_email( $matches[1] );
		}

		$email_patterns = array(
			'/Final-Recipient:\s*rfc822;\s*<?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})>?/i',
			'/Original-Recipient:\s*rfc822;\s*<?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})>?/i',
			'/Your message wasn\'t delivered to\s*<?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})>?/i',
			'/delivery to the following recipient(?:s)? failed(?: permanently)?:\s*<?([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})>?/i',
		);

		foreach ( $email_patterns as $pattern ) {
			if ( preg_match_all( $pattern, $body, $matches ) ) {
				foreach ( (array) $matches[1] as $candidate_email ) {
					$candidate_emails[] = sanitize_email( $candidate_email );
				}
			}
		}

		$candidate_emails = array_values(
			array_unique(
				array_filter( $candidate_emails, 'is_email' )
			)
		);

		return array(
			'is_bounce'        => $is_bounce,
			'queue_id'         => $queue_id,
			'campaign_id'      => $campaign_id,
			'recipient_id'     => $recipient_id,
			'candidate_emails' => $candidate_emails,
			'error_message'    => $this->extract_bounce_error_message( $subject, $normalized_body ),
			'subject'          => $subject,
		);
	}

	/**
	 * Generate one stable processed-key value for an IMAP message.
	 *
	 * @param object $header IMAP header info.
	 * @param int    $message_number IMAP message number.
	 * @param string $body Message body.
	 * @return string
	 */
	private function get_bounce_processed_key( $header, $message_number, $body ) {
		if ( isset( $header->message_id ) && '' !== trim( (string) $header->message_id ) ) {
			return md5( strtolower( trim( (string) $header->message_id ) ) );
		}

		return md5( $message_number . '|' . ( isset( $header->date ) ? (string) $header->date : '' ) . '|' . substr( (string) $body, 0, 500 ) );
	}

	/**
	 * Escape one value for a raw IMAP command.
	 *
	 * @param string $value Command value.
	 * @return string
	 */
	private function raw_imap_escape( $value ) {
		return '"' . addcslashes( (string) $value, "\\\"" ) . '"';
	}

	/**
	 * Open a raw IMAP socket connection.
	 *
	 * @param array $config Mailbox config.
	 * @return array|WP_Error
	 */
	private function raw_imap_open_connection( $config ) {
		$transport = 'tcp://';

		if ( 'ssl' === $config['encryption'] ) {
			$transport = 'ssl://';
		} elseif ( 'tls' === $config['encryption'] ) {
			$transport = 'tls://';
		}

		$errno   = 0;
		$errstr  = '';
		$stream  = @stream_socket_client( $transport . $config['host'] . ':' . (int) $config['port'], $errno, $errstr, 20, STREAM_CLIENT_CONNECT );

		if ( ! is_resource( $stream ) ) {
			return new WP_Error( 'bounce_socket_connect_failed', trim( $errstr ) ?: __( 'Could not open the mailbox socket connection.', 'wp-bulk-mail' ) );
		}

		stream_set_timeout( $stream, 20 );
		$greeting = fgets( $stream );

		if ( false === $greeting || 0 !== strpos( (string) $greeting, '* OK' ) ) {
			fclose( $stream );

			return new WP_Error( 'bounce_socket_greeting_failed', __( 'Mailbox server did not return a valid IMAP greeting.', 'wp-bulk-mail' ) );
		}

		return array(
			'stream'      => $stream,
			'tag_counter' => 1,
			'config'      => $config,
		);
	}

	/**
	 * Read one raw IMAP response until the tagged completion line.
	 *
	 * @param resource $stream Open socket stream.
	 * @param string   $command IMAP command.
	 * @param int      $tag_counter Next tag counter.
	 * @return array|WP_Error
	 */
	private function raw_imap_run_command( $stream, $command, &$tag_counter ) {
		$tag     = 'A' . str_pad( (string) $tag_counter, 4, '0', STR_PAD_LEFT );
		$command = trim( (string) $command );
		++$tag_counter;

		if ( false === fwrite( $stream, $tag . ' ' . $command . "\r\n" ) ) {
			return new WP_Error( 'bounce_socket_write_failed', __( 'Could not write the IMAP command to the mailbox connection.', 'wp-bulk-mail' ) );
		}

		$lines = array();

		while ( ! feof( $stream ) ) {
			$line = fgets( $stream );

			if ( false === $line ) {
				break;
			}

			$lines[] = $line;

			if ( preg_match( '/\{(\d+)\}\r?\n$/', $line, $matches ) ) {
				$literal_bytes = (int) $matches[1];
				$literal       = '';

				while ( $literal_bytes > 0 && ! feof( $stream ) ) {
					$chunk = fread( $stream, $literal_bytes );

					if ( false === $chunk || '' === $chunk ) {
						break;
					}

					$literal      .= $chunk;
					$literal_bytes -= strlen( $chunk );
				}

				$lines[] = $literal;

				$closing_line = fgets( $stream );

				if ( false !== $closing_line ) {
					$lines[] = $closing_line;

					if ( 0 === strpos( $closing_line, $tag . ' ' ) ) {
						break;
					}
				}

				continue;
			}

			if ( 0 === strpos( $line, $tag . ' ' ) ) {
				break;
			}
		}

		$final_line = '';

		for ( $index = count( $lines ) - 1; $index >= 0; --$index ) {
			if ( is_string( $lines[ $index ] ) && 0 === strpos( $lines[ $index ], $tag . ' ' ) ) {
				$final_line = $lines[ $index ];
				break;
			}
		}

		if ( '' === $final_line ) {
			return new WP_Error( 'bounce_socket_response_incomplete', __( 'Mailbox server returned an incomplete IMAP response.', 'wp-bulk-mail' ) );
		}

		if ( ! preg_match( '/^' . preg_quote( $tag, '/' ) . '\s+OK\b/i', $final_line ) ) {
			return new WP_Error( 'bounce_socket_command_failed', trim( preg_replace( '/^' . preg_quote( $tag, '/' ) . '\s+(NO|BAD)\s*/i', '', $final_line ) ) );
		}

		return array(
			'tag'   => $tag,
			'lines' => $lines,
		);
	}

	/**
	 * Parse raw email headers from one IMAP message body.
	 *
	 * @param string $raw_message Full raw message.
	 * @return array
	 */
	private function raw_imap_parse_message( $raw_message ) {
		$parts       = preg_split( "/\r\n\r\n|\n\n|\r\r/", (string) $raw_message, 2 );
		$raw_headers = isset( $parts[0] ) ? (string) $parts[0] : '';
		$body        = isset( $parts[1] ) ? (string) $parts[1] : '';
		$headers     = array();
		$last_key    = '';

		foreach ( preg_split( '/\r\n|\r|\n/', $raw_headers ) as $line ) {
			if ( preg_match( '/^\s+/', $line ) && '' !== $last_key ) {
				$headers[ $last_key ] .= ' ' . trim( $line );
				continue;
			}

			if ( false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $name, $value ) = explode( ':', $line, 2 );
			$last_key             = strtolower( trim( $name ) );
			$headers[ $last_key ] = trim( $value );
		}

		return array(
			'header' => (object) array(
				'subject'     => isset( $headers['subject'] ) ? $this->decode_bounce_header_value( $headers['subject'] ) : '',
				'fromaddress' => isset( $headers['from'] ) ? $this->decode_bounce_header_value( $headers['from'] ) : '',
				'message_id'  => isset( $headers['message-id'] ) ? $headers['message-id'] : '',
				'date'        => isset( $headers['date'] ) ? $headers['date'] : '',
			),
			'body'   => $body,
		);
	}

	/**
	 * Search the mailbox using a raw IMAP socket.
	 *
	 * @param array  $connection Open connection data.
	 * @param string $last_synced_at Previous sync timestamp.
	 * @return int[]|WP_Error
	 */
	private function raw_imap_search_recent_messages( &$connection, $last_synced_at ) {
		$since_timestamp = ! empty( $last_synced_at ) ? strtotime( $last_synced_at . ' -1 day' ) : strtotime( '-7 days', current_time( 'timestamp' ) );
		$since_date      = wp_date( 'd-M-Y', $since_timestamp );
		$response        = $this->raw_imap_run_command( $connection['stream'], 'LOGIN ' . $this->raw_imap_escape( $connection['config']['username'] ) . ' ' . $this->raw_imap_escape( $connection['config']['password'] ), $connection['tag_counter'] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = $this->raw_imap_run_command( $connection['stream'], 'SELECT ' . $this->raw_imap_escape( $connection['config']['folder'] ), $connection['tag_counter'] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = $this->raw_imap_run_command( $connection['stream'], 'SEARCH SINCE ' . $this->raw_imap_escape( $since_date ), $connection['tag_counter'] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$message_numbers = array();

		foreach ( $response['lines'] as $line ) {
			if ( ! is_string( $line ) || 0 !== strpos( $line, '* SEARCH' ) ) {
				continue;
			}

			$line_numbers = preg_split( '/\s+/', trim( str_replace( '* SEARCH', '', $line ) ) );

			foreach ( (array) $line_numbers as $number ) {
				if ( '' !== $number && ctype_digit( $number ) ) {
					$message_numbers[] = (int) $number;
				}
			}
		}

		rsort( $message_numbers );

		return array_slice( array_values( array_unique( $message_numbers ) ), 0, 80 );
	}

	/**
	 * Fetch one raw IMAP message using a socket connection.
	 *
	 * @param array $connection Open connection data.
	 * @param int   $message_number IMAP message number.
	 * @return array|WP_Error
	 */
	private function raw_imap_fetch_message( &$connection, $message_number ) {
		$response = $this->raw_imap_run_command( $connection['stream'], 'FETCH ' . absint( $message_number ) . ' BODY.PEEK[]', $connection['tag_counter'] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$raw_message = '';

		foreach ( $response['lines'] as $line ) {
			if ( ! is_string( $line ) ) {
				continue;
			}

			if ( 0 === strpos( $line, '*' ) || preg_match( '/^A\d{4}\s+/i', $line ) || ')' === trim( $line ) ) {
				continue;
			}

			$raw_message .= $line;
		}

		if ( '' === $raw_message ) {
			return new WP_Error( 'bounce_message_empty', __( 'Mailbox message body was empty during bounce sync.', 'wp-bulk-mail' ) );
		}

		return $this->raw_imap_parse_message( $raw_message );
	}

	/**
	 * Find the queue row that best matches one parsed bounce message.
	 *
	 * @param array $parsed Parsed bounce data.
	 * @return array|null
	 */
	private function find_queue_item_for_bounce( $parsed ) {
		global $wpdb;

		$queue_table = self::get_queue_table_name();

		if ( ! empty( $parsed['queue_id'] ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, campaign_id, recipient_id, recipient_email, status
					FROM {$queue_table}
					WHERE id = %d AND status = %s
					LIMIT 1",
					(int) $parsed['queue_id'],
					'sent'
				),
				ARRAY_A
			);

			if (
				is_array( $row ) &&
				( empty( $parsed['campaign_id'] ) || (int) $parsed['campaign_id'] === (int) $row['campaign_id'] ) &&
				( empty( $parsed['recipient_id'] ) || (int) $parsed['recipient_id'] === (int) $row['recipient_id'] )
			) {
				return $row;
			}
		}

		$candidates  = array();
		$where_parts = array( 'status = %s', 'sent_at IS NOT NULL', 'sent_at >= %s' );
		$query_args  = array(
			'sent',
			wp_date( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * 30 ) ),
		);

		if ( ! empty( $parsed['campaign_id'] ) ) {
			$where_parts[] = 'campaign_id = %d';
			$query_args[]  = (int) $parsed['campaign_id'];
		}

		if ( ! empty( $parsed['recipient_id'] ) ) {
			$where_parts[] = 'recipient_id = %d';
			$query_args[]  = (int) $parsed['recipient_id'];
		}

		$candidate_emails = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_email', (array) $parsed['candidate_emails'] ),
					'is_email'
				)
			)
		);

		if ( ! empty( $candidate_emails ) ) {
			$email_placeholders = implode( ',', array_fill( 0, count( $candidate_emails ), '%s' ) );
			$where_parts[]      = "recipient_email IN ({$email_placeholders})";
			$query_args         = array_merge( $query_args, $candidate_emails );
		}

		if ( empty( $parsed['recipient_id'] ) && empty( $candidate_emails ) ) {
			return null;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, campaign_id, recipient_id, recipient_email, status
				FROM {$queue_table}
				WHERE " . implode( ' AND ', $where_parts ) . '
				ORDER BY sent_at DESC, id DESC
				LIMIT 5',
				$query_args
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$candidates[ (int) $row['id'] ] = $row;
		}

		return 1 === count( $candidates ) ? reset( $candidates ) : null;
	}

	/**
	 * Convert one queue row from sent to failed using bounce-mail data.
	 *
	 * @param array $queue_item Queue row.
	 * @param array $parsed Parsed bounce data.
	 * @return int|WP_Error
	 */
	private function apply_bounce_to_queue_item( $queue_item, $parsed ) {
		global $wpdb;

		$queue_id    = isset( $queue_item['id'] ) ? absint( $queue_item['id'] ) : 0;
		$campaign_id = isset( $queue_item['campaign_id'] ) ? absint( $queue_item['campaign_id'] ) : 0;

		if ( $queue_id < 1 || $campaign_id < 1 ) {
			return new WP_Error( 'missing_queue_item', __( 'Could not match the bounce message to a queued recipient.', 'wp-bulk-mail' ) );
		}

		$updated = $wpdb->update(
			self::get_queue_table_name(),
			array(
				'status'        => 'failed',
				'locked_at'     => null,
				'lock_token'    => '',
				'sent_at'       => null,
				'error_message' => sanitize_text_field( '[Bounce Sync] ' . $parsed['error_message'] ),
			),
			array(
				'id'     => $queue_id,
				'status' => 'sent',
			),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'bounce_update_failed', __( 'Bounce message was found, but the queue row could not be updated.', 'wp-bulk-mail' ) );
		}

		if ( 0 === (int) $updated ) {
			return new WP_Error( 'bounce_queue_item_no_longer_sent', __( 'Bounce was matched, but the queue row was no longer eligible for a sent-to-failed update.', 'wp-bulk-mail' ) );
		}

		$this->update_campaign_statuses( array( $campaign_id ) );

		return $campaign_id;
	}

	/**
	 * Search the mailbox for recent messages that might contain bounces.
	 *
	 * @param resource $imap_stream Open IMAP stream.
	 * @param string   $last_synced_at Previous sync timestamp.
	 * @return int[]
	 */
	private function search_recent_bounce_messages( $imap_stream, $last_synced_at ) {
		$since_timestamp = ! empty( $last_synced_at ) ? strtotime( $last_synced_at . ' -1 day' ) : strtotime( '-7 days', current_time( 'timestamp' ) );
		$criteria        = 'SINCE "' . wp_date( 'd-M-Y', $since_timestamp ) . '"';
		$message_numbers = imap_search( $imap_stream, $criteria );

		if ( false === $message_numbers ) {
			$message_numbers = imap_search( $imap_stream, 'ALL' );
		}

		if ( false === $message_numbers ) {
			return array();
		}

		rsort( $message_numbers );

		return array_slice( array_map( 'absint', $message_numbers ), 0, 80 );
	}

	/**
	 * Sync the configured bounce mailbox and convert late bounces into queue failures.
	 *
	 * @return array|WP_Error
	 */
	private function sync_bounce_mailbox() {
		if ( ! $this->is_bounce_tracking_enabled() ) {
			return new WP_Error( 'bounce_tracking_disabled', __( 'Later bounce tracking is not enabled yet.', 'wp-bulk-mail' ) );
		}

		if ( ! $this->is_imap_available() ) {
			return new WP_Error( 'imap_extension_missing', __( 'PHP IMAP support is not available on this server.', 'wp-bulk-mail' ) );
		}

		$config = $this->get_bounce_mailbox_config();

		if ( empty( $config['host'] ) || empty( $config['username'] ) || empty( $config['password'] ) ) {
			return new WP_Error( 'bounce_tracking_not_configured', __( 'Bounce mailbox settings are incomplete. Add IMAP host, username, and password first.', 'wp-bulk-mail' ) );
		}

		$imap_stream = $this->open_bounce_imap_stream( $config );

		if ( is_wp_error( $imap_stream ) ) {
			return $imap_stream;
		}

		$state             = $this->get_bounce_state();
		$processed_keys    = $state['processed_keys'];
		$message_numbers   = $this->search_recent_bounce_messages( $imap_stream, $state['last_synced_at'] );
		$scanned_count     = 0;
		$matched_count     = 0;
		$updated_campaigns = array();

		if ( is_wp_error( $message_numbers ) ) {
			imap_close( $imap_stream );

			return $message_numbers;
		}

		foreach ( $message_numbers as $message_number ) {
			$message_data = $this->fetch_imap_message( $imap_stream, $message_number );

			if ( is_wp_error( $message_data ) ) {
				continue;
			}

			$header = $message_data['header'];
			$body   = $message_data['body'];
			$key    = $this->get_bounce_processed_key( $header, $message_number, $body );

			if ( in_array( $key, $processed_keys, true ) ) {
				continue;
			}

			$processed_keys[] = $key;
			++$scanned_count;

			$parsed = $this->parse_bounce_message( $header, $body );

			if ( empty( $parsed['is_bounce'] ) ) {
				continue;
			}

			$queue_item = $this->find_queue_item_for_bounce( $parsed );

			if ( ! is_array( $queue_item ) ) {
				continue;
			}

			$result = $this->apply_bounce_to_queue_item( $queue_item, $parsed );

			if ( ! is_wp_error( $result ) && $result > 0 ) {
				++$matched_count;
				$updated_campaigns[] = (int) $result;
			}
		}

		imap_close( $imap_stream );

		if ( ! empty( $updated_campaigns ) ) {
			$this->update_campaign_statuses( $updated_campaigns );
		}

		$this->update_bounce_state(
			array(
				'processed_keys'   => $processed_keys,
				'last_synced_at'   => current_time( 'mysql' ),
				'last_error'       => '',
				'last_scan_count'  => $scanned_count,
				'last_match_count' => $matched_count,
			)
		);

		return array(
			'scanned_count' => $scanned_count,
			'matched_count' => $matched_count,
		);
	}

	/**
	 * Background action entry point for bounce processing.
	 *
	 * @return void
	 */
	public function process_bounce_mailbox() {
		$result = $this->sync_bounce_mailbox();

		if ( is_wp_error( $result ) ) {
			$this->update_bounce_state(
				array(
					'last_synced_at'   => current_time( 'mysql' ),
					'last_error'       => $result->get_error_message(),
					'last_scan_count'  => 0,
					'last_match_count' => 0,
				)
			);
		}

		$this->maybe_schedule_bounce_processing();
	}

	/**
	 * Handle manual bounce mailbox sync from the admin.
	 *
	 * @return void
	 */
	public function handle_sync_bounces_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		check_admin_referer( 'wp_bulk_mail_sync_bounces_now' );

		$result = $this->sync_bounce_mailbox();

		if ( is_wp_error( $result ) ) {
			$this->set_bounce_notice( 'error', $result->get_error_message() );
		} else {
			$this->set_bounce_notice(
				'success',
				sprintf(
					/* translators: 1: scanned message count, 2: matched bounce count */
					__( 'Bounce sync finished. Scanned %1$d mailbox message(s) and matched %2$d late bounce(s) back into the queue.', 'wp-bulk-mail' ),
					(int) $result['scanned_count'],
					(int) $result['matched_count']
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_MENU_SLUG ) );
		exit;
	}

	/**
	 * Set the current mail trace context for custom headers.
	 *
	 * @param array $queue_item Current queue item.
	 * @return void
	 */
	private function set_current_mail_trace_context( $queue_item ) {
		$this->current_mail_trace_context = array(
			'queue_id'       => isset( $queue_item['id'] ) ? absint( $queue_item['id'] ) : 0,
			'campaign_id'    => isset( $queue_item['campaign_id'] ) ? absint( $queue_item['campaign_id'] ) : 0,
			'recipient_id'   => isset( $queue_item['recipient_id'] ) ? absint( $queue_item['recipient_id'] ) : 0,
			'recipient_email'=> isset( $queue_item['recipient_email'] ) ? sanitize_email( $queue_item['recipient_email'] ) : '',
		);
	}

	/**
	 * Clear the current mail trace context.
	 *
	 * @return void
	 */
	private function clear_current_mail_trace_context() {
		$this->current_mail_trace_context = array();
	}

	/**
	 * Add trace headers to outbound mail for future bounce matching.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer instance.
	 * @return void
	 */
	private function apply_bounce_tracking_headers( $phpmailer ) {
		if ( empty( $this->current_mail_trace_context ) || ! is_object( $phpmailer ) ) {
			return;
		}

		if ( ! empty( $this->current_mail_trace_context['queue_id'] ) ) {
			$phpmailer->addCustomHeader( 'X-WBM-Queue-ID', (string) $this->current_mail_trace_context['queue_id'] );
		}

		if ( ! empty( $this->current_mail_trace_context['campaign_id'] ) ) {
			$phpmailer->addCustomHeader( 'X-WBM-Campaign-ID', (string) $this->current_mail_trace_context['campaign_id'] );
		}

		if ( ! empty( $this->current_mail_trace_context['recipient_id'] ) ) {
			$phpmailer->addCustomHeader( 'X-WBM-Recipient-ID', (string) $this->current_mail_trace_context['recipient_id'] );
		}

		if ( ! empty( $this->current_mail_trace_context['recipient_email'] ) ) {
			$phpmailer->addCustomHeader( 'X-WBM-Recipient-Email', (string) $this->current_mail_trace_context['recipient_email'] );
		}
	}
}
