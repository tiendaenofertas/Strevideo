<?php
// public/pages/upload.php - Página de subida de videos
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php?redirect=upload');
    exit;
}

// Obtener información del usuario
$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Obtener configuración de almacenamiento
$storageConfig = include '../../config/storage.php';
$activeStorages = [];

foreach ($storageConfig['services'] as $service => $config) {
    if ($config['enabled']) {
        $activeStorages[] = $service;
    }
}

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Video - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <a href="/user/dashboard.php" class="nav-link">Panel de Control</a>
                    <a href="/logout.php" class="nav-link">Cerrar Sesión</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Upload Section -->
    <div class="upload-container">
        <div class="container">
            <h1 class="page-title">Subir Video</h1>
            
            <!-- Upload Methods -->
            <div class="upload-methods">
                <button class="method-btn active" data-method="file">
                    <i class="fas fa-file-video"></i>
                    <span>Archivo Local</span>
                </button>
                <button class="method-btn" data-method="url">
                    <i class="fas fa-link"></i>
                    <span>Desde URL</span>
                </button>
            </div>

            <!-- File Upload Form -->
            <div id="file-upload" class="upload-form active">
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="upload_method" value="file">
                    
                    <div class="upload-zone" id="dropZone">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <h3>Arrastra tu video aquí</h3>
                        <p>o haz clic para seleccionar</p>
                        <input type="file" id="fileInput" name="video" accept="video/*" hidden>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                            Seleccionar Archivo
                        </button>
                        <p class="upload-info">
                            Formatos: MP4, WebM, MOV, AVI, MKV<br>
                            Tamaño máximo: <?php echo formatBytes(MAX_UPLOAD_SIZE); ?>
                        </p>
                    </div>

                    <div class="video-details" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Título del Video</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Descripción (opcional)</label>
                            <textarea name="description" class="form-control" rows="4"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Privacidad</label>
                                <select name="privacy" class="form-control">
                                    <option value="public">Público</option>
                                    <option value="unlisted">No listado</option>
                                    <option value="private">Privado</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Almacenamiento</label>
                                <select name="storage" class="form-control">
                                    <?php foreach ($activeStorages as $storage): ?>
                                        <option value="<?php echo $storage; ?>">
                                            <?php echo ucfirst($storage); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="allow_download" value="1" checked>
                                Permitir descargas
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <i class="fas fa-upload"></i> Subir Video
                        </button>
                    </div>
                </form>
            </div>

            <!-- URL Upload Form -->
            <div id="url-upload" class="upload-form">
                <form id="urlUploadForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="upload_method" value="url">
                    
                    <div class="form-group">
                        <label class="form-label">URL del Video</label>
                        <input type="url" name="video_url" class="form-control" 
                               placeholder="https://ejemplo.com/video.mp4" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Título del Video</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Descripción (opcional)</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Privacidad</label>
                            <select name="privacy" class="form-control">
                                <option value="public">Público</option>
                                <option value="unlisted">No listado</option>
                                <option value="private">Privado</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Almacenamiento</label>
                            <select name="storage" class="form-control">
                                <?php foreach ($activeStorages as $storage): ?>
                                    <option value="<?php echo $storage; ?>">
                                        <?php echo ucfirst($storage); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-download"></i> Descargar y Subir
                    </button>
                </form>
            </div>

            <!-- Upload Progress -->
            <div id="uploadProgress" class="upload-progress" style="display: none;">
                <h3>Subiendo video...</h3>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-info">
                    <span id="progressPercent">0%</span>
                    <span id="progressSpeed"></span>
                    <span id="progressTime"></span>
                </div>
                <button type="button" class="btn btn-danger" id="cancelUpload">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>

            <!-- Upload Success -->
            <div id="uploadSuccess" class="upload-success" style="display: none;">
                <i class="fas fa-check-circle success-icon"></i>
                <h2>¡Video Subido con Éxito!</h2>
                <p>Tu video se está procesando y estará disponible en breve.</p>
                <div class="video-links">
                    <div class="form-group">
                        <label class="form-label">Enlace del Video:</label>
                        <div class="input-group">
                            <input type="text" id="videoLink" class="form-control" readonly>
                            <button class="btn btn-secondary" onclick="copyLink('videoLink')">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Código de Inserción:</label>
                        <div class="input-group">
                            <textarea id="embedCode" class="form-control" rows="3" readonly></textarea>
                            <button class="btn btn-secondary" onclick="copyLink('embedCode')">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="success-actions">
                    <a href="/pages/watch.php?v=" id="watchLink" class="btn btn-primary">
                        <i class="fas fa-play"></i> Ver Video
                    </a>
                    <a href="/pages/upload.php" class="btn btn-secondary">
                        <i class="fas fa-plus"></i> Subir Otro
                    </a>
                    <a href="/user/my-videos.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Mis Videos
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload CSS adicional -->
    <style>
    .upload-container {
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
        background-color: var(--light-color);
    }

    .page-title {
        text-align: center;
        margin-bottom: 2rem;
    }

    .upload-methods {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .method-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem 2rem;
        background: var(--white);
        border: 2px solid #e5e7eb;
        border-radius: var(--border-radius);
        cursor: pointer;
        transition: var(--transition);
    }

    .method-btn.active {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .upload-form {
        display: none;
        max-width: 600px;
        margin: 0 auto;
    }

    .upload-form.active {
        display: block;
    }

    .upload-zone {
        background: var(--white);
        border: 2px dashed #d1d5db;
        border-radius: var(--border-radius);
        padding: 3rem;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
    }

    .upload-zone.dragover {
        border-color: var(--primary-color);
        background-color: #f0f9ff;
    }

    .upload-icon {
        font-size: 4rem;
        color: #9ca3af;
        margin-bottom: 1rem;
    }

    .upload-info {
        margin-top: 1rem;
        color: #6b7280;
        font-size: 0.875rem;
    }

    .video-details {
        background: var(--white);
        padding: 2rem;
        border-radius: var(--border-radius);
        margin-top: 2rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }

    .upload-progress {
        background: var(--white);
        padding: 2rem;
        border-radius: var(--border-radius);
        text-align: center;
    }

    .progress-bar {
        background: #e5e7eb;
        height: 20px;
        border-radius: 10px;
        overflow: hidden;
        margin: 1rem 0;
    }

    .progress-fill {
        background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        height: 100%;
        width: 0%;
        transition: width 0.3s ease;
    }

    .progress-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1rem;
        color: #6b7280;
    }

    .upload-success {
        background: var(--white);
        padding: 3rem;
        border-radius: var(--border-radius);
        text-align: center;
    }

    .success-icon {
        font-size: 4rem;
        color: var(--success-color);
        margin-bottom: 1rem;
    }

    .video-links {
        margin: 2rem 0;
        text-align: left;
    }

    .input-group {
        display: flex;
        gap: 0.5rem;
    }

    .success-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    .btn-block {
        width: 100%;
    }
    </style>

    <script src="/assets/js/upload.js"></script>
</body>
</html>