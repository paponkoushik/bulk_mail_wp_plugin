<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Import_Trait {

	/**
	 * Check whether there are any unfinished import jobs.
	 *
	 * @return bool
	 */
	private function has_open_import_jobs() {
		global $wpdb;

		$table_name = self::get_import_jobs_table_name();

		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status IN (%s, %s)",
				'pending',
				'processing'
			)
		);

		return $count > 0;
	}

	/**
	 * Ensure the import runner is scheduled when unfinished jobs exist.
	 *
	 * @return void
	 */
	public function maybe_schedule_import_processing() {
		if ( ! $this->has_open_import_jobs() || $this->is_background_action_scheduled( self::IMPORT_PROCESS_HOOK ) ) {
			return;
		}

		$this->schedule_background_action( self::IMPORT_PROCESS_HOOK );
	}

	/**
	 * Get recent import jobs for the recipients screen.
	 *
	 * @return array
	 */
	public function get_import_jobs_overview() {
		global $wpdb;

		$jobs       = array();
		$table_name = self::get_import_jobs_table_name();

		if ( $this->table_exists( $table_name ) ) {
			$jobs = $wpdb->get_results(
				"SELECT id, file_name, file_type, status, processed_rows, imported_count, duplicate_count, invalid_count, error_message, created_at, updated_at, finished_at
				FROM {$table_name}
				ORDER BY id DESC
				LIMIT 8",
				ARRAY_A
			);
		}

		return array(
			'runner_label'          => $this->get_queue_runner_label(),
			'uses_action_scheduler' => $this->is_action_scheduler_available(),
			'jobs'                  => is_array( $jobs ) ? $jobs : array(),
		);
	}

	/**
	 * Inspect an uploaded import file and prepare metadata for background processing.
	 *
	 * @param string $file_path Uploaded file path.
	 * @return array|WP_Error
	 */
	private function prepare_import_job_metadata( $file_path ) {
		$handle = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			return new WP_Error( 'import_open_failed', __( 'Could not open the uploaded file.', 'wp-bulk-mail' ) );
		}

		$first_line = fgets( $handle );

		if ( false === $first_line ) {
			fclose( $handle );

			return new WP_Error( 'import_empty_file', __( 'The selected file was empty.', 'wp-bulk-mail' ) );
		}

		$delimiter = $this->detect_import_delimiter( $first_line );

		rewind( $handle );

		$first_row = fgetcsv( $handle, 0, $delimiter );

		if ( false === $first_row ) {
			fclose( $handle );

			return new WP_Error( 'import_empty_file', __( 'The selected file was empty.', 'wp-bulk-mail' ) );
		}

		$header_map  = array();
		$next_offset = 0;

		if ( $this->import_row_looks_like_header( $first_row ) ) {
			$header_map  = $this->build_import_header_map( $first_row );
			$next_offset = ftell( $handle );
		}

		fclose( $handle );

		return array(
			'delimiter'   => $delimiter,
			'header_map'  => wp_json_encode( $header_map ),
			'next_offset' => max( 0, (int) $next_offset ),
		);
	}

	/**
	 * Create one import job from an uploaded file.
	 *
	 * @param string $file_name Uploaded file name.
	 * @param string $file_path Stored file path.
	 * @param string $file_type File extension.
	 * @return array|WP_Error
	 */
	private function create_import_job( $file_name, $file_path, $file_type ) {
		global $wpdb;

		self::create_import_jobs_table();

		$metadata = $this->prepare_import_job_metadata( $file_path );

		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}

		$table_name = self::get_import_jobs_table_name();
		$result     = $wpdb->insert(
			$table_name,
			array(
				'file_name'   => $file_name,
				'file_path'   => $file_path,
				'file_type'   => $file_type,
				'delimiter'   => $metadata['delimiter'],
				'header_map'  => $metadata['header_map'],
				'next_offset' => $metadata['next_offset'],
				'status'      => 'pending',
				'created_by'  => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'import_job_failed', __( 'Could not queue the import job right now.', 'wp-bulk-mail' ) );
		}

		$this->schedule_background_action( self::IMPORT_PROCESS_HOOK );

		return array(
			'job_id' => (int) $wpdb->insert_id,
		);
	}

	/**
	 * Release stale import job locks so jobs can retry.
	 *
	 * @return void
	 */
	private function release_stale_import_jobs() {
		global $wpdb;

		$table_name = self::get_import_jobs_table_name();

		if ( ! $this->table_exists( $table_name ) ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::IMPORT_STALE_LOCK_AGE );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET status = %s, locked_at = NULL
				WHERE status = %s AND locked_at IS NOT NULL AND locked_at < %s",
				'pending',
				'processing',
				$cutoff
			)
		);
	}

	/**
	 * Claim the next pending import job for background processing.
	 *
	 * @return array|null
	 */
	private function claim_import_job() {
		global $wpdb;

		$table_name = self::get_import_jobs_table_name();

		if ( ! $this->table_exists( $table_name ) ) {
			return null;
		}

		$job_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE status = %s ORDER BY id ASC LIMIT 1",
				'pending'
			)
		);

		if ( $job_id < 1 ) {
			return null;
		}

		$locked_at = current_time( 'mysql' );
		$updated   = $wpdb->update(
			$table_name,
			array(
				'status'    => 'processing',
				'locked_at' => $locked_at,
			),
			array(
				'id'     => $job_id,
				'status' => 'pending',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( false === $updated || 0 === $updated ) {
			return null;
		}

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
				$job_id
			),
			ARRAY_A
		);

		return is_array( $job ) ? $job : null;
	}

	/**
	 * Mark one import job as failed.
	 *
	 * @param int    $job_id Import job ID.
	 * @param string $message Failure message.
	 * @return void
	 */
	private function mark_import_job_failed( $job_id, $message ) {
		global $wpdb;

		$wpdb->update(
			self::get_import_jobs_table_name(),
			array(
				'status'        => 'failed',
				'locked_at'     => null,
				'error_message' => $message,
				'finished_at'   => current_time( 'mysql' ),
			),
			array( 'id' => absint( $job_id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Process the next batch for one import job.
	 *
	 * @return void
	 */
	public function process_import_jobs() {
		global $wpdb;

		$this->release_stale_import_jobs();

		$job = $this->claim_import_job();

		if ( ! is_array( $job ) ) {
			return;
		}

		$file_path = isset( $job['file_path'] ) ? (string) $job['file_path'] : '';

		if ( '' === $file_path || ! file_exists( $file_path ) ) {
			$this->mark_import_job_failed( (int) $job['id'], __( 'The uploaded import file could not be found anymore.', 'wp-bulk-mail' ) );

			if ( $this->has_open_import_jobs() && ! $this->is_background_action_scheduled( self::IMPORT_PROCESS_HOOK ) ) {
				$this->schedule_background_action( self::IMPORT_PROCESS_HOOK );
			}

			return;
		}

		$handle = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			$this->mark_import_job_failed( (int) $job['id'], __( 'Could not open the stored import file.', 'wp-bulk-mail' ) );

			if ( $this->has_open_import_jobs() && ! $this->is_background_action_scheduled( self::IMPORT_PROCESS_HOOK ) ) {
				$this->schedule_background_action( self::IMPORT_PROCESS_HOOK );
			}

			return;
		}

		$offset = isset( $job['next_offset'] ) ? max( 0, (int) $job['next_offset'] ) : 0;

		if ( $offset > 0 ) {
			fseek( $handle, $offset );
		}

		$delimiter       = isset( $job['delimiter'] ) && '' !== $job['delimiter'] ? $job['delimiter'] : ',';
		$header_map      = json_decode( isset( $job['header_map'] ) ? $job['header_map'] : '', true );
		$header_map      = is_array( $header_map ) ? $header_map : array();
		$processed_batch = 0;
		$imported_batch  = 0;
		$duplicate_batch = 0;
		$invalid_batch   = 0;
		$completed       = false;

		while ( $processed_batch < self::IMPORT_BATCH_SIZE ) {
			$row = fgetcsv( $handle, 0, $delimiter );

			if ( false === $row ) {
				$completed = true;
				break;
			}

			$recipient = $this->normalize_import_recipient_row( $row, $header_map );

			if ( null === $recipient ) {
				continue;
			}

			++$processed_batch;

			$result = $this->insert_recipient( $recipient['email'], $recipient['name'] );

			if ( true === $result ) {
				++$imported_batch;
				continue;
			}

			if ( is_wp_error( $result ) && 'duplicate_email' === $result->get_error_code() ) {
				++$duplicate_batch;
				continue;
			}

			++$invalid_batch;
		}

		$next_offset = ftell( $handle );
		fclose( $handle );

		$update_data = array(
			'next_offset'     => max( 0, (int) $next_offset ),
			'processed_rows'  => (int) $job['processed_rows'] + $processed_batch,
			'imported_count'  => (int) $job['imported_count'] + $imported_batch,
			'duplicate_count' => (int) $job['duplicate_count'] + $duplicate_batch,
			'invalid_count'   => (int) $job['invalid_count'] + $invalid_batch,
			'locked_at'       => null,
			'error_message'   => '',
			'status'          => $completed ? 'completed' : 'pending',
		);
		$formats     = array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' );

		if ( $completed ) {
			$update_data['finished_at'] = current_time( 'mysql' );
			$formats[]                  = '%s';
		}

		$wpdb->update(
			self::get_import_jobs_table_name(),
			$update_data,
			array( 'id' => (int) $job['id'] ),
			$formats,
			array( '%d' )
		);

		if ( $this->has_open_import_jobs() && ! $this->is_background_action_scheduled( self::IMPORT_PROCESS_HOOK ) ) {
			$this->schedule_background_action( self::IMPORT_PROCESS_HOOK );
		}
	}

	/**
	 * Parse bulk email input into valid and invalid address lists.
	 *
	 * @param string $raw_emails Raw textarea input.
	 * @return array
	 */
	public function parse_email_addresses( $raw_emails ) {
		$segments   = preg_split( '/[\r\n,;]+/', (string) $raw_emails );
		$valid      = array();
		$invalid    = array();
		$seen_valid = array();

		foreach ( $segments as $segment ) {
			$email = trim( $segment );

			if ( '' === $email ) {
				continue;
			}

			$normalized = sanitize_email( $email );

			if ( ! $normalized || ! is_email( $normalized ) ) {
				$invalid[] = $email;
				continue;
			}

			$dedupe_key = strtolower( $normalized );

			if ( isset( $seen_valid[ $dedupe_key ] ) ) {
				continue;
			}

			$seen_valid[ $dedupe_key ] = true;
			$valid[]                   = $normalized;
		}

		return array(
			'valid'   => $valid,
			'invalid' => $invalid,
		);
	}

	/**
	 * Detect the delimiter used in an imported recipient file.
	 *
	 * @param string $line First line from the import file.
	 * @return string
	 */
	private function detect_import_delimiter( $line ) {
		$candidates = array( ',', ';', "\t" );
		$best_match = ',';
		$best_count = -1;

		foreach ( $candidates as $candidate ) {
			$count = substr_count( (string) $line, $candidate );

			if ( $count > $best_count ) {
				$best_count = $count;
				$best_match = $candidate;
			}
		}

		return $best_match;
	}

	/**
	 * Normalize an import header label into a known field key.
	 *
	 * @param string $label Header label.
	 * @return string
	 */
	private function normalize_import_header_label( $label ) {
		$normalized = ltrim( (string) $label, "\xEF\xBB\xBF" );
		$normalized = strtolower( trim( $normalized ) );
		$normalized = str_replace( array( '-', '_' ), ' ', $normalized );
		$normalized = preg_replace( '/\s+/', ' ', $normalized );

		if ( in_array( $normalized, array( 'email', 'e mail', 'email address', 'mail', 'recipient email' ), true ) ) {
			return 'email';
		}

		if ( in_array( $normalized, array( 'name', 'full name', 'recipient name' ), true ) ) {
			return 'name';
		}

		return '';
	}

	/**
	 * Check whether the first row looks like a header row.
	 *
	 * @param array $row Imported row.
	 * @return bool
	 */
	private function import_row_looks_like_header( $row ) {
		foreach ( (array) $row as $column ) {
			if ( '' !== $this->normalize_import_header_label( $column ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a header map for CSV import.
	 *
	 * @param array $row Header row values.
	 * @return array
	 */
	private function build_import_header_map( $row ) {
		$header_map = array();

		foreach ( (array) $row as $index => $column ) {
			$field_key = $this->normalize_import_header_label( $column );

			if ( '' !== $field_key ) {
				$header_map[ (int) $index ] = $field_key;
			}
		}

		return $header_map;
	}

	/**
	 * Normalize one imported row into a name/email pair.
	 *
	 * @param array $row Imported row values.
	 * @param array $header_map Optional header map.
	 * @return array|null
	 */
	private function normalize_import_recipient_row( $row, $header_map = array() ) {
		$row = array_map(
			static function ( $value ) {
				return trim( ltrim( (string) $value, "\xEF\xBB\xBF" ) );
			},
			(array) $row
		);

		$non_empty_values = array_values(
			array_filter(
				$row,
				static function ( $value ) {
					return '' !== $value;
				}
			)
		);

		if ( empty( $non_empty_values ) ) {
			return null;
		}

		$name  = '';
		$email = '';

		if ( ! empty( $header_map ) ) {
			foreach ( $header_map as $index => $field_key ) {
				if ( ! isset( $row[ $index ] ) ) {
					continue;
				}

				if ( 'name' === $field_key ) {
					$name = $row[ $index ];
				} elseif ( 'email' === $field_key ) {
					$email = $row[ $index ];
				}
			}
		} elseif ( 1 === count( $non_empty_values ) ) {
			$email = $non_empty_values[0];
		} else {
			$first_value  = $non_empty_values[0];
			$second_value = $non_empty_values[1];

			if ( is_email( sanitize_email( $first_value ) ) && ! is_email( sanitize_email( $second_value ) ) ) {
				$email = $first_value;
				$name  = $second_value;
			} else {
				$name  = $first_value;
				$email = $second_value;
			}
		}

		if ( '' === $email && isset( $non_empty_values[0] ) ) {
			$email = $non_empty_values[0];
		}

		return array(
			'name'  => $name,
			'email' => $email,
		);
	}

	/**
	 * Import recipients from one uploaded CSV or text file.
	 *
	 * @param string $file_path Uploaded file path.
	 * @return array|WP_Error
	 */
	private function import_recipients_from_file( $file_path ) {
		$handle = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			return new WP_Error( 'import_open_failed', __( 'Could not open the uploaded file.', 'wp-bulk-mail' ) );
		}

		$first_line = fgets( $handle );

		if ( false === $first_line ) {
			fclose( $handle );

			return new WP_Error( 'import_empty_file', __( 'The selected file was empty.', 'wp-bulk-mail' ) );
		}

		$delimiter = $this->detect_import_delimiter( $first_line );
		rewind( $handle );

		$header_map = array();
		$first_row  = true;
		$stats      = array(
			'imported'   => 0,
			'duplicates' => 0,
			'invalid'    => 0,
		);

		while ( false !== ( $row = fgetcsv( $handle, 0, $delimiter ) ) ) {
			if ( $first_row && $this->import_row_looks_like_header( $row ) ) {
				$header_map = $this->build_import_header_map( $row );
				$first_row  = false;
				continue;
			}

			$first_row = false;

			$recipient = $this->normalize_import_recipient_row( $row, $header_map );

			if ( null === $recipient ) {
				continue;
			}

			$result = $this->insert_recipient( $recipient['email'], $recipient['name'] );

			if ( true === $result ) {
				++$stats['imported'];
				continue;
			}

			if ( is_wp_error( $result ) && 'duplicate_email' === $result->get_error_code() ) {
				++$stats['duplicates'];
				continue;
			}

			++$stats['invalid'];
		}

		fclose( $handle );

		return $stats;
	}

	/**
	 * Handle importing recipients from a CSV or text file.
	 *
	 * @return void
	 */
	public function handle_import_recipients() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		check_admin_referer( 'wp_bulk_mail_import_recipients' );

		$redirect_search = isset( $_POST['redirect_search'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_search'] ) ) : '';
		$redirect_paged  = isset( $_POST['redirect_paged'] ) ? max( 1, absint( $_POST['redirect_paged'] ) ) : 1;
		$redirect_args   = array_filter(
			array(
				'recipient_search' => $redirect_search,
				'paged'            => $redirect_paged > 1 ? $redirect_paged : null,
			),
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);

		if ( empty( $_FILES['wp_bulk_mail_recipient_import_file'] ) || ! is_array( $_FILES['wp_bulk_mail_recipient_import_file'] ) ) {
			$this->set_recipients_notice( 'error', __( 'Choose a CSV or TXT file before importing.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
			exit;
		}

		$uploaded_file = $_FILES['wp_bulk_mail_recipient_import_file'];
		$file_name     = isset( $uploaded_file['name'] ) ? sanitize_file_name( wp_unslash( $uploaded_file['name'] ) ) : '';
		$file_error    = isset( $uploaded_file['error'] ) ? (int) $uploaded_file['error'] : UPLOAD_ERR_NO_FILE;
		$file_ext      = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		if ( UPLOAD_ERR_OK !== $file_error ) {
			$this->set_recipients_notice( 'error', __( 'The import file could not be uploaded. Please try again.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
			exit;
		}

		if ( ! in_array( $file_ext, array( 'csv', 'txt' ), true ) ) {
			$this->set_recipients_notice( 'error', __( 'Only CSV or TXT recipient files are supported right now.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
			exit;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$uploaded = wp_handle_upload(
			$uploaded_file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'csv' => 'text/csv',
					'txt' => 'text/plain',
				),
			)
		);

		if ( ! is_array( $uploaded ) || ! empty( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
			$this->set_recipients_notice( 'error', __( 'The import file could not be stored for background processing.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
			exit;
		}

		$result = $this->create_import_job( $file_name, $uploaded['file'], $file_ext );

		if ( is_wp_error( $result ) ) {
			$this->set_recipients_notice( 'error', $result->get_error_message() );
			wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
			exit;
		}

		$this->set_recipients_notice(
			'success',
			sprintf(
				/* translators: 1: import job id, 2: runner label */
				__( 'Import job #%1$d queued. Background processing started with %2$s.', 'wp-bulk-mail' ),
				(int) $result['job_id'],
				$this->get_queue_runner_label()
			)
		);

		wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
		exit;
	}
}
