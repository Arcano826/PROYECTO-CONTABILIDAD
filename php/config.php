<?php
// config.php

// =============================================
// CONFIGURACIÓN BÁSICA DE LA BASE DE DATOS
// =============================================
define('DB_HOST', '127.0.0.1'); // O la IP de tu servidor MySQL
define('DB_USER', 'root'); // Usuario de MySQL Workbench
define('DB_PASS', '12345'); // Contraseña del usuario
define('DB_NAME', 'sistema_facturacion'); // Nombre de tu base de datos
define('DB_PORT', 3310); // Puerto de MySQL (por defecto 3306)

// =============================================
// CONFIGURACIÓN PARA TCPDF (GENERACIÓN DE PDF)
// =============================================
define('PDF_FONT_NAME_MAIN', 'helvetica');
define('PDF_FONT_SIZE_MAIN', 10);
define('PDF_FONT_NAME_DATA', 'helvetica');
define('PDF_FONT_SIZE_DATA', 8);
define('PDF_FONT_MONOSPACED', 'courier');
define('PDF_IMAGE_SCALE_RATIO', 1.25);
define('HEAD_MAGNIFICATION', 1.1);
define('K_CELL_HEIGHT_RATIO', 1.25);
define('K_TITLE_MAGNIFICATION', 1.3);
define('K_SMALL_RATIO', 2/3);

// =============================================
// CONFIGURACIÓN ADICIONAL PARA MYSQL WORKBENCH
// =============================================
define('DB_SSL', false); // Habilitar si usas conexión SSL
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');
define('DB_TIMEZONE', '-05:00'); // Zona horaria de Ecuador

// =============================================
// FUNCIÓN MEJORADA PARA CONEXIÓN A LA BASE DE DATOS
// =============================================
function conectarDB() {
    try {
        $dsn = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
        $opciones = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        return new PDO($dsn, DB_USER, DB_PASS, $opciones);
    } catch (PDOException $e) {
        error_log('Error de conexión: '.$e->getMessage());
        die("Error al conectar con la base de datos. Por favor intente más tarde.");
    }
}

// =============================================
// FUNCIÓN MEJORADA PARA SUBIR ARCHIVOS
// =============================================
function subirArchivo($file, $directorio = 'uploads/') {
    if (!file_exists($directorio)) {
        mkdir($directorio, 0755, true);
        file_put_contents($directorio . '.htaccess', "Deny from all"); // Proteger directorio
    }
    
    // Validar el archivo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensionesValidas = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    
    $extension = array_search($mime, $extensionesValidas, true);
    
    if ($extension === false) {
        throw new Exception("Formato de archivo no permitido");
    }
    
    $nombreArchivo = sprintf('%s.%s', sha1_file($file['tmp_name']), $extension);
    $rutaArchivo = $directorio . $nombreArchivo;
    
    if (!move_uploaded_file($file['tmp_name'], $rutaArchivo)) {
        throw new Exception("Error al subir el archivo");
    }
    
    return $rutaArchivo;
}

// =============================================
// MANEJO DE ERRORES Y SEGURIDAD
// =============================================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error: [$errno] $errstr en $errfile línea $errline");
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        echo "<div class='error'><b>Error:</b> $errstr en <i>$errfile</i> línea <i>$errline</i></div>";
    }
});

// Configuración de sesión segura
function iniciarSesionSegura() {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // Solo si usas HTTPS
    ini_set('session.use_strict_mode', 1);
    session_start();
    
    // Regenerar ID de sesión periódicamente
    if (!isset($_SESSION['generated']) || $_SESSION['generated'] < (time() - 3600)) {
        session_regenerate_id(true);
        $_SESSION['generated'] = time();
    }
}

// =============================================
// CONSTANTES DEL SISTEMA
// =============================================
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB máximo para uploads
define('SESSION_TIMEOUT', 1800); // 30 minutos de inactividad
define('ENVIRONMENT', 'development'); // 'production' en entorno real

