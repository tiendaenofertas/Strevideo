=== Agendetect ===
Contributors: joelramos
Tags: user agent, bots, crawler, registro, seguridad
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detecta y registra el user agent de cada visitante con historial diario, clasificación bot/humano y exportación a Excel por rango de fechas.

== Description ==

Agendetect registra automáticamente el user agent de todas las visitas del frontend y las organiza en un historial diario, para que puedas distinguir usuarios reales de bots e identificar qué bots no deseas en tu sitio. El plugin solo observa y registra: no bloquea nada. Con el historial como referencia, tú decides qué bloquear manualmente (robots.txt, .htaccess o tu firewall).

**Características**

* Registro automático de cada visita del frontend, agregado por user agent y día: una fila por user agent y día, con contador de visitas, primera/última hora y URLs visitadas. El historial escala indefinidamente sin afectar al rendimiento (máximo 2 consultas ligeras por visita).
* Clasificación automática de cada user agent: buscadores (Googlebot, Bingbot…), bots de IA (GPTBot, ClaudeBot…), herramientas SEO (Ahrefs, Semrush…), redes sociales, monitorización, bots genéricos, clientes sospechosos (curl, python, escáneres) y navegadores humanos.
* Pantalla de registros con selector de fecha, filtros (todos / bots / humanos / sospechosos), búsqueda, paginación y detalle expandible con el user agent completo y las URLs visitadas.
* Exportación a Excel (.xlsx real, sin dependencias) de todos los registros del rango de fechas que elijas. Si el servidor no dispone de ZipArchive, exporta CSV compatible con Excel.
* Retención configurable: 30/90/180/365 días o para siempre. Una tarea diaria elimina automáticamente los registros antiguos.
* Desinstalación 100% limpia: al eliminar el plugin se borran sus tablas, opciones y tareas programadas, sin dejar rastro.
* Seguridad: consultas preparadas, escapado estricto de datos controlados por el visitante, nonces y comprobación de permisos en todas las acciones.

**Limitación conocida (caché de página)**

Si usas un plugin de caché de página o un CDN que sirve HTML cacheado, esas visitas no ejecutan PHP y no se contabilizan. Los bots que rastrean URLs no cacheadas (la mayoría) sí quedan registrados. Para sitios con mucho tráfico se recomienda desactivar WP-Cron (`DISABLE_WP_CRON`) y usar un cron real del sistema.

== Installation ==

1. Sube la carpeta `agendetect` a `/wp-content/plugins/` o instala el ZIP desde Plugins → Añadir nuevo.
2. Activa el plugin.
3. Ve al menú **Agendetect** para consultar los registros y a **Agendetect → Ajustes** para configurar la retención.

== Frequently Asked Questions ==

= ¿El plugin bloquea bots? =

No. Agendetect es una herramienta de observación: te da un historial fiable para que decidas qué bloquear manualmente.

= ¿Guarda direcciones IP o datos personales? =

No. Solo guarda el user agent, la fecha, las horas de primera/última visita del día, el contador de visitas y las rutas de URL visitadas.

= ¿Crece la base de datos sin control? =

No. Al agregar por user agent y día, incluso un sitio con cientos de miles de visitas diarias genera solo unos cientos de filas al día, y la purga automática elimina lo más antiguo según la retención configurada.

== Changelog ==

= 1.0.0 =
* Versión inicial: registro diario agregado, clasificación automática, pantalla de registros con selector de fecha, exportación XLSX/CSV, retención configurable y desinstalación limpia.
