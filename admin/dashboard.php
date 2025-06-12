<?php
// admin/dashboard.php - Panel de administración principal
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../app/middleware/AdminMiddleware.php';

// Verificar acceso de administrador
AdminMiddleware::check();

$db = Database::getInstance();

// Obtener estadísticas generales
$stats = [
    'total_users' => $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'total_videos' => $db->query("SELECT COUNT(*) as count FROM videos WHERE status = 'active'")->fetch_assoc()['count'],
    'total_views' => $db->query("SELECT SUM(views) as sum FROM videos")->fetch_assoc()['sum'] ?? 0,
    'total_storage' => $db->query("SELECT SUM(file_size) as sum FROM videos WHERE status = 'active'")->fetch_assoc()['sum'] ?? 0,
    'today_uploads' => $db->query("SELECT COUNT(*) as count FROM videos WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'],
    'pending_reports' => $db->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count']
];

// Gráficos de últimos 7 días
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $uploads = $db->query("SELECT COUNT(*) as count FROM videos WHERE DATE(created_at) = '$date'")->fetch_assoc()['count'];
    $views = $db->query("SELECT COUNT(*) as count FROM statistics WHERE DATE(created_at) = '$date' AND action = 'view'")->fetch_assoc()['count'];
    $last7Days[] = [
        'date' => date('d/m', strtotime($date)),
        'uploads' => $uploads,
        'views' => $views
    ];
}

// Videos recientes
$recentVideos = $db->query("
    SELECT v.*, u.username 
    FROM videos v 
    JOIN users u ON v.user_id = u.id 
    ORDER BY v.created_at DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Usuarios activos
$activeUsers = $db->query("
    SELECT u.*, COUNT(v.id) as video_count, SUM(v.views) as total_views
    FROM users u
    LEFT JOIN videos v ON u.id = v.user_id
    GROUP BY u.id
    ORDER BY total_views DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cog"></i> Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="/admin/dashboard.php" class="nav-item active">
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
                    <?php if ($stats['pending_reports'] > 0): ?>
                        <span class="badge"><?php echo $stats['pending_reports']; ?></span>
                    <?php endif; ?>
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
                <a href="/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1>Dashboard</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Usuarios Totales</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_users']); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon videos">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Videos Activos</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_videos']); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon views">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Vistas Totales</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_views']); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon storage">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Almacenamiento</h3>
                        <p class="stat-number"><?php echo formatBytes($stats['total_storage']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Actividad de los Últimos 7 Días</h3>
                    <canvas id="activityChart"></canvas>
                </div>

                <div class="chart-card">
                    <h3>Distribución de Almacenamiento</h3>
                    <canvas id="storageChart"></canvas>
                </div>
            </div>

            <!-- Recent Videos -->
            <div class="admin-section">
                <div class="section-header">
                    <h2>Videos Recientes</h2>
                    <a href="/admin/videos.php" class="btn btn-sm">Ver Todos</a>
                </div>
                <div class="admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Usuario</th>
                                <th>Tamaño</th>
                                <th>Vistas</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentVideos as $video): ?>
                                <tr>
                                    <td>
                                        <a href="/pages/watch.php?v=<?php echo $video['video_code']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($video['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($video['username']); ?></td>
                                    <td><?php echo formatBytes($video['file_size']); ?></td>
                                    <td><?php echo number_format($video['views']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($video['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="/admin/video-edit.php?id=<?php echo $video['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="deleteVideo(<?php echo $video['id']; ?>)" 
                                                    class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Users -->
            <div class="admin-section">
                <div class="section-header">
                    <h2>Usuarios Más Activos</h2>
                    <a href="/admin/users.php" class="btn btn-sm">Ver Todos</a>
                </div>
                <div class="admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th>Videos</th>
                                <th>Vistas Totales</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeUsers as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($user['video_count']); ?></td>
                                    <td><?php echo number_format($user['total_views'] ?? 0); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="/admin/user-edit.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Gráfico de actividad
        const activityData = <?php echo json_encode($last7Days); ?>;
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: activityData.map(d => d.date),
                datasets: [{
                    label: 'Subidas',
                    data: activityData.map(d => d.uploads),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Vistas',
                    data: activityData.map(d => d.views),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de almacenamiento
        <?php
        $storageData = $db->query("
            SELECT storage_type, SUM(file_size) as total 
            FROM videos 
            WHERE status = 'active' 
            GROUP BY storage_type
        ")->fetch_all(MYSQLI_ASSOC);
        ?>
        
        const storageCtx = document.getElementById('storageChart').getContext('2d');
        new Chart(storageCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($storageData, 'storage_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($storageData, 'total')); ?>,
                    backgroundColor: ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Función para eliminar video
        function deleteVideo(id) {
            if (confirm('¿Estás seguro de eliminar este video?')) {
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
                        alert('Error al eliminar el video');
                    }
                });
            }
        }
    </script>
</body>
</html>