<?php

namespace Art\LibTools\WordPress;

use Art\LibTools\Helpers\LogHelper;
use wpdb;

/**
 * Каталожный мьютекс: одна строка (id=1) в общей таблице wp_skl_catalog_lock.
 *
 * Защищает фоновые полные проходы записи каталога (wp_posts/wp_postmeta) от
 * параллельной работы: skl-title-uniq (apply) и skl-dedup-scan (scan + purge).
 * Лок advisory — защищает только тех, кто вызывает acquire.
 *
 * Лок держится активной работой (touch на каждый батч) и сам освобождается
 * в паузах: истёкший чужой лок «воруется» следующим acquire (краш-безопасность).
 *
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
 */
class CatalogLock {

	private const SOURCE = 'art-lib-tools';

	private wpdb $wpdb;

	private string $table;

	private bool $schema_ensured = false;


	/**
	 * @param  wpdb|null $db           Инстанс $wpdb (по умолчанию глобальный).
	 * @param  string    $table_suffix Суффикс таблицы; переопределяется в тестах.
	 */
	public function __construct( ?wpdb $db = null, string $table_suffix = 'skl_catalog_lock' ) {

		global $wpdb;

		$this->wpdb  = $db ?: $wpdb;
		$this->table = $this->wpdb->prefix . $table_suffix;
	}


	/**
	 * Атомарный захват лока.
	 *
	 * INSERT IGNORE строки id=1, затем условный UPDATE: свободен / наш / истёк.
	 * affected === 1 — победа.
	 *
	 * @param  string $holder Слаг плагина (skl-title-uniq / skl-dedup-scan).
	 * @param  string $note   scan_id / job_id / человекочитаемый текст.
	 * @param  int    $ttl    Срок жизни лока, сек.
	 *
	 * @return array|null null при успехе, иначе status() (кто держит лок).
	 */
	public function acquire( string $holder, string $note = '', int $ttl = 3600 ): ?array {

		$this->ensure_schema();

		$now = time();

		$this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT IGNORE INTO {$this->table} (id, holder, note, started_at, locked_until) VALUES (1, '', '', 0, 0)"
			)
		);

		$affected = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table}
				 SET holder=%s, note=%s, started_at=%d, locked_until=%d
				 WHERE id=1 AND (holder='' OR holder=%s OR locked_until < %d)",
				$holder,
				$note,
				$now,
				$now + $ttl,
				$holder,
				$now
			)
		);

		if ( 1 === (int) $affected ) {
			LogHelper::log(
				[
					'message'      => 'CatalogLock.acquire: lock acquired',
					'holder'       => $holder,
					'note'         => $note,
					'locked_until' => $now + $ttl,
				],
				'info',
				self::SOURCE
			);

			return null;
		}

		$status = $this->status();

		LogHelper::log(
			[
				'message' => 'CatalogLock.acquire: busy',
				'holder'  => $holder,
				'status'  => $status,
			],
			'warn',
			self::SOURCE
		);

		return $status;
	}


	/**
	 * Продлевает locked_until, только если лок держит этот holder.
	 *
	 * @return bool true если лок продлён (был наш), false иначе.
	 */
	public function touch( string $holder, int $ttl = 3600 ): bool {

		$affected = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table} SET locked_until=%d WHERE id=1 AND holder=%s",
				time() + $ttl,
				$holder
			)
		);

		$ok = 1 === (int) $affected;

		LogHelper::log(
			[
				'message' => 'CatalogLock.touch',
				'holder'  => $holder,
				'ok'      => $ok,
			],
			'debug',
			self::SOURCE
		);

		return $ok;
	}


	/**
	 * Снимает лок только у своего holder. Идемпотентен.
	 */
	public function release( string $holder ): void {

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table}
				 SET holder='', note='', started_at=0, locked_until=0
				 WHERE id=1 AND holder=%s",
				$holder
			)
		);

		LogHelper::log(
			[
				'message' => 'CatalogLock.release',
				'holder'  => $holder,
			],
			'debug',
			self::SOURCE
		);
	}


	/**
	 * Текущее состояние лока.
	 *
	 * @return array{holder: string, note: string, started_at: int, locked_until: int, expires_in: int}|null
	 */
	public function status(): ?array {

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT holder, note, started_at, locked_until FROM {$this->table} WHERE id=1"
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) || '' === (string) ( $row['holder'] ?? '' ) ) {
			return null;
		}

		$locked_until = (int) ( $row['locked_until'] ?? 0 );

		return [
			'holder'       => (string) ( $row['holder'] ?? '' ),
			'note'         => (string) ( $row['note'] ?? '' ),
			'started_at'   => (int) ( $row['started_at'] ?? 0 ),
			'locked_until' => $locked_until,
			'expires_in'   => $locked_until > 0 ? max( 0, $locked_until - time() ) : 0,
		];
	}


	/**
	 * CREATE TABLE IF NOT EXISTS. Идемпотентен, вызывается из acquire.
	 */
	public function ensure_schema(): void {

		if ( $this->schema_ensured ) {
			return;
		}

		$charset = $this->wpdb->get_charset_collate();

		$this->wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$this->table} (
				id TINYINT UNSIGNED NOT NULL,
				holder VARCHAR(32) NOT NULL DEFAULT '',
				note VARCHAR(64) NOT NULL DEFAULT '',
				started_at INT UNSIGNED NOT NULL DEFAULT 0,
				locked_until INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (id)
			) {$charset}"
		);

		$this->schema_ensured = true;
	}
}
