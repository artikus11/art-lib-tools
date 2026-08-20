<?php

namespace Art\LibTools\Tests;

use WP_Mock;
use WP_Mock\Tools\TestCase as WPMockTestCase;

abstract class TestCase extends WPMockTestCase {

	protected function mock_wp_parse_args(): void {

		WP_Mock::userFunction(
			'wp_parse_args',
			[
				'return' => static function ( $args, $defaults = [] ) {
					if ( is_object( $args ) ) {
						$args = get_object_vars( $args );
					}

					if ( ! is_array( $args ) ) {
						$args = [];
					}

					return array_merge( $defaults, $args );
				},
			]
		);
	}
}
