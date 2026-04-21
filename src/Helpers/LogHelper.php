<?php

namespace Art\LibTools\Helpers;

/**
 * Универсальный помощник для логирования.
 * Работает с WooCommerce (если есть) или через системный error_log (как fallback).
 */
class LogHelper {
	
	/**
	 * Запись в лог.
	 *
	 * @param  mixed  $message    Сообщение (строка, массив, объект).
	 * @param  string $level      Уровень (info, error, debug, notice).
	 * @param  string $source     Идентификатор (название плагина/модуля).
	 * @param  string $option_key Ключ опции в БД для проверки чекбокса "Включить дебаг".
	 */
	public static function log( $message, string $level = 'info', string $source = 'skl-general', string $option_key = '' ): void {

		if ( $level !== 'error' && ! empty( $option_key ) ) {
			$settings = get_option( $option_key, [] );
			

			$is_debug_enabled = is_array( $settings )
				? ( isset( $settings['debug_logging'] ) && $settings['debug_logging'] === '1' )
				: ( $settings === '1' || $settings === true );
			
			if ( ! $is_debug_enabled ) {
				return;
			}
		}
		
		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true );
		}
		
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, [ 'source' => $source ] );
		} else {
			$log_entry = sprintf( '[%s] [%s] [%s]: %s', date( 'Y-m-d H:i:s' ), strtoupper( $level ), $source, $message );
			error_log( $log_entry );
		}
	}
}
