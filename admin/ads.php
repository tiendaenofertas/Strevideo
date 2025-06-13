<?php
// admin/ads.php - Gestión de anuncios
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../app/middleware/AdminMiddleware.php';

AdminMiddleware::check();

$db = Database::getInstance();
$message = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = $db->escape($_POST['name']);
                $type = $db->escape($_POST['type']);
                $code = $db->escape($_POST['code']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $db->query("INSERT INTO ads (name, type, code, is_active) VALUES ('$name', '$type', '$code', $is_active)");
                $message = 'Anuncio creado exitosamente';
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $db->query("DELETE FROM ads WHERE id = $id");
                $message = 'Anuncio eliminado';
                break;
                
            case 'toggle':
                $id = intval($_POST['id']);
                $db->query("UPDATE ads SET is_active = NOT is_active WHERE id = $id");
                $message = 'Estado actualizado';
                break;
        }
    }
}

// Obtener anuncios
$ads = $db->query("SELECT * FROM ads ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Anuncios - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cog"></i> Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="/admin/users.php" class="nav-item">
                    <i class="fas fa-users"></i> Usuarios
                </a>
                <a href="/admin/videos.php" class="nav-item">
                    <i class="fas fa-video"></i> Videos
                </a>
                <a href="/admin/storage.php" class="nav-item">
                    <i class="fas fa-hdd"></i> Almacenamiento
                </a>
                <a href="/admin/reports.php" class="nav-item">
                    <i class="fas fa-flag"></i> Reportes
                </a>
                <a href="/admin/ads.php" class="nav-item active">
                    <i class="fas fa-ad"></i> Anuncios
                </a>
                <a href="/admin/settings.php" class="nav-item">
                    <i class="fas fa-cog"></i> Configuración
                </a>
                <div class="nav-divider"></div>
                <a href="/" class="nav-item">
                    <i class="fas fa-home"></i> Volver al Sitio
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1>Gestión de Anuncios</h1>
                <button class="btn btn-primary" onclick="showCreateModal()">
                    <i class="fas fa-plus"></i> Nuevo Anuncio
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Lista de anuncios -->
            <div class="admin-section">
                <div class="admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ads as $ad): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ad['name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $ad['type']; ?>">
                                            <?php echo ucfirst($ad['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $ad['is_active'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                <?php echo $ad['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($ad['created_at'])); ?></td>
                                    <td>
                                        <button onclick="editAd(<?php echo htmlspecialchars(json_encode($ad)); ?>)" 
                                                class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('¿Eliminar este anuncio?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Crear/Editar -->
    <div id="adModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Nuevo Anuncio</h2>
            <form method="POST" id="adForm">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="id" id="adId">
                
                <div class="form-group">
                    <label>Nombre del Anuncio</label>
                    <input type="text" name="name" id="adName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="type" id="adType" class="form-control" required>
                        <option value="preroll">Pre-roll (Antes del video)</option>
                        <option value="midroll">Mid-roll (Durante el video)</option>
                        <option value="popup">Popup</option>
                        <option value="banner">Banner</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Código del Anuncio (HTML/JavaScript)</label>
                    <textarea name="code" id="adCode" class="form-control" rows="6" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" id="adActive" value="1" checked>
                        Activar anuncio
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            </form>
        </div>
    </div>

    <style>
    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border-radius: var(--border-radius);
        width: 90%;
        max-width: 600px;
    }
    
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover {
        color: #000;
    }
    
    .badge-preroll { background: #3b82f6; color: white; }
    .badge-midroll { background: #8b5cf6; color: white; }
    .badge-popup { background: #f59e0b; color: white; }
    .badge-banner { background: #10b981; color: white; }
    </style>

    <script>
    function showCreateModal() {
        document.getElementById('modalTitle').textContent = 'Nuevo Anuncio';
        document.getElementById('adForm').reset();
        document.getElementById('adModal').style.display = 'block';
    }
    
    function editAd(ad) {
        document.getElementById('modalTitle').textContent = 'Editar Anuncio';
        document.getElementById('adId').value = ad.id;
        document.getElementById('adName').value = ad.name;
        document.getElementById('adType').value = ad.type;
        document.getElementById('adCode').value = ad.code;
        document.getElementById('adActive').checked = ad.is_active == 1;
        document.getElementById('adModal').style.display = 'block';
    }
    
    function closeModal() {
        document.getElementById('adModal').style.display = 'none';
    }
    </script>
</body>
</html>

--- ARCHIVO SEPARADO: admin/videos.php ---
<?php
// admin/videos.php - Gestión de videos
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../app/middleware/AdminMiddleware.php';

AdminMiddleware::check();

$db = Database::getInstance();

// Paginación
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Búsqueda y filtros
$search = isset($_GET['search']) ? $db->escape($_GET['search']) : '';
$status = isset($_GET['status']) ? $db->escape($_GET['status']) : '';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Construir consulta
$where = [];
if ($search) {
    $where[] = "(v.title LIKE '%$search%' OR v.video_code LIKE '%$search%')";
}
if ($status) {
    $where[] = "v.status = '$status'";
}
if ($user_id) {
    $where[] = "v.user_id = $user_id";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener total
$totalResult = $db->query("SELECT COUNT(*) as total FROM videos v $whereClause");
$total = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($total / $perPage);

// Obtener videos
$videos = $db->query("
    SELECT v.*, u.username 
    FROM videos v 
    JOIN users u ON v.user_id = u.id 
    $whereClause
    ORDER BY v.created_at DESC 
    LIMIT $offset, $perPage
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Videos - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cog"></i> Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="/admin/users.php" class="nav-item">
                    <i class="fas fa-users"></i> Usuarios
                </a>
                <a href="/admin/videos.php" class="nav-item active">
                    <i class="fas fa-video"></i> Videos
                </a>
                <a href="/admin/storage.php" class="nav-item">
                    <i class="fas fa-hdd"></i> Almacenamiento
                </a>
                <a href="/admin/reports.php" class="nav-item">
                    <i class="fas fa-flag"></i> Reportes
                </a>
                <a href="/admin/ads.php" class="nav-item">
                    <i class="fas fa-ad"></i> Anuncios
                </a>
                <a href="/admin/settings.php" class="nav-item">
                    <i class="fas fa-cog"></i> Configuración
                </a>
                <div class="nav-divider"></div>
                <a href="/" class="nav-item">
                    <i class="fas fa-home"></i> Volver al Sitio
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1>Gestión de Videos</h1>
                <div class="header-stats">
                    <span class="stat-badge">
                        <i class="fas fa-video"></i> <?php echo number_format($total); ?> videos
                    </span>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Buscar por título o código..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select name="status" class="form-control">
                            <option value="">Todos los estados</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activo</option>
                            <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Procesando</option>
                            <option value="deleted" <?php echo $status === 'deleted' ? 'selected' : ''; ?>>Eliminado</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    
                    <a href="/admin/videos.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </form>
            </div>

            <!-- Tabla de videos -->
            <div class="admin-section">
                <div class="admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Miniatura</th>
                                <th>Título</th>
                                <th>Usuario</th>
                                <th>Tamaño</th>
                                <th>Vistas</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos as $video): ?>
                                <tr>
                                    <td><?php echo $video['id']; ?></td>
                                    <td>
                                        <img src="<?php echo $video['thumbnail'] ?: '/assets/img/default-thumb.jpg'; ?>" 
                                             alt="Thumbnail" class="table-thumb">
                                    </td>
                                    <td>
                                        <a href="/pages/watch.php?v=<?php echo $video['video_code']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($video['title']); ?>
                                        </a>
                                        <br>
                                        <small class="text-muted"><?php echo $video['video_code']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($video['username']); ?></td>
                                    <td><?php echo formatBytes($video['file_size']); ?></td>
                                    <td><?php echo number_format($video['views']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $video['status']; ?>">
                                            <?php echo ucfirst($video['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($video['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="/admin/video-edit.php?id=<?php echo $video['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="deleteVideo(<?php echo $video['id']; ?>)" 
                                                    class="btn btn-sm btn-danger" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>" 
                               class="btn btn-sm">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        
                        <span class="page-info">
                            Página <?php echo $page; ?> de <?php echo $totalPages; ?>
                        </span>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>" 
                               class="btn btn-sm">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
    .filters-section {
        background: var(--white);
        padding: 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
    }
    
    .filters-form {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .table-thumb {
        width: 80px;
        height: 45px;
        object-fit: cover;
        border-radius: 4px;
    }
    
    .badge-active { background: var(--success-color); color: white; }
    .badge-processing { background: var(--warning-color); color: white; }
    .badge-deleted { background: var(--danger-color); color: white; }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1rem;
        margin-top: 2rem;
    }
    
    .page-info {
        color: #6b7280;
    }
    </style>

    <script>
    function deleteVideo(id) {
        if (confirm('¿Estás seguro de eliminar este video? Esta acción no se puede deshacer.')) {
            fetch('/api/admin/delete-video.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar el video: ' + (data.message || 'Error desconocido'));
                }
            })
            .catch(error => {
                alert('Error de conexión');
                console.error(error);
            });
        }
    }
    </script>
</body>
</html>