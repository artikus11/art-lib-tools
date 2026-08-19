<?php

namespace Art\LibTools\Tests\Helpers;

use Art\LibTools\Helpers\LogHelper;
use Art\LibTools\Tests\TestCase;
use Mockery;
use WP_Mock;

class LogHelperTest extends TestCase {

	public function test_skips_non_error_when_debug_option_is_off(): void {

		WP_Mock::userFunction(
			'get_option',
			[
				'times'  => 1,
				'args'   => [ 'my_plugin_settings', [] ],
				'return' => [ 'debug_logging' => '0' ],
			]
		);

		WP_Mock::userFunction( 'wc_get_logger', [ 'times' => 0 ] );

		LogHelper::log( 'skip me', 'debug', 'my-plugin', 'my_plugin_settings' );

		$this->assertTrue( true );
	}


	public function test_always_logs_errors_when_debug_is_off(): void {

		$logger = Mockery::mock();
		$logger->shouldReceive( 'log' )
			->once()
			->with( 'error', 'boom', [ 'source' => 'my-plugin' ] );

		WP_Mock::userFunction( 'get_option', [ 'times' => 0 ] );
		WP_Mock::userFunction( 'wc_get_logger', [ 'return' => $logger ] );

		LogHelper::log( 'boom', 'error', 'my-plugin', 'my_plugin_settings' );

		$this->assertTrue( true );
	}


	public function test_logs_when_debug_flag_is_enabled(): void {

		$logger = Mockery::mock();
		$logger->shouldReceive( 'log' )
			->once()
			->with( 'debug', 'hello', [ 'source' => 'my-plugin' ] );

		WP_Mock::userFunction(
			'get_option',
			[
				'return' => [ 'debug_logging' => '1' ],
			]
		);
		WP_Mock::userFunction( 'wc_get_logger', [ 'return' => $logger ] );

		LogHelper::log( 'hello', 'debug', 'my-plugin', 'my_plugin_settings' );

		$this->assertTrue( true );
	}


	public function test_stringifies_arrays_before_logging(): void {

		$logger = Mockery::mock();
		$logger->shouldReceive( 'log' )
			->once()
			->withArgs(
				static function ( $level, $message, $context ) {
					return 'info' === $level
						&& false !== strpos( $message, 'foo' )
						&& 'my-plugin' === $context['source'];
				}
			);

		WP_Mock::userFunction( 'wc_get_logger', [ 'return' => $logger ] );

		LogHelper::log( [ 'foo' => 'bar' ], 'info', 'my-plugin' );

		$this->assertTrue( true );
	}


	public function test_accepts_scalar_debug_option(): void {

		$logger = Mockery::mock();
		$logger->shouldReceive( 'log' )->once();

		WP_Mock::userFunction(
			'get_option',
			[
				'return' => '1',
			]
		);
		WP_Mock::userFunction( 'wc_get_logger', [ 'return' => $logger ] );

		LogHelper::log( 'on', 'debug', 'my-plugin', 'debug_enabled' );

		$this->assertTrue( true );
	}
}
