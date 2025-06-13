<?php
// api/admin/delete-video.php - API para eliminar videos (admin)
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../app/helpers/upload.php';

header('Content-Type: application/json');

// Verificar que sea admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$videoId = intval($data['id'] ?? 0);

if (!$videoId) {
    echo json_encode(['success' => false, 'message' => 'ID de video inválido']);
    exit;
}

$db = Database::getInstance();

// Obtener información del video
$stmt = $db->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->bind_param("i", $videoId);
$stmt->execute();
$result = $stmt->get_result();
$video = $result->fetch_assoc();

if (!$video) {
    echo json_encode(['success' => false, 'message' => 'Video no encontrado']);
    exit;
}

try {
    // Eliminar archivo físico
    deleteFromStorage($video['filename'], $video['storage_type'], $video['storage_path']);
    
    // Eliminar miniatura
    if ($video['thumbnail']) {
        $thumbPath = PUBLIC_PATH . $video['thumbnail'];
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }
    }
    
    // Actualizar estado en BD (soft delete)
    $stmt = $db->prepare("UPDATE videos SET status = 'deleted' WHERE id = ?");
    $stmt->bind_param("i", $videoId);
    $stmt->execute();
    
    // Actualizar almacenamiento del usuario
    $stmt = $db->prepare("UPDATE users SET storage_used = storage_used - ? WHERE id = ?");
    $stmt->bind_param("ii", $video['file_size'], $video['user_id']);
    $stmt->execute();
    
    // Eliminar estadísticas
    $stmt = $db->prepare("DELETE FROM statistics WHERE video_id = ?");
    $stmt->bind_param("i", $videoId);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Video eliminado correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}

--- CREAR CARPETA: api/admin/ ---
--- ARCHIVO SEPARADO: api/delete-video.php (para usuarios) ---
<?php
// api/delete-video.php - API para eliminar videos (usuarios)
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../app/helpers/upload.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$videoId = intval($data['id'] ?? 0);

if (!$videoId) {
    echo json_encode(['success' => false, 'message' => 'ID de video inválido']);
    exit;
}

$db = Database::getInstance();

// Verificar que el video pertenece al usuario
$stmt = $db->prepare("SELECT * FROM videos WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $videoId, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$video = $result->fetch_assoc();

if (!$video) {
    echo json_encode(['success' => false, 'message' => 'Video no encontrado o no tienes permisos']);
    exit;
}

try {
    // Eliminar archivo físico
    deleteFromStorage($video['filename'], $video['storage_type'], $video['storage_path']);
    
    // Eliminar miniatura
    if ($video['thumbnail']) {
        $thumbPath = PUBLIC_PATH . $video['thumbnail'];
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }
    }
    
    // Eliminar de BD
    $stmt = $db->prepare("DELETE FROM videos WHERE id = ?");
    $stmt->bind_param("i", $videoId);
    $stmt->execute();
    
    // Actualizar almacenamiento del usuario
    $stmt = $db->prepare("UPDATE users SET storage_used = storage_used - ? WHERE id = ?");
    $stmt->bind_param("ii", $video['file_size'], $_SESSION['user_id']);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Video eliminado correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el video']);
}