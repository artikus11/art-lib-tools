<?php

namespace Art\LibTools\Tests\ActionScheduler;

use Art\LibTools\ActionScheduler\StorePruner;
use Art\LibTools\Tests\TestCase;
use wpdb;

class StorePrunerTest extends TestCase {

	private wpdb $db;


	public function setUp(): void {

		parent::setUp();

		$this->db = new wpdb();
	}


	public function test_empty_statuses_returns_zero_without_queries(): void {

		$pruner = new StorePruner( $this->db );

		$this->assertSame( 0, $pruner->prune_actions( [], 10 ) );
		$this->assertSame( [], $this->db->prepare_calls );
	}


	public function test_unknown_group_returns_zero(): void {

		$this->queue_tables_exist( true );
		$this->db->get_var_queue[] = null;

		$pruner = new StorePruner( $this->db );

		$this->assertSame( 0, $pruner->prune_actions( [ 'complete' ], 10, 'missing-group' ) );
	}


	public function test_batch_limit_is_passed_to_sql(): void {

		$this->queue_tables_exist( false );
		$this->db->get_col_queue[] = [ 1, 2 ];
		$this->db->get_col_queue[] = [];
		$this->db->query_queue[]   = 2;
		$this->db->query_queue[]   = 2;

		$pruner = new StorePruner( $this->db );
		$pruner->prune_actions( [ 'complete' ], 10 );

		$select = $this->find_prepare( 'SELECT action_id' );

		$this->assertNotNull( $select );
		$this->assertStringContainsString( 'LIMIT %d', $select['query'] );
		$this->assertContains( 10, $select['args'] );
	}


	public function test_group_null_does_not_add_group_id(): void {

		$this->queue_tables_exist( false );
		$this->db->get_col_queue[] = [];

		$pruner = new StorePruner( $this->db );
		$pruner->prune_actions( [ 'complete' ], 10, null );

		$select = $this->find_prepare( 'SELECT action_id' );

		$this->assertNotNull( $select );
		$this->assertStringNotContainsString( 'group_id', $select['query'] );
	}


	public function test_older_than_adds_scheduled_date(): void {

		$this->queue_tables_exist( false );
		$this->db->get_col_queue[] = [];

		$pruner = new StorePruner( $this->db );
		$pruner->prune_actions( [ 'complete' ], 5, null, 3600 );

		$select = $this->find_prepare( 'SELECT action_id' );

		$this->assertNotNull( $select );
		$this->assertStringContainsString( 'scheduled_date_gmt', $select['query'] );
		$this->assertMatchesRegularExpression( '/\d{4}-\d{2}-\d{2} /', (string) $select['args'][1] );
	}


	public function test_forget_actions_skips_empty(): void {

		$pruner = new StorePruner( $this->db );

		$this->assertSame( 0, $pruner->forget_actions( [ 0, -1 ] ) );
		$this->assertSame( [], $this->db->prepare_calls );
	}


	public function test_forget_actions_deletes_by_ids(): void {

		$this->queue_tables_exist( false );
		$this->db->get_col_queue[] = [];
		$this->db->query_queue[]   = 2;
		$this->db->query_queue[]   = 2;

		$pruner = new StorePruner( $this->db );

		$this->assertSame( 2, $pruner->forget_actions( [ 4, 8 ] ) );

		$delete = $this->find_prepare( 'DELETE FROM' );

		$this->assertNotNull( $delete );
		$this->assertContains( 4, $delete['args'] );
		$this->assertContains( 8, $delete['args'] );
	}


	public function test_last_error_returns_zero(): void {

		$this->queue_tables_exist( false );
		$this->db->get_col_queue[] = [ 11 ];
		$this->db->get_col_queue[] = [];
		$this->db->query_queue[]   = false;
		$this->db->last_error      = 'deadlock';

		$pruner = new StorePruner( $this->db );

		$this->assertSame( 0, $pruner->prune_actions( [ 'failed' ], 50 ) );
	}


	private function queue_tables_exist( bool $with_groups ): void {

		$this->db->get_var_queue[] = 'wp_actionscheduler_actions';
		$this->db->get_var_queue[] = 'wp_actionscheduler_claims';
		$this->db->get_var_queue[] = 'wp_actionscheduler_logs';

		if ( $with_groups ) {
			$this->db->get_var_queue[] = 'wp_actionscheduler_groups';
		}
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
