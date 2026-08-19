<?php

namespace Art\LibTools\Tests\Helpers;

use Art\LibTools\Helpers\UrlNormalizer;
use Art\LibTools\Tests\TestCase;

class UrlNormalizerTest extends TestCase {

	public function setUp(): void {

		parent::setUp();
		UrlNormalizer::clear_cache();
	}


	public function tearDown(): void {

		UrlNormalizer::clear_cache();
		parent::tearDown();
	}


	public function test_normalize_cdn_subdomain(): void {

		$this->assertSame(
			'https://example.com/image.jpg',
			UrlNormalizer::normalize_url( 'https://s1.example.com/image.jpg' )
		);
	}


	public function test_leaves_non_cdn_url_unchanged(): void {

		$url = 'https://example.com/image.jpg';

		$this->assertSame( $url, UrlNormalizer::normalize_url( $url ) );
	}


	public function test_leaves_non_http_strings_unchanged(): void {

		$this->assertSame( 'not-a-url', UrlNormalizer::normalize_url( 'not-a-url' ) );
	}


	public function test_process_array_recursively(): void {

		$data = [
			'url'  => 'https://s2.example.com/a.jpg',
			'meta' => [
				'nested' => 'https://s3.example.com/b.jpg',
				'count'  => 2,
			],
		];

		$expected = [
			'url'  => 'https://example.com/a.jpg',
			'meta' => [
				'nested' => 'https://example.com/b.jpg',
				'count'  => 2,
			],
		];

		$this->assertSame( $expected, UrlNormalizer::process_array( $data ) );
	}


	public function test_cache_returns_same_result(): void {

		$url = 'https://s1.example.com/cached.jpg';

		$first  = UrlNormalizer::normalize_url( $url );
		$second = UrlNormalizer::normalize_url( $url );

		$this->assertSame( $first, $second );
		$this->assertSame( 'https://example.com/cached.jpg', $second );
	}


	public function test_clear_cache_forces_recompute(): void {

		$url = 'https://s1.example.com/x.jpg';

		UrlNormalizer::normalize_url( $url );
		UrlNormalizer::clear_cache();

		$this->assertSame(
			'https://example.com/x.jpg',
			UrlNormalizer::normalize_url( $url )
		);
	}


	public function test_cache_eviction_keeps_recent_entries(): void {

		for ( $i = 0; $i < 10001; $i++ ) {
			UrlNormalizer::normalize_url( 'https://example.com/' . $i );
		}

		$recent = UrlNormalizer::normalize_url( 'https://example.com/10000' );

		$this->assertSame( 'https://example.com/10000', $recent );
	}
}
