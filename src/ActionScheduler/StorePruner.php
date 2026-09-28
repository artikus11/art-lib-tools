<?php

namespace Art\LibTools\ActionScheduler;

use Art\LibTools\Helpers\LogHelper;
use wpdb;

/**
 * Очистка таблиц Action Scheduler.
 *
 * Статусы AS: complete, failed, canceled, pending, in-progress.
 * Библиотека не запрещает pending: ограничение site-wide гигиены — на стороне хоста.
 * Куски SQL (SELECT LIMIT → DELETE IN) не ставят новых задач в очередь.
 *
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
 */
class StorePruner {

	private const SOURCE = 'art-lib-tools';

	public const DEFAULT_CHUNK = 50000;

	private const DELETE_IN_CHUNK = 1000;

	private wpdb $wpdb;


	public function __construct( wpdb $wpdb ) {

		$this->wpdb = $wpdb;
	}


	/**
	 * Удаляет один батч actions (и связанные claims/logs).
	 *
	 * @param array<int, string> $statuses Статусы AS: complete, failed, canceled, pending, in-progress.
	 * @param int                $batch_size
	 * @param string|null        $group             Slug группы. null — все группы.
	 * @param int|null           $older_than_seconds Фильтр last_attempt_gmt. null — без фильтра по дате.
	 *
	 * @return int Число удалённых actions в этом батче.
	 */
	public function prune_actions(
		array $statuses,
		int $batch_size,
		?string $group = null,
		?int $older_than_seconds = null
	): int {

		$statuses = $this->sanitize_statuses( $statuses );


		if ( [] === $statuses || $batch_size < 1 ) {
			return 0;
		}

		$need_groups = null !== $group;

		if ( ! $this->tables_exist( $need_groups ) ) {
			return 0;
		}

		$group_id = null;

		if ( $need_groups ) {
			$group_id = $this->resolve_group_id( $group );

			if ( null === $group_id ) {
				return 0;
			}
		}

		$action_ids = $this->select_action_ids( $statuses, $batch_size, $group_id, $older_than_seconds );

		if ( [] === $action_ids ) {
			return 0;
		}

		$deleted = $this->delete_actions_by_ids( $action_ids, $group, $statuses, $batch_size, $older_than_seconds );

		return $deleted;
	}


	/**
	 * Удаляет один батч логов: orphan (нет action) и/или старше порога.
	 *
	 * @return int Число удалённых строк логов в этом батче.
	 */
	public function prune_logs( int $batch_size, ?int $older_than_seconds = null ): int {

		if ( $batch_size < 1 ) {
			return 0;
		}

		if ( ! $this->tables_exist( false ) ) {

			return 0;
		}

		$log_ids = $this->select_log_ids( $batch_size, $older_than_seconds );

		if ( [] === $log_ids ) {
			return 0;
		}

		$deleted = $this->delete_by_ids( $this->table( 'actionscheduler_logs' ), 'log_id', $log_ids );

		if ( false === $deleted ) {
			return 0;
		}

		return $deleted;
	}


	/**
	 * Повторяет prune_actions, пока кусок не станет короче LIMIT. Без постановки задач AS.
	 *
	 * @param array<int, string> $statuses
	 */
	public function sweep_actions(
		array $statuses,
		int $chunk_size = self::DEFAULT_CHUNK,
		?string $group = null,
		?int $older_than_seconds = null
	): int {

		$chunk_size = max( 1, $chunk_size );
		$total      = 0;

		do {
			$deleted = $this->prune_actions( $statuses, $chunk_size, $group, $older_than_seconds );
			$total  += $deleted;
		} while ( $deleted === $chunk_size );

		return $total;
	}


	/**
	 * Повторяет prune_logs, пока кусок не станет короче LIMIT.
	 */
	public function sweep_logs( int $chunk_size = self::DEFAULT_CHUNK, ?int $older_than_seconds = null ): int {

		$chunk_size = max( 1, $chunk_size );
		$total      = 0;

		do {
			$deleted = $this->prune_logs( $chunk_size, $older_than_seconds );
			$total  += $deleted;
		} while ( $deleted === $chunk_size );

		return $total;
	}


	/**
	 * Удаляет конкретные action_id (claims + actions + logs). Для самоудаления после успеха.
	 *
	 * @param array<int, int> $action_ids
	 */
	public function forget_actions( array $action_ids ): int {

		$action_ids = $this->to_positive_ints( $action_ids );

		if ( [] === $action_ids ) {
			return 0;
		}

		if ( ! $this->tables_exist( false ) ) {
			return 0;
		}

		$total = 0;

		foreach ( array_chunk( $action_ids, self::DELETE_IN_CHUNK ) as $chunk ) {
			$total += $this->delete_actions_by_ids( $chunk, null, [], count( $chunk ), null );
		}

		return $total;
	}


	/**
	 * @param array<int, string> $statuses
	 *
	 * @return array<int, string>
	 */
	private function sanitize_statuses( array $statuses ): array {

		$clean = [];

		foreach ( $statuses as $status ) {
			if ( ! is_string( $status ) || '' === $status ) {
				continue;
			}

			$clean[] = $status;
		}

		return array_values( array_unique( $clean ) );
	}


	private function tables_exist( bool $need_groups ): bool {

		$tables = [
			'actionscheduler_actions',
			'actionscheduler_claims',
			'actionscheduler_logs',
		];

		if ( $need_groups ) {
			$tables[] = 'actionscheduler_groups';
		}

		foreach ( $tables as $suffix ) {
			if ( ! $this->table_exists( $this->table( $suffix ) ) ) {
				return false;
			}
		}

		return true;
	}


	private function table_exists( string $table ): bool {

		$like  = str_replace( [ '_', '%' ], [ '\\_', '\\%' ], $table );
		$found = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $like )
		);

		return $found === $table;
	}


	private function table( string $suffix ): string {

		return $this->wpdb->prefix . $suffix;
	}


	private function resolve_group_id( string $group ): ?int {

		$id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT group_id FROM ' . $this->table( 'actionscheduler_groups' ) . ' WHERE slug = %s',
				$group
			)
		);

		if ( null === $id || false === $id || '' === $id ) {
			return null;
		}

		return (int) $id;
	}


	/**
	 * @param array<int, string> $statuses
	 *
	 * @return array<int, int>
	 */
	private function select_action_ids(
		array $statuses,
		int $batch_size,
		?int $group_id,
		?int $older_than_seconds
	): array {

		$actions = $this->table( 'actionscheduler_actions' );
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		$sql    = "SELECT action_id FROM {$actions} WHERE status IN ({$placeholders})";
		$params = $statuses;

		if ( null !== $group_id ) {
			$sql     .= ' AND group_id = %d';
			$params[] = $group_id;
		}

		if ( null !== $older_than_seconds ) {
			$sql     .= ' AND last_attempt_gmt <= %s';
			$params[] = $this->cutoff_gmt( $older_than_seconds );
		}

		$sql     .= ' LIMIT %d';
		$params[] = $batch_size;

		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare( $sql, ...$params )
		);

		if ( ! is_array( $ids ) ) {
			return [];
		}

		return $this->to_positive_ints( $ids );
	}


	/**
	 * @return array<int, int>
	 */
	private function select_log_ids( int $batch_size, ?int $older_than_seconds ): array {

		$logs    = $this->table( 'actionscheduler_logs' );
		$actions = $this->table( 'actionscheduler_actions' );

		$sql = "SELECT l.log_id FROM {$logs} l
			LEFT JOIN {$actions} a ON a.action_id = l.action_id
			WHERE a.action_id IS NULL";
		$params = [];

		if ( null !== $older_than_seconds ) {
			$sql     = "SELECT l.log_id FROM {$logs} l
				LEFT JOIN {$actions} a ON a.action_id = l.action_id
				WHERE a.action_id IS NULL OR l.log_date_gmt <= %s";
			$params[] = $this->cutoff_gmt( $older_than_seconds );
		}

		$sql     .= ' LIMIT %d';
		$params[] = $batch_size;

		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare( $sql, ...$params )
		);

		if ( ! is_array( $ids ) ) {
			return [];
		}

		return $this->to_positive_ints( $ids );
	}


	/**
	 * @param array<int, int>    $action_ids
	 * @param string|null        $group
	 * @param array<int, string> $statuses
	 * @param int                $batch_size
	 * @param int|null           $older_than_seconds
	 */
	private function delete_actions_by_ids(
		array $action_ids,
		?string $group,
		array $statuses,
		int $batch_size,
		?int $older_than_seconds
	): int {

		$actions = $this->table( 'actionscheduler_actions' );
		$claims  = $this->table( 'actionscheduler_claims' );
		$logs    = $this->table( 'actionscheduler_logs' );

		$in     = implode( ', ', array_fill( 0, count( $action_ids ), '%d' ) );
		$params = $action_ids;

		$claim_ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT DISTINCT claim_id FROM {$actions} WHERE action_id IN ({$in}) AND claim_id > 0",
				...$params
			)
		);

		if ( is_array( $claim_ids ) ) {
			$claim_ids = $this->to_positive_ints( $claim_ids );

			if ( [] !== $claim_ids ) {
				$deleted_claims = $this->delete_by_ids( $claims, 'claim_id', $claim_ids );

				if ( false === $deleted_claims ) {
					return 0;
				}
			}
		}

		$deleted = $this->delete_by_ids( $actions, 'action_id', $action_ids );

		if ( false === $deleted ) {
			return 0;
		}

		$deleted_logs = $this->delete_by_ids( $logs, 'action_id', $action_ids );

		if ( false === $deleted_logs ) {
			$this->log(
				'error',
				[
					'message'            => '[StorePruner.prune_actions] logs delete failed',
					'group'              => $group,
					'statuses'           => $statuses,
					'batch_size'         => $batch_size,
					'older_than_seconds' => $older_than_seconds,
					'deleted'            => $deleted,
					'wpdb_error'         => $this->wpdb->last_error,
				]
			);
		}

		return $deleted;
	}


	/**
	 * @param string          $table
	 * @param string          $column
	 * @param array<int, int> $ids
	 *
	 * @return int|false
	 */
	private function delete_by_ids( string $table, string $column, array $ids ) {

		if ( [] === $ids ) {
			return 0;
		}

		$in = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$table} WHERE {$column} IN ({$in})",
				...$ids
			)
		);

		if ( false === $result || '' !== (string) $this->wpdb->last_error ) {
			$this->log(
				'error',
				[
					'message'            => '[StorePruner] DELETE failed',
					'group'              => null,
					'statuses'           => [],
					'batch_size'         => count( $ids ),
					'older_than_seconds' => null,
					'deleted'            => 0,
					'wpdb_error'         => $this->wpdb->last_error,
				]
			);

			return false;
		}

		return (int) $result;
	}


	/**
	 * @param array<int, mixed> $ids
	 *
	 * @return array<int, int>
	 */
	private function to_positive_ints( array $ids ): array {

		$clean = [];

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}

		return $clean;
	}


	private function cutoff_gmt( int $older_than_seconds ): string {

		$seconds = max( 0, $older_than_seconds );

		return gmdate( 'Y-m-d H:i:s', time() - $seconds );
	}


	/**
	 * @param string               $level
	 * @param array<string, mixed> $payload
	 */
	private function log( string $level, array $payload ): void {

		LogHelper::log( $payload, $level, self::SOURCE );
	}
}
