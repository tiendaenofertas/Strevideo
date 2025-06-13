<?php
// user/dashboard.php - Panel de control del usuario
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Obtener información del usuario
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Estadísticas del usuario
$stats = [
    'total_videos' => $db->query("SELECT COUNT(*) as count FROM videos WHERE user_id = $userId AND status = 'active'")->fetch_assoc()['count'],
    'total_views' => $db->query("SELECT SUM(views) as sum FROM videos WHERE user_id = $userId")->fetch_assoc()['sum'] ?? 0,
    'total_downloads' => $db->query("SELECT SUM(downloads) as sum FROM videos WHERE user_id = $userId")->fetch_assoc()['sum'] ?? 0,
    'storage_used' => $user['storage_used'],
    'storage_limit' => $user['storage_limit'],
    'pending_earnings' => $db->query("SELECT SUM(amount) as sum FROM earnings WHERE user_id = $userId AND status = 'pending'")->fetch_assoc()['sum'] ?? 0,
    'total_earnings' => $db->query("SELECT SUM(amount) as sum FROM earnings WHERE user_id = $userId AND status = 'paid'")->fetch_assoc()['sum'] ?? 0
];

// Videos recientes
$recentVideos = $db->query("
    SELECT * FROM videos 
    WHERE user_id = $userId 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Estadísticas de los últimos 30 días
$last30Days = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $views = $db->query("
        SELECT COUNT(*) as count 
        FROM statistics s 
        JOIN videos v ON s.video_id = v.id 
        WHERE v.user_id = $userId 
        AND DATE(s.created_at) = '$date' 
        AND s.action = 'view'
    ")->fetch_assoc()['count'];
    
    $last30Days[] = [
        'date' => date('d', strtotime($date)),
        'views' => $views
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <nav class="navbar">
            <div class="container">
                <div class="nav-brand">
                    <a href="/" class="logo">
                        <i class="fas fa-play-circle"></i> <?php echo SITE_NAME; ?>
                    </a>
                </div>
                <div class="nav-menu">
                    <a href="/" class="nav-link">Inicio</a>
                    <a href="/pages/upload.php" class="nav-link">
                        <i class="fas fa-upload"></i> Subir Video
                    </a>
                    <div class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username']); ?>
                        </a>
                        <div class="dropdown-menu">
                            <a href="/user/settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Configuración
                            </a>
                            <a href="/logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="user-container">
        <div class="container">
            <!-- User Sidebar -->
            <aside class="user-sidebar">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                    <span class="user-role badge-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </div>
                
                <nav class="sidebar-menu">
                    <a href="/user/dashboard.php" class="menu-item active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="/user/my-videos.php" class="menu-item">
                        <i class="fas fa-video"></i> Mis Videos
                    </a>
                    <a href="/user/statistics.php" class="menu-item">
                        <i class="fas fa-chart-line"></i> Estadísticas
                    </a>
                    <a href="/user/earnings.php" class="menu-item">
                        <i class="fas fa-dollar-sign"></i> Ganancias
                    </a>
                    <a href="/user/settings.php" class="menu-item">
                        <i class="fas fa-cog"></i> Configuración
                    </a>
                </nav>
                
                <!-- Storage Usage -->
                <div class="storage-widget">
                    <h4>Almacenamiento</h4>
                    <div class="storage-bar">
                        <div class="storage-used" style="width: <?php echo ($stats['storage_used'] / $stats['storage_limit']) * 100; ?>%"></div>
                    </div>
                    <p class="storage-text">
                        <?php echo formatBytes($stats['storage_used']); ?> / <?php echo formatBytes($stats['storage_limit']); ?>
                    </p>
                    <?php if ($user['role'] === 'user'): ?>
                        <a href="/upgrade" class="btn btn-sm btn-primary btn-block">
                            <i class="fas fa-crown"></i> Actualizar a Premium
                        </a>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="user-main">
                <h1 class="page-title">Panel de Control</h1>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-video stat-icon"></i>
                        <div class="stat-content">
                            <h3>Videos</h3>
                            <p class="stat-number"><?php echo number_format($stats['total_videos']); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-eye stat-icon"></i>
                        <div class="stat-content">
                            <h3>Vistas Totales</h3>
                            <p class="stat-number"><?php echo number_format($stats['total_views']); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-download stat-icon"></i>
                        <div class="stat-content">
                            <h3>Descargas</h3>
                            <p class="stat-number"><?php echo number_format($stats['total_downloads']); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-dollar-sign stat-icon"></i>
                        <div class="stat-content">
                            <h3>Ganancias Pendientes</h3>
                            <p class="stat-number">$<?php echo number_format($stats['pending_earnings'], 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Views Chart -->
                <div class="chart-section">
                    <h2>Vistas en los Últimos 30 Días</h2>
                    <canvas id="viewsChart"></canvas>
                </div>

                <!-- Recent Videos -->
                <div class="recent-section">
                    <div class="section-header">
                        <h2>Videos Recientes</h2>
                        <a href="/user/my-videos.php" class="btn btn-sm">Ver Todos</a>
                    </div>
                    
                    <?php if (count($recentVideos) > 0): ?>
                        <div class="video-list">
                            <?php foreach ($recentVideos as $video): ?>
                                <div class="video-item">
                                    <div class="video-thumb">
                                        <img src="<?php echo $video['thumbnail'] ?: '/assets/img/default-thumb.jpg'; ?>" 
                                             alt="<?php echo htmlspecialchars($video['title']); ?>">
                                    </div>
                                    <div class="video-details">
                                        <h4>
                                            <a href="/pages/watch.php?v=<?php echo $video['video_code']; ?>" target="_blank">
                                                <?php echo htmlspecialchars($video['title']); ?>
                                            </a>
                                        </h4>
                                        <div class="video-stats">
                                            <span><i class="fas fa-eye"></i> <?php echo number_format($video['views']); ?></span>
                                            <span><i class="fas fa-download"></i> <?php echo number_format($video['downloads']); ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($video['created_at'])); ?></span>
                                            <span class="video-status status-<?php echo $video['status']; ?>">
                                                <?php echo ucfirst($video['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="video-actions">
                                        <a href="/user/video-edit.php?id=<?php echo $video['id']; ?>" 
                                           class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="copyVideoLink('<?php echo $video['video_code']; ?>')" 
                                                class="btn btn-sm btn-secondary" title="Copiar enlace">
                                            <i class="fas fa-link"></i>
                                        </button>
                                        <button onclick="deleteVideo(<?php echo $video['id']; ?>)" 
                                                class="btn btn-sm btn-danger" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-video"></i>
                            <p>No has subido ningún video aún</p>
                            <a href="/pages/upload.php" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Subir mi primer video
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Acciones Rápidas</h2>
                    <div class="actions-grid">
                        <a href="/pages/upload.php" class="action-card">
                            <i class="fas fa-upload"></i>
                            <span>Subir Video</span>
                        </a>
                        <a href="/user/my-videos.php" class="action-card">
                            <i class="fas fa-list"></i>
                            <span>Ver Todos</span>
                        </a>
                        <a href="/user/statistics.php" class="action-card">
                            <i class="fas fa-chart-bar"></i>
                            <span>Estadísticas</span>
                        </a>
                        <a href="/user/earnings.php" class="action-card">
                            <i class="fas fa-money-bill"></i>
                            <span>Ganancias</span>
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Gráfico de vistas
        const viewsData = <?php echo json_encode($last30Days); ?>;
        const ctx = document.getElementById('viewsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: viewsData.map(d => d.date),
                datasets: [{
                    label: 'Vistas',
                    data: viewsData.map(d => d.views),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Copiar enlace del video
        function copyVideoLink(videoCode) {
            const url = '<?php echo SITE_URL; ?>/watch/' + videoCode;
            navigator.clipboard.writeText(url).then(() => {
                alert('Enlace copiado al portapapeles');
            });
        }

        // Eliminar video
        function deleteVideo(id) {
            if (confirm('¿Estás seguro de eliminar este video?')) {
                fetch('/api/delete-video.php', {
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
                        alert('Error al eliminar el video');
                    }
                });
            }
        }
    </script>
</body>
</html>