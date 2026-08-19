<?php

namespace Art\LibTools\Tests\WordPress\WooCommerce;

use Art\LibTools\Tests\TestCase;
use Art\LibTools\WordPress\WooCommerce\HPOSCompatible;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WP_Mock;

class HPOSCompatibleTest extends TestCase {

	public function setUp(): void {

		parent::setUp();
		FeaturesUtil::reset();
	}


	public function test_boot_registers_action_once(): void {

		$hpos = new HPOSCompatible( '/path/to/my-plugin/my-plugin.php', true );

		WP_Mock::expectActionAdded( 'before_woocommerce_init', [ $hpos, 'declare_compatibility' ] );

		$hpos->boot();
		$hpos->boot();
	}


	public function test_declare_compatibility_uses_plugin_basename(): void {

		WP_Mock::userFunction(
			'plugin_basename',
			[
				'times'  => 1,
				'args'   => [ '/path/to/my-plugin/my-plugin.php' ],
				'return' => 'my-plugin/my-plugin.php',
			]
		);

		$hpos = new HPOSCompatible( '/path/to/my-plugin/my-plugin.php', true );
		$hpos->declare_compatibility();

		$this->assertSame(
			[
				[
					'feature'     => 'custom_order_tables',
					'plugin_file' => 'my-plugin/my-plugin.php',
					'compatible'  => true,
				],
			],
			FeaturesUtil::$declared
		);
	}


	public function test_can_declare_incompatibility(): void {

		WP_Mock::userFunction(
			'plugin_basename',
			[
				'return' => 'my-plugin/my-plugin.php',
			]
		);

		$hpos = new HPOSCompatible( __FILE__, false );
		$hpos->declare_compatibility();

		$this->assertFalse( FeaturesUtil::$declared[0]['compatible'] );
	}
}
