<?php
/**
 * Oficiální API MemberPro (memberzone): https://api.memberzone.online:49297/Help
 *
 * Autorizace Basic Auth (údaje přiděluje memberzone; pro test name=test, password=test).
 * API povoluje jen určené IP adresy → IP serveru webu je potřeba nahlásit memberzone.
 *
 * Používané endpointy:
 *   GET api/Lesson?resort=&date_start=dd.MM.yyyy&id_kategorie=&user_id=0&pocet_dni_dopredu=&id_service=0&lang=cs
 *       → pole lekcí; když v období nic není, vrací 404 (ne prázdné pole)
 *   GET api/Room?lang=cs        → názvy sálů podle cislo_salu
 *   GET api/Categories?lang=cs  → kategorie (type 2 = lekce/sály, type 10 = kurty)
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_Api_Client {

	private const TZ = 'Europe/Prague';

	/**
	 * Normalizované lekce od pondělí $monday na $days dní.
	 *
	 * @return array<int,array<string,mixed>>
	 * @throws RuntimeException
	 */
	public function fetch_lessons( DateTimeImmutable $monday, int $days ): array {
		$rooms   = $this->rooms();
		$lessons = array();

		foreach ( $this->categories() as $cat ) {
			$rows = $this->get(
				'api/Lesson',
				array(
					'resort'            => (int) Harmony_Rozvrh_Config::get( 'HARMONY_MZ_API_RESORT' ),
					'date_start'        => $monday->format( 'd.m.Y' ), // API chce dd.MM.yyyy s nulami
					'id_kategorie'      => $cat,
					'user_id'           => 0,
					'pocet_dni_dopredu' => $days,
					'id_service'        => 0,
					'lang'              => 'cs',
				)
			);
			foreach ( $rows as $row ) {
				$l = self::normalize( $row, $rooms );
				if ( $l ) {
					$lessons[ $l['id'] . '@' . $l['zacatek'] ] = $l;
				}
			}
		}

		$lessons = array_values( $lessons );
		usort( $lessons, static fn( $a, $b ) => strcmp( $a['zacatek'], $b['zacatek'] ) ?: strcmp( (string) $a['sal'], (string) $b['sal'] ) );
		return $lessons;
	}

	/** Lekce API → normalizovaný model (stejný jako dřív ze scrapingu). */
	public static function normalize( array $r, array $rooms = array() ): ?array {
		if ( empty( $r['date_time'] ) || empty( $r['nazev'] ) ) {
			return null;
		}
		$tz  = new DateTimeZone( self::TZ );
		$raw = (string) $r['date_time'];
		// API posílá „…Z“, ale jde o místní čas (lekce v 08:00 = 08:00 v Písku).
		// Kdyby memberzone potvrdil skutečné UTC, nastavte HARMONY_MZ_API_TIME_UTC = true.
		$start = Harmony_Rozvrh_Config::get( 'HARMONY_MZ_API_TIME_UTC' )
			? ( new DateTimeImmutable( $raw ) )->setTimezone( $tz )
			: new DateTimeImmutable( preg_replace( '/(Z|[+-]\d{2}:?\d{2})$/', '', $raw ), $tz );

		$duration = max( 1, (int) ( $r['delka'] ?? 55 ) );
		$end      = $start->modify( '+' . $duration . ' minutes' );
		$capacity = isset( $r['kapacita'] ) ? (int) $r['kapacita'] : null;
		$booked   = isset( $r['obsazeno'] ) ? (int) $r['obsazeno'] : null;
		$free     = isset( $r['volno'] ) ? max( 0, (int) $r['volno'] ) : ( null !== $capacity && null !== $booked ? max( 0, $capacity - $booked ) : null );
		$room_no  = (int) ( $r['cislo_salu'] ?? 0 );

		$name = trim( (string) $r['nazev'] );
		$name = mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );

		return array(
			'id'         => (int) ( $r['id_service'] ?? 0 ),
			'datum'      => $start->format( 'Y-m-d' ),
			'den'        => (int) $start->format( 'N' ),
			'cas'        => $start->format( 'H:i' ),
			'konec'      => $end->format( 'H:i' ),
			'zacatek'    => $start->format( DATE_ATOM ),
			'delka_min'  => $duration,
			'nazev'      => apply_filters( 'harmony_rozvrh_nazev_lekce', $name, $r ),
			'sal'        => $rooms[ $room_no ] ?? ( $room_no ? 'Sál ' . $room_no : '' ),
			'sal_id'     => $room_no ?: null,
			'instruktor' => trim( preg_replace( '/^\s*instr\.?\s*/iu', '', (string) ( $r['osloveni'] ?? '' ) ) ),
			'kapacita'   => $capacity,
			'obsazeno'   => $booked,
			'volno'      => $free,
			'cena'       => isset( $r['cena'] ) ? (float) $r['cena'] : null,
			'popis'      => wp_strip_all_tags( (string) ( $r['popis'] ?? '' ) ),
		);
	}

	/** @return array<int,string> cislo_salu → název */
	private function rooms(): array {
		$map = array();
		try {
			foreach ( $this->get( 'api/Room', array( 'lang' => 'cs' ) ) as $room ) {
				$map[ (int) $room['cislo_salu'] ] = (string) $room['nazev'];
			}
		} catch ( RuntimeException $e ) {
			// Bez názvů sálů se rozvrh zobrazí i tak („Sál 1“).
		}
		return apply_filters( 'harmony_rozvrh_saly', $map );
	}

	/** @return int[] ID kategorií s lekcemi */
	private function categories(): array {
		$conf = trim( (string) Harmony_Rozvrh_Config::get( 'HARMONY_MZ_API_CATEGORIES' ) );
		if ( '' !== $conf ) {
			return array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $conf ) ) ) );
		}
		// Autodetekce: kategorie typu 2 (skupinová cvičení, sály). Typ 10 = kurty.
		$ids = array();
		foreach ( $this->get( 'api/Categories', array( 'lang' => 'cs' ) ) as $c ) {
			if ( 2 === (int) ( $c['type'] ?? 0 ) ) {
				$ids[] = (int) $c['id'];
			}
		}
		if ( ! $ids ) {
			throw new RuntimeException( 'API nevrátilo žádnou kategorii lekcí. Nastavte HARMONY_MZ_API_CATEGORIES.' );
		}
		return $ids;
	}

	/** @return array<int,array<string,mixed>> */
	private function get( string $path, array $query ): array {
		$url = trailingslashit( (string) Harmony_Rozvrh_Config::get( 'HARMONY_MZ_API_URL' ) ) . $path . '?' . http_build_query( $query );
		$res = wp_remote_get(
			$url,
			array(
				'timeout' => (int) Harmony_Rozvrh_Config::get( 'HARMONY_ROZVRH_TIMEOUT' ),
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( Harmony_Rozvrh_Config::username() . ':' . Harmony_Rozvrh_Config::password() ),
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			throw new RuntimeException( 'API nedostupné: ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 404 === $code ) {
			return array(); // API tak hlásí „žádné záznamy“
		}
		if ( 401 === $code || 403 === $code ) {
			throw new RuntimeException( "API odmítlo přístup (HTTP {$code}). Zkontrolujte jméno/heslo a že IP serveru je povolená u memberzone." );
		}
		if ( 200 !== $code ) {
			throw new RuntimeException( "API vrátilo HTTP {$code}: " . mb_substr( wp_strip_all_tags( (string) wp_remote_retrieve_body( $res ) ), 0, 200 ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( "API vrátilo neplatný JSON ({$path})." );
		}
		return $data;
	}
}
