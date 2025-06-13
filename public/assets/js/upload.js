// public/assets/js/upload.js - Funcionalidad de subida de videos

// Variables globales
let uploadRequest = null;
const maxFileSize = 2147483648; // 2GB
const allowedExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv'];

// Elementos del DOM
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const uploadForm = document.getElementById('uploadForm');
const urlUploadForm = document.getElementById('urlUploadForm');
const methodBtns = document.querySelectorAll('.method-btn');

// Cambiar método de subida
methodBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        const method = this.dataset.method;
        
        // Actualizar botones activos
        methodBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Mostrar formulario correspondiente
        document.querySelectorAll('.upload-form').forEach(form => {
            form.classList.remove('active');
        });
        
        if (method === 'file') {
            document.getElementById('file-upload').classList.add('active');
        } else {
            document.getElementById('url-upload').classList.add('active');
        }
    });
});

// Drag & Drop
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('dragover');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFileSelect(files[0]);
    }
});

// Selección de archivo
fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        handleFileSelect(e.target.files[0]);
    }
});

// Manejar archivo seleccionado
function handleFileSelect(file) {
    // Validar extensión
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(ext)) {
        showAlert('Formato de archivo no permitido. Solo se aceptan: ' + allowedExtensions.join(', '), 'danger');
        return;
    }
    
    // Validar tamaño
    if (file.size > maxFileSize) {
        showAlert('El archivo es demasiado grande. Tamaño máximo: 2GB', 'danger');
        return;
    }
    
    // Actualizar UI
    dropZone.innerHTML = `
        <div class="selected-file">
            <i class="fas fa-file-video"></i>
            <h4>${file.name}</h4>
            <p>${formatBytes(file.size)}</p>
            <button type="button" class="btn btn-secondary" onclick="resetFileSelection()">
                <i class="fas fa-times"></i> Cambiar archivo
            </button>
        </div>
    `;
    
    // Mostrar detalles del video
    document.querySelector('.video-details').style.display = 'block';
    
    // Auto-completar título con nombre del archivo
    const titleInput = uploadForm.querySelector('input[name="title"]');
    if (!titleInput.value) {
        titleInput.value = file.name.replace(/\.[^/.]+$/, '');
    }
}

// Resetear selección de archivo
function resetFileSelection() {
    fileInput.value = '';
    location.reload();
}

// Subir archivo
uploadForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(uploadForm);
    const file = fileInput.files[0];
    
    if (!file) {
        showAlert('Por favor selecciona un archivo', 'danger');
        return;
    }
    
    // Mostrar progreso
    document.getElementById('file-upload').style.display = 'none';
    document.getElementById('uploadProgress').style.display = 'block';
    
    try {
        uploadRequest = new XMLHttpRequest();
        
        // Progreso de subida
        uploadRequest.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                updateProgress(percentComplete, e.loaded, e.total);
            }
        });
        
        // Completado
        uploadRequest.addEventListener('load', () => {
            if (uploadRequest.status === 200) {
                const response = JSON.parse(uploadRequest.responseText);
                if (response.success) {
                    showSuccess(response.data);
                } else {
                    showAlert(response.message || 'Error al subir el video', 'danger');
                    resetUpload();
                }
            } else {
                showAlert('Error del servidor', 'danger');
                resetUpload();
            }
        });
        
        // Error
        uploadRequest.addEventListener('error', () => {
            showAlert('Error de conexión', 'danger');
            resetUpload();
        });
        
        // Enviar
        uploadRequest.open('POST', '/api/upload.php');
        uploadRequest.send(formData);
        
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
        resetUpload();
    }
});

// Subir desde URL
urlUploadForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(urlUploadForm);
    
    // Mostrar progreso
    document.getElementById('url-upload').style.display = 'none';
    document.getElementById('uploadProgress').style.display = 'block';
    document.querySelector('#uploadProgress h3').textContent = 'Descargando video...';
    
    try {
        const response = await fetch('/api/upload.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(result.data);
        } else {
            showAlert(result.message || 'Error al procesar el video', 'danger');
            resetUpload();
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
        resetUpload();
    }
});

// Actualizar progreso
function updateProgress(percent, loaded, total) {
    document.getElementById('progressFill').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
    
    // Calcular velocidad
    const speed = calculateSpeed(loaded);
    document.getElementById('progressSpeed').textContent = speed;
    
    // Estimar tiempo restante
    const timeLeft = calculateTimeLeft(loaded, total);
    document.getElementById('progressTime').textContent = timeLeft;
}

// Cancelar subida
document.getElementById('cancelUpload').addEventListener('click', () => {
    if (uploadRequest) {
        uploadRequest.abort();
    }
    resetUpload();
});

// Mostrar éxito
function showSuccess(data) {
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadSuccess').style.display = 'block';
    
    // Llenar información
    const videoUrl = `${window.location.origin}/watch/${data.video_code}`;
    const embedUrl = `${window.location.origin}/embed/${data.video_code}`;
    
    document.getElementById('videoLink').value = videoUrl;
    document.getElementById('embedCode').value = `<iframe src="${embedUrl}" width="640" height="360" frameborder="0" allowfullscreen></iframe>`;
    document.getElementById('watchLink').href = `/pages/watch.php?v=${data.video_code}`;
}

// Resetear formulario
function resetUpload() {
    document.getElementById('file-upload').style.display = 'block';
    document.getElementById('url-upload').style.display = 'none';
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadSuccess').style.display = 'none';
    document.querySelector('.method-btn[data-method="file"]').click();
}

// Copiar al portapapeles
function copyLink(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    document.execCommand('copy');
    
    // Feedback visual
    const btn = element.nextElementSibling;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copiado';
    btn.classList.add('btn-success');
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.classList.remove('btn-success');
    }, 2000);
}

// Utilidades
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

let startTime = Date.now();
let lastLoaded = 0;

function calculateSpeed(loaded) {
    const currentTime = Date.now();
    const elapsedTime = (currentTime - startTime) / 1000;
    const bytesPerSecond = loaded / elapsedTime;
    return formatBytes(bytesPerSecond) + '/s';
}

function calculateTimeLeft(loaded, total) {
    const currentTime = Date.now();
    const elapsedTime = (currentTime - startTime) / 1000;
    const bytesPerSecond = loaded / elapsedTime;
    const bytesRemaining = total - loaded;
    const secondsRemaining = bytesRemaining / bytesPerSecond;
    
    if (secondsRemaining < 60) {
        return Math.round(secondsRemaining) + 's restantes';
    } else if (secondsRemaining < 3600) {
        return Math.round(secondsRemaining / 60) + 'm restantes';
    } else {
        return Math.round(secondsRemaining / 3600) + 'h restantes';
    }
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
    `;
    
    const container = document.querySelector('.upload-container .container');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => alertDiv.remove(), 5000);
}