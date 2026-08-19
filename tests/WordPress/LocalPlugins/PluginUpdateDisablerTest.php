<?php

namespace Art\LibTools\Tests\WordPress\LocalPlugins;

use Art\LibTools\Tests\TestCase;
use Art\LibTools\WordPress\LocalPlugins\PluginUpdateDisabler;
use WP_Mock;

class PluginUpdateDisablerTest extends TestCase {

	private PluginUpdateDisabler $disabler;


	public function setUp(): void {

		parent::setUp();

		$this->disabler = new PluginUpdateDisabler();
		$this->disabler->register(
			'my-custom-plugin',
			'my-custom-plugin/my-custom-plugin.php',
			[
				'name'    => 'My Custom Plugin',
				'version' => '1.2.3',
			]
		);
	}


	public function test_boot_registers_filters_once(): void {

		WP_Mock::expectFilterAdded( 'plugins_api', [ $this->disabler, 'filter_plugin_info' ], 10, 3 );
		WP_Mock::expectFilterAdded( 'site_transient_update_plugins', [ $this->disabler, 'filter_updates' ] );

		$this->disabler->boot();
		$this->disabler->boot();
	}


	public function test_filter_updates_removes_registered_plugin(): void {

		$transient           = new \stdClass();
		$transient->response = [
			'my-custom-plugin/my-custom-plugin.php' => (object) [ 'new_version' => '9.9.9' ],
			'other/other.php'                       => (object) [ 'new_version' => '1.0.0' ],
		];

		$result = $this->disabler->filter_updates( $transient );

		$this->assertArrayNotHasKey( 'my-custom-plugin/my-custom-plugin.php', $result->response );
		$this->assertArrayHasKey( 'other/other.php', $result->response );
	}


	public function test_filter_updates_returns_transient_when_response_empty(): void {

		$transient           = new \stdClass();
		$transient->response = [];

		$this->assertSame( $transient, $this->disabler->filter_updates( $transient ) );
	}


	public function test_filter_plugin_info_returns_stub_for_registered_slug(): void {

		$args       = (object) [ 'slug' => 'my-custom-plugin' ];
		$info       = $this->disabler->filter_plugin_info( false, 'plugin_information', $args );

		$this->assertIsObject( $info );
		$this->assertSame( 'My Custom Plugin', $info->name );
		$this->assertSame( 'my-custom-plugin', $info->slug );
		$this->assertSame( '1.2.3', $info->version );
		$this->assertArrayHasKey( 'description', $info->sections );
	}


	public function test_filter_plugin_info_ignores_other_actions_and_slugs(): void {

		$args = (object) [ 'slug' => 'my-custom-plugin' ];

		$this->assertFalse( $this->disabler->filter_plugin_info( false, 'query_plugins', $args ) );

		$other = (object) [ 'slug' => 'woocommerce' ];
		$this->assertSame( 'kept', $this->disabler->filter_plugin_info( 'kept', 'plugin_information', $other ) );
	}
}
