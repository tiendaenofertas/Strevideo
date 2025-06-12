# Plataforma de Alojamiento de Videos

## Requisitos del Sistema

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Apache/Nginx
- FFmpeg (para procesamiento de video)
- Composer (opcional, para gestión de dependencias)

### Extensiones PHP requeridas:
- mysqli
- gd
- fileinfo
- curl
- json
- mbstring

## Instalación

1. **Subir archivos al servidor**
   ```bash
   # Subir toda la carpeta video-platform a tu servidor
   # Mantener la estructura de directorios intacta
   ```

2. **Configurar base de datos**
   ```bash
   # Crear base de datos
   mysql -u root -p
   CREATE DATABASE video_platform;
   
   # Importar esquema
   mysql -u root -p video_platform < database/schema.sql
   ```

3. **Configurar variables de entorno**
   ```bash
   # Copiar archivo de ejemplo
   cp .env.example .env
   
   # Editar con tus datos
   nano .env
   ```

4. **Instalar dependencias (si usas Composer)**
   ```bash
   composer install
   ```

5. **Configurar permisos**
   ```bash
   chmod 755 -R /ruta/a/video-platform/
   chmod 777 -R public/uploads/
   chmod 777 -R storage/
   ```

6. **Configurar servidor web**

   Para Apache (ya incluido .htaccess)
   
   Para Nginx:
   ```nginx
   server {
       listen 80;
       server_name xzorra.net/video-platform;
       root /ruta/a/video-platform/public;
       
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }
       
       location ~ /\. {
           deny all;
       }
   }
   ```

7. **Instalar FFmpeg**
   ```bash
   # Ubuntu/Debian
   sudo apt update
   sudo apt install ffmpeg
   
   # CentOS/RHEL
   sudo yum install epel-release
   sudo yum install ffmpeg
   ```

## Configuración Inicial

1. **Acceder al panel de administración**
   - URL: `https://xzorra.net/video-platform/admin`
   - Usuario: `admin`
   - Contraseña: Cambiar inmediatamente después del primer login

2. **Configurar servicios de almacenamiento**
   - Ir a Admin Panel > Almacenamiento
   - Configurar los servicios que desees usar
   - Activar/desactivar según necesidad

3. **Configurar límites y tarifas**
   - Editar `config/config.php` para ajustar:
     - Límites de subida
     - Tarifas de monetización
     - Límites de ancho de banda

## Estructura de URLs

- **Página principal**: `/`
- **Subir video**: `/pages/upload.php`
- **Ver video**: `/watch/CODIGO_VIDEO` o `/pages/watch.php?v=CODIGO_VIDEO`
- **Embed**: `/embed/CODIGO_VIDEO`
- **Panel de usuario**: `/user/`
- **Panel admin**: `/admin/`

## API

### Endpoints disponibles:

- `POST /api/upload.php` - Subir video
- `POST /api/delete.php` - Eliminar video
- `GET /api/stats.php` - Obtener estadísticas

### Autenticación API:
```bash
curl -X POST https://xzorra.net/video-platform/api/upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "api_key=TU_API_KEY" \
  -F "video=@video.mp4" \
  -F "title=Mi Video"
```

## Mantenimiento

### Tareas programadas (cron)
```bash
# Limpiar archivos temporales (diario)
0 2 * * * php /ruta/a/video-platform/cron/cleanup.php

# Procesar estadísticas (cada hora)
0 * * * * php /ruta/a/video-platform/cron/stats.php

# Calcular ganancias (diario)
0 3 * * * php /ruta/a/video-platform/cron/earnings.php
```

### Backup
```bash
# Backup de base de datos
mysqldump -u usuario -p video_platform > backup_$(date +%Y%m%d).sql

# Backup de archivos
tar -czf videos_backup_$(date +%Y%m%d).tar.gz public/uploads/videos/
```

## Seguridad

1. **Cambiar contraseñas por defecto**
2. **Configurar SSL/HTTPS**
3. **Actualizar claves en .env**
4. **Configurar firewall**
5. **Mantener PHP y dependencias actualizadas**

## Solución de Problemas

### Videos no se reproducen
- Verificar permisos de archivos
- Comprobar formato de video
- Revisar configuración de almacenamiento

### Error de subida
- Verificar `upload_max_filesize` en PHP
- Comprobar `post_max_size`
- Revisar límites de tiempo de ejecución

### Problemas de miniatura
- Verificar instalación de FFmpeg
- Comprobar permisos en carpeta thumbnails

## Soporte

Para soporte adicional, revisar la documentación o contactar al desarrollador.