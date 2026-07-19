<?php

namespace Art\LibTools\Helpers;

class UrlNormalizer {

	private static string $pattern = '#^https?://[^./]+\.([^/]+\.[^/]+)#';

	private static array $url_cache = [];

	private static int $max_cache_size = 10000;

	private static int $hits = 0;

	private static int $misses = 0;


	public static function process_array( array $data ): array {

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::process_array( $value );
			} elseif ( is_string( $value ) ) {
				$data[ $key ] = self::normalize_url( $value );
			}
		}

		return $data;
	}


	public static function normalize_url( string $url ): string {

		// Быстрая проверка кэша
		if ( array_key_exists( $url, self::$url_cache ) ) {
			self::$hits ++;

			return self::$url_cache[ $url ];
		}

		self::$misses ++;

		$normalized = $url;
		if ( str_starts_with( $url, 'http' ) && str_contains( $url, '://s' ) ) {
			$normalized = preg_replace( self::$pattern, 'https://$1', $url );
		}

		self::$url_cache[ $url ] = $normalized;

		if ( count( self::$url_cache ) > self::$max_cache_size ) {
			self::$url_cache = array_slice(
				self::$url_cache, - intval( self::$max_cache_size * 0.7 ),
				null,
				true
			);
		}

		return $normalized;
	}


	public static function clear_cache(): void {

		self::$url_cache = [];
	}
}
