<?php
// public/pages/register.php - Página de registro
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /user/dashboard.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validaciones
    if (strlen($username) < 3) {
        $errors[] = 'El nombre de usuario debe tener al menos 3 caracteres';
    }
    
    if (!isValidEmail($email)) {
        $errors[] = 'Email inválido';
    }
    
    if (strlen($password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    
    if (empty($errors)) {
        $db = Database::getInstance();
        
        // Verificar si el usuario o email ya existen
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'El usuario o email ya está registrado';
        } else {
            // Crear usuario
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $apiKey = generateApiKey();
            
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, api_key) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("ssss", $username, $email, $hashedPassword, $apiKey);
            
            if ($stmt->execute()) {
                $success = true;
                $_SESSION['user_id'] = $db->lastInsertId();
                $_SESSION['user_role'] = 'user';
                
                // Redirigir después de 2 segundos
                header("refresh:2;url=/user/dashboard.php");
            } else {
                $errors[] = 'Error al crear la cuenta';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - <?php echo SITE_NAME; ?></title>
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
                <h2>Crear Cuenta</h2>
                <p>Únete a nuestra plataforma</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    ¡Cuenta creada exitosamente! Redirigiendo...
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Nombre de Usuario</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Elige un nombre de usuario" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" 
                               placeholder="tu@email.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Mínimo 6 caracteres" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirmar Contraseña</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" class="form-control" 
                               placeholder="Repite tu contraseña" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" required>
                        Acepto los <a href="/terms" target="_blank">términos y condiciones</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Crear Cuenta
                </button>
            </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p>¿Ya tienes cuenta? <a href="/pages/login.php">Inicia sesión aquí</a></p>
            </div>
        </div>
    </div>
</body>
</html>