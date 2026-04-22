<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Recipients_Trait {

	/**
	 * Normalize one recipient row.
	 *
	 * @param array $row Recipient row.
	 * @return array
	 */
	private function normalize_recipient_row( $row ) {
		$row         = is_array( $row ) ? $row : array();
		$row['tags'] = isset( $row['tags'] ) ? $this->sanitize_recipient_tags( $row['tags'] ) : array();

		return $row;
	}

	/**
	 * Normalize recipient tags into a clean array.
	 *
	 * @param string|array $raw_tags Raw tags.
	 * @return string[]
	 */
	public function sanitize_recipient_tags( $raw_tags ) {
		if ( is_array( $raw_tags ) ) {
			$segments = $raw_tags;
		} else {
			$segments = preg_split( '/[\r\n,;]+/', (string) $raw_tags );
		}

		$tags = array();

		foreach ( (array) $segments as $segment ) {
			$tag = sanitize_text_field( trim( (string) $segment ) );

			if ( '' === $tag ) {
				continue;
			}

			$key = strtolower( $tag );

			if ( isset( $tags[ $key ] ) ) {
				continue;
			}

			$tags[ $key ] = $tag;
		}

		return array_values( $tags );
	}

	/**
	 * Convert recipient tags into a storable string.
	 *
	 * @param string[] $tags Recipient tags.
	 * @return string
	 */
	private function serialize_recipient_tags( $tags ) {
		return implode( ', ', $this->sanitize_recipient_tags( $tags ) );
	}

	/**
	 * Check whether the recipient belongs to a segment tag.
	 *
	 * @param array  $recipient Recipient row.
	 * @param string $tag Segment tag.
	 * @return bool
	 */
	private function recipient_has_tag( $recipient, $tag ) {
		$tag = strtolower( trim( (string) $tag ) );

		if ( '' === $tag ) {
			return true;
		}

		foreach ( $this->sanitize_recipient_tags( isset( $recipient['tags'] ) ? $recipient['tags'] : array() ) as $recipient_tag ) {
			if ( strtolower( $recipient_tag ) === $tag ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create a stable unsubscribe token for a recipient.
	 *
	 * @param int    $recipient_id Recipient ID.
	 * @param string $email Recipient email.
	 * @return string
	 */
	private function build_unsubscribe_token( $recipient_id, $email ) {
		return wp_hash( $recipient_id . '|' . strtolower( (string) $email ) . '|wp-bulk-mail', 'nonce' );
	}

	/**
	 * Ensure one recipient row has an unsubscribe token.
	 *
	 * @param array $recipient Recipient row.
	 * @return array
	 */
	private function ensure_recipient_unsubscribe_token( $recipient ) {
		global $wpdb;

		$recipient = is_array( $recipient ) ? $recipient : array();

		if ( empty( $recipient['id'] ) || empty( $recipient['email'] ) || ! empty( $recipient['unsubscribe_token'] ) ) {
			return $recipient;
		}

		$token = $this->build_unsubscribe_token( (int) $recipient['id'], $recipient['email'] );

		$wpdb->update(
			self::get_recipients_table_name(),
			array(
				'unsubscribe_token' => $token,
			),
			array(
				'id' => (int) $recipient['id'],
			),
			array( '%s' ),
			array( '%d' )
		);

		$recipient['unsubscribe_token'] = $token;

		return $recipient;
	}

	/**
	 * Return the public unsubscribe URL for a recipient row.
	 *
	 * @param array $recipient Recipient row.
	 * @return string
	 */
	public function get_recipient_unsubscribe_url( $recipient ) {
		$recipient = $this->ensure_recipient_unsubscribe_token( $recipient );

		if ( empty( $recipient['unsubscribe_token'] ) ) {
			return home_url( '/' );
		}

		return add_query_arg(
			array(
				'wp_bulk_mail_unsubscribe' => rawurlencode( $recipient['unsubscribe_token'] ),
			),
			home_url( '/' )
		);
	}

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
		$sql        = "SELECT id, name, email, tags, status, unsubscribe_token, unsubscribed_at, unsubscribe_source, created_at, updated_at
			FROM {$table_name}
			WHERE status = %s
			ORDER BY email ASC";
		$rows       = $wpdb->get_results( $wpdb->prepare( $sql, 'active' ), ARRAY_A );

		return array_map( array( $this, 'normalize_recipient_row' ), (array) $rows );
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
		$sql        = "SELECT id, name, email, tags, status, unsubscribe_token, unsubscribed_at, unsubscribe_source, created_at, updated_at
			FROM {$table_name}
			WHERE id = %d
			LIMIT 1";
		$row        = $wpdb->get_row( $wpdb->prepare( $sql, $recipient_id ), ARRAY_A );

		return is_array( $row ) ? $this->normalize_recipient_row( $this->ensure_recipient_unsubscribe_token( $row ) ) : null;
	}

	/**
	 * Get paginated recipients for the admin list with optional search.
	 *
	 * @param string $search_term Search query.
	 * @param int    $page Current page number.
	 * @param int    $per_page Items per page.
	 * @return array
	 */
	public function get_recipients_page_data( $search_term = '', $page = 1, $per_page = self::RECIPIENTS_PER_PAGE, $status_filter = 'active', $tag_filter = '' ) {
		global $wpdb;

		$table_name  = self::get_recipients_table_name();
		$search_term = sanitize_text_field( (string) $search_term );
		$page        = max( 1, absint( $page ) );
		$per_page    = max( 1, absint( $per_page ) );
		$status_filter = sanitize_key( (string) $status_filter );
		$tag_filter    = sanitize_text_field( (string) $tag_filter );

		if ( '' !== $tag_filter ) {
			$page     = 1;
			$per_page = 5000;
		}
		$where_sql   = 'WHERE 1=1';
		$query_args  = array();

		if ( in_array( $status_filter, array( 'active', 'unsubscribed' ), true ) ) {
			$where_sql   .= ' AND status = %s';
			$query_args[] = $status_filter;
		} else {
			$status_filter = 'all';
		}

		if ( '' !== $search_term ) {
			$like         = '%' . $wpdb->esc_like( $search_term ) . '%';
			$where_sql   .= ' AND (name LIKE %s OR email LIKE %s OR tags LIKE %s)';
			$query_args[] = $like;
			$query_args[] = $like;
			$query_args[] = $like;
		}

		$count_sql   = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
		$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $query_args ) );
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
		$page        = min( $page, $total_pages );
		$offset      = ( $page - 1 ) * $per_page;
		$list_sql    = "SELECT id, name, email, tags, status, unsubscribe_token, unsubscribed_at, unsubscribe_source, created_at, updated_at
			FROM {$table_name}
			{$where_sql}
			ORDER BY created_at DESC, id DESC
			LIMIT %d OFFSET %d";
		$list_args   = array_merge( $query_args, array( $per_page, $offset ) );
		$items       = array_map(
			array( $this, 'normalize_recipient_row' ),
			(array) $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ), ARRAY_A )
		);

		if ( '' !== $tag_filter ) {
			$items = array_values(
				array_filter(
					$items,
					function ( $recipient ) use ( $tag_filter ) {
						return $this->recipient_has_tag( $recipient, $tag_filter );
					}
				)
			);
		}

		return array(
			'items'        => is_array( $items ) ? $items : array(),
			'search_term'  => $search_term,
			'status_filter' => $status_filter,
			'tag_filter'   => $tag_filter,
			'current_page' => $page,
			'per_page'     => $per_page,
			'total_items'  => '' === $tag_filter ? $total_items : count( $items ),
			'total_pages'  => '' === $tag_filter ? $total_pages : 1,
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
		$sql          = "SELECT id, name, email, tags, status, unsubscribe_token, unsubscribed_at, unsubscribe_source, created_at, updated_at
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
				$ordered[] = $this->normalize_recipient_row( $this->ensure_recipient_unsubscribe_token( $indexed[ $recipient_id ] ) );
			}
		}

		return $ordered;
	}

	/**
	 * Get all known recipient tags.
	 *
	 * @return string[]
	 */
	public function get_all_recipient_tags() {
		$tags = array();

		foreach ( $this->get_recipients_page_data( '', 1, 500, 'all' )['items'] as $recipient ) {
			foreach ( $this->sanitize_recipient_tags( isset( $recipient['tags'] ) ? $recipient['tags'] : array() ) as $tag ) {
				$tags[ strtolower( $tag ) ] = $tag;
			}
		}

		asort( $tags, SORT_NATURAL | SORT_FLAG_CASE );

		return array_values( $tags );
	}

	/**
	 * Get active recipients matching one segment tag.
	 *
	 * @param string $segment_tag Segment tag.
	 * @return array[]
	 */
	public function get_recipients_by_segment_tag( $segment_tag ) {
		$segment_tag = sanitize_text_field( (string) $segment_tag );

		if ( '' === $segment_tag ) {
			return array();
		}

		return array_values(
			array_filter(
				$this->get_all_recipients(),
				function ( $recipient ) use ( $segment_tag ) {
					return $this->recipient_has_tag( $recipient, $segment_tag );
				}
			)
		);
	}

	/**
	 * Insert one recipient row if the email is new.
	 *
	 * @param string $email Recipient email.
	 * @param string $name Recipient name.
	 * @return true|WP_Error
	 */
	private function insert_recipient( $email, $name = '', $tags = array() ) {
		global $wpdb;

		$email = sanitize_email( $email );
		$name  = sanitize_text_field( $name );
		$tags  = $this->sanitize_recipient_tags( $tags );

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

		$token  = $this->build_unsubscribe_token( 0, $email . '|' . wp_generate_password( 8, false ) );
		$result = $wpdb->insert(
			$table_name,
			array(
				'name'              => $name,
				'email'             => $email,
				'tags'              => $this->serialize_recipient_tags( $tags ),
				'status'            => 'active',
				'unsubscribe_token' => $token,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
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
	private function update_recipient( $recipient_id, $email, $name = '', $tags = array() ) {
		global $wpdb;

		$recipient_id = absint( $recipient_id );
		$email        = sanitize_email( $email );
		$name         = sanitize_text_field( $name );
		$tags         = $this->sanitize_recipient_tags( $tags );

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
				'name'              => $name,
				'email'             => $email,
				'tags'              => $this->serialize_recipient_tags( $tags ),
				'unsubscribe_token' => ! empty( $current['unsubscribe_token'] ) ? $current['unsubscribe_token'] : $this->build_unsubscribe_token( $recipient_id, $email ),
			),
			array( 'id' => $recipient_id ),
			array( '%s', '%s', '%s', '%s' ),
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
		$status_filter        = isset( $_GET['recipient_status'] ) ? sanitize_key( wp_unslash( $_GET['recipient_status'] ) ) : 'active';
		$tag_filter           = isset( $_GET['recipient_tag'] ) ? sanitize_text_field( wp_unslash( $_GET['recipient_tag'] ) ) : '';
		$current_page         = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$edit_recipient_id    = isset( $_GET['edit_recipient'] ) ? absint( $_GET['edit_recipient'] ) : 0;
		$recipients_page      = $this->get_recipients_page_data( $search_term, $current_page, self::RECIPIENTS_PER_PAGE, $status_filter, $tag_filter );
		$stored_recipients    = $recipients_page['items'];
		$available_tags       = $this->get_all_recipient_tags();
		$editing_recipient    = $edit_recipient_id > 0 ? $this->get_recipient_by_id( $edit_recipient_id ) : null;
		$is_edit_mode         = is_array( $editing_recipient );
		$recipient_form_data  = array(
			'id'    => $is_edit_mode ? (int) $editing_recipient['id'] : 0,
			'name'  => $is_edit_mode ? $editing_recipient['name'] : '',
			'email' => $is_edit_mode ? $editing_recipient['email'] : '',
			'tags'  => $is_edit_mode ? implode( ', ', $editing_recipient['tags'] ) : '',
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
		$tags            = isset( $_POST['wp_bulk_mail_recipient_tags'] ) ? $this->sanitize_recipient_tags( wp_unslash( $_POST['wp_bulk_mail_recipient_tags'] ) ) : array();
		$recipient_id    = isset( $_POST['wp_bulk_mail_recipient_id'] ) ? absint( $_POST['wp_bulk_mail_recipient_id'] ) : 0;
		$redirect_search = isset( $_POST['redirect_search'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_search'] ) ) : '';
		$redirect_paged  = isset( $_POST['redirect_paged'] ) ? max( 1, absint( $_POST['redirect_paged'] ) ) : 1;
		$redirect_status = isset( $_POST['redirect_status'] ) ? sanitize_key( wp_unslash( $_POST['redirect_status'] ) ) : 'active';
		$redirect_tag    = isset( $_POST['redirect_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_tag'] ) ) : '';
		$redirect_args   = array_filter(
			array(
				'recipient_search' => $redirect_search,
				'recipient_status' => 'active' !== $redirect_status ? $redirect_status : null,
				'recipient_tag'    => '' !== $redirect_tag ? $redirect_tag : null,
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

		$result = $recipient_id > 0 ? $this->update_recipient( $recipient_id, $email, $name, $tags ) : $this->insert_recipient( $email, $name, $tags );

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
		$redirect_status = isset( $_POST['redirect_status'] ) ? sanitize_key( wp_unslash( $_POST['redirect_status'] ) ) : 'active';
		$redirect_tag    = isset( $_POST['redirect_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_tag'] ) ) : '';
		$redirect_args   = array_filter(
			array(
				'recipient_search' => $redirect_search,
				'recipient_status' => 'active' !== $redirect_status ? $redirect_status : null,
				'recipient_tag'    => '' !== $redirect_tag ? $redirect_tag : null,
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
		$this->save_compose_draft( $draft );

		$this->set_recipients_notice( 'success', __( 'Recipient deleted.', 'wp-bulk-mail' ) );
		wp_safe_redirect( $this->get_recipients_page_url( $redirect_args ) );
		exit;
	}
	/**
	 * Mark one recipient as unsubscribed by public token.
	 *
	 * @param string $token Unsubscribe token.
	 * @return array|WP_Error
	 */
	private function unsubscribe_recipient_by_token( $token ) {
		global $wpdb;

		$token = sanitize_text_field( (string) $token );

		if ( '' === $token ) {
			return new WP_Error( 'missing_token', __( 'The unsubscribe link is missing or invalid.', 'wp-bulk-mail' ) );
		}

		$table_name = self::get_recipients_table_name();
		$recipient  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, email, tags, status, unsubscribe_token, unsubscribed_at, unsubscribe_source, created_at, updated_at
				FROM {$table_name}
				WHERE unsubscribe_token = %s
				LIMIT 1",
				$token
			),
			ARRAY_A
		);

		if ( ! is_array( $recipient ) ) {
			return new WP_Error( 'missing_recipient', __( 'We could not find a recipient for this unsubscribe link.', 'wp-bulk-mail' ) );
		}

		if ( 'unsubscribed' === $recipient['status'] ) {
			return $this->normalize_recipient_row( $recipient );
		}

		$wpdb->update(
			$table_name,
			array(
				'status'             => 'unsubscribed',
				'unsubscribed_at'    => current_time( 'mysql' ),
				'unsubscribe_source' => 'public_link',
			),
			array(
				'id' => (int) $recipient['id'],
			),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		$recipient['status']             = 'unsubscribed';
		$recipient['unsubscribed_at']    = current_time( 'mysql' );
		$recipient['unsubscribe_source'] = 'public_link';

		return $this->normalize_recipient_row( $recipient );
	}

	/**
	 * Handle the public unsubscribe endpoint.
	 *
	 * @return void
	 */
	public function maybe_handle_unsubscribe_request() {
		if ( empty( $_GET['wp_bulk_mail_unsubscribe'] ) ) {
			return;
		}

		$result = $this->unsubscribe_recipient_by_token( wp_unslash( $_GET['wp_bulk_mail_unsubscribe'] ) );
		$title  = __( 'Subscription Updated', 'wp-bulk-mail' );
		$body   = is_wp_error( $result )
			? $result->get_error_message()
			: __( 'You have been unsubscribed from future mail sent by this site.', 'wp-bulk-mail' );
		$status = is_wp_error( $result ) ? 400 : 200;

		status_header( $status );
		nocache_headers();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php echo esc_html( $title ); ?></title>
			<style>
				body { font-family: Arial, sans-serif; background: #f5f8fb; color: #102a43; margin: 0; padding: 32px 16px; }
				.card { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid #d7e3f1; border-radius: 20px; padding: 28px; box-shadow: 0 16px 36px rgba(15,23,42,.08); }
				h1 { margin-top: 0; }
				p { line-height: 1.7; color: #52667a; }
			</style>
		</head>
		<body>
			<div class="card">
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo esc_html( $body ); ?></p>
				<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return to site', 'wp-bulk-mail' ); ?></a></p>
			</div>
		</body>
		</html>
		<?php
		exit;
	}
}
