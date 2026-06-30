<?php
defined( 'ABSPATH' ) || exit;

/**
 * Exportación del historial por rango de fechas.
 *
 * Genera un .xlsx real sin dependencias: un paquete OOXML mínimo construido
 * con ZipArchive. La hoja de cálculo se escribe en streaming a un archivo
 * temporal y las lecturas de BD usan paginación por id, de modo que la
 * memoria se mantiene plana sea cual sea el tamaño del rango.
 * Si ZipArchive no está disponible, exporta CSV (UTF-8 con BOM).
 */
class Agendetect_Exporter {

	const BATCH_SIZE = 5000;

	public static function init() {
		add_action( 'admin_post_agendetect_export', array( __CLASS__, 'handle_export' ) );
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'agendetect' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'agendetect_export' );

		$from = self::sanitize_date( isset( $_GET['from'] ) ? wp_unslash( $_GET['from'] ) : '' );
		$to   = self::sanitize_date( isset( $_GET['to'] ) ? wp_unslash( $_GET['to'] ) : '' );

		if ( ! $from || ! $to ) {
			wp_die(
				esc_html__( 'Rango de fechas no válido. Vuelve atrás y selecciona fechas correctas.', 'agendetect' ),
				'',
				array( 'response' => 400, 'back_link' => true )
			);
		}
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}

		if ( class_exists( 'ZipArchive' ) ) {
			self::send_xlsx( $from, $to );
		} else {
			self::send_csv( $from, $to );
		}
		exit;
	}

	// ------------------------------------------------------------------
	// XLSX
	// ------------------------------------------------------------------

	private static function send_xlsx( $from, $to ) {
		$sheet_path = wp_tempnam( 'agendetect-sheet' );
		$zip_path   = wp_tempnam( 'agendetect-xlsx' );

		if ( ! self::write_xlsx_file( self::fetch_rows( $from, $to ), $zip_path, $sheet_path ) ) {
			wp_delete_file( $sheet_path );
			wp_delete_file( $zip_path );
			wp_die( esc_html__( 'No se pudo crear el archivo temporal de exportación.', 'agendetect' ) );
		}

		self::send_headers(
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			sprintf( 'agendetect-%s_%s.xlsx', $from, $to ),
			filesize( $zip_path )
		);
		readfile( $zip_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $zip_path );
	}

	/**
	 * Construye el paquete .xlsx completo en $zip_path a partir de un
	 * iterable de filas. Sin capa HTTP: testeable de forma aislada.
	 *
	 * @param  iterable $rows       Filas del historial (arrays asociativos).
	 * @param  string   $zip_path   Destino del .xlsx.
	 * @param  string   $sheet_path Archivo temporal para la hoja en streaming.
	 * @return bool
	 */
	public static function write_xlsx_file( $rows, $zip_path, $sheet_path ) {
		$sheet = fopen( $sheet_path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $sheet ) {
			return false;
		}

		fwrite( $sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $sheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' );
		fwrite( $sheet, '<cols><col min="1" max="1" width="12" customWidth="1"/><col min="2" max="2" width="15" customWidth="1"/><col min="3" max="3" width="24" customWidth="1"/><col min="4" max="4" width="10" customWidth="1"/><col min="5" max="6" width="13" customWidth="1"/><col min="7" max="7" width="80" customWidth="1"/><col min="8" max="8" width="50" customWidth="1"/></cols>' );
		fwrite( $sheet, '<sheetData>' );

		$headers = array(
			__( 'Fecha', 'agendetect' ),
			__( 'Categoría', 'agendetect' ),
			__( 'Nombre detectado', 'agendetect' ),
			__( 'Visitas', 'agendetect' ),
			__( 'Primera visita', 'agendetect' ),
			__( 'Última visita', 'agendetect' ),
			__( 'User agent', 'agendetect' ),
			__( 'Última URL', 'agendetect' ),
		);

		$header_xml = '<row r="1">';
		foreach ( $headers as $header ) {
			$header_xml .= '<c t="inlineStr" s="1"><is><t>' . self::xml( $header ) . '</t></is></c>';
		}
		$header_xml .= '</row>';
		fwrite( $sheet, $header_xml );

		foreach ( $rows as $row ) {
			fwrite( $sheet, self::row_xml( $row ) );
		}

		fwrite( $sheet, '</sheetData></worksheet>' );
		fclose( $sheet ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $sheet_path );
			return false;
		}

		$zip->addFromString(
			'[Content_Types].xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>'
		);

		$zip->addFromString(
			'_rels/.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>'
		);

		$zip->addFromString(
			'xl/workbook.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Agendetect" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>'
		);

		$zip->addFromString(
			'xl/_rels/workbook.xml.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>'
		);

		// Excel exige al menos dos fills y que el segundo sea gray125.
		$zip->addFromString(
			'xl/styles.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
			. '</styleSheet>'
		);

		$zip->addFile( $sheet_path, 'xl/worksheets/sheet1.xml' );
		$ok = $zip->close(); // ZipArchive lee los addFile() aquí: no borrar los temporales antes.

		wp_delete_file( $sheet_path );

		return (bool) $ok;
	}

	private static function row_xml( $row ) {
		$labels    = Agendetect_Classifier::category_labels();
		$category  = isset( $labels[ $row['category'] ] ) ? $labels[ $row['category'] ] : $row['category'];

		return '<row>'
			. '<c t="inlineStr"><is><t>' . self::xml( $row['day'] ) . '</t></is></c>'
			. '<c t="inlineStr"><is><t>' . self::xml( $category ) . '</t></is></c>'
			. '<c t="inlineStr"><is><t>' . self::xml( $row['bot_name'] ) . '</t></is></c>'
			. '<c t="n"><v>' . (int) $row['hits'] . '</v></c>'
			. '<c t="inlineStr"><is><t>' . self::xml( $row['first_seen'] ) . '</t></is></c>'
			. '<c t="inlineStr"><is><t>' . self::xml( $row['last_seen'] ) . '</t></is></c>'
			. '<c t="inlineStr"><is><t xml:space="preserve">' . self::xml( $row['user_agent'] ) . '</t></is></c>'
			. '<c t="inlineStr"><is><t xml:space="preserve">' . self::xml( $row['last_url'] ) . '</t></is></c>'
			. '</row>';
	}

	/**
	 * Escapado XML: elimina caracteres de control inválidos en XML 1.0
	 * (un solo 0x0B corrompería el libro entero) y escapa entidades.
	 */
	private static function xml( $value ) {
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value );
		return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	// ------------------------------------------------------------------
	// CSV (fallback sin ZipArchive)
	// ------------------------------------------------------------------

	private static function send_csv( $from, $to ) {
		self::send_headers( 'text/csv; charset=utf-8', sprintf( 'agendetect-%s_%s.csv', $from, $to ), 0 );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		// BOM UTF-8 para que Excel detecte la codificación.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// Parámetros explícitos: en PHP 8.4 omitir $escape está deprecado.
		fputcsv(
			$out,
			array( 'Fecha', 'Categoría', 'Nombre detectado', 'Visitas', 'Primera visita', 'Última visita', 'User agent', 'Última URL' ),
			',',
			'"',
			'\\'
		);

		$labels = Agendetect_Classifier::category_labels();
		foreach ( self::fetch_rows( $from, $to ) as $row ) {
			$category = isset( $labels[ $row['category'] ] ) ? $labels[ $row['category'] ] : $row['category'];
			fputcsv(
				$out,
				array(
					$row['day'],
					self::csv_guard( $category ),
					self::csv_guard( $row['bot_name'] ),
					(int) $row['hits'],
					$row['first_seen'],
					$row['last_seen'],
					self::csv_guard( $row['user_agent'] ),
					self::csv_guard( $row['last_url'] ),
				),
				',',
				'"',
				'\\'
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * A diferencia del XLSX con cadenas inline, el CSV sí es vulnerable a
	 * inyección de fórmulas: se prefijan las celdas peligrosas.
	 */
	private static function csv_guard( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	// ------------------------------------------------------------------
	// Compartido
	// ------------------------------------------------------------------

	/**
	 * Lee el rango en lotes con paginación por id (memoria plana).
	 */
	private static function fetch_rows( $from, $to ) {
		global $wpdb;
		$table   = Agendetect_DB::daily_table();
		$last_id = 0;

		do {
			$batch = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, day, category, bot_name, hits, first_seen, last_seen, user_agent, last_url
					FROM {$table}
					WHERE day >= %s AND day <= %s AND id > %d
					ORDER BY id ASC LIMIT %d",
					$from,
					$to,
					$last_id,
					self::BATCH_SIZE
				),
				ARRAY_A
			);
			foreach ( $batch as $row ) {
				$last_id = (int) $row['id'];
				yield $row;
			}
		} while ( count( $batch ) === self::BATCH_SIZE );
	}

	private static function send_headers( $content_type, $filename, $size ) {
		nocache_headers();
		// Vaciar cualquier buffer abierto para no corromper el binario.
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		if ( $size > 0 ) {
			header( 'Content-Length: ' . $size );
		}
		header( 'X-Content-Type-Options: nosniff' );
	}

	private static function sanitize_date( $value ) {
		$value = sanitize_text_field( $value );
		$d     = DateTime::createFromFormat( 'Y-m-d', $value );
		return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : false;
	}
}
