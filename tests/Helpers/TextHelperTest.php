<?php

namespace Art\LibTools\Tests\Helpers;

use Art\LibTools\Helpers\TextHelper;
use Art\LibTools\Tests\TestCase;
use WP_Mock;

class TextHelperTest extends TestCase {

	public function setUp(): void {

		parent::setUp();

		WP_Mock::userFunction(
			'wp_strip_all_tags',
			[
				'return' => static function ( $text ) {
					$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
					$text = strip_tags( $text );

					return trim( $text );
				},
			]
		);

		WP_Mock::userFunction(
			'strip_shortcodes',
			[
				'return' => static function ( $text ) {
					return (string) $text;
				},
			]
		);
	}


	public function test_truncate_by_letters(): void {

		$result = TextHelper::get_truncate( 'Hello world from tests', 5, 'letters', '...' );

		$this->assertSame( 'Hello...', $result );
	}


	public function test_truncate_by_words(): void {

		$result = TextHelper::get_truncate( 'One two three four five', 3, 'words', '...' );

		$this->assertSame( 'One two three...', $result );
	}


	public function test_truncate_does_not_append_ellipsis_when_short(): void {

		$this->assertSame( 'Hi', TextHelper::get_truncate( 'Hi', 10, 'letters', '...' ) );
		$this->assertSame( 'Hi there', TextHelper::get_truncate( 'Hi there', 10, 'words', '...' ) );
	}


	public function test_truncate_strips_media_gutenberg_and_html(): void {

		$content = '<p>Hello <img src="x.jpg" alt="x" /> world</p><!-- wp:paragraph --><figure><img src="y.jpg" /></figure><!-- /wp:paragraph -->';

		$result = TextHelper::get_truncate( $content, 20, 'letters', '' );

		$this->assertSame( 'Hello world', $result );
	}


	public function test_truncate_strips_shortcodes(): void {

		$content = 'Before [gallery ids="1"] after [caption]inner[/caption] end';

		$result = TextHelper::get_truncate( $content, 40, 'letters', '' );

		$this->assertStringNotContainsString( '[gallery', $result );
		$this->assertStringNotContainsString( '[caption]', $result );
		$this->assertStringContainsString( 'Before', $result );
		$this->assertStringContainsString( 'end', $result );
	}


	/**
	 * @dataProvider plural_form_provider
	 */
	public function test_plural_form( $number, $titles, string $expected ): void {

		$this->assertSame( $expected, TextHelper::plural_form( $number, $titles ) );
	}


	public function plural_form_provider(): array {

		$ru = 'книга,книги,книг';

		return [
			'one'              => [ 1, $ru, 'книга' ],
			'two'              => [ 2, $ru, 'книги' ],
			'five'             => [ 5, $ru, 'книг' ],
			'eleven'           => [ 11, $ru, 'книг' ],
			'twelve'           => [ 12, $ru, 'книг' ],
			'twenty_one'       => [ 21, $ru, 'книга' ],
			'twenty_two'       => [ 22, $ru, 'книги' ],
			'twenty_five'      => [ 25, $ru, 'книг' ],
			'zero'             => [ 0, $ru, 'книг' ],
			'one_hundred_one'  => [ 101, $ru, 'книга' ],
			'one_hundred_eleven' => [ 111, $ru, 'книг' ],
			'array_titles'     => [ 3, [ 'товар', 'товара', 'товаров' ], 'товара' ],
			'english_two_forms' => [ 2, 'book,books', 'books' ],
			'html_number'      => [ '<span>3</span>', $ru, 'книги' ],
		];
	}
}
