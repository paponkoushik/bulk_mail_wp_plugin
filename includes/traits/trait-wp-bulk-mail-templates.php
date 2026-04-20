<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Templates_Trait {

	/**
	 * Store a templates page notice for the current user.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_templates_notice( $type, $message ) {
		set_transient(
			'wp_bulk_mail_templates_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Get and clear the templates page notice for the current user.
	 *
	 * @return array|null
	 */
	public function get_templates_notice() {
		$key    = 'wp_bulk_mail_templates_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( false === $notice ) {
			return null;
		}

		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Return the admin URL for the templates page.
	 *
	 * @param array $args Optional query args.
	 * @return string
	 */
	public function get_templates_page_url( $args = array() ) {
		$url = admin_url( 'admin.php?page=' . self::TEMPLATES_MENU_SLUG );

		if ( empty( $args ) ) {
			return $url;
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * Return the supported template tokens for builders and previews.
	 *
	 * @return array[]
	 */
	public function get_template_tokens() {
		return array(
			array(
				'token'       => '{{recipient_name}}',
				'description' => __( 'Recipient name from the saved recipient list.', 'wp-bulk-mail' ),
			),
			array(
				'token'       => '{{recipient_email}}',
				'description' => __( 'Recipient email address.', 'wp-bulk-mail' ),
			),
			array(
				'token'       => '{{site_name}}',
				'description' => __( 'Your WordPress site name.', 'wp-bulk-mail' ),
			),
			array(
				'token'       => '{{site_url}}',
				'description' => __( 'Your site URL.', 'wp-bulk-mail' ),
			),
			array(
				'token'       => '{{unsubscribe_url}}',
				'description' => __( 'A public unsubscribe link unique to the recipient.', 'wp-bulk-mail' ),
			),
		);
	}

	/**
	 * Get all stored email templates.
	 *
	 * @return array[]
	 */
	public function get_all_templates() {
		global $wpdb;

		$table_name = self::get_templates_table_name();

		return $wpdb->get_results(
			"SELECT id, name, slug, description, subject, body, is_default, created_at, updated_at
			FROM {$table_name}
			ORDER BY is_default DESC, name ASC, id DESC",
			ARRAY_A
		);
	}

	/**
	 * Get one stored template by ID.
	 *
	 * @param int $template_id Template ID.
	 * @return array|null
	 */
	public function get_template_by_id( $template_id ) {
		global $wpdb;

		$template_id = absint( $template_id );

		if ( $template_id < 1 ) {
			return null;
		}

		$table_name = self::get_templates_table_name();
		$row        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, slug, description, subject, body, is_default, created_by, created_at, updated_at
				FROM {$table_name}
				WHERE id = %d
				LIMIT 1",
				$template_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Build a unique slug for custom templates.
	 *
	 * @param string $name Template name.
	 * @param int    $template_id Existing template ID.
	 * @return string
	 */
	private function build_template_slug( $name, $template_id = 0 ) {
		global $wpdb;

		$table_name  = self::get_templates_table_name();
		$template_id = absint( $template_id );
		$base_slug   = sanitize_title( $name );
		$slug_base   = '' !== $base_slug ? $base_slug : 'template';
		$slug        = $slug_base;
		$suffix      = 2;

		while ( true ) {
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE slug = %s LIMIT 1",
					$slug
				)
			);

			if ( $existing_id < 1 || $existing_id === $template_id ) {
				return $slug;
			}

			$slug = $slug_base . '-' . $suffix;
			++$suffix;
		}
	}

	/**
	 * Create or update one template record.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $name Template name.
	 * @param string $description Template description.
	 * @param string $subject Template subject.
	 * @param string $body Template body.
	 * @return int|WP_Error
	 */
	private function save_template_record( $template_id, $name, $description, $subject, $body ) {
		global $wpdb;

		$template_id = absint( $template_id );
		$name        = sanitize_text_field( $name );
		$description = sanitize_textarea_field( $description );
		$subject     = sanitize_text_field( $subject );
		$body        = wp_kses_post( $body );

		if ( '' === $name ) {
			return new WP_Error( 'missing_template_name', __( 'Template name is required.', 'wp-bulk-mail' ) );
		}

		if ( '' === $subject ) {
			return new WP_Error( 'missing_template_subject', __( 'Template subject is required.', 'wp-bulk-mail' ) );
		}

		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return new WP_Error( 'missing_template_body', __( 'Template body is required.', 'wp-bulk-mail' ) );
		}

		$table_name   = self::get_templates_table_name();
		$current      = $template_id > 0 ? $this->get_template_by_id( $template_id ) : null;
		$is_default   = is_array( $current ) ? (int) $current['is_default'] : 0;
		$template_slug = is_array( $current ) ? $current['slug'] : $this->build_template_slug( $name, $template_id );

		if ( ! is_array( $current ) && $template_id > 0 ) {
			return new WP_Error( 'missing_template', __( 'Template was not found.', 'wp-bulk-mail' ) );
		}

		$data = array(
			'name'        => $name,
			'slug'        => $template_slug,
			'description' => $description,
			'subject'     => $subject,
			'body'        => $body,
			'is_default'  => $is_default,
			'created_by'  => is_array( $current ) ? (int) $current['created_by'] : get_current_user_id(),
		);

		if ( $template_id > 0 ) {
			$result = $wpdb->update(
				$table_name,
				$data,
				array( 'id' => $template_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' ),
				array( '%d' )
			);

			if ( false === $result ) {
				return new WP_Error( 'template_update_failed', __( 'Could not update the template right now.', 'wp-bulk-mail' ) );
			}

			return $template_id;
		}

		$data['is_default'] = 0;
		$data['created_by'] = get_current_user_id();

		$result = $wpdb->insert(
			$table_name,
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'template_insert_failed', __( 'Could not save the template right now.', 'wp-bulk-mail' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Render the template builder page.
	 *
	 * @return void
	 */
	public function render_templates_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin             = $this;
		$templates_notice   = $this->get_templates_notice();
		$stored_templates   = $this->get_all_templates();
		$edit_template_id   = isset( $_GET['edit_template'] ) ? absint( $_GET['edit_template'] ) : 0;
		$editing_template   = $edit_template_id > 0 ? $this->get_template_by_id( $edit_template_id ) : null;
		$is_edit_mode       = is_array( $editing_template );
		$template_tokens    = $this->get_template_tokens();
		$template_form_data = array(
			'id'          => $is_edit_mode ? (int) $editing_template['id'] : 0,
			'name'        => $is_edit_mode ? $editing_template['name'] : '',
			'description' => $is_edit_mode ? $editing_template['description'] : '',
			'subject'     => $is_edit_mode ? $editing_template['subject'] : '',
			'body'        => $is_edit_mode ? $editing_template['body'] : '',
			'is_default'  => $is_edit_mode ? (int) $editing_template['is_default'] : 0,
		);

		require WP_BULK_MAIL_PATH . 'views/templates-page.php';
	}

	/**
	 * Handle creating or updating a template.
	 *
	 * @return void
	 */
	public function handle_save_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		check_admin_referer( 'wp_bulk_mail_save_template' );

		$template_id  = isset( $_POST['wp_bulk_mail_template_id'] ) ? absint( $_POST['wp_bulk_mail_template_id'] ) : 0;
		$name         = isset( $_POST['wp_bulk_mail_template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_bulk_mail_template_name'] ) ) : '';
		$description  = isset( $_POST['wp_bulk_mail_template_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wp_bulk_mail_template_description'] ) ) : '';
		$subject      = isset( $_POST['wp_bulk_mail_template_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_bulk_mail_template_subject'] ) ) : '';
		$body         = isset( $_POST['wp_bulk_mail_template_body'] ) ? wp_kses_post( wp_unslash( $_POST['wp_bulk_mail_template_body'] ) ) : '';
		$redirect_url = $this->get_templates_page_url();

		$result = $this->save_template_record( $template_id, $name, $description, $subject, $body );

		if ( is_wp_error( $result ) ) {
			$this->set_templates_notice( 'error', $result->get_error_message() );

			if ( $template_id > 0 ) {
				$redirect_url = $this->get_templates_page_url(
					array(
						'edit_template' => $template_id,
					)
				);
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}

		$this->set_templates_notice( 'success', $template_id > 0 ? __( 'Template updated.', 'wp-bulk-mail' ) : __( 'Template created.', 'wp-bulk-mail' ) );
		wp_safe_redirect(
			$this->get_templates_page_url(
				array(
					'edit_template' => (int) $result,
				)
			)
		);
		exit;
	}

	/**
	 * Handle deleting a custom template.
	 *
	 * @return void
	 */
	public function handle_delete_template() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		if ( $template_id < 1 ) {
			$this->set_templates_notice( 'error', __( 'Template ID was missing.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_templates_page_url() );
			exit;
		}

		check_admin_referer( 'wp_bulk_mail_delete_template_' . $template_id );

		$template = $this->get_template_by_id( $template_id );

		if ( ! $template ) {
			$this->set_templates_notice( 'error', __( 'Template was not found.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_templates_page_url() );
			exit;
		}

		if ( ! empty( $template['is_default'] ) ) {
			$this->set_templates_notice( 'error', __( 'Default templates cannot be deleted.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_templates_page_url() );
			exit;
		}

		$wpdb->update(
			self::get_campaigns_table_name(),
			array(
				'template_id' => 0,
			),
			array( 'template_id' => $template_id ),
			array( '%d' ),
			array( '%d' )
		);

		$deleted = $wpdb->delete(
			self::get_templates_table_name(),
			array( 'id' => $template_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			$this->set_templates_notice( 'error', __( 'Could not delete the template right now.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_templates_page_url() );
			exit;
		}

		$this->set_templates_notice( 'success', __( 'Template deleted.', 'wp-bulk-mail' ) );
		wp_safe_redirect( $this->get_templates_page_url() );
		exit;
	}
}
