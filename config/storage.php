<?php
// config/storage.php - Configuración de servicios de almacenamiento

return [
    'default' => 'local',
    
    'services' => [
        'local' => [
            'driver' => 'local',
            'enabled' => true,
            'path' => VIDEO_PATH,
            'url' => SITE_URL . '/uploads/videos'
        ],
        
        'contabo' => [
            'driver' => 's3',
            'enabled' => false,
            'endpoint' => 'https://eu2.contabostorage.com',
            'region' => 'eu2',
            'bucket' => 'your-bucket-name',
            'access_key' => env('CONTABO_ACCESS_KEY', ''),
            'secret_key' => env('CONTABO_SECRET_KEY', ''),
            'use_path_style' => true
        ],
        
        's3' => [
            'driver' => 's3',
            'enabled' => false,
            'endpoint' => null,
            'region' => 'us-east-1',
            'bucket' => 'your-bucket-name',
            'access_key' => env('AWS_ACCESS_KEY', ''),
            'secret_key' => env('AWS_SECRET_KEY', ''),
            'use_path_style' => false
        ],
        
        'wasabi' => [
            'driver' => 's3',
            'enabled' => false,
            'endpoint' => 'https://s3.wasabisys.com',
            'region' => 'us-east-1',
            'bucket' => 'your-bucket-name',
            'access_key' => env('WASABI_ACCESS_KEY', ''),
            'secret_key' => env('WASABI_SECRET_KEY', ''),
            'use_path_style' => true
        ],
        
        'gdrive' => [
            'driver' => 'gdrive',
            'enabled' => false,
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            'refresh_token' => env('GOOGLE_REFRESH_TOKEN', ''),
            'folder_id' => env('GOOGLE_FOLDER_ID', '')
        ]
    ],
    
    // Configuración de CDN (opcional)
    'cdn' => [
        'enabled' => false,
        'url' => 'https://cdn.tudominio.com',
        'pull_zone' => 'videos'
    ],
    
    // Límites de almacenamiento por tipo de usuario
    'limits' => [
        'user' => [
            'max_file_size' => 524288000, // 500MB
            'total_storage' => 10737418240, // 10GB
            'concurrent_uploads' => 1
        ],
        'premium' => [
            'max_file_size' => 2147483648, // 2GB
            'total_storage' => 107374182400, // 100GB
            'concurrent_uploads' => 5
        ],
        'admin' => [
            'max_file_size' => 5368709120, // 5GB
            'total_storage' => -1, // Sin límite
            'concurrent_uploads' => -1 // Sin límite
        ]
    ]
];