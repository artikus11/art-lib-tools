<?php

namespace Art\LibTools\WordPress;

use wpdb;

/**
 * Runtime-резолвер имён таблиц для переходного периода ADR-0004.
 *
 * Поплагинный роллаут ренейминга таблиц означает окно, в котором читающий
 * плагин обновлён раньше или позже плагина-владельца таблицы. Кто читает
 * таблицы чужого плагина, передаёт список кандидатов — каноническое имя
 * (ADR-0004) первым, легаси-литерал вторым — и получает первое существующее.
 *
 * Кеш на запрос: позитивные совпадения запоминаются в статике; когда ни одно
 * имя не найдено, возвращается первый кандидат без кеша — следующий вызов
 * перепроверит БД (таблица-владелец может появиться в любой момент).
 */
class DbTable {

	/**
	 * @var array<string, string> Полное имя (с префиксом) по ключу "prefix|кандидаты".
	 */
	private static array $resolved = [];


	/**
	 * Возвращает первое существующее имя таблицы из списка кандидатов.
	 *
	 * @param  array<int, string> $names Суффиксы без префикса wpdb; канонический первым.
	 * @param  wpdb|null          $db    Инстанс $wpdb (по умолчанию глобальный).
	 *
	 * @return string Полное имя таблицы (с префиксом).
	 */
	public static function resolve( array $names, ?wpdb $db = null ): string {

		global $wpdb;

		$db = $db ?: $wpdb;

		$names = array_values( $names );

		if ( [] === $names ) {
			return '';
		}

		$key = $db->prefix . '|' . implode( '|', $names );

		if ( isset( self::$resolved[ $key ] ) ) {
			return self::$resolved[ $key ];
		}

		foreach ( $names as $name ) {
			$full = $db->prefix . $name;

			if ( $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $full ) ) === $full ) {
				self::$resolved[ $key ] = $full;

				return $full;
			}
		}

		return $db->prefix . $names[0];
	}
}
