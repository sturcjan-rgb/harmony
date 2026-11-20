<?php
/**
 * Skupiny lekcí (barvy a filtry). memberzone kategorie neposílá, proto se
 * přiřazují podle názvu lekce. Mapu lze upravit filtrem
 * `harmony_rozvrh_kategorie_lekci` (název → [skupiny]).
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_Categories {

	public const GROUPS = array(
		'cardio' => 'Kardio',
		'sila'   => 'Síla',
		'mind'   => 'Klid a mobilita',
		'boj'    => 'Jiu-jitsu',
		'deti'   => 'Děti',
	);

	/** Převzato z prototypu (rozvrh-varianty.html). Klíče malými písmeny. */
	private const MAP = array(
		'jumping'           => array( 'cardio' ),
		'spinning'          => array( 'cardio' ),
		'fit aerobic'       => array( 'cardio' ),
		'fitbox'            => array( 'cardio', 'sila' ),
		'problémové partie' => array( 'sila', 'cardio' ),
		'total body'        => array( 'sila', 'cardio' ),
		'kruhový trénink'   => array( 'sila', 'cardio' ),
		'kruháč'            => array( 'sila', 'cardio' ),
		'bodyform'          => array( 'sila' ),
		'břicho a hýždě'    => array( 'sila' ),
		'barre'             => array( 'sila', 'mind' ),
		'pilates'           => array( 'mind', 'sila' ),
		'power pilates'     => array( 'mind', 'sila' ),
		'ball pilates'      => array( 'mind', 'sila' ),
		'power jóga'        => array( 'mind', 'sila' ),
		'jóga'              => array( 'mind' ),
		'zdravá záda'       => array( 'mind' ),
		'zdravé cvičení'    => array( 'mind' ),
		'sm systém'         => array( 'mind' ),
	);

	/** @return string[] první = hlavní barva; prázdné = nezařazeno */
	public static function for_lesson( string $name ): array {
		$n = mb_strtolower( trim( $name ) );

		if ( preg_match( '/dět|děti|kroužek|kids|junior/u', $n ) ) {
			return array( 'deti' );
		}
		if ( preg_match( '/jiu|bjj|grappling/u', $n ) ) {
			return array( 'boj' );
		}

		$map = apply_filters( 'harmony_rozvrh_kategorie_lekci', self::MAP );
		if ( isset( $map[ $n ] ) ) {
			return $map[ $n ];
		}
		// Nejdelší shoda prefixu („Kruháč 2“ → „kruháč“, „Pilates s Hankou“ → „pilates“).
		$best = null;
		foreach ( $map as $key => $groups ) {
			if ( 0 === strpos( $n, $key ) && ( null === $best || strlen( $key ) > strlen( $best ) ) ) {
				$best = $key;
			}
		}
		return null !== $best ? $map[ $best ] : array(); // neznámá lekce → neutrální barva
	}
}
