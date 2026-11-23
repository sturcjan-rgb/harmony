<?php
/**
 * Test API zdroje proti mocku s reálnými testovacími daty z api.memberzone.online (tests/fixtures).
 *   php -S 127.0.0.1:8765 tests/mock/router.php &
 *   HARMONY_ROZVRH_START_DATE=2026-09-28 php tests/run-api.php
 */
require __DIR__ . '/bootstrap.php';
$fail  = 0;
$check = function ( $ok, $msg ) use ( &$fail ) { echo ( $ok ? 'OK   ' : 'FAIL ' ) . $msg . "\n"; $fail += $ok ? 0 : 1; };

$r = Harmony_Rozvrh_Repository::get();
$w = $r['data']['tydny'] ?? array();
$check( 3 === count( $w ), '3 týdny' );
$check( '2026-09-28' === ( $w[0]['od'] ?? '' ) && 19 === count( $w[0]['lekce'] ) && 3 === count( $w[1]['lekce'] ) && 0 === count( $w[2]['lekce'] ), 'rozdělení do týdnů (19 / 3 / 0) + 404 = prázdno' );
$l = $w[0]['lekce'][0];
$check( 'Hatha yoga' === $l['nazev'] && '09:00' === $l['cas'] && '10:00' === $l['konec'] && 'Hájková' === $l['instruktor'], 'normalizace: čas jako místní, „Instr.“ pryč' );
$sp = array_values( array_filter( $w[0]['lekce'], fn( $x ) => 'Spinning' === $x['nazev'] ) )[0];
$check( 'Aerobní sál' === $sp['sal'] && 1 === $sp['obsazeno'] && 12 === $sp['volno'], 'sál z api/Room, obsazenost' );
$check( array( 'cardio' ) === $sp['skupiny'], 'skupina podle klíčového slova' );
$k = array_values( array_filter( $w[0]['lekce'], fn( $x ) => 22638 === $x['id'] ) )[0];
$check( 'KRUHOVÝ TRÉNINK' === $k['nazev'], 'malé první písmeno opraveno (kRUHOVÝ → KRUHOVÝ)' );
exit( $fail ? 1 : 0 );
