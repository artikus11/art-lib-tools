<?php

namespace Art\LibTools\Helpers;

class UrlAccessible {

	/**
	 * Проверка доступности URL через HEAD/GET-запрос
	 *
	 * @param  string $url
	 * @param  array  $args Кастомные параметры HTTP-запроса (timeout, user-agent, headers и т.д.)
	 *
	 * @return bool
	 */
	public static function is_url_accessible( string $url, array $args = [] ): bool {

		$defaults = [
			'timeout'     => 5,
			'redirection' => 5,
			'sslverify'   => false,
			'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
		];

		$parsed_args = wp_parse_args( $args, $defaults );

		$response = wp_remote_head( $url, $parsed_args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 405 === $code ) {
			$get_args            = $parsed_args;
			$get_args['headers'] = wp_parse_args( $get_args['headers'] ?? [], [ 'Range' => 'bytes=0-0' ] );

			$response = wp_remote_get( $url, $get_args );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
		}

		return $code >= 200 && $code < 400;
	}
}