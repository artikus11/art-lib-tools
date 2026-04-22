<?php

namespace Art\LibTools\WordPress\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Class HPOSCompatible
 *
 * Обеспечивает декларацию совместимости плагина с HPOS (High-Performance Order Storage)
 */
class HPOSCompatible {
	
	/**
	 * @var string Plugin basename (например, 'my-plugin/my-plugin.php')
	 */
	private string $plugin_file;
	
	/**
	 * @var bool Совместим ли плагин с HPOS
	 */
	private bool $compatible;
	
	private bool $booted = false;
	
	
	/**
	 * @param  string $plugin_file Абсолютный путь к главному файлу плагина (__FILE__)
	 * @param  bool   $compatible  Совместим ли плагин с HPOS (по умолчанию true)
	 */
	public function __construct( string $plugin_file, bool $compatible = true ) {
		
		$this->plugin_file = $plugin_file;
		$this->compatible  = $compatible;
	}
	
	
	/**
	 * Инициализирует декларацию HPOS
	 */
	public function boot(): void {
		
		if ( $this->booted ) {
			return;
		}
		
		$this->booted = true;
		
		add_action( 'before_woocommerce_init', [ $this, 'declare_compatibility' ] );
	}
	
	
	/**
	 * Декларирует совместимость с HPOS
	 */
	public function declare_compatibility(): void {
		
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				plugin_basename( $this->plugin_file ),
				$this->compatible
			);
		}
	}
}