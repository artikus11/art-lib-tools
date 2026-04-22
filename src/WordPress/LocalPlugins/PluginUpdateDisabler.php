<?php

namespace Art\LibTools\WordPress\LocalPlugins;

/**
 * Class PluginUpdateDisabler
 *
 * Отключает обновления для указанных плагинов, удаляя их из transient update_plugins
 * и подменяя информацию о плагине в API, чтобы избежать ошибок "плагин не найден".
 */
class PluginUpdateDisabler {
	
	/**
	 * @var array<string, array{file: string, name?: string, version?: string}>
	 * Key: plugin slug, Value: plugin data
	 */
	private array $plugins = [];
	
	private bool $booted = false;
	
	
	/**
	 * Register a plugin to disable updates for.
	 *
	 * @param  string $slug Plugin slug (directory name).
	 * @param  string $file Plugin main file relative path (e.g., 'plugin-dir/plugin-main.php').
	 * @param  array  $data Optional metadata to fake API response.
	 *
	 * @return void
	 */
	public function register( string $slug, string $file, array $data = [] ): void {
		
		$this->plugins[ $slug ] = [
			'file'    => $file,
			'name'    => $data['name'] ?? $slug,
			'version' => $data['version'] ?? '1.0.0',
		];
	}
	
	
	public function boot(): void {
		
		if ( $this->booted ) {
			return;
		}
		
		$this->booted = true;
		
		// Hook into plugin info API to prevent errors when user clicks "View details"
		add_filter( 'plugins_api', [ $this, 'filter_plugin_info' ], 10, 3 );
		
		// Remove plugins from update checks
		add_filter( 'site_transient_update_plugins', [ $this, 'filter_updates' ] );
	}
	
	
	/**
	 * Filters the response for the current Plugin Installation screen.
	 */
	public function filter_plugin_info( $result, string $action, object $args ) {
		
		if ( $action !== 'plugin_information' ) {
			return $result;
		}
		
		if ( ! empty( $args->slug ) && isset( $this->plugins[ $args->slug ] ) ) {
			$p = $this->plugins[ $args->slug ];
			
			// Return minimal valid object to satisfy WP core requirements
			return (object) [
				'name'         => $p['name'],
				'slug'         => $args->slug,
				'version'      => $p['version'],
				'author'       => '',
				'homepage'     => '',
				'downloaded'   => 0,
				'last_updated' => '',
				'sections'     => [
					'description' => 'Updates disabled by administrator.',
				],
				'banners'      => [],
				'icons'        => [],
			];
		}
		
		return $result;
	}
	
	
	/**
	 * Removes registered plugins from the update transient.
	 */
	public function filter_updates( $transient ) {
		
		if ( empty( $transient->response ) ) {
			return $transient;
		}
		
		foreach ( $this->plugins as $slug => $data ) {
			
			if ( isset( $transient->response[ $data['file'] ] ) ) {
				unset( $transient->response[ $data['file'] ] );
			}
		}
		
		return $transient;
	}
}