<?php
/**
 * Cache rozvrhu.
 *
 * - Čerstvá data: transient (TTL 15–30 min, HARMONY_ROZVRH_TTL).
 * - Poslední úspěšná data: option bez autoloadu → fallback při výpadku.
 * - Po vypršení cache se návštěvníkovi hned vrátí poslední data a obnova
 *   proběhne na pozadí přes WP-Cron (stale-while-revalidate). Synchronně se
 *   memberzone volá jen tehdy, když neexistují vůbec žádná data.
 * - Zámek zabrání souběžnému scrapování, backoff opakovaným pokusům při výpadku.
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_Repository {

	public const CRON_HOOK = 'harmony_rozvrh_refresh';

	private const KEY_FRESH   = 'harmony_rozvrh_v1';
	private const KEY_LOCK    = 'harmony_rozvrh_lock';
	private const KEY_BACKOFF = 'harmony_rozvrh_backoff';
	private const OPT_LAST    = 'harmony_rozvrh_last_good';
	private const OPT_ERROR   = 'harmony_rozvrh_last_error';
	private const BACKOFF     = 300;

	/**
	 * @return array{data:?array,stale:bool}
	 *   data = ['aktualizovano'=>int, 'tydny'=>[['tyden'=>0,'od'=>'Y-m-d','do'=>'Y-m-d','lekce'=>[…]], …]]
	 */
	public static function get(): array {
		$fresh = get_transient( self::KEY_FRESH );
		if ( is_array( $fresh ) ) {
			return array( 'data' => $fresh, 'stale' => false );
		}

		$last = get_option( self::OPT_LAST );
		if ( is_array( $last ) ) {
			self::schedule_refresh();
			return array( 'data' => $last, 'stale' => true );
		}

		// Studený start: zatím nic nemáme, zkusíme to hned.
		self::refresh();
		$fresh = get_transient( self::KEY_FRESH );
		return array( 'data' => is_array( $fresh ) ? $fresh : null, 'stale' => false );
	}

	public static function week( int $index ): ?array {
		$res = self::get();
		foreach ( $res['data']['tydny'] ?? array() as $w ) {
			if ( (int) $w['tyden'] === $index ) {
				return $w;
			}
		}
		return null;
	}

	/** Stáhne a uloží rozvrh. Volá WP-Cron, WP-CLI, případně studený start. */
	public static function refresh( bool $force = false ): bool {
		if ( ! $force && ( get_transient( self::KEY_LOCK ) || get_transient( self::KEY_BACKOFF ) ) ) {
			return false;
		}
		set_transient( self::KEY_LOCK, 1, 120 );

		try {
			$client = new Harmony_Rozvrh_Memberzone_Client();
			$pages  = $client->fetch_weeks( Harmony_Rozvrh_Config::weeks_ahead() );
			$data   = self::build( $pages );

			set_transient( self::KEY_FRESH, $data, Harmony_Rozvrh_Config::ttl() );
			update_option( self::OPT_LAST, $data, false );
			delete_option( self::OPT_ERROR );
			delete_transient( self::KEY_BACKOFF );
			return true;
		} catch ( Throwable $e ) {
			update_option(
				self::OPT_ERROR,
				array(
					'cas'   => time(),
					'zprava' => $e->getMessage(),
				),
				false
			);
			set_transient( self::KEY_BACKOFF, 1, self::BACKOFF );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[harmony-rozvrh] ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
			if ( $force ) {
				throw $e instanceof RuntimeException ? $e : new RuntimeException( $e->getMessage(), 0, $e );
			}
			return false;
		} finally {
			delete_transient( self::KEY_LOCK );
		}
	}

	/** @param array<int,string> $pages */
	public static function build( array $pages ): array {
		$tz    = new DateTimeZone( 'Europe/Prague' );
		$weeks = array();
		$prev  = null;

		foreach ( $pages as $i => $html ) {
			$lessons = Harmony_Rozvrh_Parser::parse( $html );
			foreach ( $lessons as &$l ) {
				$l['skupiny'] = Harmony_Rozvrh_Categories::for_lesson( $l['nazev'] );
			}
			unset( $l );

			if ( $lessons ) {
				$monday = ( new DateTimeImmutable( $lessons[0]['datum'], $tz ) )->modify( 'monday this week' );
			} elseif ( $prev ) {
				$monday = $prev->modify( '+7 days' );
			} else {
				$monday = ( new DateTimeImmutable( 'now', $tz ) )->modify( 'monday this week' )->setTime( 0, 0 );
			}

			// Kontrola, že btnNext opravdu posunul týden (jinak by se data duplikovala).
			if ( $prev && $monday <= $prev ) {
				break;
			}
			if ( 0 === $i && ! $lessons ) {
				// Aktuální týden bez jediné lekce je podezřelý – raději neukládat a nechat starou cache.
				throw new RuntimeException( 'Aktuální týden neobsahuje žádné lekce – parser možná nesedí na šablonu.' );
			}

			$weeks[] = array(
				'tyden' => (int) $i,
				'od'    => $monday->format( 'Y-m-d' ),
				'do'    => $monday->modify( '+6 days' )->format( 'Y-m-d' ),
				'lekce' => $lessons,
			);
			$prev = $monday;
		}

		return array(
			'aktualizovano' => time(),
			'tydny'         => $weeks,
		);
	}

	private static function schedule_refresh(): void {
		if ( get_transient( self::KEY_LOCK ) || get_transient( self::KEY_BACKOFF ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
	}

	/** Data bez spuštění obnovy (pro status). */
	public static function get_cached_only(): ?array {
		$d = get_transient( self::KEY_FRESH ) ?: get_option( self::OPT_LAST );
		return is_array( $d ) ? $d : null;
	}

	public static function last_error(): ?array {
		$e = get_option( self::OPT_ERROR );
		return is_array( $e ) ? $e : null;
	}

	public static function purge(): void {
		delete_transient( self::KEY_FRESH );
		delete_transient( self::KEY_LOCK );
		delete_transient( self::KEY_BACKOFF );
		delete_option( self::OPT_LAST );
		delete_option( self::OPT_ERROR );
		( new Harmony_Rozvrh_Memberzone_Client() )->clear_session();
	}
}
