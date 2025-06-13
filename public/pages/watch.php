<?php
// public/pages/watch.php - Página de reproducción de video
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

// Obtener código del video
$videoCode = $_GET['v'] ?? '';
if (empty($videoCode)) {
    header('Location: /');
    exit;
}

$db = Database::getInstance();

// Obtener información del video
$stmt = $db->prepare("
    SELECT v.*, u.username, u.id as owner_id
    FROM videos v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.video_code = ? AND v.status = 'active'
");
$stmt->bind_param("s", $videoCode);
$stmt->execute();
$result = $stmt->get_result();
$video = $result->fetch_assoc();

if (!$video) {
    header('Location: /404.php');
    exit;
}

// Verificar privacidad
if ($video['privacy'] === 'private') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $video['owner_id']) {
        header('Location: /403.php');
        exit;
    }
}

// Incrementar vistas
$userIP = $_SERVER['REMOTE_ADDR'];
$stmt = $db->prepare("
    INSERT INTO statistics (video_id, viewer_ip, action, referer, user_agent) 
    VALUES (?, ?, 'view', ?, ?)
");
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$stmt->bind_param("isss", $video['id'], $userIP, $referer, $userAgent);
$stmt->execute();

// Actualizar contador de vistas
$db->query("UPDATE videos SET views = views + 1 WHERE id = " . $video['id']);

// Obtener URL del video según el tipo de almacenamiento
$videoUrl = getVideoUrl($video);

// Obtener videos relacionados
$relatedVideos = $db->query("
    SELECT v.*, u.username 
    FROM videos v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.status = 'active' 
    AND v.privacy = 'public' 
    AND v.id != {$video['id']}
    ORDER BY RAND() 
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Obtener anuncios activos
$ads = $db->query("
    SELECT * FROM ads 
    WHERE is_active = 1 
    AND type IN ('preroll', 'midroll')
    ORDER BY RAND() 
    LIMIT 1
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['title']); ?> - <?php echo SITE_NAME; ?></title>
    <meta property="og:title" content="<?php echo htmlspecialchars($video['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($video['description']); ?>">
    <meta property="og:image" content="<?php echo $video['thumbnail']; ?>">
    <meta property="og:type" content="video.other">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/player.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://vjs.zencdn.net/7.20.3/video-js.css" rel="stylesheet">
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="/user/dashboard.php" class="nav-link">Panel de Control</a>
                        <a href="/logout.php" class="nav-link">Cerrar Sesión</a>
                    <?php else: ?>
                        <a href="/pages/login.php" class="nav-link">Iniciar Sesión</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <div class="watch-container">
        <div class="container">
            <div class="watch-grid">
                <!-- Player Column -->
                <div class="player-column">
                    <!-- Video Player -->
                    <div class="video-player-wrapper">
                        <video
                            id="videoPlayer"
                            class="video-js vjs-default-skin vjs-big-play-centered"
                            controls
                            preload="auto"
                            poster="<?php echo $video['thumbnail']; ?>"
                            data-setup='{"fluid": true}'>
                            <source src="<?php echo $videoUrl; ?>" type="video/<?php echo $video['format']; ?>">
                            <p class="vjs-no-js">
                                Para ver este video, habilita JavaScript y considera actualizar tu navegador.
                            </p>
                        </video>
                        
                        <?php if ($ads && $ads['type'] === 'preroll'): ?>
                            <div id="adContainer" class="ad-container">
                                <?php echo $ads['code']; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Video Info -->
                    <div class="video-info-section">
                        <h1 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h1>
                        
                        <div class="video-stats">
                            <span><i class="fas fa-eye"></i> <?php echo number_format($video['views']); ?> vistas</span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($video['created_at'])); ?></span>
                        </div>

                        <!-- Video Actions -->
                        <div class="video-actions">
                            <?php if ($video['allow_download']): ?>
                                <a href="/download/<?php echo $video['video_code']; ?>" class="btn btn-primary">
                                    <i class="fas fa-download"></i> Descargar
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-secondary" onclick="showShareModal()">
                                <i class="fas fa-share"></i> Compartir
                            </button>
                            
                            <button class="btn btn-secondary" onclick="showEmbedModal()">
                                <i class="fas fa-code"></i> Insertar
                            </button>
                            
                            <button class="btn btn-secondary" onclick="reportVideo()">
                                <i class="fas fa-flag"></i> Reportar
                            </button>
                        </div>

                        <!-- Video Description -->
                        <?php if ($video['description']): ?>
                            <div class="video-description">
                                <h3>Descripción</h3>
                                <p><?php echo nl2br(htmlspecialchars($video['description'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Uploader Info -->
                        <div class="uploader-info">
                            <div class="uploader-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="uploader-details">
                                <h4><?php echo htmlspecialchars($video['username']); ?></h4>
                                <a href="/user/profile/<?php echo $video['username']; ?>" class="btn btn-sm">
                                    Ver Perfil
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="sidebar-column">
                    <h3>Videos Relacionados</h3>
                    <div class="related-videos">
                        <?php foreach ($relatedVideos as $related): ?>
                            <a href="/pages/watch.php?v=<?php echo $related['video_code']; ?>" class="related-video-card">
                                <div class="related-thumbnail">
                                    <img src="<?php echo $related['thumbnail'] ?: '/assets/img/default-thumb.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($related['title']); ?>">
                                    <span class="video-duration"><?php echo formatDuration($related['duration']); ?></span>
                                </div>
                                <div class="related-info">
                                    <h4><?php echo htmlspecialchars($related['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($related['username']); ?></p>
                                    <span><?php echo number_format($related['views']); ?> vistas</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="shareModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('shareModal')">&times;</span>
            <h2>Compartir Video</h2>
            <div class="share-options">
                <input type="text" id="shareLink" class="form-control" 
                       value="<?php echo SITE_URL . '/watch/' . $video['video_code']; ?>" readonly>
                <button class="btn btn-primary" onclick="copyToClipboard('shareLink')">
                    <i class="fas fa-copy"></i> Copiar Enlace
                </button>
            </div>
            <div class="social-share">
                <a href="https://facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/watch/' . $video['video_code']); ?>" 
                   target="_blank" class="social-btn facebook">
                    <i class="fab fa-facebook"></i>
                </a>
                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/watch/' . $video['video_code']); ?>&text=<?php echo urlencode($video['title']); ?>" 
                   target="_blank" class="social-btn twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="https://wa.me/?text=<?php echo urlencode($video['title'] . ' ' . SITE_URL . '/watch/' . $video['video_code']); ?>" 
                   target="_blank" class="social-btn whatsapp">
                    <i class="fab fa-whatsapp"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Embed Modal -->
    <div id="embedModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('embedModal')">&times;</span>
            <h2>Código de Inserción</h2>
            <textarea id="embedCode" class="form-control" rows="4" readonly><iframe src="<?php echo SITE_URL; ?>/embed/<?php echo $video['video_code']; ?>" width="640" height="360" frameborder="0" allowfullscreen></iframe></textarea>
            <button class="btn btn-primary" onclick="copyToClipboard('embedCode')">
                <i class="fas fa-copy"></i> Copiar Código
            </button>
        </div>
    </div>

    <script src="https://vjs.zencdn.net/7.20.3/video.min.js"></script>
    <script src="/assets/js/player.js"></script>
    <script>
        // Inicializar player
        var player = videojs('videoPlayer', {
            controls: true,
            autoplay: false,
            preload: 'auto',
            fluid: true,
            playbackRates: [0.5, 1, 1.5, 2],
            controlBar: {
                volumePanel: {
                    inline: false
                }
            }
        });

        <?php if ($ads && $ads['type'] === 'midroll'): ?>
        // Anuncio midroll
        player.on('timeupdate', function() {
            var currentTime = player.currentTime();
            var duration = player.duration();
            if (currentTime > duration / 2 && !player.midrollShown) {
                player.midrollShown = true;
                // Mostrar anuncio midroll
                showMidrollAd();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

<?php
function getVideoUrl($video) {
    $storageConfig = include '../../config/storage.php';
    
    switch($video['storage_type']) {
        case 'local':
            return SITE_URL . '/uploads/videos/' . $video['filename'];
            
        case 'contabo':
        case 's3':
        case 'wasabi':
            // Para servicios S3-compatible
            $service = $storageConfig['services'][$video['storage_type']];
            $endpoint = $service['endpoint'] ?? 'https://s3.amazonaws.com';
            return $endpoint . '/' . $service['bucket'] . '/' . $video['storage_path'];
            
        case 'gdrive':
            // Para Google Drive necesitarías implementar la API
            return 'https://drive.google.com/uc?id=' . $video['storage_path'];
            
        default:
            return SITE_URL . '/uploads/videos/' . $video['filename'];
    }
}
?>