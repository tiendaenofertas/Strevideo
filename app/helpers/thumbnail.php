<?php
// app/helpers/thumbnail.php - Generación de miniaturas

function generateThumbnail($videoPath, $videoCode) {
    $thumbnailName = $videoCode . '.jpg';
    $thumbnailPath = THUMBNAIL_PATH . '/' . $thumbnailName;
    
    // Intentar usar FFmpeg para generar miniatura
    if (isFFmpegAvailable()) {
        $success = generateWithFFmpeg($videoPath, $thumbnailPath);
        if ($success) {
            return '/uploads/thumbnails/' . $thumbnailName;
        }
    }
    
    // Si FFmpeg no está disponible o falla, usar imagen por defecto
    $defaultThumb = '/assets/img/default-thumb.jpg';
    copy(PUBLIC_PATH . $defaultThumb, $thumbnailPath);
    
    return '/uploads/thumbnails/' . $thumbnailName;
}

function generateWithFFmpeg($videoPath, $outputPath) {
    try {
        // Comando FFmpeg para extraer frame a los 5 segundos
        $cmd = sprintf(
            'ffmpeg -i %s -ss 00:00:05 -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2" -y %s 2>&1',
            escapeshellarg($videoPath),
            THUMBNAIL_WIDTH,
            THUMBNAIL_HEIGHT,
            THUMBNAIL_WIDTH,
            THUMBNAIL_HEIGHT,
            escapeshellarg($outputPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        return $returnCode === 0 && file_exists($outputPath);
        
    } catch (Exception $e) {
        return false;
    }
}

function isFFmpegAvailable() {
    $output = shell_exec('ffmpeg -version 2>&1');
    return strpos($output, 'ffmpeg version') !== false;
}

function getVideoInfo($videoPath) {
    if (!isFFmpegAvailable()) {
        return [
            'duration' => 0,
            'width' => 0,
            'height' => 0,
            'resolution' => 'Unknown'
        ];
    }
    
    try {
        // Usar FFprobe para obtener información del video
        $cmd = sprintf(
            'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1',
            escapeshellarg($videoPath)
        );
        
        $output = shell_exec($cmd);
        $data = json_decode($output, true);
        
        if (!$data) {
            return getDefaultVideoInfo();
        }
        
        $duration = 0;
        $width = 0;
        $height = 0;
        
        // Obtener duración
        if (isset($data['format']['duration'])) {
            $duration = (int)$data['format']['duration'];
        }
        
        // Obtener resolución del primer stream de video
        foreach ($data['streams'] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $width = $stream['width'] ?? 0;
                $height = $stream['height'] ?? 0;
                break;
            }
        }
        
        return [
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'resolution' => $width > 0 ? "{$width}x{$height}" : 'Unknown'
        ];
        
    } catch (Exception $e) {
        return getDefaultVideoInfo();
    }
}

function getDefaultVideoInfo() {
    return [
        'duration' => 0,
        'width' => 1920,
        'height' => 1080,
        'resolution' => '1920x1080'
    ];
}

function optimizeVideo($inputPath, $outputPath, $quality = 'medium') {
    if (!isFFmpegAvailable()) {
        return false;
    }
    
    $qualityPresets = [
        'low' => '-crf 28 -preset faster',
        'medium' => '-crf 23 -preset fast',
        'high' => '-crf 18 -preset slow'
    ];
    
    $preset = $qualityPresets[$quality] ?? $qualityPresets['medium'];
    
    $cmd = sprintf(
        'ffmpeg -i %s -c:v libx264 %s -c:a aac -movflags +faststart -y %s 2>&1',
        escapeshellarg($inputPath),
        $preset,
        escapeshellarg($outputPath)
    );
    
    exec($cmd, $output, $returnCode);
    
    return $returnCode === 0;
}

function generateMultipleResolutions($inputPath, $videoCode) {
    if (!isFFmpegAvailable()) {
        return [];
    }
    
    $resolutions = [
        '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '800k'],
        '480p' => ['width' => 854, 'height' => 480, 'bitrate' => '1200k'],
        '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2500k'],
        '1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '5000k']
    ];
    
    $generatedFiles = [];
    
    foreach ($resolutions as $label => $settings) {
        $outputFile = $videoCode . '_' . $label . '.mp4';
        $outputPath = VIDEO_PATH . '/' . $outputFile;
        
        $cmd = sprintf(
            'ffmpeg -i %s -vf scale=%d:%d -c:v libx264 -preset fast -crf 23 -b:v %s -c:a aac -movflags +faststart -y %s 2>&1',
            escapeshellarg($inputPath),
            $settings['width'],
            $settings['height'],
            $settings['bitrate'],
            escapeshellarg($outputPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($outputPath)) {
            $generatedFiles[$label] = $outputFile;
        }
    }
    
    return $generatedFiles;
}