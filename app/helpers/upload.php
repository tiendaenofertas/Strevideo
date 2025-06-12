<?php
// app/helpers/upload.php - Funciones de subida y almacenamiento

require_once dirname(__DIR__) . '/../vendor/autoload.php'; // Si usas Composer

function uploadToStorage($sourceFile, $filename, $storageType = 'local') {
    $storageConfig = include dirname(__DIR__) . '/../config/storage.php';
    $service = $storageConfig['services'][$storageType];
    
    if (!$service || !$service['enabled']) {
        return ['success' => false, 'message' => 'Servicio de almacenamiento no disponible'];
    }
    
    switch ($storageType) {
        case 'local':
            return uploadToLocal($sourceFile, $filename);
            
        case 'contabo':
        case 's3':
        case 'wasabi':
            return uploadToS3Compatible($sourceFile, $filename, $service);
            
        case 'gdrive':
            return uploadToGoogleDrive($sourceFile, $filename, $service);
            
        default:
            return ['success' => false, 'message' => 'Tipo de almacenamiento no soportado'];
    }
}

function uploadToLocal($sourceFile, $filename) {
    $uploadPath = VIDEO_PATH . '/' . $filename;
    
    if (move_uploaded_file($sourceFile, $uploadPath) || copy($sourceFile, $uploadPath)) {
        return [
            'success' => true,
            'path' => $filename
        ];
    }
    
    return ['success' => false, 'message' => 'Error al mover el archivo'];
}

function uploadToS3Compatible($sourceFile, $filename, $config) {
    try {
        // Configurar cliente S3
        $s3Config = [
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['access_key'],
                'secret' => $config['secret_key']
            ]
        ];
        
        if ($config['endpoint']) {
            $s3Config['endpoint'] = $config['endpoint'];
            $s3Config['use_path_style_endpoint'] = $config['use_path_style'] ?? true;
        }
        
        $s3 = new Aws\S3\S3Client($s3Config);
        
        // Subir archivo
        $result = $s3->putObject([
            'Bucket' => $config['bucket'],
            'Key' => 'videos/' . $filename,
            'SourceFile' => $sourceFile,
            'ACL' => 'public-read',
            'ContentType' => mime_content_type($sourceFile)
        ]);
        
        return [
            'success' => true,
            'path' => 'videos/' . $filename
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error S3: ' . $e->getMessage()];
    }
}

function uploadToGoogleDrive($sourceFile, $filename, $config) {
    try {
        // Configurar cliente de Google
        $client = new Google_Client();
        $client->setClientId($config['client_id']);
        $client->setClientSecret($config['client_secret']);
        $client->setAccessType('offline');
        $client->refreshToken($config['refresh_token']);
        
        $service = new Google_Service_Drive($client);
        
        // Crear metadata del archivo
        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $filename,
            'parents' => [$config['folder_id']]
        ]);
        
        // Subir archivo
        $content = file_get_contents($sourceFile);
        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => mime_content_type($sourceFile),
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);
        
        // Hacer el archivo público
        $permission = new Google_Service_Drive_Permission([
            'type' => 'anyone',
            'role' => 'reader'
        ]);
        
        $service->permissions->create($file->id, $permission);
        
        return [
            'success' => true,
            'path' => $file->id
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error Google Drive: ' . $e->getMessage()];
    }
}

function deleteFromStorage($filename, $storageType, $storagePath) {
    $storageConfig = include dirname(__DIR__) . '/../config/storage.php';
    $service = $storageConfig['services'][$storageType];
    
    switch ($storageType) {
        case 'local':
            $filePath = VIDEO_PATH . '/' . $filename;
            if (file_exists($filePath)) {
                return unlink($filePath);
            }
            return false;
            
        case 'contabo':
        case 's3':
        case 'wasabi':
            return deleteFromS3Compatible($storagePath, $service);
            
        case 'gdrive':
            return deleteFromGoogleDrive($storagePath, $service);
            
        default:
            return false;
    }
}

function deleteFromS3Compatible($path, $config) {
    try {
        $s3Config = [
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['access_key'],
                'secret' => $config['secret_key']
            ]
        ];
        
        if ($config['endpoint']) {
            $s3Config['endpoint'] = $config['endpoint'];
            $s3Config['use_path_style_endpoint'] = $config['use_path_style'] ?? true;
        }
        
        $s3 = new Aws\S3\S3Client($s3Config);
        
        $s3->deleteObject([
            'Bucket' => $config['bucket'],
            'Key' => $path
        ]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function deleteFromGoogleDrive($fileId, $config) {
    try {
        $client = new Google_Client();
        $client->setClientId($config['client_id']);
        $client->setClientSecret($config['client_secret']);
        $client->setAccessType('offline');
        $client->refreshToken($config['refresh_token']);
        
        $service = new Google_Service_Drive($client);
        $service->files->delete($fileId);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}