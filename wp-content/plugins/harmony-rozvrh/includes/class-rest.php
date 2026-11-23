<?php
/**
 * GET /wp-json/harmony/v1/rozvrh?tyden=0|1|2
 * → JSON pole lekcí. Metadata v hlavičkách:
 *   X-Harmony-Tyden-Od / -Do, X-Harmony-Aktualizovano (ISO 8601), X-Harmony-Zastarale (0/1).
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_Rest {

	public static function register(): void {
		if ( ! Harmony_Rozvrh_Config::rest_enabled() ) {
			return;
		}
		register_rest_route(
			'harmony/v1',
			'/rozvrh',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true', // veřejný rozvrh, žádná osobní data
				'args'                => array(
					'tyden' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
						'maximum' => Harmony_Rozvrh_Config::weeks_ahead(),
					),
				),
			)
		);
	}

	public static function handle( WP_REST_Request $req ) {
		$res  = Harmony_Rozvrh_Repository::get();
		$week = null;
		foreach ( $res['data']['tydny'] ?? array() as $w ) {
			if ( (int) $w['tyden'] === (int) $req['tyden'] ) {
				$week = $w;
			}
		}

		if ( ! $res['data'] ) {
			return new WP_Error( 'harmony_rozvrh_unavailable', 'Rozvrh je dočasně nedostupný.', array( 'status' => 503 ) );
		}

		$lessons = array_map( array( __CLASS__, 'public_fields' ), $week['lekce'] ?? array() );
		$out     = new WP_REST_Response( $lessons );
		$out->header( 'Cache-Control', 'public, max-age=300' );
		$out->header( 'X-Harmony-Aktualizovano', wp_date( DATE_ATOM, (int) $res['data']['aktualizovano'] ) );
		$out->header( 'X-Harmony-Zastarale', $res['stale'] ? '1' : '0' );
		if ( $week ) {
			$out->header( 'X-Harmony-Tyden-Od', $week['od'] );
			$out->header( 'X-Harmony-Tyden-Do', $week['do'] );
		}
		return $out;
	}

	/** Normalizovaný model lekce pro API (stejný jako interní, bez interních polí). */
	public static function public_fields( array $l ): array {
		return array(
			'id'         => $l['id'],
			'datum'      => $l['datum'],
			'cas'        => $l['cas'],
			'konec'      => $l['konec'],
			'zacatek'    => $l['zacatek'],
			'delka_min'  => $l['delka_min'],
			'nazev'      => $l['nazev'],
			'sal'        => $l['sal'],
			'instruktor' => $l['instruktor'],
			'kapacita'   => $l['kapacita'],
			'obsazeno'   => $l['obsazeno'],
			'volno'      => $l['volno'],
			'cena'       => $l['cena'],
			'popis'      => $l['popis'],
			'skupiny'    => $l['skupiny'] ?? array(),
		);
	}
}
