<?php
/**
 * Test proti falešnému memberzone (tests/mock/router.php) bez WordPressu.
 *   php -S 127.0.0.1:8765 tests/mock/router.php &
 *   php tests/run.php
 */
require __DIR__ . '/bootstrap.php';
$fail = 0;
$check = function ( $ok, $msg ) use ( &$fail ) { echo ( $ok ? 'OK   ' : 'FAIL ' ) . $msg . "\n"; $fail += $ok ? 0 : 1; };

$r = Harmony_Rozvrh_Repository::get();
$check( 3 === count( $r['data']['tydny'] ?? array() ), 'login + 3 týdny přes btnNext' );
$l = $r['data']['tydny'][0]['lekce'][0] ?? array();
$check( 'Zdravá záda' === ( $l['nazev'] ?? '' ) && 'Sál 1' === $l['sal'] && 'Eliška Nováková' === $l['instruktor'], 'název, sál, instruktor z karty' );
$check( 55 === $l['delka_min'] && '09:25' === $l['konec'] && 6 === $l['obsazeno'] && 14 === $l['kapacita'] && 150.0 === $l['cena'], 'délka (OLE hack), obsazenost, cena' );
$check( array( 'mind' ) === $l['skupiny'], 'skupina podle názvu' );
$n = $GLOBALS['HTTP_COUNT']; Harmony_Rozvrh_Repository::get();
$check( $n === $GLOBALS['HTTP_COUNT'], 'cache hit nevolá memberzone' );
delete_transient( 'harmony_rozvrh_v1' );
$r = Harmony_Rozvrh_Repository::get();
$check( $r['stale'] && $n === $GLOBALS['HTTP_COUNT'] && in_array( Harmony_Rozvrh_Repository::CRON_HOOK, $GLOBALS['CRON'], true ), 'prošlá cache → stará data + obnova na pozadí' );
try { Harmony_Rozvrh_Parser::parse( '<form><p>jiná šablona</p></form>' ); $check( false, 'změna šablony vyhodí chybu' ); }
catch ( RuntimeException $e ) { $check( true, 'změna šablony vyhodí chybu' ); }
exit( $fail ? 1 : 0 );
