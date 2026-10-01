<?php

namespace Art\LibTools\Tests\Text;

use Art\LibTools\Tests\TestCase;
use Art\LibTools\Text\TitleNormalizer;

class TitleNormalizerTest extends TestCase {

	public function test_normalize_title_plain(): void {

		$n = new TitleNormalizer();

		$this->assertSame( 'seo оптимизация сайта', $n->normalize_title( 'SEO Оптимизация сайта' ) );
	}

	public function test_normalize_title_lowercases_and_collapses_spaces(): void {

		$n = new TitleNormalizer();

		$this->assertSame(
			'seo оптимизация сайта',
			$n->normalize_title( "  SEO   Оптимизация\tсайта  " )
		);
	}

	public function test_normalize_title_decodes_html_entities(): void {

		$n = new TitleNormalizer();

		$this->assertSame(
			'«seo» «оптимизация»',
			$n->normalize_title( '&laquo;SEO&raquo; &#171;Оптимизация&#187;' )
		);
	}

	public function test_normalize_title_maps_dashes_to_hyphen(): void {

		$n = new TitleNormalizer();

		$this->assertSame( 'seo-оптимизация', $n->normalize_title( 'SEO — Оптимизация' ) );
		$this->assertSame( 'seo-оптимизация', $n->normalize_title( 'SEO – Оптимизация' ) );
		$this->assertSame( 'seo-оптимизация', $n->normalize_title( 'SEO − Оптимизация' ) );
		$this->assertSame( 'seo-оптимизация', $n->normalize_title( 'SEO - Оптимизация' ) );
	}

	public function test_normalize_title_keeps_quotes(): void {

		$n = new TitleNormalizer();

		$this->assertSame( 'seo «оптимизация» сайта', $n->normalize_title( 'SEO «Оптимизация» сайта' ) );
		$this->assertSame( 'seo "оптимизация"', $n->normalize_title( 'SEO &quot;Оптимизация&quot;' ) );
	}

	public function test_normalize_title_keeps_trailing_number_and_year(): void {

		$n = new TitleNormalizer();

		$this->assertSame( 'выкройка 123', $n->normalize_title( 'Выкройка 123' ) );
		$this->assertSame( 'чтение жизни (2016)', $n->normalize_title( 'Чтение жизни (2016)' ) );
	}

	public function test_slug_strip_suffix_removes_wp_suffix(): void {

		$n = new TitleNormalizer();

		$this->assertSame( 'vykroyka-123', $n->slug_strip_suffix( 'vykroyka-123-2' ) );
		$this->assertSame( 'kurs-seo', $n->slug_strip_suffix( 'kurs-seo-2016' ) );
	}

	public function test_slug_strip_suffix_keeps_slug_without_suffix(): void {

		$n = new TitleNormalizer();

		$this->assertSame( 'vykroyka', $n->slug_strip_suffix( 'vykroyka' ) );
		$this->assertSame( 'prosto', $n->slug_strip_suffix( 'prosto' ) );
	}
}