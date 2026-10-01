<?php

namespace Art\LibTools\Text;

/**
 * Нормализация заголовков: каноническая цепочка
 * html_entity_decode -> mb_strtolower -> тире -> '-' -> сжатие -> trim.
 *
 * Единый источник контракта `title_hash = md5( normalize_title )` для skl-плагинов
 * (feed / dedup / title-uniq) вместо зеркал кода.
 */
class TitleNormalizer {

	/**
	 * Нормализация title:
	 * html_entity_decode -> mb_strtolower -> тире (U+2010, U+2012–U+2014, U+2212) -> '-' ->
	 * сжатие пробелов вокруг '-' -> сжатие whitespace -> trim.
	 */
	public function normalize_title( string $title ): string {

		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = mb_strtolower( $title );

		$title = (string) preg_replace( '/[‐-‒–—−]+/u', '-', $title );
		$title = (string) preg_replace( '/\s*-\s*/u', '-', $title );
		$title = (string) preg_replace( '/\s+/u', ' ', $title );

		return trim( $title );
	}

	/**
	 * Срез одного хвостового WP-суффикса уникализации slug (-2, -3, ...).
	 */
	public function slug_strip_suffix( string $slug ): string {

		return (string) preg_replace( '/-\d+$/', '', $slug );
	}
}
