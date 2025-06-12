<?php
// public/index.php - Punto de entrada principal
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Verificar si el usuario está autenticado
$isLoggedIn = isset($_SESSION['user_id']);
$user = null;

if ($isLoggedIn) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// Obtener videos recientes
$db = Database::getInstance();
$recentVideos = $db->query("
    SELECT v.*, u.username 
    FROM videos v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.status = 'active' AND v.privacy = 'public' 
    ORDER BY v.created_at DESC 
    LIMIT 12
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Plataforma de Videos</title>
    <link rel="stylesheet" href="/assets/css/main.css">
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
                    <a href="/" class="nav-link active">Inicio</a>
                    <a href="/pages/upload.php" class="nav-link">
                        <i class="fas fa-upload"></i> Subir Video
                    </a>
                    
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <a href="#" class="nav-link dropdown-toggle">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username']); ?>
                            </a>
                            <div class="dropdown-menu">
                                <a href="/user/dashboard.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Panel de Control
                                </a>
                                <a href="/user/my-videos.php" class="dropdown-item">
                                    <i class="fas fa-video"></i> Mis Videos
                                </a>
                                <a href="/user/earnings.php" class="dropdown-item">
                                    <i class="fas fa-dollar-sign"></i> Ganancias
                                </a>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <a href="/admin/" class="dropdown-item">
                                        <i class="fas fa-cog"></i> Administración
                                    </a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a href="/logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/pages/login.php" class="nav-link">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </a>
                        <a href="/pages/register.php" class="nav-link btn-primary">
                            <i class="fas fa-user-plus"></i> Registrarse
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="nav-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Comparte tus Videos con el Mundo</h1>
                <p>Sube, almacena y comparte videos de alta calidad de forma rápida y segura</p>
                <div class="hero-buttons">
                    <a href="/pages/upload.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-cloud-upload-alt"></i> Subir Video
                    </a>
                    <a href="/pages/register.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-rocket"></i> Comenzar Gratis
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="features">
        <div class="container">
            <h2 class="section-title">¿Por qué elegirnos?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-tachometer-alt feature-icon"></i>
                    <h3>Velocidad Ultra Rápida</h3>
                    <p>Servidores optimizados para streaming sin interrupciones</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-shield-alt feature-icon"></i>
                    <h3>Seguridad Garantizada</h3>
                    <p>Protección contra hotlinking y control total de privacidad</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-dollar-sign feature-icon"></i>
                    <h3>Monetización Fácil</h3>
                    <p>Gana dinero con cada reproducción y descarga</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-line feature-icon"></i>
                    <h3>Estadísticas Detalladas</h3>
                    <p>Analiza el rendimiento de tus videos en tiempo real</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Videos -->
    <section class="recent-videos">
        <div class="container">
            <h2 class="section-title">Videos Recientes</h2>
            <div class="videos-grid">
                <?php foreach ($recentVideos as $video): ?>
                    <div class="video-card">
                        <a href="/pages/watch.php?v=<?php echo $video['video_code']; ?>">
                            <div class="video-thumbnail">
                                <img src="<?php echo $video['thumbnail'] ?: '/assets/img/default-thumb.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($video['title']); ?>">
                                <span class="video-duration"><?php echo formatDuration($video['duration']); ?></span>
                            </div>
                            <div class="video-info">
                                <h4 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h4>
                                <div class="video-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($video['username']); ?></span>
                                    <span><i class="fas fa-eye"></i> <?php echo number_format($video['views']); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo timeAgo($video['created_at']); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?php echo SITE_NAME; ?></h3>
                    <p>La mejor plataforma para compartir tus videos</p>
                </div>
                <div class="footer-section">
                    <h4>Enlaces</h4>
                    <ul>
                        <li><a href="/terms">Términos de Uso</a></li>
                        <li><a href="/privacy">Privacidad</a></li>
                        <li><a href="/dmca">DMCA</a></li>
                        <li><a href="/contact">Contacto</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Para Usuarios</h4>
                    <ul>
                        <li><a href="/pages/register.php">Crear Cuenta</a></li>
                        <li><a href="/api/docs">API Docs</a></li>
                        <li><a href="/faq">Preguntas Frecuentes</a></li>
                        <li><a href="/support">Soporte</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="/assets/js/main.js"></script>
</body>
</html>