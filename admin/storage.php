<?php
// admin/storage.php - Gestión de servicios de almacenamiento
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../app/middleware/AdminMiddleware.php';

AdminMiddleware::check();

$db = Database::getInstance();
$storageConfig = include '../config/storage.php';

// Procesar formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_service') {
        $service = $_POST['service'];
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        
        // Actualizar configuración en la base de datos
        foreach ($_POST as $key => $value) {
            if (strpos($key, $service . '_') === 0) {
                $configKey = str_replace($service . '_', '', $key);
                
                $stmt = $db->prepare("
                    INSERT INTO storage_config (service, config_key, config_value) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE config_value = ?
                ");
                $stmt->bind_param("ssss", $service, $configKey, $value, $value);
                $stmt->execute();
            }
        }
        
        // Actualizar estado del servicio
        $stmt = $db->prepare("
            INSERT INTO storage_config (service, config_key, config_value, is_active) 
            VALUES (?, 'enabled', ?, ?) 
            ON DUPLICATE KEY UPDATE config_value = ?, is_active = ?
        ");
        $enabledStr = $enabled ? 'true' : 'false';
        $stmt->bind_param("ssisi", $service, $enabledStr, $enabled, $enabledStr, $enabled);
        $stmt->execute();
        
        $_SESSION['success_message'] = 'Configuración actualizada correctamente';
    }
}

// Obtener configuración actual de la base de datos
$dbConfig = [];
$result = $db->query("SELECT * FROM storage_config");
while ($row = $result->fetch_assoc()) {
    $dbConfig[$row['service']][$row['config_key']] = $row['config_value'];
}

// Estadísticas de almacenamiento
$storageStats = $db->query("
    SELECT 
        storage_type,
        COUNT(*) as file_count,
        SUM(file_size) as total_size,
        AVG(file_size) as avg_size
    FROM videos
    WHERE status = 'active'
    GROUP BY storage_type
")->fetch_all(MYSQLI_ASSOC);

$totalStorage = array_sum(array_column($storageStats, 'total_size'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Almacenamiento - <?php echo SITE_NAME; ?></title>
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
                <a href="/admin/storage.php" class="nav-item active">
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
                <h1>Gestión de Almacenamiento</h1>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <!-- Storage Statistics -->
            <div class="stats-section">
                <h2>Estadísticas de Uso</h2>
                <div class="storage-stats">
                    <div class="stat-card">
                        <i class="fas fa-database"></i>
                        <h3>Almacenamiento Total</h3>
                        <p><?php echo formatBytes($totalStorage); ?></p>
                    </div>
                    <?php foreach ($storageStats as $stat): ?>
                        <div class="stat-card">
                            <i class="fas fa-server"></i>
                            <h3><?php echo ucfirst($stat['storage_type']); ?></h3>
                            <p><?php echo formatBytes($stat['total_size']); ?></p>
                            <small><?php echo $stat['file_count']; ?> archivos</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Storage Services Configuration -->
            <div class="storage-services">
                <h2>Servicios de Almacenamiento</h2>
                
                <!-- Local Storage -->
                <div class="service-card">
                    <div class="service-header">
                        <h3><i class="fas fa-hdd"></i> Almacenamiento Local</h3>
                        <span class="badge badge-success">Siempre Activo</span>
                    </div>
                    <div class="service-info">
                        <p>Almacenamiento en el servidor local. Ideal para archivos pequeños y acceso rápido.</p>
                        <div class="info-grid">
                            <div>
                                <strong>Ruta:</strong> <?php echo VIDEO_PATH; ?>
                            </div>
                            <div>
                                <strong>Espacio usado:</strong> 
                                <?php 
                                $localUsed = 0;
                                foreach ($storageStats as $stat) {
                                    if ($stat['storage_type'] === 'local') {
                                        $localUsed = $stat['total_size'];
                                        break;
                                    }
                                }
                                echo formatBytes($localUsed);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contabo Object Storage -->
                <div class="service-card">
                    <form method="POST" class="storage-form">
                        <input type="hidden" name="action" value="update_service">
                        <input type="hidden" name="service" value="contabo">
                        
                        <div class="service-header">
                            <h3><i class="fas fa-cloud"></i> Contabo Object Storage</h3>
                            <label class="switch">
                                <input type="checkbox" name="enabled" 
                                       <?php echo ($dbConfig['contabo']['enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="service-config">
                            <div class="form-group">
                                <label>Endpoint URL</label>
                                <input type="url" name="contabo_endpoint" class="form-control" 
                                       value="<?php echo $dbConfig['contabo']['endpoint'] ?? 'https://eu2.contabostorage.com'; ?>"
                                       placeholder="https://eu2.contabostorage.com">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Access Key</label>
                                    <input type="text" name="contabo_access_key" class="form-control" 
                                           value="<?php echo $dbConfig['contabo']['access_key'] ?? ''; ?>"
                                           placeholder="Tu Access Key">
                                </div>
                                
                                <div class="form-group">
                                    <label>Secret Key</label>
                                    <input type="password" name="contabo_secret_key" class="form-control" 
                                           value="<?php echo $dbConfig['contabo']['secret_key'] ?? ''; ?>"
                                           placeholder="Tu Secret Key">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Bucket</label>
                                    <input type="text" name="contabo_bucket" class="form-control" 
                                           value="<?php echo $dbConfig['contabo']['bucket'] ?? ''; ?>"
                                           placeholder="Nombre del bucket">
                                </div>
                                
                                <div class="form-group">
                                    <label>Región</label>
                                    <input type="text" name="contabo_region" class="form-control" 
                                           value="<?php echo $dbConfig['contabo']['region'] ?? 'eu2'; ?>"
                                           placeholder="eu2">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Amazon S3 -->
                <div class="service-card">
                    <form method="POST" class="storage-form">
                        <input type="hidden" name="action" value="update_service">
                        <input type="hidden" name="service" value="s3">
                        
                        <div class="service-header">
                            <h3><i class="fab fa-aws"></i> Amazon S3</h3>
                            <label class="switch">
                                <input type="checkbox" name="enabled" 
                                       <?php echo ($dbConfig['s3']['enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="service-config">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Access Key ID</label>
                                    <input type="text" name="s3_access_key" class="form-control" 
                                           value="<?php echo $dbConfig['s3']['access_key'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Secret Access Key</label>
                                    <input type="password" name="s3_secret_key" class="form-control" 
                                           value="<?php echo $dbConfig['s3']['secret_key'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Bucket</label>
                                    <input type="text" name="s3_bucket" class="form-control" 
                                           value="<?php echo $dbConfig['s3']['bucket'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Región</label>
                                    <select name="s3_region" class="form-control">
                                        <option value="us-east-1">US East (N. Virginia)</option>
                                        <option value="us-west-2">US West (Oregon)</option>
                                        <option value="eu-west-1">EU (Ireland)</option>
                                        <option value="ap-southeast-1">Asia Pacific (Singapore)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Wasabi -->
                <div class="service-card">
                    <form method="POST" class="storage-form">
                        <input type="hidden" name="action" value="update_service">
                        <input type="hidden" name="service" value="wasabi">
                        
                        <div class="service-header">
                            <h3><i class="fas fa-database"></i> Wasabi Cloud Storage</h3>
                            <label class="switch">
                                <input type="checkbox" name="enabled" 
                                       <?php echo ($dbConfig['wasabi']['enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="service-config">
                            <div class="form-group">
                                <label>Endpoint URL</label>
                                <select name="wasabi_endpoint" class="form-control">
                                    <option value="https://s3.wasabisys.com">US East 1 (N. Virginia)</option>
                                    <option value="https://s3.us-west-1.wasabisys.com">US West 1 (Oregon)</option>
                                    <option value="https://s3.eu-central-1.wasabisys.com">EU Central 1 (Amsterdam)</option>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Access Key</label>
                                    <input type="text" name="wasabi_access_key" class="form-control" 
                                           value="<?php echo $dbConfig['wasabi']['access_key'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Secret Key</label>
                                    <input type="password" name="wasabi_secret_key" class="form-control" 
                                           value="<?php echo $dbConfig['wasabi']['secret_key'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Bucket</label>
                                <input type="text" name="wasabi_bucket" class="form-control" 
                                       value="<?php echo $dbConfig['wasabi']['bucket'] ?? ''; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Google Drive -->
                <div class="service-card">
                    <form method="POST" class="storage-form">
                        <input type="hidden" name="action" value="update_service">
                        <input type="hidden" name="service" value="gdrive">
                        
                        <div class="service-header">
                            <h3><i class="fab fa-google-drive"></i> Google Drive</h3>
                            <label class="switch">
                                <input type="checkbox" name="enabled" 
                                       <?php echo ($dbConfig['gdrive']['enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="service-config">
                            <div class="form-group">
                                <label>Client ID</label>
                                <input type="text" name="gdrive_client_id" class="form-control" 
                                       value="<?php echo $dbConfig['gdrive']['client_id'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Client Secret</label>
                                <input type="password" name="gdrive_client_secret" class="form-control" 
                                       value="<?php echo $dbConfig['gdrive']['client_secret'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Refresh Token</label>
                                <input type="text" name="gdrive_refresh_token" class="form-control" 
                                       value="<?php echo $dbConfig['gdrive']['refresh_token'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Folder ID</label>
                                <input type="text" name="gdrive_folder_id" class="form-control" 
                                       value="<?php echo $dbConfig['gdrive']['folder_id'] ?? ''; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Migration Tools -->
            <div class="migration-section">
                <h2>Herramientas de Migración</h2>
                <div class="migration-tools">
                    <button class="btn btn-secondary" onclick="testStorageConnections()">
                        <i class="fas fa-plug"></i> Probar Conexiones
                    </button>
                    <button class="btn btn-secondary" onclick="showMigrationModal()">
                        <i class="fas fa-exchange-alt"></i> Migrar Archivos
                    </button>
                    <button class="btn btn-secondary" onclick="cleanupStorage()">
                        <i class="fas fa-broom"></i> Limpiar Almacenamiento
                    </button>
                </div>
            </div>
        </main>
    </div>

    <style>
    .storage-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .service-card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .service-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .service-header h3 {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: var(--success-color);
    }

    input:checked + .slider:before {
        transform: translateX(26px);
    }

    .service-config {
        margin-top: 1rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .migration-tools {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    </style>

    <script>
    function testStorageConnections() {
        // Implementar prueba de conexiones
        alert('Probando conexiones de almacenamiento...');
    }

    function showMigrationModal() {
        // Implementar modal de migración
        alert('Función de migración en desarrollo');
    }

    function cleanupStorage() {
        if (confirm('¿Limpiar archivos huérfanos del almacenamiento?')) {
            // Implementar limpieza
            alert('Limpieza iniciada...');
        }
    }
    </script>
</body>
</html>