<?php
// public/pages/login.php - Página de inicio de sesión
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

// Si ya está autenticado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: /user/dashboard.php');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor completa todos los campos';
    } else {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, password, status, role FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if ($user['status'] === 'banned') {
                $error = 'Tu cuenta ha sido suspendida';
            } elseif (password_verify($password, $user['password'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                
                // Actualizar último login
                $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                
                // Redirigir
                if ($redirect === 'upload') {
                    header('Location: /pages/upload.php');
                } elseif ($user['role'] === 'admin') {
                    header('Location: /admin/dashboard.php');
                } else {
                    header('Location: /user/dashboard.php');
                }
                exit;
            } else {
                $error = 'Contraseña incorrecta';
            }
        } else {
            $error = 'Usuario no encontrado';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <a href="/" class="logo">
                    <i class="fas fa-play-circle"></i> <?php echo SITE_NAME; ?>
                </a>
                <h2>Iniciar Sesión</h2>
                <p>Bienvenido de vuelta</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Usuario o Email</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Ingresa tu usuario o email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Ingresa tu contraseña" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        Recordarme
                    </label>
                    <a href="/pages/forgot-password.php" class="forgot-link">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>
            </form>
            
            <div class="auth-footer">
                <p>¿No tienes cuenta? <a href="/pages/register.php">Regístrate aquí</a></p>
            </div>
        </div>
    </div>
    
    <style>
    .auth-body {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .auth-container {
        width: 100%;
        max-width: 400px;
        padding: 20px;
    }
    
    .auth-box {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        padding: 2rem;
    }
    
    .auth-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .auth-header .logo {
        font-size: 2rem;
        color: var(--primary-color);
        text-decoration: none;
        display: inline-block;
        margin-bottom: 1rem;
    }
    
    .auth-header h2 {
        margin-bottom: 0.5rem;
    }
    
    .auth-header p {
        color: #6b7280;
    }
    
    .input-group {
        position: relative;
    }
    
    .input-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }
    
    .input-group .form-control {
        padding-left: 45px;
    }
    
    .forgot-link {
        float: right;
        color: var(--primary-color);
        text-decoration: none;
        font-size: 0.875rem;
    }
    
    .auth-footer {
        text-align: center;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid #e5e7eb;
    }
    </style>
</body>
</html>