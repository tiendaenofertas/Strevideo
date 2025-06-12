<?php
// api/upload.php - API para subir videos
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../app/helpers/upload.php';
require_once '../app/helpers/thumbnail.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id']) && !isset($_POST['api_key'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Si es por API key, verificar
if (isset($_POST['api_key'])) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id, role FROM users WHERE api_key = ? AND status = 'active'");
    $stmt->bind_param("s", $_POST['api_key']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'API key inválida']);
        exit;
    }
    
    $_SESSION['user_id'] = $user['id'];
}

// Verificar CSRF token para uploads web
if (!isset($_POST['api_key']) && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// Obtener información del usuario
$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Verificar límites de almacenamiento
$storageConfig = include '../config/storage.php';
$userLimits = $storageConfig['limits'][$user['role']];

if ($user['storage_used'] >= $userLimits['total_storage'] && $userLimits['total_storage'] != -1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Límite de almacenamiento alcanzado']);
    exit;
}

try {
    $uploadMethod = $_POST['upload_method'] ?? 'file';
    $videoData = null;
    
    if ($uploadMethod === 'file') {
        // Subida de archivo local
        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo');
        }
        
        $file = $_FILES['video'];
        
        // Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            throw new Exception('Formato de archivo no permitido');
        }
        
        // Validar tamaño
        if ($file['size'] > $userLimits['max_file_size'] && $userLimits['max_file_size'] != -1) {
            throw new Exception('El archivo excede el tamaño máximo permitido');
        }
        
        // Generar código único para el video
        $videoCode = generateVideoCode();
        $filename = $videoCode . '.' . $ext;
        
        // Determinar almacenamiento
        $storageType = $_POST['storage'] ?? 'local';
        
        // Subir archivo según el tipo de almacenamiento
        $uploadResult = uploadToStorage($file['tmp_name'], $filename, $storageType);
        
        if (!$uploadResult['success']) {
            throw new Exception($uploadResult['message']);
        }
        
        // Generar miniatura
        $thumbnailPath = generateThumbnail($file['tmp_name'], $videoCode);
        
        // Obtener información del video
        $videoInfo = getVideoInfo($file['tmp_name']);
        
        $videoData = [
            'video_code' => $videoCode,
            'filename' => $filename,
            'original_filename' => $file['name'],
            'file_size' => $file['size'],
            'storage_path' => $uploadResult['path'],
            'thumbnail' => $thumbnailPath,
            'duration' => $videoInfo['duration'] ?? 0,
            'format' => $ext,
            'resolution' => $videoInfo['resolution'] ?? null
        ];
        
    } else if ($uploadMethod === 'url') {
        // Descarga desde URL
        $videoUrl = filter_var($_POST['video_url'], FILTER_VALIDATE_URL);
        if (!$videoUrl) {
            throw new Exception('URL inválida');
        }
        
        // Descargar video
        $tempFile = tempnam(sys_get_temp_dir(), 'video_');
        $downloadResult = downloadFromUrl($videoUrl, $tempFile);
        
        if (!$downloadResult['success']) {
            throw new Exception($downloadResult['message']);
        }
        
        // Obtener extensión del archivo descargado
        $ext = $downloadResult['extension'];
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            unlink($tempFile);
            throw new Exception('Formato de archivo no permitido');
        }
        
        // Validar tamaño
        $fileSize = filesize($tempFile);
        if ($fileSize > $userLimits['max_file_size'] && $userLimits['max_file_size'] != -1) {
            unlink($tempFile);
            throw new Exception('El archivo excede el tamaño máximo permitido');
        }
        
        // Generar código único
        $videoCode = generateVideoCode();
        $filename = $videoCode . '.' . $ext;
        
        // Subir a almacenamiento
        $storageType = $_POST['storage'] ?? 'local';
        $uploadResult = uploadToStorage($tempFile, $filename, $storageType);
        
        if (!$uploadResult['success']) {
            unlink($tempFile);
            throw new Exception($uploadResult['message']);
        }
        
        // Generar miniatura
        $thumbnailPath = generateThumbnail($tempFile, $videoCode);
        
        // Obtener información del video
        $videoInfo = getVideoInfo($tempFile);
        
        // Limpiar archivo temporal
        unlink($tempFile);
        
        $videoData = [
            'video_code' => $videoCode,
            'filename' => $filename,
            'original_filename' => basename($videoUrl),
            'file_size' => $fileSize,
            'storage_path' => $uploadResult['path'],
            'thumbnail' => $thumbnailPath,
            'duration' => $videoInfo['duration'] ?? 0,
            'format' => $ext,
            'resolution' => $videoInfo['resolution'] ?? null
        ];
    }
    
    // Guardar en base de datos
    $stmt = $db->prepare("
        INSERT INTO videos (
            user_id, video_code, title, description, filename, original_filename,
            file_size, duration, format, resolution, thumbnail, storage_type,
            storage_path, privacy, allow_download, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    
    $title = $_POST['title'] ?? $videoData['original_filename'];
    $description = $_POST['description'] ?? '';
    $privacy = $_POST['privacy'] ?? 'public';
    $allowDownload = isset($_POST['allow_download']) ? 1 : 0;
    
    $stmt->bind_param(
        "issssssissssssi",
        $_SESSION['user_id'],
        $videoData['video_code'],
        $title,
        $description,
        $videoData['filename'],
        $videoData['original_filename'],
        $videoData['file_size'],
        $videoData['duration'],
        $videoData['format'],
        $videoData['resolution'],
        $videoData['thumbnail'],
        $storageType,
        $videoData['storage_path'],
        $privacy,
        $allowDownload
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Error al guardar en la base de datos');
    }
    
    // Actualizar almacenamiento usado del usuario
    $stmt = $db->prepare("UPDATE users SET storage_used = storage_used + ? WHERE id = ?");
    $stmt->bind_param("ii", $videoData['file_size'], $_SESSION['user_id']);
    $stmt->execute();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Video subido exitosamente',
        'data' => [
            'video_code' => $videoData['video_code'],
            'url' => SITE_URL . '/watch/' . $videoData['video_code'],
            'embed_url' => SITE_URL . '/embed/' . $videoData['video_code'],
            'thumbnail' => $videoData['thumbnail']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Funciones auxiliares
function generateVideoCode() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 12; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function downloadFromUrl($url, $destination) {
    $ch = curl_init($url);
    $fp = fopen($destination, 'wb');
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    curl_close($ch);
    fclose($fp);
    
    if (!$success || $httpCode !== 200) {
        return ['success' => false, 'message' => 'Error al descargar el archivo'];
    }
    
    // Determinar extensión basada en content-type
    $extension = 'mp4'; // Por defecto
    if (strpos($contentType, 'video/webm') !== false) {
        $extension = 'webm';
    } else if (strpos($contentType, 'video/quicktime') !== false) {
        $extension = 'mov';
    } else if (strpos($contentType, 'video/x-msvideo') !== false) {
        $extension = 'avi';
    }
    
    return [
        'success' => true,
        'extension' => $extension
    ];
}

function getVideoInfo($filePath) {
    // Aquí deberías usar FFmpeg para obtener información del video
    // Por ahora retornamos valores por defecto
    return [
        'duration' => 0,
        'resolution' => '1920x1080'
    ];
}
