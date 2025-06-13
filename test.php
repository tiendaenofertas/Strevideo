<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';

echo "PHP Version: " . phpversion() . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current Directory: " . __DIR__ . "<br>";
echo "Site URL: " . SITE_URL . "<br>";

// Test database connection
try {
    $db = Database::getInstance();
    echo "Database connection: OK<br>";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// Check directories
$dirs = ['public', 'admin', 'config', 'app'];
foreach ($dirs as $dir) {
    echo "$dir exists: " . (is_dir($dir) ? 'Yes' : 'No') . "<br>";
}