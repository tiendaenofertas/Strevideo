<?php
// app/helpers/functions.php - Funciones auxiliares globales

/**
 * Formatear bytes a formato legible
 */
function formatBytes($bytes, $precision = 2) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), $precision) . ' ' . $sizes[$i];
}

/**
 * Formatear duración de video
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return '0:' . str_pad($seconds, 2, '0', STR_PAD_LEFT);
    }
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
    }
    
    return sprintf('%d:%02d', $minutes, $seconds);
}

/**
 * Tiempo transcurrido en formato legible
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'hace unos segundos';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return 'hace ' . $minutes . ' ' . ($minutes == 1 ? 'minuto' : 'minutos');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return 'hace ' . $hours . ' ' . ($hours == 1 ? 'hora' : 'horas');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return 'hace ' . $days . ' ' . ($days == 1 ? 'día' : 'días');
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return 'hace ' . $weeks . ' ' . ($weeks == 1 ? 'semana' : 'semanas');
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return 'hace ' . $months . ' ' . ($months == 1 ? 'mes' : 'meses');
    } else {
        $years = floor($diff / 31536000);
        return 'hace ' . $years . ' ' . ($years == 1 ? 'año' : 'años');
    }
}

/**
 * Obtener variable de entorno
 */
function env($key, $default = null) {
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    
    return $default;
}

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitizar entrada de usuario
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generar API key única
 */
function generateApiKey() {
    return bin2hex(random_bytes(32));
}

/**
 * Verificar límite de ancho de banda
 */
function checkBandwidthLimit($ip) {
    $db = Database::getInstance();
    $today = date('Y-m-d');
    
    // Verificar si existe registro para hoy
    $stmt = $db->prepare("
        SELECT bandwidth_used FROM bandwidth_limits 
        WHERE ip_address = ? AND reset_date = ?
    ");
    $stmt->bind_param("ss", $ip, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['bandwidth_used'] < DAILY_BANDWIDTH_LIMIT;
    }
    
    // Crear nuevo registro
    $stmt = $db->prepare("
        INSERT INTO bandwidth_limits (ip_address, reset_date) 
        VALUES (?, ?)
    ");
    $stmt->bind_param("ss", $ip, $today);
    $stmt->execute();
    
    return true;
}

/**
 * Actualizar uso de ancho de banda
 */
function updateBandwidthUsage($ip, $bytes) {
    $db = Database::getInstance();
    $today = date('Y-m-d');
    
    $stmt = $db->prepare("
        UPDATE bandwidth_limits 
        SET bandwidth_used = bandwidth_used + ? 
        WHERE ip_address = ? AND reset_date = ?
    ");
    $stmt->bind_param("iss", $bytes, $ip, $today);
    $stmt->execute();
}

/**
 * Obtener país por IP
 */
function getCountryByIP($ip) {
    // Aquí podrías implementar una API de geolocalización
    // Por ahora retornamos un valor por defecto
    return 'US';
}

/**
 * Validar tipo MIME de video
 */
function isValidVideoMimeType($mimeType) {
    $validTypes = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska'
    ];
    
    return in_array($mimeType, $validTypes);
}

/**
 * Generar slug desde texto
 */
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

/**
 * Calcular ganancias por vistas
 */
function calculateEarnings($views, $downloads = 0) {
    $viewEarnings = ($views / 1000) * CPM_RATE;
    $downloadEarnings = ($downloads / 1000) * DOWNLOAD_RATE;
    
    return $viewEarnings + $downloadEarnings;
}

/**
 * Verificar si el usuario puede hacer payout
 */
function canRequestPayout($userId) {
    $db = Database::getInstance();
    
    $stmt = $db->prepare("
        SELECT SUM(amount) as total 
        FROM earnings 
        WHERE user_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'] >= MIN_PAYOUT;
}

/**
 * Log de actividad
 */
function logActivity($type, $message, $userId = null) {
    $logFile = ROOT_PATH . '/storage/logs/activity_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $logEntry = "[{$timestamp}] [{$type}] [IP: {$ip}] [User: {$userId}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Limpiar archivos temporales antiguos
 */
function cleanupTempFiles() {
    $tempDir = ROOT_PATH . '/storage/temp/';
    $maxAge = 86400; // 24 horas
    
    $files = glob($tempDir . '*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file) > $maxAge)) {
            unlink($file);
        }
    }
}