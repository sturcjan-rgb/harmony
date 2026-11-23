<?php
/**
 * WP-CLI: ladění a ruční obnova.
 *
 *   wp harmony-rozvrh status            stav cache a poslední chyba
 *   wp harmony-rozvrh refresh           stáhne rozvrh hned (ignoruje zámek i backoff)
 *   wp harmony-rozvrh debug [--dir=…]   uloží syrové HTML týdnů + vypíše naparsované lekce
 *   wp harmony-rozvrh parse <soubor>    naparsuje uložené HTML (ladění selektorů offline)
 *   wp harmony-rozvrh purge             smaže cache i uloženou session
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_CLI {

	public function status(): void {
		$res = Harmony_Rozvrh_Repository::get_cached_only();
		WP_CLI::log( 'Přihlašovací údaje: ' . ( Harmony_Rozvrh_Config::has_credentials() ? 'nastaveny' : 'CHYBÍ' ) );
		if ( $res ) {
			WP_CLI::log( 'Poslední data: ' . wp_date( 'j. n. Y H:i', (int) $res['aktualizovano'] ) );
			foreach ( $res['tydny'] as $w ) {
				WP_CLI::log( sprintf( '  týden %d (%s – %s): %d lekcí', $w['tyden'], $w['od'], $w['do'], count( $w['lekce'] ) ) );
			}
		} else {
			WP_CLI::log( 'Poslední data: žádná' );
		}
		$err = Harmony_Rozvrh_Repository::last_error();
		if ( $err ) {
			WP_CLI::warning( 'Poslední chyba (' . wp_date( 'j. n. H:i', $err['cas'] ) . '): ' . $err['zprava'] );
		}
	}

	public function refresh(): void {
		try {
			Harmony_Rozvrh_Repository::refresh( true );
			WP_CLI::success( 'Rozvrh obnoven.' );
			$this->status();
		} catch ( Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * ## OPTIONS
	 * [--dir=<dir>]
	 * : Kam uložit HTML. Výchozí je systémový temp (ne veřejný adresář webu – stránka může obsahovat jméno servisního účtu).
	 */
	public function debug( $args, $assoc ): void {
		$dir = rtrim( $assoc['dir'] ?? sys_get_temp_dir(), '/' );
		$c   = new Harmony_Rozvrh_Memberzone_Client();
		try {
			$pages = $c->fetch_weeks( Harmony_Rozvrh_Config::weeks_ahead() );
		} catch ( Throwable $e ) {
			array_map( array( 'WP_CLI', 'log' ), $c->log );
			WP_CLI::error( $e->getMessage() );
		}
		array_map( array( 'WP_CLI', 'log' ), $c->log );
		foreach ( $pages as $i => $html ) {
			$file = "{$dir}/harmony-rozvrh-tyden-{$i}.html";
			file_put_contents( $file, $html );
			WP_CLI::log( "Uloženo: {$file}" );
		}
		$data = Harmony_Rozvrh_Repository::build( $pages );
		WP_CLI::log( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * ## OPTIONS
	 * <file>
	 * : Cesta k uloženému HTML stránky StudioveHodiny.aspx.
	 */
	public function parse( $args ): void {
		$html = file_get_contents( $args[0] );
		if ( false === $html ) {
			WP_CLI::error( 'Soubor nejde přečíst.' );
		}
		try {
			$lessons = Harmony_Rozvrh_Parser::parse( $html );
		} catch ( Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		WP_CLI\Utils\format_items( 'table', $lessons, array( 'id', 'datum', 'cas', 'delka_min', 'nazev', 'sal', 'instruktor', 'obsazeno', 'kapacita', 'cena' ) );
	}

	public function purge(): void {
		Harmony_Rozvrh_Repository::purge();
		WP_CLI::success( 'Cache i session smazány.' );
	}
}
