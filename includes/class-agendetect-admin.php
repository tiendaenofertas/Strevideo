<?php
defined( 'ABSPATH' ) || exit;

class Agendetect_Admin {

	const PER_PAGE = 20;

	private static $log_hook      = '';
	private static $settings_hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_agendetect_save_settings', array( __CLASS__, 'save_settings' ) );
	}

	public static function register_menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1a9 9 0 1 0 9 9h-1.8A7.2 7.2 0 1 1 10 2.8V1zm0 3.6A5.4 5.4 0 1 0 15.4 10h-1.8A3.6 3.6 0 1 1 10 6.4V4.6zm0 3.6A1.8 1.8 0 1 0 11.8 10c0-.3-.07-.57-.2-.81l3.77-3.77-1.27-1.27-3.78 3.77A1.8 1.8 0 0 0 10 8.2v0z"/></svg>'
		);

		self::$log_hook = add_menu_page(
			__( 'Agendetect — Registros', 'agendetect' ),
			'Agendetect',
			'manage_options',
			'agendetect',
			array( __CLASS__, 'render_log_page' ),
			$icon,
			80
		);

		add_submenu_page(
			'agendetect',
			__( 'Agendetect — Registros', 'agendetect' ),
			__( 'Registros', 'agendetect' ),
			'manage_options',
			'agendetect',
			array( __CLASS__, 'render_log_page' )
		);

		self::$settings_hook = add_submenu_page(
			'agendetect',
			__( 'Agendetect — Ajustes', 'agendetect' ),
			__( 'Ajustes', 'agendetect' ),
			'manage_options',
			'agendetect-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( self::$log_hook, self::$settings_hook ), true ) ) {
			return;
		}
		wp_enqueue_style( 'agendetect-admin', AGENDETECT_URL . 'assets/admin.css', array(), AGENDETECT_VERSION );
		wp_enqueue_script( 'agendetect-admin', AGENDETECT_URL . 'assets/admin.js', array(), AGENDETECT_VERSION, true );
	}

	// ------------------------------------------------------------------
	// Pantalla de registros
	// ------------------------------------------------------------------

	public static function render_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'agendetect' ) );
		}

		global $wpdb;

		$today = current_time( 'Y-m-d' );
		$date  = self::sanitize_date( isset( $_GET['date'] ) ? wp_unslash( $_GET['date'] ) : '', $today ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- vista GET de solo lectura.

		$filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $filter, array( 'all', 'bots', 'humans', 'suspicious' ), true ) ) {
			$filter = 'all';
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$daily = Agendetect_DB::daily_table();

		// Estadísticas del día (independientes del filtro y la búsqueda): una sola consulta.
		$stat_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT category, COUNT(*) AS uas, SUM(hits) AS hit_total FROM {$daily} WHERE day = %s GROUP BY category",
				$date
			),
			ARRAY_A
		);

		$stats = array(
			'total_hits'      => 0,
			'total_uas'       => 0,
			'bot_hits'        => 0,
			'bot_uas'         => 0,
			'human_hits'      => 0,
			'human_uas'       => 0,
			'suspicious_uas'  => 0,
			'suspicious_hits' => 0,
		);
		foreach ( $stat_rows as $row ) {
			$hits = (int) $row['hit_total'];
			$uas  = (int) $row['uas'];

			$stats['total_hits'] += $hits;
			$stats['total_uas']  += $uas;

			if ( 'browser' === $row['category'] ) {
				$stats['human_hits'] += $hits;
				$stats['human_uas']  += $uas;
			} else {
				$stats['bot_hits'] += $hits;
				$stats['bot_uas']  += $uas;
			}
			if ( 'suspicious' === $row['category'] ) {
				$stats['suspicious_uas']  += $uas;
				$stats['suspicious_hits'] += $hits;
			}
		}
		$bot_pct = $stats['total_hits'] > 0 ? round( 100 * $stats['bot_hits'] / $stats['total_hits'] ) : 0;

		// Consulta del listado, con filtro, búsqueda y paginación.
		$where  = 'day = %s';
		$params = array( $date );

		if ( 'bots' === $filter ) {
			$where .= " AND category != 'browser'";
		} elseif ( 'humans' === $filter ) {
			$where .= " AND category = 'browser'";
		} elseif ( 'suspicious' === $filter ) {
			$where .= " AND category = 'suspicious'";
		}

		if ( '' !== $search ) {
			$where   .= ' AND user_agent LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$total_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$daily} WHERE {$where}", $params ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where solo contiene placeholders y literales propios.
		);

		$total_pages = max( 1, (int) ceil( $total_rows / self::PER_PAGE ) );
		$paged       = min( $paged, $total_pages );
		$offset      = ( $paged - 1 ) * self::PER_PAGE;

		$query_params   = $params;
		$query_params[] = self::PER_PAGE;
		$query_params[] = $offset;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$daily} WHERE {$where} ORDER BY hits DESC, id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query_params
			),
			ARRAY_A
		);

		// URLs de los user agents visibles en esta página: una sola consulta, sin AJAX.
		$urls_by_ua = array();
		if ( $rows ) {
			$hashes       = wp_list_pluck( $rows, 'ua_hash' );
			$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
			$urls_table   = Agendetect_DB::urls_table();
			$url_params   = array_merge( array( $date ), $hashes );

			$url_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ua_hash, url, hits FROM {$urls_table} WHERE day = %s AND ua_hash IN ($placeholders) ORDER BY hits DESC, id ASC LIMIT 2000", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders generado con %s.
					$url_params
				),
				ARRAY_A
			);
			foreach ( $url_rows as $url_row ) {
				$urls_by_ua[ $url_row['ua_hash'] ][] = $url_row;
			}
		}

		$max_hits = $rows ? max( wp_list_pluck( $rows, 'hits' ) ) : 1;
		$labels   = Agendetect_Classifier::category_labels();

		$base_url = add_query_arg(
			array(
				'page'   => 'agendetect',
				'date'   => $date,
				'filter' => $filter,
				's'      => ( '' !== $search ) ? rawurlencode( $search ) : false,
			),
			admin_url( 'admin.php' )
		);

		?>
		<div class="wrap agd-wrap">

			<header class="agd-hero">
				<div class="agd-hero-sweep" aria-hidden="true"></div>
				<div class="agd-hero-grid" aria-hidden="true"></div>

				<div class="agd-hero-top">
					<div class="agd-brand">
						<span class="agd-logo" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/><path d="M12 12 19 5"/></svg>
						</span>
						<div>
							<h1>Agendetect</h1>
							<p class="agd-tagline"><?php esc_html_e( 'Radar de user agents · historial diario de visitantes y bots', 'agendetect' ); ?></p>
						</div>
					</div>

					<form class="agd-datenav" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
						<input type="hidden" name="page" value="agendetect" />
						<?php if ( 'all' !== $filter ) : ?>
							<input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>" />
						<?php endif; ?>
						<?php if ( '' !== $search ) : ?>
							<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>" />
						<?php endif; ?>
						<label for="agd-date"><?php esc_html_e( 'Día', 'agendetect' ); ?></label>
						<input id="agd-date" type="date" name="date" value="<?php echo esc_attr( $date ); ?>" max="<?php echo esc_attr( $today ); ?>" />
						<button type="submit" class="agd-btn agd-btn-ghost"><?php esc_html_e( 'Consultar', 'agendetect' ); ?></button>
					</form>
				</div>

				<div class="agd-stats">
					<div class="agd-stat">
						<span class="agd-stat-label"><?php esc_html_e( 'Visitas registradas', 'agendetect' ); ?></span>
						<span class="agd-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_hits'] ) ); ?></span>
						<span class="agd-stat-sub"><?php echo esc_html( date_i18n( 'j \d\e F, Y', strtotime( $date . ' 12:00:00' ) ) ); ?></span>
					</div>
					<div class="agd-stat">
						<span class="agd-stat-label"><?php esc_html_e( 'User agents únicos', 'agendetect' ); ?></span>
						<span class="agd-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_uas'] ) ); ?></span>
						<span class="agd-stat-sub"><?php echo esc_html( sprintf( /* translators: 1: bots, 2: humanos */ __( '%1$s bots · %2$s humanos', 'agendetect' ), number_format_i18n( $stats['bot_uas'] ), number_format_i18n( $stats['human_uas'] ) ) ); ?></span>
					</div>
					<div class="agd-stat">
						<span class="agd-stat-label"><?php esc_html_e( 'Tráfico de bots', 'agendetect' ); ?></span>
						<span class="agd-stat-value"><?php echo esc_html( $bot_pct ); ?><small>%</small></span>
						<span class="agd-stat-sub"><?php echo esc_html( sprintf( /* translators: %s: nº de visitas bot */ __( '%s visitas no humanas', 'agendetect' ), number_format_i18n( $stats['bot_hits'] ) ) ); ?></span>
					</div>
					<div class="agd-stat agd-stat-alert">
						<span class="agd-stat-label"><?php esc_html_e( 'Sospechosos', 'agendetect' ); ?></span>
						<span class="agd-stat-value"><?php echo esc_html( number_format_i18n( $stats['suspicious_uas'] ) ); ?></span>
						<span class="agd-stat-sub"><?php echo esc_html( sprintf( /* translators: %s: nº de visitas sospechosas */ __( '%s visitas sospechosas', 'agendetect' ), number_format_i18n( $stats['suspicious_hits'] ) ) ); ?></span>
					</div>
				</div>
			</header>

			<div class="agd-toolbar">
				<nav class="agd-tabs" aria-label="<?php esc_attr_e( 'Filtrar registros', 'agendetect' ); ?>">
					<?php
					$tabs = array(
						'all'        => array( __( 'Todos', 'agendetect' ), $stats['total_uas'] ),
						'bots'       => array( __( 'Bots', 'agendetect' ), $stats['bot_uas'] ),
						'humans'     => array( __( 'Humanos', 'agendetect' ), $stats['human_uas'] ),
						'suspicious' => array( __( 'Sospechosos', 'agendetect' ), $stats['suspicious_uas'] ),
					);
					foreach ( $tabs as $key => $tab ) :
						$tab_url = add_query_arg(
							array(
								'page'   => 'agendetect',
								'date'   => $date,
								'filter' => $key,
								's'      => ( '' !== $search ) ? rawurlencode( $search ) : false,
							),
							admin_url( 'admin.php' )
						);
						?>
						<a href="<?php echo esc_url( $tab_url ); ?>" class="agd-tab<?php echo $filter === $key ? ' is-active' : ''; ?>">
							<?php echo esc_html( $tab[0] ); ?>
							<span class="agd-tab-count"><?php echo esc_html( number_format_i18n( $tab[1] ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>

				<form class="agd-search" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" role="search">
					<input type="hidden" name="page" value="agendetect" />
					<input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>" />
					<input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>" />
					<svg aria-hidden="true" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar user agent…', 'agendetect' ); ?>" />
				</form>
			</div>

			<div class="agd-card agd-table-card">
				<?php if ( empty( $rows ) ) : ?>
					<div class="agd-empty">
						<svg aria-hidden="true" viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/></svg>
						<p><strong><?php esc_html_e( 'Sin registros para esta selección', 'agendetect' ); ?></strong></p>
						<p><?php esc_html_e( 'Prueba con otra fecha o cambia el filtro. Los registros aparecen con la primera visita del día.', 'agendetect' ); ?></p>
					</div>
				<?php else : ?>
					<table class="agd-table">
						<thead>
							<tr>
								<th class="agd-col-ua"><?php esc_html_e( 'User agent', 'agendetect' ); ?></th>
								<th><?php esc_html_e( 'Categoría', 'agendetect' ); ?></th>
								<th class="agd-col-num"><?php esc_html_e( 'Visitas', 'agendetect' ); ?></th>
								<th><?php esc_html_e( 'Actividad', 'agendetect' ); ?></th>
								<th class="agd-col-urls"><?php esc_html_e( 'URLs', 'agendetect' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $rows as $i => $row ) :
								$category  = $row['category'];
								$cat_label = isset( $labels[ $category ] ) ? $labels[ $category ] : $labels['unknown'];
								$ua_text   = ( '' === $row['user_agent'] ) ? __( '(sin user agent)', 'agendetect' ) : $row['user_agent'];
								$name      = ( '' !== $row['bot_name'] ) ? $row['bot_name'] : ( 'browser' === $category ? __( 'Navegador', 'agendetect' ) : __( 'Desconocido', 'agendetect' ) );
								$ua_urls   = isset( $urls_by_ua[ $row['ua_hash'] ] ) ? $urls_by_ua[ $row['ua_hash'] ] : array();
								$bar_pct   = max( 2, round( 100 * (int) $row['hits'] / max( 1, (int) $max_hits ) ) );
								$detail_id = 'agd-detail-' . $i;
								?>
								<tr class="agd-row" data-target="<?php echo esc_attr( $detail_id ); ?>" tabindex="0" role="button" aria-expanded="false">
									<td class="agd-col-ua">
										<span class="agd-ua-name"><?php echo esc_html( $name ); ?></span>
										<span class="agd-ua-string"><?php echo esc_html( $ua_text ); ?></span>
									</td>
									<td>
										<span class="agd-badge agd-badge-<?php echo esc_attr( $category ); ?>">
											<span class="agd-dot" aria-hidden="true"></span><?php echo esc_html( $cat_label ); ?>
										</span>
									</td>
									<td class="agd-col-num">
										<span class="agd-hits"><?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?></span>
										<span class="agd-bar" aria-hidden="true"><span style="width:<?php echo esc_attr( $bar_pct ); ?>%"></span></span>
									</td>
									<td class="agd-col-time">
										<span class="agd-time"><?php echo esc_html( substr( $row['first_seen'], 0, 5 ) . ' – ' . substr( $row['last_seen'], 0, 5 ) ); ?></span>
									</td>
									<td class="agd-col-urls">
										<span class="agd-url-count"><?php echo esc_html( number_format_i18n( count( $ua_urls ) ) ); ?></span>
										<svg class="agd-chevron" aria-hidden="true" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
									</td>
								</tr>
								<tr id="<?php echo esc_attr( $detail_id ); ?>" class="agd-detail" hidden>
									<td colspan="5">
										<div class="agd-detail-inner">
											<div class="agd-detail-block">
												<h4><?php esc_html_e( 'User agent completo', 'agendetect' ); ?></h4>
												<div class="agd-ua-full">
													<code><?php echo esc_html( $ua_text ); ?></code>
													<button type="button" class="agd-btn agd-btn-mini agd-copy" data-copy="<?php echo esc_attr( $row['user_agent'] ); ?>"><?php esc_html_e( 'Copiar', 'agendetect' ); ?></button>
												</div>
											</div>
											<div class="agd-detail-block">
												<h4><?php echo esc_html( sprintf( /* translators: %s: nº de URLs */ __( 'URLs visitadas (%s)', 'agendetect' ), number_format_i18n( count( $ua_urls ) ) ) ); ?></h4>
												<?php if ( empty( $ua_urls ) ) : ?>
													<p class="agd-muted"><?php esc_html_e( 'Sin detalle de URLs para este registro.', 'agendetect' ); ?></p>
												<?php else : ?>
													<ul class="agd-url-list">
														<?php foreach ( $ua_urls as $u ) : ?>
															<li>
																<span class="agd-url-hits"><?php echo esc_html( number_format_i18n( (int) $u['hits'] ) ); ?>×</span>
																<code><?php echo esc_html( $u['url'] ); ?></code>
															</li>
														<?php endforeach; ?>
													</ul>
												<?php endif; ?>
											</div>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div class="agd-table-footer">
						<span class="agd-muted">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: primer registro, 2: último registro, 3: total */
									__( 'Mostrando %1$s–%2$s de %3$s user agents', 'agendetect' ),
									number_format_i18n( $offset + 1 ),
									number_format_i18n( min( $offset + self::PER_PAGE, $total_rows ) ),
									number_format_i18n( $total_rows )
								)
							);
							?>
						</span>
						<?php if ( $total_pages > 1 ) : ?>
							<nav class="agd-pagination" aria-label="<?php esc_attr_e( 'Paginación', 'agendetect' ); ?>">
								<?php if ( $paged > 1 ) : ?>
									<a class="agd-page-btn" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>">‹ <?php esc_html_e( 'Anterior', 'agendetect' ); ?></a>
								<?php endif; ?>
								<span class="agd-page-info"><?php echo esc_html( sprintf( /* translators: 1: página actual, 2: total páginas */ __( 'Página %1$s de %2$s', 'agendetect' ), number_format_i18n( $paged ), number_format_i18n( $total_pages ) ) ); ?></span>
								<?php if ( $paged < $total_pages ) : ?>
									<a class="agd-page-btn" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>"><?php esc_html_e( 'Siguiente', 'agendetect' ); ?> ›</a>
								<?php endif; ?>
							</nav>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="agd-card agd-export-card">
				<div class="agd-export-info">
					<h2><?php esc_html_e( 'Exportar historial a Excel', 'agendetect' ); ?></h2>
					<p class="agd-muted"><?php esc_html_e( 'Descarga un archivo .xlsx con todos los registros del rango de fechas seleccionado.', 'agendetect' ); ?></p>
				</div>
				<form class="agd-export-form" method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="agendetect_export" />
					<?php wp_nonce_field( 'agendetect_export' ); ?>
					<label>
						<span><?php esc_html_e( 'Desde', 'agendetect' ); ?></span>
						<input type="date" name="from" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( $date . ' 12:00:00' ) - 29 * DAY_IN_SECONDS ) ); ?>" max="<?php echo esc_attr( $today ); ?>" required />
					</label>
					<label>
						<span><?php esc_html_e( 'Hasta', 'agendetect' ); ?></span>
						<input type="date" name="to" value="<?php echo esc_attr( $date ); ?>" max="<?php echo esc_attr( $today ); ?>" required />
					</label>
					<button type="submit" class="agd-btn agd-btn-primary">
						<svg aria-hidden="true" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
						<?php esc_html_e( 'Descargar Excel', 'agendetect' ); ?>
					</button>
				</form>
			</div>

			<p class="agd-footnote">
				<?php esc_html_e( 'Nota: si usas un plugin de caché de página o un CDN con caché HTML, las visitas servidas desde la caché no ejecutan PHP y no se contabilizan. Los bots que rastrean URLs no cacheadas sí quedan registrados.', 'agendetect' ); ?>
			</p>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Pantalla de ajustes
	// ------------------------------------------------------------------

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'agendetect' ) );
		}

		$settings   = Agendetect::settings();
		$updated    = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo decide si mostrar un aviso.
		$retentions = array(
			30  => __( '30 días', 'agendetect' ),
			90  => __( '90 días (recomendado)', 'agendetect' ),
			180 => __( '180 días', 'agendetect' ),
			365 => __( '1 año', 'agendetect' ),
			0   => __( 'Para siempre (sin purga)', 'agendetect' ),
		);
		$next_cron  = wp_next_scheduled( Agendetect_Cron::HOOK );
		?>
		<div class="wrap agd-wrap agd-wrap-settings">

			<header class="agd-hero agd-hero-compact">
				<div class="agd-hero-sweep" aria-hidden="true"></div>
				<div class="agd-hero-grid" aria-hidden="true"></div>
				<div class="agd-hero-top">
					<div class="agd-brand">
						<span class="agd-logo" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/><path d="M12 12 19 5"/></svg>
						</span>
						<div>
							<h1><?php esc_html_e( 'Ajustes', 'agendetect' ); ?></h1>
							<p class="agd-tagline"><?php esc_html_e( 'Retención del historial y comportamiento del registro', 'agendetect' ); ?></p>
						</div>
					</div>
				</div>
			</header>

			<?php if ( $updated ) : ?>
				<div class="agd-notice agd-notice-success" role="status">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 13 4 4L19 7"/></svg>
					<?php esc_html_e( 'Ajustes guardados correctamente.', 'agendetect' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="agendetect_save_settings" />
				<?php wp_nonce_field( 'agendetect_save_settings' ); ?>

				<div class="agd-card agd-settings-card">
					<h2><?php esc_html_e( 'Retención del historial', 'agendetect' ); ?></h2>
					<p class="agd-muted"><?php esc_html_e( 'Los registros más antiguos que este periodo se eliminan automáticamente cada noche para mantener la base de datos ligera.', 'agendetect' ); ?></p>
					<label class="agd-field">
						<span><?php esc_html_e( 'Conservar registros durante', 'agendetect' ); ?></span>
						<select name="retention_days">
							<?php foreach ( $retentions as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (int) $settings['retention_days'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<?php if ( $next_cron ) : ?>
						<p class="agd-muted agd-small">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: fecha y hora */
									__( 'Próximo mantenimiento programado: %s', 'agendetect' ),
									wp_date( 'j \d\e F, Y · H:i', $next_cron )
								)
							);
							?>
						</p>
					<?php endif; ?>
				</div>

				<div class="agd-card agd-settings-card">
					<h2><?php esc_html_e( 'Comportamiento del registro', 'agendetect' ); ?></h2>
					<label class="agd-switch">
						<input type="checkbox" name="skip_admins" value="1" <?php checked( ! empty( $settings['skip_admins'] ) ); ?> />
						<span class="agd-slider" aria-hidden="true"></span>
						<span class="agd-switch-text">
							<strong><?php esc_html_e( 'No registrar administradores', 'agendetect' ); ?></strong>
							<small><?php esc_html_e( 'Las visitas de usuarios con permisos de administración no se contabilizan.', 'agendetect' ); ?></small>
						</span>
					</label>
					<label class="agd-switch">
						<input type="checkbox" name="skip_logged_in" value="1" <?php checked( ! empty( $settings['skip_logged_in'] ) ); ?> />
						<span class="agd-slider" aria-hidden="true"></span>
						<span class="agd-switch-text">
							<strong><?php esc_html_e( 'No registrar usuarios conectados', 'agendetect' ); ?></strong>
							<small><?php esc_html_e( 'Excluye cualquier visita de usuarios con sesión iniciada, sea cual sea su rol.', 'agendetect' ); ?></small>
						</span>
					</label>
				</div>

				<div class="agd-card agd-settings-card agd-about">
					<h2><?php esc_html_e( 'Cómo funciona', 'agendetect' ); ?></h2>
					<ul class="agd-howto">
						<li><?php esc_html_e( 'Cada visita del frontend se agrega por user agent y día: el historial escala indefinidamente sin afectar al rendimiento (máximo 2 consultas ligeras por visita).', 'agendetect' ); ?></li>
						<li><?php esc_html_e( 'Cada user agent se clasifica automáticamente: buscadores, bots de IA, herramientas SEO, redes sociales, monitorización, bots genéricos, sospechosos o navegadores humanos.', 'agendetect' ); ?></li>
						<li><?php esc_html_e( 'Este plugin solo observa y registra: no bloquea ningún bot. Usa el historial para decidir qué bloquear manualmente (robots.txt, .htaccess o tu firewall).', 'agendetect' ); ?></li>
						<li><?php esc_html_e( 'Al desinstalar el plugin se eliminan por completo sus tablas, opciones y tareas programadas.', 'agendetect' ); ?></li>
					</ul>
				</div>

				<div class="agd-actions">
					<button type="submit" class="agd-btn agd-btn-primary"><?php esc_html_e( 'Guardar ajustes', 'agendetect' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'agendetect' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'agendetect_save_settings' );

		$retention = isset( $_POST['retention_days'] ) ? absint( $_POST['retention_days'] ) : 90;
		if ( ! in_array( $retention, array( 0, 30, 90, 180, 365 ), true ) ) {
			$retention = 90;
		}

		update_option(
			'agendetect_settings',
			array(
				'retention_days' => $retention,
				'skip_admins'    => empty( $_POST['skip_admins'] ) ? 0 : 1,
				'skip_logged_in' => empty( $_POST['skip_logged_in'] ) ? 0 : 1,
			)
		);

		wp_safe_redirect( add_query_arg( array( 'page' => 'agendetect-settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ------------------------------------------------------------------

	private static function sanitize_date( $value, $fallback ) {
		$value = sanitize_text_field( $value );
		$d     = DateTime::createFromFormat( 'Y-m-d', $value );
		return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : $fallback;
	}
}
