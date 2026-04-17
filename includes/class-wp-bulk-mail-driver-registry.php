<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Bulk_Mail_Driver_Registry {

	/**
	 * Registered drivers.
	 *
	 * @var WP_Bulk_Mail_Driver[]
	 */
	private $drivers = array();

	/**
	 * Register a driver instance.
	 *
	 * @param WP_Bulk_Mail_Driver $driver Mail driver.
	 * @return WP_Bulk_Mail_Driver_Registry
	 */
	public function register( WP_Bulk_Mail_Driver $driver ) {
		$this->drivers[ $driver->get_id() ] = $driver;

		return $this;
	}

	/**
	 * Get all registered drivers.
	 *
	 * @return WP_Bulk_Mail_Driver[]
	 */
	public function all() {
		return $this->drivers;
	}

	/**
	 * Get drivers that are ready to use.
	 *
	 * @return WP_Bulk_Mail_Driver[]
	 */
	public function selectable() {
		return array_filter(
			$this->drivers,
			static function ( $driver ) {
				return $driver->is_selectable();
			}
		);
	}

	/**
	 * Get future drivers that are registered but not selectable yet.
	 *
	 * @return WP_Bulk_Mail_Driver[]
	 */
	public function planned() {
		return array_filter(
			$this->drivers,
			static function ( $driver ) {
				return ! $driver->is_selectable();
			}
		);
	}

	/**
	 * Fetch a driver by key.
	 *
	 * @param string $id Driver key.
	 * @return WP_Bulk_Mail_Driver|null
	 */
	public function get( $id ) {
		return isset( $this->drivers[ $id ] ) ? $this->drivers[ $id ] : null;
	}

	/**
	 * Get the fallback selectable driver key.
	 *
	 * @param string $preferred Preferred key.
	 * @return string
	 */
	public function get_default_driver_id( $preferred = 'wordpress' ) {
		if ( isset( $this->drivers[ $preferred ] ) && $this->drivers[ $preferred ]->is_selectable() ) {
			return $preferred;
		}

		foreach ( $this->drivers as $driver ) {
			if ( $driver->is_selectable() ) {
				return $driver->get_id();
			}
		}

		return $preferred;
	}

	/**
	 * Collect defaults from all registered drivers.
	 *
	 * @return array
	 */
	public function get_defaults() {
		$defaults = array();

		foreach ( $this->drivers as $driver ) {
			$defaults = array_merge( $defaults, $driver->get_defaults() );
		}

		return $defaults;
	}
}
