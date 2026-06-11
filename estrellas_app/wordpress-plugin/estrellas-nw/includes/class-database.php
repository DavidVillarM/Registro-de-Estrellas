<?php
/**
 * Tablas MySQL (Hostinger / WordPress) equivalentes al esquema original.
 */
defined('ABSPATH') || exit;

class Estrellas_NW_Database {

	public static function table(string $suffix): string {
		global $wpdb;
		return $wpdb->prefix . 'estrellas_' . $suffix;
	}

	public static function install(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$collate = ( strpos( $charset, 'utf8mb4' ) !== false ) ? $charset : 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$companies  = self::table('companies');
		$star_types = self::table('star_types');
		$challenges = self::table('challenges');
		$employees  = self::table('employees');
		$awards     = self::table('star_awards');
		$stickers   = self::table('star_stickers');

		// Orden: primero tablas sin FK externas a estrellas.
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$companies} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(191) NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY uq_name (name)
			) {$collate};"
		);

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$star_types} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				code VARCHAR(32) NOT NULL,
				name VARCHAR(191) NOT NULL,
				sticker_prefix VARCHAR(8) NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY uq_code (code)
			) {$collate};"
		);

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$challenges} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(191) NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY uq_name (name)
			) {$collate};"
		);

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$employees} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				full_name VARCHAR(255) NOT NULL,
				company_id BIGINT UNSIGNED NOT NULL,
				is_active TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY uq_company_name (company_id, full_name(191)),
				KEY idx_company (company_id),
				CONSTRAINT fk_estrellas_emp_company FOREIGN KEY (company_id) REFERENCES {$companies} (id)
			) {$collate};"
		);

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$awards} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				employee_id BIGINT UNSIGNED NOT NULL,
				star_type_id BIGINT UNSIGNED NOT NULL,
				award_date DATE NOT NULL,
				challenge_id BIGINT UNSIGNED NULL,
				note TEXT NULL,
				unique_code VARCHAR(64) NOT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_emp_date (employee_id, award_date),
				KEY idx_date (award_date),
				KEY idx_type (star_type_id),
				CONSTRAINT fk_estrellas_award_emp FOREIGN KEY (employee_id) REFERENCES {$employees} (id) ON DELETE RESTRICT,
				CONSTRAINT fk_estrellas_award_star FOREIGN KEY (star_type_id) REFERENCES {$star_types} (id),
				CONSTRAINT fk_estrellas_award_ch FOREIGN KEY (challenge_id) REFERENCES {$challenges} (id) ON DELETE SET NULL
			) {$collate};"
		);

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$stickers} (
				code VARCHAR(64) NOT NULL,
				star_type_id BIGINT UNSIGNED NOT NULL,
				num INT UNSIGNED NOT NULL,
				is_used TINYINT(1) NOT NULL DEFAULT 0,
				used_at DATETIME NULL,
				used_award_id BIGINT UNSIGNED NULL,
				PRIMARY KEY (code),
				KEY idx_type_num (star_type_id, num),
				KEY idx_used (star_type_id, is_used),
				CONSTRAINT fk_estrellas_st_type FOREIGN KEY (star_type_id) REFERENCES {$star_types} (id),
				CONSTRAINT fk_estrellas_st_award FOREIGN KEY (used_award_id) REFERENCES {$awards} (id) ON DELETE SET NULL
			) {$collate};"
		);

		self::seed();
	}

	private static function seed(): void {
		global $wpdb;
		$c = self::table('companies');
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$c} (name) VALUES (%s), (%s)",
				'Newton Centro de Estudios',
				'Crextar S.A.'
			)
		);

		$st = self::table('star_types');
		$types = array(
			array( 'FUNNY', 'Funny', 'F' ),
			array( 'TEACHE', 'Teache', 'T' ),
			array( 'EARLY', 'Early', 'E' ),
			array( 'BUDDY', 'Buddy', 'B' ),
			array( 'SMARTY', 'Smarty', 'S' ),
			array( 'BIRTHDAY', 'Birthday', 'D' ),
		);
		foreach ( $types as $row ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$st} (code, name, sticker_prefix) VALUES (%s, %s, %s)",
					$row[0],
					$row[1],
					$row[2]
				)
			);
		}

		$ch = self::table('challenges');
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$ch} (name) VALUES (%s)", 'Misión 00 - Fin de año' ) );

		self::maybe_seed_stickers();
	}

	/**
	 * Genera códigos F0001…F9999 por tipo (misma lógica que el backend .NET).
	 */
	public static function maybe_seed_stickers(): void {
		global $wpdb;
		$t_stickers = self::table('star_stickers');
		$t_types    = self::table('star_types');

		$types = $wpdb->get_results( "SELECT id, sticker_prefix FROM {$t_types}", ARRAY_A );
		if ( ! $types ) {
			return;
		}

		foreach ( $types as $st ) {
			$tid = (int) $st['id'];
			$cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t_stickers} WHERE star_type_id = %d", $tid ) );
			if ( $cnt > 0 ) {
				continue;
			}

			$prefix = $st['sticker_prefix'];
			$batch  = array();
			for ( $n = 1; $n <= 9999; $n++ ) {
				$code    = $prefix . str_pad( (string) $n, 4, '0', STR_PAD_LEFT );
				$batch[] = $wpdb->prepare( '(%s, %d, %d, 0)', $code, $tid, $n );
				if ( count( $batch ) >= 400 || $n === 9999 ) {
					$sql = "INSERT INTO {$t_stickers} (code, star_type_id, num, is_used) VALUES " . implode( ',', $batch );
					$wpdb->query( $sql );
					$batch = array();
				}
			}
		}
	}
}
