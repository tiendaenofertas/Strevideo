<?php
defined( 'ABSPATH' ) || ( defined( 'AGENDETECT_TESTING' ) || exit );

/**
 * Clasificador puro de user agents: sin dependencias de WordPress ni de
 * base de datos, para poder probarlo de forma aislada desde la CLI.
 */
class Agendetect_Classifier {

	/**
	 * Clasifica un user agent ya sanitizado.
	 *
	 * @param  string $ua User agent.
	 * @return array { category: string, label: string }
	 */
	public static function classify( $ua ) {
		$ua = trim( (string) $ua );

		if ( '' === $ua ) {
			return array(
				'category' => 'suspicious',
				'label'    => 'Sin user agent',
			);
		}

		foreach ( self::patterns() as $category => $needles ) {
			foreach ( $needles as $needle => $label ) {
				if ( false !== stripos( $ua, $needle ) ) {
					return array(
						'category' => $category,
						'label'    => $label,
					);
				}
			}
		}

		// Después de los patrones, para que "curl/8.4.0" reciba su etiqueta
		// específica y no la genérica de UA corto.
		if ( strlen( $ua ) < 12 ) {
			return array(
				'category' => 'suspicious',
				'label'    => 'UA anómalo',
			);
		}

		// Bot genérico: se identifica por palabra clave y se intenta extraer su nombre.
		if ( preg_match( '/([a-z0-9_.\-]*(?:bot|crawler|spider)[a-z0-9_.\-]*)/i', $ua, $m ) ) {
			return array(
				'category' => 'bot',
				'label'    => substr( $m[1], 0, 100 ),
			);
		}
		if ( preg_match( '/crawl|fetch|scan|harvest/i', $ua ) ) {
			return array(
				'category' => 'bot',
				'label'    => 'Bot genérico',
			);
		}

		return array(
			'category' => 'browser',
			'label'    => '',
		);
	}

	/**
	 * Patrones por categoría en orden de prioridad (la primera coincidencia gana).
	 * Formato: 'aguja' => 'Etiqueta legible'.
	 */
	public static function patterns() {
		$patterns = array(
			'suspicious' => array(
				'curl/'             => 'cURL',
				'wget'              => 'Wget',
				'python-requests'   => 'Python Requests',
				'python-urllib'     => 'Python urllib',
				'python/'           => 'Python',
				'aiohttp'           => 'Python aiohttp',
				'go-http-client'    => 'Go HTTP Client',
				'java/'             => 'Java HTTP',
				'okhttp'            => 'OkHttp',
				'scrapy'            => 'Scrapy',
				'libwww-perl'       => 'Perl LWP',
				'node-fetch'        => 'Node Fetch',
				'axios'             => 'Axios',
				'guzzlehttp'        => 'Guzzle PHP',
				'apache-httpclient' => 'Apache HttpClient',
				'winhttp'           => 'WinHTTP',
				'masscan'           => 'Masscan',
				'zgrab'             => 'ZGrab',
				'nikto'             => 'Nikto',
				'sqlmap'            => 'sqlmap',
				'nmap'              => 'Nmap',
				'dirbuster'         => 'DirBuster',
			),
			'ai'         => array(
				'gptbot'             => 'GPTBot (OpenAI)',
				'oai-searchbot'      => 'OAI-SearchBot (OpenAI)',
				'chatgpt-user'       => 'ChatGPT-User (OpenAI)',
				'claudebot'          => 'ClaudeBot (Anthropic)',
				'claude-user'        => 'Claude-User (Anthropic)',
				'claude-searchbot'   => 'Claude-SearchBot (Anthropic)',
				'claude-web'         => 'Claude-Web (Anthropic)',
				'anthropic-ai'       => 'Anthropic AI',
				'perplexitybot'      => 'PerplexityBot',
				'perplexity-user'    => 'Perplexity-User',
				'google-extended'    => 'Google-Extended (IA)',
				'ccbot'              => 'CCBot (Common Crawl)',
				'bytespider'         => 'Bytespider (ByteDance)',
				'amazonbot'          => 'Amazonbot',
				'meta-externalagent' => 'Meta-ExternalAgent (IA)',
				'cohere-ai'          => 'Cohere AI',
				'ai2bot'             => 'AI2Bot',
				'omgilibot'          => 'Omgilibot',
				'diffbot'            => 'Diffbot',
				'timpibot'           => 'TimpiBot',
				'mistralai'          => 'MistralAI',
			),
			'search'     => array(
				'googlebot'    => 'Googlebot',
				'storebot-google' => 'Storebot (Google)',
				'google-inspectiontool' => 'Google InspectionTool',
				'bingbot'      => 'Bingbot',
				'msnbot'       => 'MSNBot',
				'slurp'        => 'Yahoo! Slurp',
				'duckduckbot'  => 'DuckDuckBot',
				'duckduckgo'   => 'DuckDuckGo',
				'baiduspider'  => 'Baiduspider',
				'yandexbot'    => 'YandexBot',
				'yandex.com/bots' => 'YandexBot',
				'applebot'     => 'Applebot',
				'sogou'        => 'Sogou',
				'seznambot'    => 'SeznamBot',
				'petalbot'     => 'PetalBot (Huawei)',
				'qwantbot'     => 'QwantBot',
				'qwantify'     => 'QwantBot',
			),
			'seo'        => array(
				'ahrefsbot'      => 'AhrefsBot',
				'ahrefssiteaudit' => 'Ahrefs Site Audit',
				'semrushbot'     => 'SemrushBot',
				'mj12bot'        => 'MJ12bot (Majestic)',
				'dotbot'         => 'DotBot (Moz)',
				'rogerbot'       => 'Rogerbot (Moz)',
				'screaming frog' => 'Screaming Frog',
				'dataforseobot'  => 'DataForSEO',
				'serpstatbot'    => 'SerpstatBot',
				'blexbot'        => 'BLEXBot',
				'seobilitybot'   => 'SeobilityBot',
				'barkrowler'     => 'Barkrowler',
				'sistrix'        => 'SISTRIX',
				'seokicks'       => 'SEOkicks',
			),
			'social'     => array(
				'facebookexternalhit' => 'Facebook',
				'facebookcatalog'     => 'Facebook Catalog',
				'twitterbot'          => 'Twitterbot (X)',
				'linkedinbot'         => 'LinkedInBot',
				'pinterestbot'        => 'PinterestBot',
				'pinterest/'          => 'Pinterest',
				'whatsapp'            => 'WhatsApp',
				'telegrambot'         => 'TelegramBot',
				'slackbot'            => 'Slackbot',
				'slack-imgproxy'      => 'Slack ImgProxy',
				'discordbot'          => 'Discordbot',
				'skypeuripreview'     => 'Skype Preview',
				'redditbot'           => 'RedditBot',
			),
			'monitor'    => array(
				'uptimerobot'       => 'UptimeRobot',
				'pingdom'           => 'Pingdom',
				'statuscake'        => 'StatusCake',
				'site24x7'          => 'Site24x7',
				'gtmetrix'          => 'GTmetrix',
				'chrome-lighthouse' => 'Lighthouse',
				'headlesschrome'    => 'Headless Chrome',
				'phantomjs'         => 'PhantomJS',
				'newrelicpinger'    => 'New Relic',
				'betteruptime'      => 'Better Uptime',
				'hetrixtools'       => 'HetrixTools',
				'jetmonitors'       => 'JetMonitors',
			),
		);

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Permite ampliar o modificar las listas de patrones sin editar el plugin.
			 *
			 * @param array $patterns category => [ needle => label ].
			 */
			$patterns = apply_filters( 'agendetect_classifier_patterns', $patterns );
		}

		return $patterns;
	}

	/**
	 * Etiquetas legibles en español para cada categoría.
	 */
	public static function category_labels() {
		return array(
			'browser'    => 'Navegador',
			'search'     => 'Buscador',
			'ai'         => 'Bot IA',
			'seo'        => 'Bot SEO',
			'social'     => 'Redes sociales',
			'monitor'    => 'Monitorización',
			'bot'        => 'Bot genérico',
			'suspicious' => 'Sospechoso',
			'unknown'    => 'Desconocido',
		);
	}
}
