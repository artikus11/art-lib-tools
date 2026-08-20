<?php

namespace Automattic\WooCommerce\Utilities;

class FeaturesUtil {

	/**
	 * @var array<int, array{feature: string, plugin_file: string, compatible: bool}>
	 */
	public static array $declared = [];


	public static function reset(): void {

		self::$declared = [];
	}


	public static function declare_compatibility( $feature, $plugin_file, $compatible = true ): void {

		self::$declared[] = [
			'feature'     => $feature,
			'plugin_file' => $plugin_file,
			'compatible'  => $compatible,
		];
	}
}
