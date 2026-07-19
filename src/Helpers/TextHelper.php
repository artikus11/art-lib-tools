<?php

namespace Art\LibTools\Helpers;

class TextHelper {

	public static function get_truncate( $content, $length = 40, $units = 'letters', $ellipsis = '&nbsp;...' ): string {

		$content = trim( preg_replace( '/\s+/', ' ', $content ) );

		$media_patterns = [
			'/<figure[^>]*>.*?<\/figure>/is',
			'/<figcaption[^>]*>.*?<\/figcaption>/is',
			'/<img[^>]+\>/i',
			'/<iframe[^>]+>.*?<\/iframe>/is',
			'/<audio[^>]+>.*?<\/audio>/is',
			'/<video[^>]+>.*?<\/video>/is',
			'/<!--\s*\/?wp:.*?-->/s',
		];

		$content = preg_replace( $media_patterns, '', $content );

		$shortcode_patterns = [
			'~\[/?.*?\](?!\()~',
			'~\[([a-z0-9_-]+)[^\]]*\](?!\().*?\[/\1\]~is',
			'~\[/?[^\]]*\](?!\()~',
		];

		$content = preg_replace( $shortcode_patterns, '', $content );

		$content = wp_strip_all_tags( $content );
		$content = strip_shortcodes( $content );
		$content = trim( $content );

		if ( 'letters' === $units ) {
			return ( mb_strlen( $content ) > $length ) ? mb_substr( $content, 0, $length ) . $ellipsis : $content;
		} else {
			$words = explode( ' ', $content );

			return ( count( $words ) > $length ? implode( ' ', array_slice( $words, 0, $length ) ) . $ellipsis : $content );
		}
	}


	/**
	 * Склонение слова после числа.
	 *
	 *     // Примеры вызова:
	 *     plural_form( $num, 'книга,книги,книг' )
	 *     plural_form( $num, 'book,books' )
	 *     plural_form( $num, [ 'книга','книги','книг' ] )
	 *     plural_form( $num, [ 'book','books' ] )
	 *
	 * @param  int|string   $number Число после которого будет слово. Можно указать число в HTML тегах.
	 * @param  array|string $titles Варианты склонения или первое слово для кратного 1.
	 *
	 * @return string Например: 1 книга, 2 книги, 10 книг.
	 *
	 * @version 3.0
	 */
	public static function plural_form( int|string $number, array|string $titles ): string {

		if ( is_string( $titles ) ) {
			$titles = preg_split( '/, */', $titles );
		}

		if ( empty( $titles[2] ) ) {
			$titles[2] = $titles[1];
		}

		$cases = [ 2, 0, 1, 1, 1, 2 ];

		$int_num = abs( (int) wp_strip_all_tags( $number ) );

		$title_index = ( $int_num % 100 > 4 && $int_num % 100 < 20 ) ? 2 : $cases[ min( $int_num % 10, 5 ) ];

		return $titles[ $title_index ];
	}
}
