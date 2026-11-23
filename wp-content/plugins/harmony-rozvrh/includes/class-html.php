<?php
/**
 * Práce s HTML stránkami ASP.NET WebForms: načtení DOM, sběr polí formuláře
 * (__VIEWSTATE atd.) a rozpoznání přihlašovacího formuláře.
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_Html {

	public static function dom( string $html ): DOMDocument {
		$doc  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		// Prefix vynutí UTF-8 i u HTML bez meta charset.
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $doc;
	}

	/**
	 * Všechna pole, která by prohlížeč odeslal při postbacku (bez tlačítek
	 * a nezaškrtnutých checkboxů). Tím se přenesou __VIEWSTATE,
	 * __VIEWSTATEGENERATOR, __EVENTVALIDATION i případná další pole.
	 *
	 * @return array<string,string>
	 */
	public static function form_fields( DOMDocument $doc ): array {
		$xp     = new DOMXPath( $doc );
		$fields = array();

		foreach ( $xp->query( '//form//input[@name]' ) as $in ) {
			/** @var DOMElement $in */
			$type = strtolower( $in->getAttribute( 'type' ) ?: 'text' );
			if ( in_array( $type, array( 'submit', 'button', 'image', 'reset', 'file' ), true ) ) {
				continue;
			}
			if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $in->hasAttribute( 'checked' ) ) {
				continue;
			}
			$fields[ $in->getAttribute( 'name' ) ] = $in->getAttribute( 'value' );
		}
		foreach ( $xp->query( '//form//select[@name]' ) as $sel ) {
			/** @var DOMElement $sel */
			$opt = $xp->query( './/option[@selected]', $sel )->item( 0 ) ?: $xp->query( './/option', $sel )->item( 0 );
			if ( $opt instanceof DOMElement ) {
				$fields[ $sel->getAttribute( 'name' ) ] = $opt->hasAttribute( 'value' ) ? $opt->getAttribute( 'value' ) : trim( $opt->textContent );
			}
		}
		foreach ( $xp->query( '//form//textarea[@name]' ) as $ta ) {
			/** @var DOMElement $ta */
			$fields[ $ta->getAttribute( 'name' ) ] = $ta->textContent;
		}
		return $fields;
	}

	/** Je na stránce přihlašovací formulář (= nejsme přihlášeni)? */
	public static function is_login_page( DOMDocument $doc ): bool {
		return ( new DOMXPath( $doc ) )->query( '//input[translate(@type,"PASWORD","pasword")="password"]' )->length > 0;
	}

	/**
	 * Najde pole přihlašovacího formuláře. Lze přepsat konstantami
	 * HARMONY_MZ_LOGIN_* (viz README), kdyby autodetekce netrefila.
	 *
	 * @return array{user:string,pass:string,submit:?array{0:string,1:string},eventtarget:string}
	 */
	public static function login_fields( DOMDocument $doc ): array {
		$xp     = new DOMXPath( $doc );
		$inputs = iterator_to_array( $xp->query( '//form//input[@name] | //form//button[@name] | //form//a[contains(@href,"__doPostBack")]' ) );

		$pass_idx = null;
		foreach ( $inputs as $i => $el ) {
			if ( 'input' === $el->nodeName && 'password' === strtolower( $el->getAttribute( 'type' ) ) ) {
				$pass_idx = $i;
				break;
			}
		}

		$user = $pass = $eventtarget = '';
		$submit = null;

		if ( null !== $pass_idx ) {
			$pass = $inputs[ $pass_idx ]->getAttribute( 'name' );
			// Uživatelské jméno = poslední textové/e-mailové pole před heslem.
			for ( $i = $pass_idx - 1; $i >= 0; $i-- ) {
				$el = $inputs[ $i ];
				if ( 'input' === $el->nodeName && in_array( strtolower( $el->getAttribute( 'type' ) ?: 'text' ), array( 'text', 'email', 'tel' ), true ) ) {
					$user = $el->getAttribute( 'name' );
					break;
				}
			}
			// Odeslání = první submit / tlačítko / postback odkaz za heslem.
			$count = count( $inputs );
			for ( $i = $pass_idx + 1; $i < $count; $i++ ) {
				$el   = $inputs[ $i ];
				$type = strtolower( $el->getAttribute( 'type' ) );
				if ( 'input' === $el->nodeName && in_array( $type, array( 'submit', 'image' ), true ) ) {
					$submit = array( $el->getAttribute( 'name' ), $el->getAttribute( 'value' ) );
					break;
				}
				if ( 'button' === $el->nodeName && in_array( $type, array( '', 'submit' ), true ) ) {
					$submit = array( $el->getAttribute( 'name' ), $el->getAttribute( 'value' ) );
					break;
				}
				if ( 'a' === $el->nodeName && preg_match( "/__doPostBack\\(\\s*'([^']+)'/", $el->getAttribute( 'href' ), $m ) ) {
					$eventtarget = html_entity_decode( $m[1] );
					break;
				}
			}
		}

		// Ruční přepsání má přednost.
		$user        = Harmony_Rozvrh_Config::get( 'HARMONY_MZ_LOGIN_USER_FIELD' ) ?: $user;
		$pass        = Harmony_Rozvrh_Config::get( 'HARMONY_MZ_LOGIN_PASS_FIELD' ) ?: $pass;
		$eventtarget = Harmony_Rozvrh_Config::get( 'HARMONY_MZ_LOGIN_EVENTTARGET' ) ?: $eventtarget;
		$submit_name = Harmony_Rozvrh_Config::get( 'HARMONY_MZ_LOGIN_SUBMIT' );
		if ( $submit_name ) {
			$btn    = $xp->query( '//*[@name=' . self::xpath_literal( $submit_name ) . ']' )->item( 0 );
			$submit = array( $submit_name, $btn instanceof DOMElement ? $btn->getAttribute( 'value' ) : '' );
		}
		if ( $eventtarget ) {
			$submit = null;
		}

		return compact( 'user', 'pass', 'submit', 'eventtarget' );
	}

	public static function xpath_literal( string $s ): string {
		if ( false === strpos( $s, "'" ) ) {
			return "'" . $s . "'";
		}
		if ( false === strpos( $s, '"' ) ) {
			return '"' . $s . '"';
		}
		return "concat('" . str_replace( "'", "',\"'\",'", $s ) . "')";
	}
}
