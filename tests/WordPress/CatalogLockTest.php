<?php

namespace Art\LibTools\Tests\WordPress;

use Art\LibTools\Tests\TestCase;
use Art\LibTools\WordPress\CatalogLock;
use Mockery;
use wpdb;
use WP_Mock;

class CatalogLockTest extends TestCase {

	private wpdb $db;

	private CatalogLock $lock;


	public function setUp(): void {

		parent::setUp();

		$this->db   = new wpdb();
		$this->lock = new CatalogLock( $this->db, 'skl_catalog_lock' );

		// LogHelper::log может ходить в wc_get_logger(); настраиваем терпимый логгер.
		$logger = Mockery::mock();
		$logger->shouldReceive( 'log' )->zeroOrMoreTimes();

		WP_Mock::userFunction( 'wc_get_logger', [ 'return' => $logger ] );
	}


	public function test_acquire_free_lock_returns_null(): void {

		// ensure_schema (CREATE TABLE), INSERT IGNORE, условный UPDATE = 1 (победа).
		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 1;

		$result = $this->lock->acquire( 'skl-title-uniq', 'scan_42', 3600 );

		$this->assertNull( $result );

		$insert = $this->find_prepare( 'INSERT IGNORE' );
		$update = $this->find_prepare( 'UPDATE' );

		$this->assertNotNull( $insert );
		$this->assertNotNull( $update );
		$this->assertStringContainsString( 'INSERT IGNORE', $insert['query'] );
		$this->assertStringContainsString( 'holder=%s', $update['query'] );
	}


	public function test_acquire_busy_by_foreign_holder_returns_status(): void {

		// ensure_schema, INSERT IGNORE, UPDATE = 0 (проигрыш) -> status() читает строку.
		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 0;

		$this->db->get_row_queue[] = [
			'holder'       => 'skl-dedup-scan',
			'note'         => 'scan_7',
			'started_at'   => 1000,
			'locked_until' => PHP_INT_MAX,
		];

		$result = $this->lock->acquire( 'skl-title-uniq', 'scan_42' );

		$this->assertIsArray( $result );
		$this->assertSame( 'skl-dedup-scan', $result['holder'] );
		$this->assertSame( 'scan_7', $result['note'] );
	}


	public function test_acquire_same_holder_is_idempotent(): void {

		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 1;

		$result = $this->lock->acquire( 'skl-title-uniq', 'scan_42' );

		$this->assertNull( $result );

		$update = $this->find_prepare( 'UPDATE' );

		$this->assertNotNull( $update );
		$this->assertStringContainsString( 'OR holder=%s', $update['query'] );
	}


	public function test_acquire_steals_expired_foreign_lock(): void {

		// UPDATE = 1: строка подходит по locked_until < now (краш-безопасность).
		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 1;

		$result = $this->lock->acquire( 'skl-title-uniq', 'scan_42' );

		$this->assertNull( $result );

		$update = $this->find_prepare( 'UPDATE' );

		$this->assertNotNull( $update );
		$this->assertStringContainsString( 'locked_until < %d', $update['query'] );
	}


	public function test_touch_foreign_holder_does_not_extend(): void {

		$this->db->query_queue[] = 0;

		$this->assertFalse( $this->lock->touch( 'skl-dedup-scan' ) );

		$update = $this->find_prepare( 'UPDATE' );

		$this->assertNotNull( $update );
		$this->assertStringContainsString( 'holder=%s', $update['query'] );
		$this->assertContains( 'skl-dedup-scan', $update['args'] );
	}


	public function test_touch_own_holder_extends(): void {

		$this->db->query_queue[] = 1;

		$this->assertTrue( $this->lock->touch( 'skl-title-uniq' ) );
	}


	public function test_release_only_clears_own_holder(): void {

		$this->db->query_queue[] = 1;

		$this->lock->release( 'skl-title-uniq' );

		$update = $this->find_prepare( 'UPDATE' );

		$this->assertNotNull( $update );
		$this->assertStringContainsString( "holder=''", $update['query'] );
		$this->assertStringContainsString( 'holder=%s', $update['query'] );
		$this->assertContains( 'skl-title-uniq', $update['args'] );
	}


	public function test_status_empty_returns_null(): void {

		$this->db->get_row_queue[] = null;

		$this->assertNull( $this->lock->status() );
	}


	public function test_status_occupied_returns_array(): void {

		$this->db->get_row_queue[] = [
			'holder'       => 'skl-title-uniq',
			'note'         => 'scan_42',
			'started_at'   => 1000,
			'locked_until' => 5000,
		];

		$status = $this->lock->status();

		$this->assertIsArray( $status );
		$this->assertSame( 'skl-title-uniq', $status['holder'] );
		$this->assertSame( 5000, $status['locked_until'] );
		$this->assertArrayHasKey( 'expires_in', $status );
	}


	public function test_ensure_schema_runs_once_and_does_not_fail_again(): void {

		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 1;

		$this->lock->acquire( 'skl-title-uniq' );

		$this->db->query_queue[] = 0;
		$this->db->query_queue[] = 1;

		$this->lock->acquire( 'skl-title-uniq' );

		$create = 0;

		foreach ( $this->db->query_calls as $call ) {
			if ( is_string( $call ) && false !== strpos( $call, 'CREATE TABLE IF NOT EXISTS' ) ) {
				$create++;
			}
		}

		$this->assertSame( 1, $create );
	}


	/**
	 * @return array{query: string, args: array<int, mixed>}|null
	 */
	private function find_prepare( string $needle ): ?array {

		foreach ( $this->db->prepare_calls as $call ) {
			if ( false !== strpos( $call['query'], $needle ) ) {
				return $call;
			}
		}

		return null;
	}
}