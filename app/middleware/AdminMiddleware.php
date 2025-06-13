<?php
// app/middleware/AdminMiddleware.php

class AdminMiddleware {
    public static function check() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /pages/login.php');
            exit;
        }
        
        if ($_SESSION['user_role'] !== 'admin') {
            header('HTTP/1.0 403 Forbidden');
            die('Access Denied - Error Code: 403');
        }
    }
}