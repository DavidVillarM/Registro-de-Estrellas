<?php
/**
 * REST API compatible con el cliente React (mismas rutas que la API .NET).
 */
defined('ABSPATH') || exit;

class Estrellas_NW_Rest_Api {

	private static function t(string $suffix): string {
		return Estrellas_NW_Database::table( $suffix );
	}

	public static function register(): void {
		$ns = 'estrellas-nw/v1';

		register_rest_route(
			$ns,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return rest_ensure_response( array( 'ok' => true, 'at' => gmdate( 'c' ) ) );
				},
				'permission_callback' => '__return_true',
			)
		);

		$auth = array( __CLASS__, 'auth' );

		register_rest_route( $ns, '/api/companies', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'companies' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/star-types', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'star_types' ), 'permission_callback' => $auth ) );

		register_rest_route( $ns, '/api/challenges', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'challenges_get' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/challenges', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'challenges_post' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/challenges/(?P<id>\d+)', array( 'methods' => 'PUT', 'callback' => array( __CLASS__, 'challenges_put' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/challenges/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'challenges_delete' ), 'permission_callback' => $auth ) );

		register_rest_route( $ns, '/api/employees', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'employees_get' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/employees', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'employees_post' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/employees/(?P<id>\d+)', array( 'methods' => 'PUT', 'callback' => array( __CLASS__, 'employees_put' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/employees/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'employees_delete' ), 'permission_callback' => $auth ) );

		register_rest_route( $ns, '/api/star-awards', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'star_awards_get' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/star-awards', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'star_awards_post' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/star-awards/(?P<id>\d+)/edit', array( 'methods' => 'PUT', 'callback' => array( __CLASS__, 'star_awards_edit' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/star-awards/(?P<id>\d+)/code', array( 'methods' => 'PUT', 'callback' => array( __CLASS__, 'star_awards_code' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/star-awards/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'star_awards_delete' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/star-awards/(?P<id>\d+)/delete', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'star_awards_delete' ), 'permission_callback' => $auth ) );

		register_rest_route( $ns, '/api/star-codes', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'star_codes' ), 'permission_callback' => $auth ) );

		register_rest_route( $ns, '/api/stats/employee/(?P<employeeId>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_employee' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/stats/star-type/(?P<starCode>[a-zA-Z0-9_-]+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_star_type' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/stats/ranking', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_ranking' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/public/stats/ranking', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats_ranking_public' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/api/backup/export', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'backup_export' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/api/backup/import', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'backup_import' ), 'permission_callback' => $auth ) );
	}

	public static function auth(): bool {
		return is_user_logged_in();
	}

	private static function json_body(): array {
		$raw = file_get_contents( 'php://input' );
		if ( ! $raw ) {
			return array();
		}
		$d = json_decode( $raw, true );
		return is_array( $d ) ? $d : array();
	}

	/**
	 * Mantiene compatibilidad con códigos históricos del sistema anterior.
	 */
	private static function normalize_star_code( string $star_code ): string {
		$code = strtoupper( trim( $star_code ) );
		$aliases = array(
			'F'        => 'FUNNY',
			'T'        => 'TEACHE',
			'E'        => 'EARLY',
			'B'        => 'BUDDY',
			'S'        => 'SMARTY',
			'FC'       => 'BIRTHDAY',
			'BDAY'     => 'BIRTHDAY',
			'CUMPLE'   => 'BIRTHDAY',
			'BIRTHDAY' => 'BIRTHDAY',
		);
		return $aliases[ $code ] ?? $code;
	}

	public static function companies(): WP_REST_Response {
		global $wpdb;
		$t = self::t( 'companies' );
		$rows = $wpdb->get_results( "SELECT id, name FROM {$t} ORDER BY name", ARRAY_A );
		return rest_ensure_response( $rows ?: array() );
	}

	public static function star_types(): WP_REST_Response {
		global $wpdb;
		$t = self::t( 'star_types' );
		$rows = $wpdb->get_results( "SELECT id, code, name FROM {$t} ORDER BY id", ARRAY_A );
		return rest_ensure_response( $rows ?: array() );
	}

	public static function challenges_get(): WP_REST_Response {
		global $wpdb;
		$t = self::t( 'challenges' );
		$rows = $wpdb->get_results( "SELECT id, name FROM {$t} ORDER BY name", ARRAY_A );
		return rest_ensure_response( $rows ?: array() );
	}

	public static function challenges_post( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$d    = self::json_body();
		$name = isset( $d['name'] ) ? trim( (string) $d['name'] ) : '';
		if ( $name === '' ) {
			return self::bad( 'Name required' );
		}
		$t = self::t( 'challenges' );
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$t} (name) VALUES (%s) ON DUPLICATE KEY UPDATE name = VALUES(name)", $name ) );
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE name = %s", $name ) );
		return rest_ensure_response( array( 'id' => $id ) );
	}

	public static function challenges_put( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$id   = (int) $req['id'];
		$d    = self::json_body();
		$name = isset( $d['name'] ) ? trim( (string) $d['name'] ) : '';
		if ( $name === '' ) {
			return self::bad( 'Name required' );
		}
		$t = self::t( 'challenges' );
		$n = $wpdb->update( $t, array( 'name' => $name ), array( 'id' => $id ) );
		if ( ! $n ) {
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE id=%d", $id ) );
			if ( ! $exists ) {
				return new WP_REST_Response( null, 404 );
			}
		}
		return new WP_REST_Response( null, 204 );
	}

	public static function challenges_delete( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$id = (int) $req['id'];
		$t  = self::t( 'challenges' );
		if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE id=%d", $id ) ) ) {
			return new WP_REST_Response( null, 404 );
		}
		$wpdb->suppress_errors();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE id=%d", $id ) );
		$err = $wpdb->last_error;
		$wpdb->suppress_errors( false );
		if ( $err ) {
			return self::bad( 'No se puede eliminar: el desafío está en uso por estrellas.' );
		}
		return new WP_REST_Response( null, 204 );
	}

	public static function employees_get( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$query      = $req->get_param( 'query' );
		$company_id = $req->get_param( 'companyId' );
		$active     = $req->get_param( 'activeOnly' );

		$e = self::t( 'employees' );
		$c = self::t( 'companies' );

		$sql = "SELECT e.id, e.full_name AS fullName, e.company_id AS companyId, c.name AS companyName,
			e.is_active AS isActive, e.created_at AS createdAt
			FROM {$e} e JOIN {$c} c ON c.id = e.company_id WHERE 1=1";
		$params = array();

		if ( $company_id !== null && $company_id !== '' ) {
			$sql     .= ' AND e.company_id = %d';
			$params[] = (int) $company_id;
		}
		if ( $active === 'true' || $active === true || $active === '1' ) {
			$sql .= ' AND e.is_active = 1';
		}
		if ( $query !== null && trim( (string) $query ) !== '' ) {
			$sql     .= ' AND e.full_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( trim( (string) $query ) ) . '%';
		}
		$sql .= ' ORDER BY e.full_name';

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows ?: array() as &$row ) {
			$row['isActive'] = (bool) (int) $row['isActive'];
			if ( ! empty( $row['createdAt'] ) ) {
				$row['createdAt'] = gmdate( 'c', strtotime( $row['createdAt'] . ' UTC' ) );
			}
		}
		return rest_ensure_response( $rows ?: array() );
	}

	public static function employees_post( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$d = self::json_body();
		$fn = isset( $d['fullName'] ) ? trim( (string) $d['fullName'] ) : '';
		$cid = isset( $d['companyId'] ) ? (int) $d['companyId'] : 0;
		if ( $fn === '' ) {
			return self::bad( 'FullName required' );
		}
		if ( $cid <= 0 ) {
			return self::bad( 'CompanyId required' );
		}
		$active = array_key_exists( 'isActive', $d ) ? (bool) $d['isActive'] : true;

		$e = self::t( 'employees' );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$e} (full_name, company_id, is_active) VALUES (%s, %d, %d)
				ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)",
				$fn,
				$cid,
				$active ? 1 : 0
			)
		);
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$e} WHERE company_id=%d AND full_name=%s",
				$cid,
				$fn
			)
		);
		return rest_ensure_response( array( 'id' => $id ) );
	}

	public static function employees_put( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$id = (int) $req['id'];
		$d  = self::json_body();
		$e  = self::t( 'employees' );

		$row = array();
		if ( isset( $d['fullName'] ) && trim( (string) $d['fullName'] ) !== '' ) {
			$row['full_name'] = trim( (string) $d['fullName'] );
		}
		if ( isset( $d['companyId'] ) ) {
			$row['company_id'] = (int) $d['companyId'];
		}
		if ( array_key_exists( 'isActive', $d ) ) {
			$row['is_active'] = $d['isActive'] ? 1 : 0;
		}
		if ( ! $row ) {
			return new WP_REST_Response( null, 204 );
		}

		$n = $wpdb->update( $e, $row, array( 'id' => $id ) );
		if ( ! $n && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$e} WHERE id=%d", $id ) ) ) {
			return new WP_REST_Response( null, 404 );
		}
		return new WP_REST_Response( null, 204 );
	}

	public static function employees_delete( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$id = (int) $req['id'];
		$e  = self::t( 'employees' );
		if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$e} WHERE id=%d", $id ) ) ) {
			return new WP_REST_Response( null, 404 );
		}
		$wpdb->suppress_errors();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$e} WHERE id=%d", $id ) );
		$err = $wpdb->last_error;
		$wpdb->suppress_errors( false );
		if ( $err ) {
			return self::bad( 'No se puede eliminar: el funcionario tiene estrellas registradas.' );
		}
		return new WP_REST_Response( null, 204 );
	}

	public static function star_awards_get( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$employee_id = $req->get_param( 'employeeId' );
		$company_id  = $req->get_param( 'companyId' );
		$star_code   = $req->get_param( 'starCode' );
		$unique_code = $req->get_param( 'uniqueCode' );
		$from        = $req->get_param( 'from' );
		$to          = $req->get_param( 'to' );

		$sa = self::t( 'star_awards' );
		$e  = self::t( 'employees' );
		$c  = self::t( 'companies' );
		$st = self::t( 'star_types' );
		$ch = self::t( 'challenges' );

		$sql = "SELECT sa.id, sa.employee_id AS employeeId, e.full_name AS fullName, e.company_id AS companyId,
			c.name AS companyName, st.code AS starCode, sa.award_date AS awardDate,
			ch.name AS challengeName, sa.note, sa.unique_code AS uniqueCode, sa.created_at AS createdAt
			FROM {$sa} sa
			JOIN {$e} e ON e.id = sa.employee_id
			JOIN {$c} c ON c.id = e.company_id
			JOIN {$st} st ON st.id = sa.star_type_id
			LEFT JOIN {$ch} ch ON ch.id = sa.challenge_id
			WHERE 1=1";
		$params = array();

		if ( $employee_id !== null && $employee_id !== '' ) {
			$sql     .= ' AND sa.employee_id = %d';
			$params[] = (int) $employee_id;
		}
		if ( $company_id !== null && $company_id !== '' ) {
			$sql     .= ' AND e.company_id = %d';
			$params[] = (int) $company_id;
		}
		if ( $star_code !== null && trim( (string) $star_code ) !== '' ) {
			$sql     .= ' AND st.code = %s';
			$params[] = self::normalize_star_code( (string) $star_code );
		}
		if ( $unique_code !== null && trim( (string) $unique_code ) !== '' ) {
			$sql     .= ' AND sa.unique_code LIKE %s';
			$params[] = $wpdb->esc_like( trim( (string) $unique_code ) ) . '%';
		}
		if ( $from !== null && $from !== '' ) {
			$sql     .= ' AND sa.award_date >= %s';
			$params[] = (string) $from;
		}
		if ( $to !== null && $to !== '' ) {
			$sql     .= ' AND sa.award_date <= %s';
			$params[] = (string) $to;
		}
		$sql .= ' ORDER BY sa.award_date DESC, sa.id DESC LIMIT 500';

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows ?: array() as &$row ) {
			if ( ! empty( $row['awardDate'] ) ) {
				$row['awardDate'] = gmdate( 'c', strtotime( $row['awardDate'] . 'T00:00:00 UTC' ) );
			}
			if ( ! empty( $row['createdAt'] ) ) {
				$row['createdAt'] = gmdate( 'c', strtotime( $row['createdAt'] . ' UTC' ) );
			}
		}
		return rest_ensure_response( $rows ?: array() );
	}

	public static function star_awards_post( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$d = self::json_body();

		$employee_id = isset( $d['employeeId'] ) ? (int) $d['employeeId'] : 0;
		$star_code   = isset( $d['starCode'] ) ? self::normalize_star_code( (string) $d['starCode'] ) : '';
		$award_raw   = isset( $d['awardDate'] ) ? $d['awardDate'] : null;
		$note        = isset( $d['note'] ) ? trim( (string) $d['note'] ) : '';
		$manual_code = isset( $d['uniqueCode'] ) ? strtoupper( trim( (string) $d['uniqueCode'] ) ) : '';

		if ( $employee_id <= 0 ) {
			return self::bad( 'EmployeeId required' );
		}
		if ( $star_code === '' ) {
			return self::bad( 'StarCode required' );
		}
		if ( $award_raw === null || $award_raw === '' ) {
			return self::bad( 'AwardDate required' );
		}
		$award_date = is_string( $award_raw ) ? substr( $award_raw, 0, 10 ) : gmdate( 'Y-m-d', (int) $award_raw );

		$st_t = self::t( 'star_types' );
		$star_type = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, sticker_prefix FROM {$st_t} WHERE code=%s", $star_code ),
			ARRAY_A
		);
		if ( ! $star_type ) {
			return self::bad( 'Invalid StarCode' );
		}
		$star_type_id = (int) $star_type['id'];

		$challenge_id = isset( $d['challengeId'] ) && $d['challengeId'] !== null ? (int) $d['challengeId'] : null;
		$ch_t         = self::t( 'challenges' );
		if ( ( $challenge_id === null || $challenge_id === 0 ) && ! empty( $d['challengeName'] ) ) {
			$cname = trim( (string) $d['challengeName'] );
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$ch_t} (name) VALUES (%s) ON DUPLICATE KEY UPDATE name = VALUES(name)", $cname ) );
			$challenge_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$ch_t} WHERE name=%s", $cname ) );
		}

		$stick = self::t( 'star_stickers' );
		$aw    = self::t( 'star_awards' );

		$wpdb->query( 'START TRANSACTION' );
		try {
			self::reconcile_stickers_for_type( $star_type_id );
			$unique_code = null;
			if ( $manual_code !== '' ) {
				$ok = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$stick} s
						LEFT JOIN {$aw} a
							ON a.unique_code = s.code
							AND a.star_type_id = s.star_type_id
						WHERE s.code=%s
							AND s.star_type_id=%d
							AND a.id IS NULL
						FOR UPDATE",
						$manual_code,
						$star_type_id
					)
				);
				if ( $ok !== 1 ) {
					$wpdb->query( 'ROLLBACK' );
					return self::bad( 'Código inválido o ya utilizado.' );
				}
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$stick} SET is_used=1, used_at=UTC_TIMESTAMP() WHERE code=%s",
						$manual_code
					)
				);
				$unique_code = $manual_code;
			} else {
				// Si el tipo no tiene stickers precargados (p.ej. tras importación),
				// intentamos generarlos automáticamente antes de fallar.
				Estrellas_NW_Database::maybe_seed_stickers();
				$unique_code = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT s.code FROM {$stick} s
						LEFT JOIN {$aw} a
							ON a.unique_code = s.code
							AND a.star_type_id = s.star_type_id
						WHERE s.star_type_id=%d
							AND a.id IS NULL
						ORDER BY s.num
						LIMIT 1 FOR UPDATE",
						$star_type_id
					)
				);
				if ( ! $unique_code ) {
					$wpdb->query( 'ROLLBACK' );
					return self::bad( 'No hay más códigos disponibles para este tipo (límite 9999).' );
				}
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$stick} SET is_used=1, used_at=UTC_TIMESTAMP() WHERE code=%s",
						$unique_code
					)
				);
			}

			$insert_row = array(
				'employee_id'  => $employee_id,
				'star_type_id' => $star_type_id,
				'award_date'   => $award_date,
				'note'         => $note === '' ? null : $note,
				'unique_code'  => $unique_code,
			);
			if ( $challenge_id ) {
				$insert_row['challenge_id'] = $challenge_id;
			}
			$wpdb->insert( $aw, $insert_row );
			$award_id = (int) $wpdb->insert_id;

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$stick} SET used_award_id=%d WHERE code=%s",
					$award_id,
					$unique_code
				)
			);

			$wpdb->query( 'COMMIT' );
			return rest_ensure_response( array( 'id' => $award_id, 'uniqueCode' => $unique_code ) );
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return self::bad( $e->getMessage() );
		}
	}

	public static function star_awards_edit( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$id = (int) $req['id'];
		$d  = self::json_body();

		$stick = self::t( 'star_stickers' );
		$aw    = self::t( 'star_awards' );
		$st_t  = self::t( 'star_types' );

		$wpdb->query( 'START TRANSACTION' );
		try {
			$cur = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT star_type_id, unique_code FROM {$aw} WHERE id=%d FOR UPDATE",
					$id
				),
				ARRAY_A
			);
			if ( ! $cur ) {
				$wpdb->query( 'ROLLBACK' );
				return self::bad( 'Star award no existe.', 404 );
			}
			$current_type_id = (int) $cur['star_type_id'];
			$old_code        = (string) $cur['unique_code'];

			$new_type_id = $current_type_id;
			if ( ! empty( $d['starCode'] ) ) {
				$code = self::normalize_star_code( (string) $d['starCode'] );
				$tid  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$st_t} WHERE code=%s", $code ) );
				if ( $tid <= 0 ) {
					$wpdb->query( 'ROLLBACK' );
					return self::bad( 'StarCode inválido.' );
				}
				$new_type_id = $tid;
			}

			self::reconcile_stickers_for_type( $new_type_id );
			if ( $old_code !== '' ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$stick} SET is_used=0, used_at=NULL, used_award_id=NULL WHERE code=%s",
						$old_code
					)
				);
			}

			$new_code = null;
			if ( ! empty( $d['uniqueCode'] ) ) {
				$want = strtoupper( trim( (string) $d['uniqueCode'] ) );
				$ok   = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$stick} s
						LEFT JOIN {$aw} a
							ON a.unique_code = s.code
							AND a.star_type_id = s.star_type_id
						WHERE s.code=%s
							AND s.star_type_id=%d
							AND a.id IS NULL
						FOR UPDATE",
						$want,
						$new_type_id
					)
				);
				if ( $ok !== 1 ) {
					$wpdb->query( 'ROLLBACK' );
					return self::bad( 'Código inválido / ya usado / no corresponde al tipo.' );
				}
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$stick} SET is_used=1, used_at=UTC_TIMESTAMP() WHERE code=%s",
						$want
					)
				);
				$new_code = $want;
			} else {
				// Recupera stickers faltantes para tipos recién importados o creados.
				Estrellas_NW_Database::maybe_seed_stickers();
				$new_code = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT s.code FROM {$stick} s
						LEFT JOIN {$aw} a
							ON a.unique_code = s.code
							AND a.star_type_id = s.star_type_id
						WHERE s.star_type_id=%d
							AND a.id IS NULL
						ORDER BY s.num LIMIT 1 FOR UPDATE",
						$new_type_id
					)
				);
				if ( ! $new_code ) {
					$wpdb->query( 'ROLLBACK' );
					return self::bad( 'No hay códigos disponibles para ese tipo.' );
				}
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$stick} SET is_used=1, used_at=UTC_TIMESTAMP() WHERE code=%s",
						$new_code
					)
				);
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$aw} SET star_type_id=%d, unique_code=%s WHERE id=%d",
					$new_type_id,
					$new_code,
					$id
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$stick} SET used_award_id=%d WHERE code=%s",
					$id,
					$new_code
				)
			);

			$wpdb->query( 'COMMIT' );
			return rest_ensure_response( array( 'id' => $id, 'uniqueCode' => $new_code ) );
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return self::bad( $e->getMessage() );
		}
	}

	public static function star_awards_code( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$id = (int) $req['id'];
		$d  = self::json_body();
		if ( empty( $d['uniqueCode'] ) ) {
			return self::bad( 'UniqueCode required' );
		}
		$new_code = strtoupper( trim( (string) $d['uniqueCode'] ) );

		$stick = self::t( 'star_stickers' );
		$aw    = self::t( 'star_awards' );

		$wpdb->query( 'START TRANSACTION' );
		try {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT star_type_id, unique_code FROM {$aw} WHERE id=%d FOR UPDATE",
					$id
				),
				ARRAY_A
			);
			if ( ! $row ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_REST_Response( null, 404 );
			}
			$type_id = (int) $row['star_type_id'];
			$old     = (string) $row['unique_code'];

			if ( $new_code === $old ) {
				$wpdb->query( 'COMMIT' );
				return rest_ensure_response( array( 'id' => $id, 'uniqueCode' => $old ) );
			}

			$ok = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$stick} s
					LEFT JOIN {$aw} a
						ON a.unique_code = s.code
						AND a.star_type_id = s.star_type_id
					WHERE s.code=%s
						AND s.star_type_id=%d
						AND a.id IS NULL
					FOR UPDATE",
					$new_code,
					$type_id
				)
			);
			if ( $ok !== 1 ) {
				$wpdb->query( 'ROLLBACK' );
				return self::bad( 'Código inválido o ya utilizado.' );
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$stick} SET is_used=0, used_award_id=NULL, used_at=NULL WHERE code=%s",
					$old
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$stick} SET is_used=1, used_award_id=%d, used_at=UTC_TIMESTAMP() WHERE code=%s",
					$id,
					$new_code
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$aw} SET unique_code=%s WHERE id=%d",
					$new_code,
					$id
				)
			);

			$wpdb->query( 'COMMIT' );
			return rest_ensure_response( array( 'id' => $id, 'uniqueCode' => $new_code ) );
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return self::bad( $e->getMessage() );
		}
	}

	public static function star_awards_delete( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$id    = (int) $req['id'];
		$aw    = self::t( 'star_awards' );
		$stick = self::t( 'star_stickers' );

		$wpdb->query( 'START TRANSACTION' );
		try {
			$code = $wpdb->get_var( $wpdb->prepare( "SELECT unique_code FROM {$aw} WHERE id=%d FOR UPDATE", $id ) );
			if ( $code === null ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_REST_Response( null, 404 );
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$stick} SET is_used=0, used_at=NULL, used_award_id=NULL
					WHERE used_award_id=%d OR (code=%s AND used_award_id IS NULL)",
					$id,
					$code
				)
			);
			$wpdb->delete( $aw, array( 'id' => $id ) );
			$wpdb->query( 'COMMIT' );
			return new WP_REST_Response( null, 204 );
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return self::bad( $e->getMessage() );
		}
	}

	public static function star_codes( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$star_code      = $req->get_param( 'starCode' );
		$available_only = $req->get_param( 'availableOnly' );
		$q              = $req->get_param( 'q' );
		$limit          = $req->get_param( 'limit' );

		if ( $star_code === null || trim( (string) $star_code ) === '' ) {
			return self::bad( 'Invalid starCode' );
		}
		$code = self::normalize_star_code( (string) $star_code );
		$st_t = self::t( 'star_types' );
		$st   = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$st_t} WHERE code=%s", $code ), ARRAY_A );
		if ( ! $st ) {
			return self::bad( 'Invalid starCode' );
		}
		$sid  = (int) $st['id'];
		self::reconcile_stickers_for_type( $sid );
		$take = $limit !== null ? max( 1, min( 2000, (int) $limit ) ) : 200;

		$stick = self::t( 'star_stickers' );
		$aw    = self::t( 'star_awards' );
		$sql   = "SELECT s.code,
			EXISTS(
				SELECT 1 FROM {$aw} a
				WHERE a.unique_code = s.code
					AND a.star_type_id = s.star_type_id
			) AS isUsed
			FROM {$stick} s
			WHERE s.star_type_id=%d";
		$params = array( $sid );

		if ( $available_only === 'true' || $available_only === true || $available_only === '1' ) {
			$sql .= " AND NOT EXISTS(
				SELECT 1 FROM {$aw} a
				WHERE a.unique_code = s.code
					AND a.star_type_id = s.star_type_id
			)";
		}
		if ( $q !== null && trim( (string) $q ) !== '' ) {
			$sql     .= ' AND s.code LIKE %s';
			$params[] = $wpdb->esc_like( trim( (string) $q ) ) . '%';
		}
		$sql .= ' ORDER BY isUsed ASC, s.num LIMIT %d';
		$params[] = $take;

		$sql = $wpdb->prepare( $sql, $params );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows ?: array() as &$row ) {
			$row['isUsed'] = (bool) (int) $row['isUsed'];
			$row['is_used'] = $row['isUsed'];
		}
		return rest_ensure_response( $rows ?: array() );
	}

	public static function stats_employee( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$employee_id = (int) $req['employeeId'];
		$from        = $req->get_param( 'from' );
		$to          = $req->get_param( 'to' );

		$sa = self::t( 'star_awards' );
		$st = self::t( 'star_types' );
		$df = '';
		$params = array( $employee_id );
		if ( $from !== null && $from !== '' ) {
			$df .= ' AND sa.award_date >= %s';
			$params[] = (string) $from;
		}
		if ( $to !== null && $to !== '' ) {
			$df .= ' AND sa.award_date <= %s';
			$params[] = (string) $to;
		}

		$sql = "SELECT st.code AS starCode, COUNT(*) AS cnt FROM {$sa} sa
			JOIN {$st} st ON st.id = sa.star_type_id
			WHERE sa.employee_id=%d {$df} GROUP BY st.code ORDER BY st.code";
		$sql = $wpdb->prepare( $sql, $params );
		$totals = $wpdb->get_results( $sql, ARRAY_A );
		$by     = array();
		$sum    = 0;
		foreach ( $totals ?: array() as $t ) {
			$by[] = array(
				'starCode' => $t['starCode'],
				'count'    => (int) $t['cnt'],
			);
			$sum += (int) $t['cnt'];
		}

		$e = self::t( 'employees' );
		$c = self::t( 'companies' );
		$info = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT e.id, e.full_name AS fullName, e.company_id AS companyId, c.name AS companyName,
				e.is_active AS isActive, e.created_at AS createdAt
				FROM {$e} e JOIN {$c} c ON c.id=e.company_id WHERE e.id=%d",
				$employee_id
			),
			ARRAY_A
		);
		if ( ! $info ) {
			return new WP_REST_Response( null, 404 );
		}
		$info['isActive'] = (bool) (int) $info['isActive'];
		if ( ! empty( $info['createdAt'] ) ) {
			$info['createdAt'] = gmdate( 'c', strtotime( $info['createdAt'] . ' UTC' ) );
		}

		return rest_ensure_response(
			array(
				'employee' => $info,
				'total'    => $sum,
				'byStar'   => $by,
			)
		);
	}

	public static function stats_star_type( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$star_code  = self::normalize_star_code( (string) $req['starCode'] );
		$company_id = $req->get_param( 'companyId' );
		$from       = $req->get_param( 'from' );
		$to         = $req->get_param( 'to' );

		$sa = self::t( 'star_awards' );
		$st = self::t( 'star_types' );
		$e  = self::t( 'employees' );
		$c  = self::t( 'companies' );

		$sql = "SELECT e.id AS employee_id, e.full_name, c.name AS company_name, COUNT(*) AS count
			FROM {$sa} sa
			JOIN {$st} st ON st.id = sa.star_type_id
			JOIN {$e} e ON e.id = sa.employee_id
			JOIN {$c} c ON c.id = e.company_id
			WHERE st.code=%s";
		$params = array( $star_code );

		if ( $company_id !== null && $company_id !== '' ) {
			$sql     .= ' AND e.company_id=%d';
			$params[] = (int) $company_id;
		}
		if ( $from !== null && $from !== '' ) {
			$sql     .= ' AND sa.award_date >= %s';
			$params[] = (string) $from;
		}
		if ( $to !== null && $to !== '' ) {
			$sql     .= ' AND sa.award_date <= %s';
			$params[] = (string) $to;
		}
		$sql .= ' GROUP BY e.id, e.full_name, c.name ORDER BY count DESC, e.full_name';

		$sql  = $wpdb->prepare( $sql, $params );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows ?: array() as &$r ) {
			$r['count'] = (int) $r['count'];
		}
		return rest_ensure_response( $rows ?: array() );
	}

	public static function stats_ranking( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$company_id = $req->get_param( 'companyId' );
		$from       = $req->get_param( 'from' );
		$to         = $req->get_param( 'to' );
		$star_code  = $req->get_param( 'starCode' );

		$sa = self::t( 'star_awards' );
		$e  = self::t( 'employees' );
		$c  = self::t( 'companies' );
		$st = self::t( 'star_types' );

		$sql = "SELECT e.id AS employee_id, e.full_name, c.name AS company_name, COUNT(*) AS total
			FROM {$sa} sa
			JOIN {$e} e ON e.id = sa.employee_id
			JOIN {$c} c ON c.id = e.company_id
			JOIN {$st} st ON st.id = sa.star_type_id
			WHERE 1=1";
		$params = array();

		if ( $company_id !== null && $company_id !== '' ) {
			$sql     .= ' AND e.company_id=%d';
			$params[] = (int) $company_id;
		}
		if ( $from !== null && $from !== '' ) {
			$sql     .= ' AND sa.award_date >= %s';
			$params[] = (string) $from;
		}
		if ( $to !== null && $to !== '' ) {
			$sql     .= ' AND sa.award_date <= %s';
			$params[] = (string) $to;
		}
		if ( $star_code !== null && trim( (string) $star_code ) !== '' ) {
			$sql     .= ' AND st.code = %s';
			$params[] = self::normalize_star_code( (string) $star_code );
		}
		$sql .= ' GROUP BY e.id, e.full_name, c.name ORDER BY total DESC, e.full_name LIMIT 200';

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows ?: array() as &$r ) {
			$r['total'] = (int) $r['total'];
		}
		return rest_ensure_response( $rows ?: array() );
	}

	public static function stats_ranking_public( WP_REST_Request $req ): WP_REST_Response {
		return self::stats_ranking( $req );
	}

	public static function backup_export(): WP_REST_Response {
		global $wpdb;
		$c  = self::t( 'companies' );
		$st = self::t( 'star_types' );
		$ch = self::t( 'challenges' );
		$e  = self::t( 'employees' );
		$sa = self::t( 'star_awards' );
		$ss = self::t( 'star_stickers' );

		$payload = array(
			'version'      => 'estrellas-backup-v1',
			'exportedAt'   => gmdate( 'c' ),
			'source'       => 'wordpress-plugin',
			'mode'         => 'merge',
			'companies'    => $wpdb->get_results( "SELECT name FROM {$c} ORDER BY name", ARRAY_A ) ?: array(),
			'starTypes'    => $wpdb->get_results( "SELECT code, name, sticker_prefix AS stickerPrefix FROM {$st} ORDER BY id", ARRAY_A ) ?: array(),
			'challenges'   => $wpdb->get_results( "SELECT name FROM {$ch} ORDER BY name", ARRAY_A ) ?: array(),
			'employees'    => $wpdb->get_results(
				"SELECT e.full_name AS fullName, c.name AS companyName, e.is_active AS isActive
				FROM {$e} e JOIN {$c} c ON c.id=e.company_id ORDER BY c.name, e.full_name",
				ARRAY_A
			) ?: array(),
			'starAwards'   => $wpdb->get_results(
				"SELECT c.name AS companyName, e.full_name AS employeeFullName, st.code AS starCode, sa.award_date AS awardDate,
				 ch.name AS challengeName, sa.note AS note, sa.unique_code AS uniqueCode
				FROM {$sa} sa
				JOIN {$e} e ON e.id=sa.employee_id
				JOIN {$c} c ON c.id=e.company_id
				JOIN {$st} st ON st.id=sa.star_type_id
				LEFT JOIN {$ch} ch ON ch.id=sa.challenge_id
				ORDER BY sa.award_date, sa.id",
				ARRAY_A
			) ?: array(),
			'starStickers' => $wpdb->get_results(
				"SELECT ss.code AS code, st.code AS starCode, ss.num AS num, ss.is_used AS isUsed
				FROM {$ss} ss
				JOIN {$st} st ON st.id=ss.star_type_id
				ORDER BY st.code, ss.num",
				ARRAY_A
			) ?: array(),
		);

		foreach ( $payload['employees'] as &$emp ) {
			$emp['isActive'] = (bool) (int) $emp['isActive'];
		}
		foreach ( $payload['starStickers'] as &$s ) {
			$s['isUsed'] = (bool) (int) $s['isUsed'];
		}

		return rest_ensure_response( $payload );
	}

	public static function backup_import( WP_REST_Request $req ): WP_REST_Response {
		global $wpdb;
		$d = self::json_body();
		$mode = isset( $d['mode'] ) ? strtolower( trim( (string) $d['mode'] ) ) : 'merge';
		if ( ! in_array( $mode, array( 'merge', 'replace' ), true ) ) {
			return self::bad( "Mode inválido. Use 'merge' o 'replace'." );
		}

		$c  = self::t( 'companies' );
		$st = self::t( 'star_types' );
		$ch = self::t( 'challenges' );
		$e  = self::t( 'employees' );
		$sa = self::t( 'star_awards' );
		$ss = self::t( 'star_stickers' );

		$wpdb->query( 'START TRANSACTION' );
		try {
			if ( 'replace' === $mode ) {
				$wpdb->query( "DELETE FROM {$sa}" );
				$wpdb->query( "UPDATE {$ss} SET is_used=0, used_at=NULL, used_award_id=NULL" );
				$wpdb->query( "DELETE FROM {$e}" );
				$wpdb->query( "DELETE FROM {$ch}" );
				$wpdb->query( "DELETE FROM {$c}" );
			}

			$companies = array();
			$rows = $wpdb->get_results( "SELECT id, name FROM {$c}", ARRAY_A ) ?: array();
			foreach ( $rows as $r ) {
				$companies[ strtolower( trim( $r['name'] ) ) ] = (int) $r['id'];
			}
			$star_types = array();
			$rows = $wpdb->get_results( "SELECT id, code FROM {$st}", ARRAY_A ) ?: array();
			foreach ( $rows as $r ) {
				$star_types[ strtoupper( trim( $r['code'] ) ) ] = (int) $r['id'];
			}
			$challenges = array();
			$rows = $wpdb->get_results( "SELECT id, name FROM {$ch}", ARRAY_A ) ?: array();
			foreach ( $rows as $r ) {
				$challenges[ strtolower( trim( $r['name'] ) ) ] = (int) $r['id'];
			}

			$imported_companies = 0;
			$imported_employees = 0;
			$imported_awards    = 0;

			foreach ( $d['companies'] ?? array() as $row ) {
				$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
				if ( '' === $name ) {
					continue;
				}
				$key = strtolower( $name );
				if ( isset( $companies[ $key ] ) ) {
					continue;
				}
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$c} (name) VALUES (%s) ON DUPLICATE KEY UPDATE name=VALUES(name)", $name ) );
				$companies[ $key ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$c} WHERE name=%s", $name ) );
				$imported_companies++;
			}

			foreach ( $d['starTypes'] ?? array() as $row ) {
				$code   = isset( $row['code'] ) ? self::normalize_star_code( (string) $row['code'] ) : '';
				$name   = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
				$prefix = isset( $row['stickerPrefix'] ) ? strtoupper( trim( (string) $row['stickerPrefix'] ) ) : '';
				if ( '' === $code || '' === $name ) {
					continue;
				}
				if ( '' === $prefix ) {
					$prefix = substr( $code, 0, 1 );
				}
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$st} (code, name, sticker_prefix) VALUES (%s, %s, %s)
						ON DUPLICATE KEY UPDATE name=VALUES(name), sticker_prefix=VALUES(sticker_prefix)",
						$code,
						$name,
						$prefix
					)
				);
				$star_types[ $code ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$st} WHERE code=%s", $code ) );
			}
			// Garantiza stock inicial de stickers para cualquier tipo importado.
			Estrellas_NW_Database::maybe_seed_stickers();

			foreach ( $d['challenges'] ?? array() as $row ) {
				$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
				if ( '' === $name ) {
					continue;
				}
				$key = strtolower( $name );
				if ( isset( $challenges[ $key ] ) ) {
					continue;
				}
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$ch} (name) VALUES (%s) ON DUPLICATE KEY UPDATE name=VALUES(name)", $name ) );
				$challenges[ $key ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$ch} WHERE name=%s", $name ) );
			}

			foreach ( $d['employees'] ?? array() as $row ) {
				$full = isset( $row['fullName'] ) ? trim( (string) $row['fullName'] ) : '';
				$comp = isset( $row['companyName'] ) ? trim( (string) $row['companyName'] ) : '';
				if ( '' === $full || '' === $comp ) {
					continue;
				}
				$ckey = strtolower( $comp );
				if ( ! isset( $companies[ $ckey ] ) ) {
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$c} (name) VALUES (%s) ON DUPLICATE KEY UPDATE name=VALUES(name)", $comp ) );
					$companies[ $ckey ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$c} WHERE name=%s", $comp ) );
					$imported_companies++;
				}
				$active = ! isset( $row['isActive'] ) || $row['isActive'] ? 1 : 0;
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$e} (full_name, company_id, is_active) VALUES (%s, %d, %d)
						ON DUPLICATE KEY UPDATE is_active=VALUES(is_active)",
						$full,
						$companies[ $ckey ],
						$active
					)
				);
				$imported_employees++;
			}

			$employee_map = array();
			$rows = $wpdb->get_results( "SELECT id, full_name, company_id FROM {$e}", ARRAY_A ) ?: array();
			foreach ( $rows as $r ) {
				$employee_map[ (int) $r['company_id'] . '|' . strtolower( trim( $r['full_name'] ) ) ] = (int) $r['id'];
			}

			foreach ( $d['starAwards'] ?? array() as $row ) {
				$comp  = isset( $row['companyName'] ) ? trim( (string) $row['companyName'] ) : '';
				$full  = isset( $row['employeeFullName'] ) ? trim( (string) $row['employeeFullName'] ) : '';
				$code  = isset( $row['starCode'] ) ? self::normalize_star_code( (string) $row['starCode'] ) : '';
				$date  = isset( $row['awardDate'] ) ? substr( (string) $row['awardDate'], 0, 10 ) : '';
				$uq    = isset( $row['uniqueCode'] ) ? strtoupper( trim( (string) $row['uniqueCode'] ) ) : '';
				$note  = isset( $row['note'] ) ? trim( (string) $row['note'] ) : '';
				$ch_nm = isset( $row['challengeName'] ) ? trim( (string) $row['challengeName'] ) : '';
				if ( '' === $comp || '' === $full || '' === $code || '' === $date || ! isset( $star_types[ $code ] ) ) {
					continue;
				}

				$ckey = strtolower( $comp );
				if ( ! isset( $companies[ $ckey ] ) ) {
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$c} (name) VALUES (%s) ON DUPLICATE KEY UPDATE name=VALUES(name)", $comp ) );
					$companies[ $ckey ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$c} WHERE name=%s", $comp ) );
					$imported_companies++;
				}
				$company_id = $companies[ $ckey ];
				$ekey = $company_id . '|' . strtolower( $full );
				if ( ! isset( $employee_map[ $ekey ] ) ) {
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$e} (full_name, company_id, is_active) VALUES (%s, %d, 1)
							ON DUPLICATE KEY UPDATE full_name=VALUES(full_name)",
							$full,
							$company_id
						)
					);
					$employee_map[ $ekey ] = (int) $wpdb->get_var(
						$wpdb->prepare( "SELECT id FROM {$e} WHERE company_id=%d AND full_name=%s", $company_id, $full )
					);
					$imported_employees++;
				}
				$employee_id = $employee_map[ $ekey ];
				$star_type_id = $star_types[ $code ];

				$challenge_id = null;
				if ( '' !== $ch_nm ) {
					$hkey = strtolower( $ch_nm );
					if ( ! isset( $challenges[ $hkey ] ) ) {
						$wpdb->query( $wpdb->prepare( "INSERT INTO {$ch} (name) VALUES (%s) ON DUPLICATE KEY UPDATE name=VALUES(name)", $ch_nm ) );
						$challenges[ $hkey ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$ch} WHERE name=%s", $ch_nm ) );
					}
					$challenge_id = $challenges[ $hkey ];
				}

				$existing_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$sa}
						WHERE employee_id=%d AND star_type_id=%d AND award_date=%s AND COALESCE(unique_code,'')=%s
						LIMIT 1",
						$employee_id,
						$star_type_id,
						$date,
						$uq
					)
				);
				if ( $existing_id > 0 ) {
					continue;
				}

				$insert = array(
					'employee_id'  => $employee_id,
					'star_type_id' => $star_type_id,
					'award_date'   => $date,
					'note'         => '' === $note ? null : $note,
					'unique_code'  => $uq,
				);
				if ( $challenge_id ) {
					$insert['challenge_id'] = $challenge_id;
				}
				$wpdb->insert( $sa, $insert );
				$award_id = (int) $wpdb->insert_id;
				$imported_awards++;

				if ( '' !== $uq ) {
					$num = (int) preg_replace( '/\D+/', '', $uq );
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$ss} (code, star_type_id, num, is_used, used_at, used_award_id)
							VALUES (%s, %d, %d, 1, UTC_TIMESTAMP(), %d)
							ON DUPLICATE KEY UPDATE
							star_type_id=VALUES(star_type_id),
							num=IF(VALUES(num)>0, VALUES(num), num),
							is_used=1, used_at=UTC_TIMESTAMP(), used_award_id=VALUES(used_award_id)",
							$uq,
							$star_type_id,
							$num,
							$award_id
						)
					);
				}
			}

			$wpdb->query( 'COMMIT' );
			return rest_ensure_response(
				array(
					'ok'                => true,
					'mode'              => $mode,
					'importedCompanies' => $imported_companies,
					'importedEmployees' => $imported_employees,
					'importedAwards'    => $imported_awards,
				)
			);
		} catch ( Exception $ex ) {
			$wpdb->query( 'ROLLBACK' );
			return self::bad( $ex->getMessage() );
		}
	}

	private static function bad( string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response( $message, $status );
	}

	/**
	 * Sincroniza el estado de stickers con las estrellas realmente registradas.
	 * Evita mostrar códigos como "usados" cuando no están asignados a ningún award.
	 */
	private static function reconcile_stickers_for_type( int $star_type_id ): void {
		global $wpdb;
		$stick = self::t( 'star_stickers' );
		$aw    = self::t( 'star_awards' );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$stick} s
				LEFT JOIN {$aw} a
					ON a.unique_code = s.code
					AND a.star_type_id = s.star_type_id
				SET s.is_used = 0, s.used_at = NULL, s.used_award_id = NULL
				WHERE s.star_type_id = %d
					AND a.id IS NULL
					AND (s.is_used <> 0 OR s.used_award_id IS NOT NULL)",
				$star_type_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$stick} s
				INNER JOIN {$aw} a
					ON a.unique_code = s.code
					AND a.star_type_id = s.star_type_id
				SET s.is_used = 1,
					s.used_award_id = a.id,
					s.used_at = COALESCE(s.used_at, UTC_TIMESTAMP())
				WHERE s.star_type_id = %d
					AND (s.is_used <> 1 OR s.used_award_id IS NULL OR s.used_award_id <> a.id)",
				$star_type_id
			)
		);
	}
}
