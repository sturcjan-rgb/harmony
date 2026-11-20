<?php
/**
 * HTTP klient pro memberzone.cz: přihlášení (ASP.NET forms auth), cookie jar,
 * stažení rozvrhu a přepínání týdnů přes __doPostBack('btnNext','').
 *
 * ▶ Celá přihlašovací logika je v této třídě (login(), ensure_schedule_page()).
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_Memberzone_Client {

	private const SESSION_KEY  = 'harmony_rozvrh_mz_session';
	private const SESSION_TTL  = 1800;          // jak dlouho držet cookies (memberzone si je může zneplatnit dřív – pak se jen znovu přihlásíme)
	private const MAX_REDIRECT = 5;
	private const NEXT_TARGET_REGEX = "/__doPostBack\\(\\s*(?:'|&#39;|&quot;)([^'\"&]*btnNext)(?:'|&#39;|&quot;)/";
	private const USER_AGENT   = 'HarmonyRozvrh/1.0 (WordPress; harmonypisek.cz)';

	/** @var array<string,string> */
	private $cookies = array();

	/** @var string[] průběh pro ladění (bez citlivých údajů) */
	public $log = array();

	public function __construct() {
		$this->cookies = $this->load_session();
	}

	/**
	 * Stáhne aktuální týden + $weeks_ahead týdnů dopředu.
	 *
	 * @return array<int,string> index týdne (0 = aktuální) → HTML
	 * @throws RuntimeException
	 */
	public function fetch_weeks( int $weeks_ahead ): array {
		if ( ! Harmony_Rozvrh_Config::has_credentials() ) {
			throw new RuntimeException( 'Chybí HARMONY_MZ_USER / HARMONY_MZ_PASS ve wp-config.php nebo v env.' );
		}

		$schedule_url = Harmony_Rozvrh_Config::url( 'HARMONY_MZ_SCHEDULE_PATH' );
		$pages        = array( 0 => $this->ensure_schedule_page( $schedule_url ) );

		for ( $i = 1; $i <= $weeks_ahead; $i++ ) {
			$prev   = $pages[ $i - 1 ];
			$target = preg_match( self::NEXT_TARGET_REGEX, $prev, $m ) ? html_entity_decode( $m[1] ) : 'btnNext';
			$html   = $this->postback( $schedule_url, $prev, $target );

			if ( Harmony_Rozvrh_Html::is_login_page( Harmony_Rozvrh_Html::dom( $html ) ) ) {
				throw new RuntimeException( "Session vypršela při přepnutí na týden +{$i}." );
			}
			$pages[ $i ] = $html;
		}

		$this->save_session();
		return $pages;
	}

	/** Načte stránku rozvrhu; když je session neplatná, přihlásí se a zkusí znovu. */
	private function ensure_schedule_page( string $schedule_url ): string {
		list( $html, $final_url ) = $this->request( 'GET', $schedule_url );
		$doc = Harmony_Rozvrh_Html::dom( $html );

		if ( ! Harmony_Rozvrh_Html::is_login_page( $doc ) ) {
			$this->log[] = 'Session z cache platí.';
			return $html;
		}

		$this->log[]   = 'Nepřihlášeno → login.';
		$this->cookies = array_intersect_key( $this->cookies, array( 'ASP.NET_SessionId' => 1 ) );
		$this->login( $doc, $final_url );

		list( $html ) = $this->request( 'GET', $schedule_url );
		if ( Harmony_Rozvrh_Html::is_login_page( Harmony_Rozvrh_Html::dom( $html ) ) ) {
			$this->clear_session();
			throw new RuntimeException( 'Přihlášení se nezdařilo (po loginu je pořád přihlašovací formulář). Zkontrolujte údaje servisního účtu, případně HARMONY_MZ_LOGIN_* konstanty.' );
		}
		return $html;
	}

	/**
	 * ASP.NET forms login: tokeny (__VIEWSTATE, __VIEWSTATEGENERATOR,
	 * __EVENTVALIDATION …) se berou z právě načteného login formuláře.
	 */
	private function login( DOMDocument $login_doc, string $login_url ): void {
		$f = Harmony_Rozvrh_Html::login_fields( $login_doc );
		if ( '' === $f['user'] || '' === $f['pass'] ) {
			throw new RuntimeException( 'Nenašel jsem pole pro jméno/heslo na přihlašovací stránce. Nastavte HARMONY_MZ_LOGIN_USER_FIELD / _PASS_FIELD.' );
		}

		$body              = Harmony_Rozvrh_Html::form_fields( $login_doc );
		$body[ $f['user'] ] = Harmony_Rozvrh_Config::username();
		$body[ $f['pass'] ] = Harmony_Rozvrh_Config::password();
		if ( $f['eventtarget'] ) {
			$body['__EVENTTARGET']   = $f['eventtarget'];
			$body['__EVENTARGUMENT'] = '';
		} elseif ( $f['submit'] && '' !== $f['submit'][0] ) {
			$body[ $f['submit'][0] ] = $f['submit'][1];
		}

		$this->log[] = sprintf( 'Login POST (pole: %s, %s, odeslání: %s).', $f['user'], $f['pass'], $f['eventtarget'] ?: ( $f['submit'][0] ?? '–' ) );
		$this->request( 'POST', $this->form_action( $login_doc, $login_url ), $body, $login_url );
	}

	/** Postback se všemi poli formuláře – stejně jako prohlížeč. */
	private function postback( string $url, string $page_html, string $event_target ): string {
		$doc  = Harmony_Rozvrh_Html::dom( $page_html );
		$body = Harmony_Rozvrh_Html::form_fields( $doc );
		$body['__EVENTTARGET']   = $event_target;
		$body['__EVENTARGUMENT'] = '';

		$this->log[] = "Postback {$event_target}.";
		list( $html ) = $this->request( 'POST', $this->form_action( $doc, $url ), $body, $url );
		return $html;
	}

	/**
	 * HTTP s ručním sledováním přesměrování, aby se neztratily cookies
	 * nastavené na 302 odpovědi po přihlášení.
	 *
	 * @return array{0:string,1:string} [HTML, finální URL]
	 */
	private function request( string $method, string $url, array $body = array(), string $referer = '' ): array {
		for ( $hop = 0; $hop <= self::MAX_REDIRECT; $hop++ ) {
			$args = array(
				'method'      => $method,
				'timeout'     => (int) Harmony_Rozvrh_Config::get( 'HARMONY_ROZVRH_TIMEOUT' ),
				'redirection' => 0,
				'user-agent'  => self::USER_AGENT,
				'cookies'     => $this->cookies,
				'headers'     => array_filter(
					array(
						'Accept'          => 'text/html,application/xhtml+xml',
						'Accept-Language' => 'cs-CZ,cs;q=0.9',
						'Referer'         => $referer,
					)
				),
			);
			if ( 'POST' === $method ) {
				$args['body'] = $body;
			}

			$res = wp_remote_request( $url, $args );
			if ( is_wp_error( $res ) ) {
				throw new RuntimeException( 'HTTP chyba: ' . $res->get_error_message() );
			}
			$this->absorb_cookies( $res );

			$code = (int) wp_remote_retrieve_response_code( $res );
			if ( $code >= 300 && $code < 400 ) {
				$location = wp_remote_retrieve_header( $res, 'location' );
				if ( ! $location ) {
					throw new RuntimeException( "Přesměrování {$code} bez Location." );
				}
				$referer = $url;
				$url     = $this->resolve( is_array( $location ) ? end( $location ) : $location, $url );
				$method  = 'GET';
				$body    = array();
				continue;
			}
			if ( $code >= 400 ) {
				throw new RuntimeException( "memberzone vrátil HTTP {$code}." );
			}
			return array( (string) wp_remote_retrieve_body( $res ), $url );
		}
		throw new RuntimeException( 'Příliš mnoho přesměrování.' );
	}

	private function absorb_cookies( $res ): void {
		foreach ( wp_remote_retrieve_cookies( $res ) as $c ) {
			/** @var WP_Http_Cookie $c */
			$expired = ! empty( $c->expires ) && (int) $c->expires < time();
			if ( $expired || '' === (string) $c->value ) {
				unset( $this->cookies[ $c->name ] );
			} else {
				$this->cookies[ $c->name ] = (string) $c->value;
			}
		}
	}

	private function form_action( DOMDocument $doc, string $page_url ): string {
		$form   = ( new DOMXPath( $doc ) )->query( '//form' )->item( 0 );
		$action = $form instanceof DOMElement ? html_entity_decode( $form->getAttribute( 'action' ) ) : '';
		return '' === $action ? $page_url : $this->resolve( $action, $page_url );
	}

	private function resolve( string $href, string $base ): string {
		if ( preg_match( '#^https?://#i', $href ) ) {
			return $href;
		}
		$p    = wp_parse_url( $base );
		$root = $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
		if ( 0 === strpos( $href, '/' ) ) {
			return $root . $href;
		}
		$dir = isset( $p['path'] ) ? preg_replace( '#/[^/]*$#', '/', $p['path'] ) : '/';
		$href = preg_replace( '#^\./#', '', $href );
		return $root . $dir . $href;
	}

	/* ---------- cache session cookies (šifrovaně) ---------- */

	private function load_session(): array {
		$raw = get_transient( self::SESSION_KEY );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$json = self::decrypt( $raw );
		$data = $json ? json_decode( $json, true ) : null;
		return is_array( $data ) ? $data : array();
	}

	private function save_session(): void {
		if ( $this->cookies ) {
			set_transient( self::SESSION_KEY, self::encrypt( wp_json_encode( $this->cookies ) ), self::SESSION_TTL );
		}
	}

	public function clear_session(): void {
		$this->cookies = array();
		delete_transient( self::SESSION_KEY );
	}

	private static function key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . '|harmony-rozvrh', true );
	}

	private static function encrypt( string $plain ): string {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return ''; // bez libsodium session necachujeme – jen se pokaždé přihlásí
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) );
	}

	private static function decrypt( string $packed ): ?string {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return null;
		}
		$bin = base64_decode( $packed, true );
		if ( false === $bin || strlen( $bin ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$plain = sodium_crypto_secretbox_open(
			substr( $bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			substr( $bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			self::key()
		);
		return false === $plain ? null : $plain;
	}
}
