<?php
/**
 * Shortcode [harmony_rozvrh] a blok harmony/rozvrh.
 *
 * HTML se renderuje na serveru (rozvrh je vidět i bez JS a vidí ho Google),
 * JS jen přepíná týdny/dny, filtruje skupiny a značí proběhlé lekce.
 * Výstup nezávisí na aktuálním čase, takže je bezpečný pro page cache.
 */

defined( 'ABSPATH' ) || exit;

final class Harmony_Rozvrh_Render {

	private const DAYS = array( 1 => 'Po', 2 => 'Út', 3 => 'St', 4 => 'Čt', 5 => 'Pá', 6 => 'So', 7 => 'Ne' );

	public static function init(): void {
		wp_register_style( 'harmony-rozvrh', HARMONY_ROZVRH_URL . 'assets/rozvrh.css', array(), HARMONY_ROZVRH_VERSION );
		wp_register_script( 'harmony-rozvrh', HARMONY_ROZVRH_URL . 'assets/rozvrh.js', array(), HARMONY_ROZVRH_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		add_shortcode( 'harmony_rozvrh', array( __CLASS__, 'shortcode' ) );

		register_block_type(
			HARMONY_ROZVRH_DIR . 'blocks/rozvrh',
			array( 'render_callback' => array( __CLASS__, 'block' ) )
		);
	}

	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'tydny'     => Harmony_Rozvrh_Config::weeks_ahead() + 1,
				'filtry'    => '1',
				'rezervace' => (string) Harmony_Rozvrh_Config::get( 'HARMONY_MZ_RESERVATION_URL' ),
			),
			$atts,
			'harmony_rozvrh'
		);
		return self::render(
			(int) $atts['tydny'],
			filter_var( $atts['filtry'], FILTER_VALIDATE_BOOLEAN ),
			(string) $atts['rezervace']
		);
	}

	public static function block( array $attrs ): string {
		$html = self::render(
			(int) ( $attrs['tydny'] ?? Harmony_Rozvrh_Config::weeks_ahead() + 1 ),
			(bool) ( $attrs['filtry'] ?? true ),
			(string) Harmony_Rozvrh_Config::get( 'HARMONY_MZ_RESERVATION_URL' )
		);
		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
	}

	public static function render( int $weeks_count, bool $filters, string $reservation_url ): string {
		wp_enqueue_style( 'harmony-rozvrh' );
		wp_enqueue_script( 'harmony-rozvrh' );

		$res = Harmony_Rozvrh_Repository::get();
		if ( empty( $res['data']['tydny'] ) ) {
			// Tichý fallback: žádná chyba, jen odkaz do rezervačního systému.
			return '<div class="hr-rozvrh hr-rozvrh--empty"><p>Aktuální rozvrh najdete v <a href="' . esc_url( $reservation_url ) . '">rezervačním systému</a>.</p></div>';
		}

		$weeks = array_slice( $res['data']['tydny'], 0, max( 1, $weeks_count ) );
		$used  = array();
		foreach ( $weeks as $w ) {
			foreach ( $w['lekce'] as $l ) {
				foreach ( $l['skupiny'] ?? array() as $g ) {
					$used[ $g ] = true;
				}
			}
		}

		ob_start();
		?>
		<div class="hr-rozvrh" data-hr-rozvrh>
			<div class="hr-controls">
				<?php if ( count( $weeks ) > 1 ) : ?>
					<div class="hr-weeks" role="tablist" aria-label="Týden">
						<?php foreach ( $weeks as $i => $w ) : ?>
							<button type="button" role="tab" data-week="<?php echo (int) $i; ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>">
								<b><?php echo esc_html( self::week_label( (int) $w['tyden'] ) ); ?></b>
								<span><?php echo esc_html( self::range( $w['od'], $w['do'] ) ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( $filters && $used ) : ?>
					<div class="hr-filters" role="group" aria-label="Typ lekce">
						<?php foreach ( Harmony_Rozvrh_Categories::GROUPS as $slug => $label ) : ?>
							<?php if ( isset( $used[ $slug ] ) ) : ?>
								<button type="button" class="c-<?php echo esc_attr( $slug ); ?>" data-group="<?php echo esc_attr( $slug ); ?>" aria-pressed="false"><span class="hr-sw"></span><?php echo esc_html( $label ); ?></button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php foreach ( $weeks as $i => $w ) : ?>
				<div class="hr-week<?php echo 0 === $i ? ' is-active' : ''; ?>" data-week="<?php echo (int) $i; ?>" role="tabpanel">
					<?php if ( count( $weeks ) > 1 ) : ?>
						<h3 class="hr-week-title"><?php echo esc_html( self::week_label( (int) $w['tyden'] ) . ' · ' . self::range( $w['od'], $w['do'] ) ); ?></h3>
					<?php endif; ?>
					<div class="hr-days" role="tablist" aria-label="Den">
						<?php for ( $d = 1; $d <= 7; $d++ ) : ?>
							<?php $date = self::day_date( $w['od'], $d ); ?>
							<button type="button" role="tab" data-day="<?php echo (int) $d; ?>" data-date="<?php echo esc_attr( $date->format( 'Y-m-d' ) ); ?>" aria-selected="<?php echo 1 === $d ? 'true' : 'false'; ?>">
								<b><?php echo esc_html( self::DAYS[ $d ] ); ?></b><span><?php echo esc_html( $date->format( 'j. n.' ) ); ?></span>
							</button>
						<?php endfor; ?>
					</div>
					<div class="hr-grid">
						<?php for ( $d = 1; $d <= 7; $d++ ) : ?>
							<?php
							$date    = self::day_date( $w['od'], $d );
							$lessons = array_filter( $w['lekce'], static fn( $l ) => (int) $l['den'] === $d );
							?>
							<section class="hr-day<?php echo 1 === $d ? ' is-active' : ''; ?>" data-day="<?php echo (int) $d; ?>" data-date="<?php echo esc_attr( $date->format( 'Y-m-d' ) ); ?>">
								<h4><?php echo esc_html( self::DAYS[ $d ] ); ?> <small><?php echo esc_html( $date->format( 'j. n.' ) ); ?></small></h4>
								<ul>
									<?php if ( ! $lessons ) : ?>
										<li class="hr-empty">Bez lekcí</li>
									<?php endif; ?>
									<?php foreach ( $lessons as $l ) : ?>
										<?php echo self::lesson( $l, $reservation_url ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno uvnitř ?>
									<?php endforeach; ?>
								</ul>
							</section>
						<?php endfor; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<p class="hr-meta">Aktualizováno <?php echo esc_html( wp_date( 'j. n. Y H:i', (int) $res['data']['aktualizovano'] ) ); ?>. Rezervace probíhají v <a href="<?php echo esc_url( $reservation_url ); ?>">rezervačním systému</a>.</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function lesson( array $l, string $reservation_url ): string {
		$groups = $l['skupiny'] ?? array();
		$main   = $groups[0] ?? 'jine';
		$full   = null !== $l['volno'] && 0 === $l['volno'];
		$sec    = '';
		foreach ( array_slice( $groups, 1 ) as $g ) {
			$sec .= '<i class="c-' . esc_attr( $g ) . '"></i>';
		}

		$label = sprintf(
			'%s %s–%s, %s%s%s',
			$l['nazev'],
			$l['cas'],
			$l['konec'],
			$l['instruktor'],
			$l['sal'] ? ', ' . $l['sal'] : '',
			null !== $l['kapacita'] ? sprintf( ', obsazeno %d z %d', (int) $l['obsazeno'], (int) $l['kapacita'] ) : ''
		);

		return sprintf(
			'<li><a class="hr-l c-%1$s%2$s" href="%3$s" data-groups="%4$s" data-start="%5$s" data-end="%6$s" aria-label="%7$s"%8$s>'
			. '<span class="hr-t"><span class="hr-from">%9$s</span><span class="hr-to">–%10$s</span></span>'
			. '<span class="hr-n">%11$s%15$s</span>'
			. '<span class="hr-w"><span>%12$s</span><span class="hr-room">%13$s</span></span>'
			. '%14$s</a></li>',
			esc_attr( $main ),
			$full ? ' is-full' : '',
			esc_url( $reservation_url ),
			esc_attr( implode( ' ', $groups ) ),
			esc_attr( $l['zacatek'] ),
			esc_attr( ( new DateTimeImmutable( $l['zacatek'] ) )->modify( '+' . (int) $l['delka_min'] . ' minutes' )->format( DATE_ATOM ) ),
			esc_attr( $label ),
			$l['popis'] ? ' title="' . esc_attr( $l['popis'] ) . '"' : '',
			esc_html( $l['cas'] ),
			esc_html( $l['konec'] ),
			esc_html( $l['nazev'] ),
			esc_html( $l['instruktor'] ),
			esc_html( self::short_room( (string) $l['sal'] ) ),
			null !== $l['kapacita'] ? '<span class="hr-occ">' . ( $full ? 'Plno' : esc_html( (int) $l['obsazeno'] . '/' . (int) $l['kapacita'] ) ) . '</span>' : '',
			$sec ? '<span class="hr-sec">' . $sec . '</span>' : ''
		);
	}

	private static function short_room( string $room ): string {
		return preg_replace( '/^Sál\s*/u', 'S', $room );
	}

	private static function day_date( string $monday, int $day ): DateTimeImmutable {
		return ( new DateTimeImmutable( $monday, new DateTimeZone( 'Europe/Prague' ) ) )->modify( '+' . ( $day - 1 ) . ' days' );
	}

	private static function week_label( int $index ): string {
		return array( 'Tento týden', 'Příští týden' )[ $index ] ?? 'Za ' . $index . ' týdny';
	}

	private static function range( string $from, string $to ): string {
		$a = new DateTimeImmutable( $from );
		$b = new DateTimeImmutable( $to );
		return $a->format( 'n' ) === $b->format( 'n' )
			? $a->format( 'j.' ) . '–' . $b->format( 'j. n.' )
			: $a->format( 'j. n.' ) . ' – ' . $b->format( 'j. n.' );
	}
}
