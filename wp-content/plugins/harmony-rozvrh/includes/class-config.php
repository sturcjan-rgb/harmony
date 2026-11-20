<?php
/**
 * Konfigurace. Vše se čte z konstant ve wp-config.php, případně z env proměnných
 * se stejným názvem. Přihlašovací údaje nikdy nejsou v kódu ani v DB.
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_Config {

	/** Výchozí hodnoty pro nepovinná nastavení. */
	private const DEFAULTS = array(
		'HARMONY_MZ_BASE_URL'        => 'https://memberzone.cz/HARMONYpisek/',
		'HARMONY_MZ_LOGIN_PATH'      => 'Uvod.aspx',
		'HARMONY_MZ_SCHEDULE_PATH'   => 'StudioveHodiny.aspx',
		'HARMONY_MZ_RESERVATION_URL' => 'https://memberzone.cz/HARMONYpisek/Uvod.aspx',
		'HARMONY_ROZVRH_WEEKS_AHEAD' => 2,     // kolik týdnů dopředu kromě aktuálního (0–4)
		'HARMONY_ROZVRH_TTL'         => 1200,  // s; doba platnosti cache (15–30 min)
		'HARMONY_ROZVRH_TIMEOUT'     => 15,    // s; timeout jednoho HTTP požadavku
		'HARMONY_ROZVRH_REST'        => true,  // vypnout REST endpoint: false
		// Přepsání autodetekce přihlašovacího formuláře (jen když autodetekce selže):
		'HARMONY_MZ_LOGIN_USER_FIELD'  => '',
		'HARMONY_MZ_LOGIN_PASS_FIELD'  => '',
		'HARMONY_MZ_LOGIN_SUBMIT'      => '',  // name submit tlačítka
		'HARMONY_MZ_LOGIN_EVENTTARGET' => '',  // když se přihlašuje přes __doPostBack('X','')
	);

	public static function get( string $name ) {
		if ( defined( $name ) ) {
			return constant( $name );
		}
		$env = getenv( $name );
		if ( false !== $env && '' !== $env ) {
			return $env;
		}
		return self::DEFAULTS[ $name ] ?? null;
	}

	public static function username(): string {
		return (string) self::get( 'HARMONY_MZ_USER' );
	}

	public static function password(): string {
		return (string) self::get( 'HARMONY_MZ_PASS' );
	}

	public static function has_credentials(): bool {
		return '' !== self::username() && '' !== self::password();
	}

	public static function url( string $path_key ): string {
		return trailingslashit( (string) self::get( 'HARMONY_MZ_BASE_URL' ) ) . ltrim( (string) self::get( $path_key ), '/' );
	}

	public static function weeks_ahead(): int {
		return max( 0, min( 4, (int) self::get( 'HARMONY_ROZVRH_WEEKS_AHEAD' ) ) );
	}

	public static function ttl(): int {
		return max( 300, (int) self::get( 'HARMONY_ROZVRH_TTL' ) );
	}

	public static function rest_enabled(): bool {
		return filter_var( self::get( 'HARMONY_ROZVRH_REST' ), FILTER_VALIDATE_BOOLEAN );
	}
}
