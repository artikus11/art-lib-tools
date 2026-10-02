<?php

class wpdb {

	public $prefix = 'wp_';

	public $last_error = '';

	/**
	 * @var array<int, array{query: string, args: array<int, mixed>}>
	 */
	public $prepare_calls = [];

	/**
	 * @var array<int, mixed>
	 */
	public $get_var_queue = [];

	/**
	 * @var array<int, mixed>
	 */
	public $get_col_queue = [];

	/**
	 * @var array<int, mixed>
	 */
	public $query_queue = [];

	/**
	 * @var array<int, mixed>
	 */
	public $query_calls = [];

	/**
	 * @var array<int, array<string, mixed>|null>
	 */
	public $get_row_queue = [];


	public function prepare( $query, ...$args ) {

		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$this->prepare_calls[] = [
			'query' => (string) $query,
			'args'  => $args,
		];

		return [
			'query' => (string) $query,
			'args'  => $args,
		];
	}


	public function get_var( $query = null ) {

		if ( [] === $this->get_var_queue ) {
			return null;
		}

		return array_shift( $this->get_var_queue );
	}


	public function get_col( $query = null ) {

		if ( [] === $this->get_col_queue ) {
			return [];
		}

		return array_shift( $this->get_col_queue );
	}


	public function get_row( $query = null, $output = null ) {

		if ( [] === $this->get_row_queue ) {
			return null;
		}

		return array_shift( $this->get_row_queue );
	}


	public function get_charset_collate() {

		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}


	public function query( $query ) {

		$this->query_calls[] = $query;

		if ( [] === $this->query_queue ) {
			return 0;
		}

		return array_shift( $this->query_queue );
	}
}
