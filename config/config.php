<?php
// config/config.php - Configuración principal del sistema

// Configuración de la aplicación
define('SITE_NAME', 'Mi Plataforma de Videos');
define('SITE_URL', 'https://xzorra.net/video-platform');
define('SITE_EMAIL', 'info@tudominio.com');

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Límites de subida
define('MAX_UPLOAD_SIZE', 2147483648); // 2GB
define('ALLOWED_EXTENSIONS', ['mp4', 'webm', 'mov', 'avi', 'mkv']);
define('THUMBNAIL_WIDTH', 1280);
define('THUMBNAIL_HEIGHT', 720);

// Configuración de seguridad
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600); // 1 hora
define('API_RATE_LIMIT', 100); // requests por hora

// Configuración de monetización
define('CPM_RATE', 0.002); // $2 por 1000 vistas
define('DOWNLOAD_RATE', 0.005); // $5 por 1000 descargas
define('MIN_PAYOUT', 50); // $50 mínimo para pago

// Rutas del sistema
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('APP_PATH', ROOT_PATH . '/app');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
define('VIDEO_PATH', UPLOAD_PATH . '/videos');
define('THUMBNAIL_PATH', UPLOAD_PATH . '/thumbnails');

// Configuración de ancho de banda
define('DAILY_BANDWIDTH_LIMIT', 10737418240); // 10GB por IP por día
define('BANDWIDTH_RESET_HOUR', 0); // Reset a medianoche

// Modo debug (cambiar a false en producción)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Cargar funciones helper
require_once APP_PATH . '/helpers/functions.php';