<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Recipients_Trait {

	/**
	 * Store a recipients page notice for the current user.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_recipients_notice( $type, $message ) {
		set_transient(
			'wp_bulk_mail_recipients_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Get and clear the recipients page notice for the current user.
	 *
	 * @return array|null
	 */
	public function get_recipients_notice() {
		$key    = 'wp_bulk_mail_recipients_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( false === $notice ) {
			return null;
		}

		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Return the admin URL for the recipients page.
	 *
	 * @param array $args Optional query args.
	 * @return string
	 */
	public function get_recipients_page_url( $args = array() ) {
		$url = admin_url( 'admin.php?page=' . self::RECIPIENTS_MENU_SLUG );

		if ( empty( $args ) ) {
			return $url;
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * Get all stored recipients.
	 *
	 * @return array[]
	 */
	public function get_all_recipients() {
		global $wpdb;

		$table_name = self::get_recipients_table_name();
		$sql        = "SELECT id, name, email, status, created_at, updated_at
			FROM {$table_name}
			WHERE status = %s
			ORDER BY email ASC";

		return $wpdb->get_results( $wpdb->prepare( $sql, 'active' ), ARRAY_A );
	}

	/**
	 * Get one stored recipient by ID.
	 *
	 * @param int $recipient_id Recipient ID.
	 * @return array|null
	 */
	public function get_recipient_by_id( $recipient_id ) {
		global $wpdb;

		$recipient_id = absint( $recipient_id );

		if ( $recipient_id < 1 ) {
			return null;
		}

		$table_name = self::get_recipients_table_name();
		$sql        = "SELECT id, name, email, status, created_at, updated_at
			FROM {$table_name}
			WHERE status = %s AND id = %d
			LIMIT 1";
		$row        = $wpdb->get_row( $wpdb->prepare( $sql, 'active', $recipient_id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get paginated recipients for the admin list with optional search.
	 *
	 * @param string $search_term Search query.
	 * @param int    $page Current page number.
	 * @param int    $per_page Items per page.
	 * @return array
	 */
	public function get_recipients_page_data( $search_term = '', $page = 1, $per_page = self::RECIPIENTS_PER_PAGE ) {
		global $wpdb;

		$table_name  = self::get_recipients_table_name();
		$search_term = sanitize_text_field( (string) $search_term );
		$page        = max( 1, absint( $page ) );
		$per_page    = max( 1, absint( $per_page ) );
		$where_sql   = 'WHERE status = %s';
		$query_args  = array( 'active' );

		if ( '' !== $search_term ) {
			$like         = '%' . $wpdb->esc_like( $search_term ) . '%';
			$where_sql   .= ' AND (name LIKE %s OR email LIKE %s)';
			$query_args[] = $like;
			$query_args[] = $like;
		}

		$count_sql   = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
		$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $query_args ) );
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
		$page        = min( $page, $total_pages );
		$offset      = ( $page - 1 ) * $per_page;
		$list_sql    = "SELECT id, name, email, status, created_at, updated_at
			FROM {$table_name}
			{$where_sql}
			ORDER BY created_at DESC, id DESC
			LIMIT %d OFFSET %d";
		$list_args   = array_merge( $query_args, array( $per_page, $offset ) );
		$items       = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ), ARRAY_A );

		return array(
			'items'        => is_array( $items ) ? $items : array(),
			'search_term'  => $search_term,
			'current_page' => $page,
			'per_page'     => $per_page,
			'total_items'  => $total_items,
			'total_pages'  => $total_pages,
		);
	}

	/**
	 * Get stored recipients by ID list while preserving order.
	 *
	 * @param int[] $recipient_ids Recipient IDs.
	 * @return array[]
	 */
	public function get_recipients_by_ids( $recipient_ids ) {
		global $wpdb;

		$recipient_ids = array_values(
			array_filter(
				array_map( 'absint', (array) $recipient_ids )
			)
		);

		if ( empty( $recipient_ids ) ) {
			return array();
		}

		$table_name   = self::get_recipients_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $recipient_ids ), '%d' ) );
		$sql          = "SELECT id, name, email, status, created_at, updated_at
			FROM {$table_name}
			WHERE status = 'active' AND id IN ({$placeholders})";
		$query        = $wpdb->prepare( $sql, $recipient_ids );
		$rows         = $wpdb->get_results( $query, ARRAY_A );
		$indexed      = array();

		foreach ( $rows as $row ) {
			$indexed[ (int) $row['id'] ] = $row;
		}

		$ordered = array();

		foreach ( $recipient_ids as $recipient_id ) {
			if ( isset( $indexed[ $recipient_id ] ) ) {
				$ordered[] = $indexed[ $recipient_id ];
			}
		}

		return $ordered;
	}

	/**
	 * Insert one recipient row if the email is new.
	 *
	 * @param string $email Recipient email.
	 * @param string $name Recipient name.
	 * @return true|WP_Error
	 */
	private function insert_recipient( $email, $name = '' ) {
		global $wpdb;

		$email = sanitize_email( $email );
		$name  = sanitize_text_field( $name );

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'wp-bulk-mail' ) );
		}

		$table_name = self::get_recipients_table_name();
		$exists     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE email = %s LIMIT 1",
				$email
			)
		);

		if ( $exists ) {
			return new WP_Error( 'duplicate_email', __( 'This email address is already stored.', 'wp-bulk-mail' ) );
		}

		$result = $wpdb->insert(
			$table_name,
			array(
				'name'   => $name,
				'email'  => $email,
				'status' => 'active',
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'insert_failed', __( 'Could not save the recipient right now.', 'wp-bulk-mail' ) );
		}

		return true;
	}

	/**
	 * Update one stored recipient row.
	 *
	 * @param int    $recipient_id Recipient ID.
	 * @param string $email Recipient email.
	 * @param string $name Recipient name.
	 * @return true|WP_Error
	 */
	private function update_recipient( $recipient_id, $email, $name = '' ) {
		global $wpdb;

		$recipient_id = absint( $recipient_id );
		$email        = sanitize_email( $email );
		$name         = sanitize_text_field( $name );

		if ( $recipient_id < 1 ) {
			return new WP_Error( 'invalid_recipient', __( 'Recipient was not found.', 'wp-bulk-mail' ) );
		}

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'wp-bulk-mail' ) );
		}

		$table_name = self::get_recipients_table_name();
		$current    = $this->get_recipient_by_id( $recipient_id );

		if ( ! $current ) {
			return new WP_Error( 'missing_recipient', __( 'Recipient was not found.', 'wp-bulk-mail' ) );
		}

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE email = %s AND id != %d LIMIT 1",
				$email,
				$recipient_id
			)
		);

		if ( $exists ) {
			return new WP_Error( 'duplicate_email', __( 'This email address is already stored.', 'wp-bulk-mail' ) );
		}

		$result = $wpdb->update(
			$table_name,
			array(
				'name'  => $name,
				'email' => $email,
			),
			array( 'id' => $recipient_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Could not update the recipient right now.', 'wp-bulk-mail' ) );
		}

		return true;
	}

	/**
	 * Render the recipients management page.
	 *
	 * @return void
	 */
	public function render_recipients_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin               = $this;
		$recipients_notice    = $this->get_recipients_notice();
		$import_jobs_overview = $this->get_import_jobs_overview();
		$search_term          = isset( $_GET['recipient_search'] ) ? sanitize_text_field( wp_unslash( $_GET['recipient_search'] ) ) : '';
		$current_page         = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$edit_recipient_id    = isset( $_GET['edit_recipient'] ) ? absint( $_GET['edit_recipient'] ) : 0;
		$recipients_page      = $this->get_recipients_page_data( $search_term, $current_page );
		$stored_recipients    = $recipients_page['items'];
		$editing_recipient    = $edit_recipient_id > 0 ? $this->get_recipient_by_id( $edit_recipient_id ) : null;
		$is_edit_mode         = is_array( $editing_recipient );
		$recipient_form_data  = array(
			'id'    => $is_edit_mode ? (int) $editing_recipient['id'] : 0,
			'name'  => $is_edit_mode ? $editing_recipient['name'] : '',
			'email' => $is_edit_mode ? $editing_recipient['email'] : '',
		);

		require WP_BULK_MAIL_PATH . 'views/recipients-page.php';
	}

	/**
	 * Handle adding stored recipients from the admin page.
	 *
	 * @return void
	 */
	public function handle_add_recipient() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		check_admin_referer( 'wp_bulk_mail_add_recipient' );

		$name            = isset( $_POST['wp_bulk_mail_recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_bulk_mail_recipient_name'] ) ) : '';
		$email           = isset( $_POST['wp_bulk_mail_recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['wp_bulk_mail_recipient_email'] ) ) : '';
		$recipient_id    = isset( $_POST['wp_bulk_mail_recipient_id'] ) ? absint( $_POST['wp_bulk_mail_recipient_id'] ) : 0;
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

		if ( '' === $email ) {
			$this->set_recipients_notice( 'error', __( 'Email Address is required to save a recipient.', 'wp-bulk-mail' ) );
			if ( $recipient_id > 0 ) {
				$redirect_args['edit_recipient'] = $recipient_id;
			}
			wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
			exit;
		}

		$result = $recipient_id > 0 ? $this->update_recipient( $recipient_id, $email, $name ) : $this->insert_recipient( $email, $name );

		if ( true === $result ) {
			$this->set_recipients_notice( 'success', $recipient_id > 0 ? __( 'Recipient updated.', 'wp-bulk-mail' ) : __( 'Recipient saved.', 'wp-bulk-mail' ) );
		} elseif ( is_wp_error( $result ) && 'duplicate_email' === $result->get_error_code() ) {
			$this->set_recipients_notice( 'error', __( 'This email address is already in the list.', 'wp-bulk-mail' ) );
		} else {
			$this->set_recipients_notice( 'error', __( 'Please enter a valid email address.', 'wp-bulk-mail' ) );
		}

		if ( is_wp_error( $result ) && $recipient_id > 0 ) {
			$redirect_args['edit_recipient'] = $recipient_id;
		}

		wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
		exit;
	}

	/**
	 * Handle deleting a stored recipient.
	 *
	 * @return void
	 */
	public function handle_delete_recipient() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		$recipient_id    = isset( $_POST['recipient_id'] ) ? absint( $_POST['recipient_id'] ) : 0;
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

		if ( $recipient_id < 1 ) {
			$this->set_recipients_notice( 'error', __( 'Recipient ID was missing.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
			exit;
		}

		check_admin_referer( 'wp_bulk_mail_delete_recipient_' . $recipient_id );

		$deleted = $wpdb->delete(
			self::get_recipients_table_name(),
			array( 'id' => $recipient_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			$this->set_recipients_notice( 'error', __( 'Could not delete the recipient right now.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
			exit;
		}

		$draft                  = $this->get_compose_draft();
		$draft['recipient_ids'] = array_values(
			array_filter(
				$draft['recipient_ids'],
				static function ( $draft_recipient_id ) use ( $recipient_id ) {
					return (int) $draft_recipient_id !== $recipient_id;
				}
			)
		);
		update_option( self::COMPOSE_OPTION_KEY, $draft, false );

		$this->set_recipients_notice( 'success', __( 'Recipient deleted.', 'wp-bulk-mail' ) );
		wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
		exit;
	}
}
