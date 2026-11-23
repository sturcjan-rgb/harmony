<?php
/**
 * Parser stránky StudioveHodiny.aspx → pole normalizovaných lekcí.
 *
 * ▶ Všechny selektory a regexy závislé na HTML memberzone jsou v konstantě
 *   SELECTORS níže. Když memberzone změní šablonu, opravuje se primárně tady.
 *   Pro ladění: `wp harmony-rozvrh debug` uloží syrové HTML a vypíše výsledek.
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_Parser {

	public const SELECTORS = array(
		// Skryté inputy jedné lekce: name="ctl00$...$ctl05$hfID_Lekce" atd.
		// Prefix před „hfID_Lekce“ spojuje inputy stejné lekce.
		'id_field'       => 'hfID_Lekce',
		'hidden_fields'  => array(
			'id'           => 'hfID_Lekce',
			'start'        => 'hfDatum2',
			'duration'     => 'hfDelka',
			'price'        => 'hfCena',
			'capacity'     => 'hfKapacita',
			'booked'       => 'hfPocetRezervaci',
			'room_id'      => 'hfID_Sportoviste',
			'instructor'   => 'hfInstruktor',
			'description'  => 'hfPopis_www',
		),
		'start_format'   => 'j.n.Y G:i:s',          // "20.11.2026 16:00:00"
		'duration_regex' => '/(\d{1,2}):(\d{2})(?::\d{2})?\s*$/', // "30.12.1899 0:55:00" → 0 h 55 min

		// Volitelné XPath výrazy relativně ke kartě lekce. Když jsou null,
		// použije se heuristika nad textem karty (viz parse_card_text()).
		// Po zjištění skutečné šablony je nejspolehlivější je sem doplnit,
		// např. './/span[contains(@id,"lblNazev")]'.
		'xpath_name'       => null,
		'xpath_room'       => null,
		'xpath_instructor' => null,
		'xpath_occupancy'  => null,

		// Regexy pro text karty.
		'room_regex'      => '/\b(Sál\s*\d+|Open\s*gym|Posilovna|Spinning\s*sál)\b/iu',
		'occupancy_regex' => '/\b(\d{1,3})\s*\/\s*(\d{1,3})\b/u',
		'time_regex'      => '/^\d{1,2}:\d{2}(\s*[-–]\s*\d{1,2}:\d{2})?$/u',
		'date_regex'      => '/^(\p{L}{2,}\s*)?\d{1,2}\.\s*\d{1,2}\.(\s*\d{4})?$/u',
		'noise_regex'     => '/^(Rezervovat|Rezervace|Zrušit|Náhradník|Detail|Obsazeno|Plno|Volno|Kč|\d+\s*Kč|Cena.*|Kapacita.*|Lektor:?|Instruktor:?)$/iu',
	);

	private const TZ = 'Europe/Prague';

	/**
	 * @return array<int,array<string,mixed>> lekce seřazené podle začátku
	 * @throws RuntimeException když stránka nevypadá jako rozvrh
	 */
	public static function parse( string $html ): array {
		$doc = Harmony_Rozvrh_Html::dom( $html );
		$xp  = new DOMXPath( $doc );
		$sel = self::SELECTORS;

		$id_inputs = $xp->query( '//input[substring(@name, string-length(@name) - ' . ( strlen( $sel['id_field'] ) - 1 ) . ') = ' . Harmony_Rozvrh_Html::xpath_literal( $sel['id_field'] ) . ']' );

		$lessons = array();
		foreach ( $id_inputs as $id_input ) {
			/** @var DOMElement $id_input */
			$prefix = substr( $id_input->getAttribute( 'name' ), 0, -strlen( $sel['id_field'] ) );
			$hf     = self::hidden_values( $xp, $prefix );
			$card   = self::card_element( $id_input, $sel['id_field'] );
			$lesson = self::build( $hf, $card, $xp );
			if ( $lesson ) {
				$lessons[ $lesson['id'] . '@' . $lesson['zacatek'] ] = $lesson;
			}
		}

		if ( ! $lessons && ! self::looks_like_schedule( $xp ) ) {
			throw new RuntimeException( 'Stránka neobsahuje rozvrh (žádné ' . $sel['id_field'] . ' ani navigaci týdnů). Změnila se šablona memberzone?' );
		}

		$lessons = array_values( $lessons );
		usort( $lessons, static fn( $a, $b ) => strcmp( $a['zacatek'], $b['zacatek'] ) ?: strcmp( (string) $a['sal'], (string) $b['sal'] ) );
		return $lessons;
	}

	/** Stránka rozvrhu bez lekcí (např. prázdný týden) má aspoň šipky týdnů. */
	public static function looks_like_schedule( DOMXPath $xp ): bool {
		return $xp->query( '//*[contains(@href,"btnNext") or contains(@onclick,"btnNext") or @name="btnNext" or @id="btnNext"]' )->length > 0;
	}

	/** @return array<string,string> klíč = sémantický název (id, start, …) */
	private static function hidden_values( DOMXPath $xp, string $prefix ): array {
		$out = array();
		foreach ( self::SELECTORS['hidden_fields'] as $key => $suffix ) {
			$node        = $xp->query( '//input[@name=' . Harmony_Rozvrh_Html::xpath_literal( $prefix . $suffix ) . ']' )->item( 0 );
			$out[ $key ] = $node instanceof DOMElement ? trim( $node->getAttribute( 'value' ) ) : '';
		}
		return $out;
	}

	/**
	 * Karta lekce = nejvyšší předek, který obsahuje právě jeden hfID_Lekce.
	 */
	private static function card_element( DOMElement $id_input, string $id_field ): DOMElement {
		$card = $id_input->parentNode instanceof DOMElement ? $id_input->parentNode : $id_input;
		while ( $card->parentNode instanceof DOMElement && 'form' !== $card->parentNode->nodeName && 'body' !== $card->parentNode->nodeName ) {
			$xp    = new DOMXPath( $card->ownerDocument );
			$count = $xp->query( './/input[contains(@name,' . Harmony_Rozvrh_Html::xpath_literal( $id_field ) . ')]', $card->parentNode )->length;
			if ( $count > 1 ) {
				break;
			}
			$card = $card->parentNode;
		}
		return $card;
	}

	private static function build( array $hf, DOMElement $card, DOMXPath $xp ): ?array {
		$sel = self::SELECTORS;
		$tz  = new DateTimeZone( self::TZ );

		$id = (int) preg_replace( '/\D/', '', $hf['id'] );
		if ( ! $id || '' === $hf['start'] ) {
			return null;
		}
		$start = DateTimeImmutable::createFromFormat( '!' . $sel['start_format'], $hf['start'], $tz );
		if ( ! $start ) {
			// Tolerance: chybějící sekundy apod.
			try {
				$start = new DateTimeImmutable( str_replace( '. ', '.', $hf['start'] ), $tz );
			} catch ( Exception $e ) {
				return null;
			}
		}

		$duration = 0;
		if ( preg_match( $sel['duration_regex'], $hf['duration'], $m ) ) {
			$duration = (int) $m[1] * 60 + (int) $m[2];
		}
		if ( $duration <= 0 ) {
			$duration = 55;
		}
		$end = $start->modify( '+' . $duration . ' minutes' );

		$text = self::parse_card_text( $card, $xp );

		$capacity = self::to_int( $hf['capacity'] );
		$booked   = self::to_int( $hf['booked'] );
		if ( null !== $text['occupancy'] ) {
			// Text karty má přednost – odpovídá tomu, co vidí člen.
			list( $booked, $capacity ) = $text['occupancy'];
		}

		$instructor = $text['instructor'];
		if ( '' === $instructor && '' !== $hf['instructor'] && ! ctype_digit( $hf['instructor'] ) ) {
			$instructor = $hf['instructor'];
		}

		$price = '' === $hf['price'] ? null : (float) str_replace( array( ' ', ',' ), array( '', '.' ), $hf['price'] );

		return array(
			'id'         => $id,
			'datum'      => $start->format( 'Y-m-d' ),
			'den'        => (int) $start->format( 'N' ), // 1 = pondělí
			'cas'        => $start->format( 'H:i' ),
			'konec'      => $end->format( 'H:i' ),
			'zacatek'    => $start->format( DATE_ATOM ),
			'delka_min'  => $duration,
			'nazev'      => $text['name'],
			'sal'        => $text['room'],
			'sal_id'     => self::to_int( $hf['room_id'] ),
			'instruktor' => $instructor,
			'kapacita'   => $capacity,
			'obsazeno'   => $booked,
			'volno'      => ( null !== $capacity && null !== $booked ) ? max( 0, $capacity - $booked ) : null,
			'cena'       => $price,
			'popis'      => wp_strip_all_tags( html_entity_decode( $hf['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
		);
	}

	/**
	 * Vytáhne z karty název, sál, instruktora a obsazenost.
	 * 1) Pokud jsou v SELECTORS vyplněné XPath výrazy, použijí se.
	 * 2) Jinak heuristika: text karty se rozdělí na úseky (texty jednotlivých
	 *    elementů), odfiltruje se čas, datum, sál, obsazenost a tlačítka;
	 *    první zbylý úsek = název, druhý = instruktor.
	 *
	 * @return array{name:string,room:string,instructor:string,occupancy:?array{0:int,1:int}}
	 */
	private static function parse_card_text( DOMElement $card, DOMXPath $xp ): array {
		$sel    = self::SELECTORS;
		$chunks = self::text_chunks( $card );
		$all    = implode( ' | ', $chunks );

		$pick = static function ( ?string $expr ) use ( $xp, $card ): ?string {
			if ( ! $expr ) {
				return null;
			}
			$n = $xp->query( $expr, $card )->item( 0 );
			return $n ? self::clean( $n->textContent ) : null;
		};

		$room = $pick( $sel['xpath_room'] );
		if ( null === $room ) {
			$room = preg_match( $sel['room_regex'], $all, $m ) ? self::normalize_room( $m[1] ) : '';
		}

		$occ_text  = $pick( $sel['xpath_occupancy'] ) ?? $all;
		$occupancy = preg_match( $sel['occupancy_regex'], $occ_text, $m ) ? array( (int) $m[1], (int) $m[2] ) : null;

		$rest = array_values(
			array_filter(
				$chunks,
				static function ( $c ) use ( $sel ) {
					return ! preg_match( $sel['time_regex'], $c )
						&& ! preg_match( $sel['date_regex'], $c )
						&& ! preg_match( $sel['occupancy_regex'], $c )
						&& ! preg_match( $sel['noise_regex'], $c )
						// úsek tvořený jen názvem sálu (např. „Sál 1“, „(Sál 2)“)
						&& mb_strlen( trim( preg_replace( $sel['room_regex'], '', $c ), " \t()[],-–|" ) ) > 0;
				}
			)
		);

		$name       = $pick( $sel['xpath_name'] ) ?? ( $rest[0] ?? '' );
		$instructor = $pick( $sel['xpath_instructor'] ) ?? ( $rest[1] ?? '' );
		$instructor = preg_replace( '/^(Lektor|Instruktor)\s*:?\s*/iu', '', $instructor );

		return array(
			'name'       => $name,
			'room'       => $room,
			'instructor' => $instructor,
			'occupancy'  => $occupancy,
		);
	}

	/** Texty jednotlivých elementů karty v pořadí dokumentu (bez skriptů a inputů). */
	private static function text_chunks( DOMElement $card ): array {
		$chunks = array();
		$walk   = static function ( DOMNode $node ) use ( &$walk, &$chunks ) {
			foreach ( $node->childNodes as $child ) {
				if ( XML_TEXT_NODE === $child->nodeType ) {
					$t = self::clean( $child->nodeValue );
					if ( '' !== $t ) {
						$chunks[] = $t;
					}
				} elseif ( XML_ELEMENT_NODE === $child->nodeType && ! in_array( $child->nodeName, array( 'script', 'style', 'input', 'select', 'option', 'noscript' ), true ) ) {
					$walk( $child );
				}
			}
		};
		$walk( $card );
		return $chunks;
	}

	private static function clean( string $s ): string {
		return trim( preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $s ) ) );
	}

	private static function normalize_room( string $room ): string {
		$room = preg_replace( '/\s+/u', ' ', trim( $room ) );
		return mb_strtoupper( mb_substr( $room, 0, 1 ) ) . mb_substr( $room, 1 );
	}

	private static function to_int( string $v ): ?int {
		$v = trim( $v );
		if ( '' === $v || ! preg_match( '/^-?\d+(?:[.,]0+)?$/', $v ) ) {
			return null;
		}
		return (int) $v;
	}
}
