<?php

namespace Art\LibTools\Tests\Helpers;

use Art\LibTools\Helpers\UrlAccessible;
use Art\LibTools\Tests\TestCase;
use WP_Mock;

class UrlAccessibleTest extends TestCase {

	public function setUp(): void {

		parent::setUp();
		$this->mock_wp_parse_args();
	}


	public function test_returns_true_for_success_status(): void {

		WP_Mock::userFunction( 'wp_remote_head', [ 'return' => [ 'body' => '' ] ] );
		WP_Mock::userFunction( 'is_wp_error', [ 'return' => false ] );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [ 'return' => 200 ] );

		$this->assertTrue( UrlAccessible::is_url_accessible( 'https://example.com/file.jpg' ) );
	}


	public function test_returns_true_for_redirect_status(): void {

		WP_Mock::userFunction( 'wp_remote_head', [ 'return' => [ 'body' => '' ] ] );
		WP_Mock::userFunction( 'is_wp_error', [ 'return' => false ] );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [ 'return' => 301 ] );

		$this->assertTrue( UrlAccessible::is_url_accessible( 'https://example.com/moved' ) );
	}


	public function test_returns_false_for_client_error(): void {

		WP_Mock::userFunction( 'wp_remote_head', [ 'return' => [ 'body' => '' ] ] );
		WP_Mock::userFunction( 'is_wp_error', [ 'return' => false ] );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [ 'return' => 404 ] );

		$this->assertFalse( UrlAccessible::is_url_accessible( 'https://example.com/missing.jpg' ) );
	}


	public function test_returns_false_on_wp_error(): void {

		$error = (object) [ 'errors' => [ 'http_request_failed' => [ 'fail' ] ] ];

		WP_Mock::userFunction( 'wp_remote_head', [ 'return' => $error ] );
		WP_Mock::userFunction( 'is_wp_error', [ 'return' => true ] );

		$this->assertFalse( UrlAccessible::is_url_accessible( 'https://example.com/down' ) );
	}


	public function test_falls_back_to_ranged_get_on_method_not_allowed(): void {

		WP_Mock::userFunction( 'wp_remote_head', [ 'return' => [ 'body' => '' ] ] );
		WP_Mock::userFunction(
			'wp_remote_get',
			[
				'times'  => 1,
				'return' => [ 'body' => '' ],
			]
		);
		WP_Mock::userFunction( 'is_wp_error', [ 'return' => false ] );
		WP_Mock::userFunction(
			'wp_remote_retrieve_response_code',
			[
				'return_in_order' => [ 405, 200 ],
			]
		);

		$this->assertTrue( UrlAccessible::is_url_accessible( 'https://example.com/file.jpg' ) );
	}


	public function test_returns_false_when_fallback_get_errors(): void {

		$get_error = (object) [ 'errors' => [ 'http_request_failed' => [ 'fail' ] ] ];

		WP_Mock::userFunction( 'wp_remote_head', [ 'return' => [ 'body' => '' ] ] );
		WP_Mock::userFunction( 'wp_remote_get', [ 'return' => $get_error ] );
		WP_Mock::userFunction(
			'is_wp_error',
			[
				'return_in_order' => [ false, true ],
			]
		);
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [ 'return' => 405 ] );

		$this->assertFalse( UrlAccessible::is_url_accessible( 'https://example.com/file.jpg' ) );
	}
}
