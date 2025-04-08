<?php
session_start();
require_once './php/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php?error=no_autenticado');
    exit;
}

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = conectarDB();

// Verificación robusta del usuario
$stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['usuario_id']]);
if (!$stmt->fetch()) {
    error_log("Intento de acceso con usuario inválido ID: ".$_SESSION['usuario_id']);
    session_destroy();
    header('Location: ../index.php?error=usuario_no_existe');
    exit;
}
        
        // Inicializar array de datos con valores por defecto
        $datos = [
            'ruc' => '',
            'razon_social' => '',
            'nombre_comercial' => '',
            'direccion_matriz' => '',
            'direccion_establecimiento' => '',
            'codigo_establecimiento' => '',
            'codigo_punto_emision' => '',
            'contribuyente_especial' => null,
            'nro_resolucion_contribuyente_especial' => null, // Nombre corregido
            'obligado_contabilidad' => 'NO',
            'exportador_bienes' => 'NO',
            'contribuyente_rimpe' => 'NO',
            'agente_de_retencion' => null,
            'nro_resolucion_agente_retencion' => null, // Nombre corregido
            'logo' => null,
            'tipo_ambiente' => 'PRUEBAS',
            'token_firma' => null,
            'ultimo_secuencial' => 0,
            'usuario_id' => $_SESSION['usuario_id']
        ];

        // Asignar valores desde $_POST con validación
        foreach ($datos as $key => &$value) {
            if ($key === 'obligado_contabilidad' || 
                $key === 'exportador_bienes' || 
                $key === 'contribuyente_rimpe') {
                $value = isset($_POST[$key]) ? 'SI' : 'NO';
            } elseif ($key === 'usuario_id' || $key === 'ultimo_secuencial') {
                continue; // Ya tienen valor
            } elseif (isset($_POST[$key])) {
                $value = htmlspecialchars(trim($_POST[$key]));
            }
        }
        unset($value); // Romper la referencia

        // Validar campos obligatorios
        $camposObligatorios = [
            'ruc' => 'RUC',
            'razon_social' => 'Razón Social',
            'direccion_matriz' => 'Dirección Matriz',
            'codigo_establecimiento' => 'Código Establecimiento',
            'codigo_punto_emision' => 'Código Punto de Emisión',
            'tipo_ambiente' => 'Tipo de Ambiente'
        ];

        foreach ($camposObligatorios as $campo => $nombre) {
            if (empty($datos[$campo])) {
                throw new Exception("El campo $nombre es obligatorio");
            }
        }

        // Validaciones específicas
        if (!preg_match('/^[0-9]{13}$/', $datos['ruc'])) {
            throw new Exception("El RUC debe tener exactamente 13 dígitos");
        }

        if (!empty($datos['codigo_establecimiento']) && !preg_match('/^[0-9]{3}$/', $datos['codigo_establecimiento'])) {
            throw new Exception("El Código de Establecimiento debe tener 3 dígitos");
        }

        if (!empty($datos['codigo_punto_emision']) && !preg_match('/^[0-9]{3}$/', $datos['codigo_punto_emision'])) {
            throw new Exception("El Código de Punto de Emisión debe tener 3 dígitos");
        }

        // Procesar logo (código sin cambios)
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            // ... (mantener el mismo código de procesamiento de logo)
        }

        // Preparar consulta SQL actualizada
        $sql = "INSERT INTO empresas (
            ruc, razon_social, nombre_comercial, direccion_matriz, 
            direccion_establecimiento, codigo_establecimiento, codigo_punto_emision,
            contribuyente_especial, nro_resolucion_contribuyente_especial, obligado_contabilidad, 
            exportador_bienes, contribuyente_rimpe, agente_de_retencion, 
            nro_resolucion_agente_retencion, logo, tipo_ambiente, token_firma, ultimo_secuencial, usuario_id
        ) VALUES (
            :ruc, :razon_social, :nombre_comercial, :direccion_matriz,
            :direccion_establecimiento, :codigo_establecimiento, :codigo_punto_emision,
            :contribuyente_especial, :nro_resolucion_contribuyente_especial, :obligado_contabilidad,
            :exportador_bienes, :contribuyente_rimpe, :agente_de_retencion,
            :nro_resolucion_agente_retencion, :logo, :tipo_ambiente, :token_firma, :ultimo_secuencial, :usuario_id
        )";
        
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt->execute($datos)) {
            throw new Exception("Error al ejecutar la consulta SQL: " . implode(", ", $stmt->errorInfo()));
        }
        
        $_SESSION['success_message'] = 'Empresa creada correctamente';
        header('Location: menu_principal.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        error_log(message: 'Error en nueva_empresa.php: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/style_nueva_empresa.css">
    <title>Nueva Empresa</title>
    
</head>
<body>
    <div class="container">
        <a href="menu_principal.php" class="btn back-btn">← Volver al Menú Principal</a>
        
        <h1>Registrar Nueva Empresa</h1>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error_message']); ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="ruc" class="required">RUC:</label>
                <input type="text" id="ruc" name="ruc" required pattern="[0-9]{13}" title="13 dígitos numéricos">
                <small>Formato: 13 dígitos numéricos</small>
            </div>
            
            <div class="form-group">
                <label for="razon_social" class="required">Razón Social:</label>
                <input type="text" id="razon_social" name="razon_social" required>
            </div>
            
            <div class="form-group">
                <label for="nombre_comercial">Nombre Comercial:</label>
                <input type="text" id="nombre_comercial" name="nombre_comercial">
            </div>
            
            <div class="form-group">
                <label for="direccion_matriz" class="required">Dirección Matriz:</label>
                <textarea id="direccion_matriz" name="direccion_matriz" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="direccion_establecimiento">Dirección Establecimiento:</label>
                <textarea id="direccion_establecimiento" name="direccion_establecimiento"></textarea>
            </div>
            
            <div class="form-group">
                <label for="codigo_establecimiento">Código Establecimiento:</label>
                <input type="text" id="codigo_establecimiento" name="codigo_establecimiento" pattern="[0-9]{3}" title="3 dígitos numéricos">
                <small>Formato: 3 dígitos numéricos</small>
            </div>
            
            <div class="form-group">
                <label for="codigo_punto_emision">Código Punto de Emisión:</label>
                <input type="text" id="codigo_punto_emision" name="codigo_punto_emision" pattern="[0-9]{3}" title="3 dígitos numéricos">
                <small>Formato: 3 dígitos numéricos</small>
            </div>
            
            <div class="form-group">
                <label for="contribuyente_especial">Contribuyente Especial:</label>
                <input type="text" id="contribuyente_especial" name="contribuyente_especial">
            </div>
            
            <div class="form-group">
                <label for="nro_resolution_contribuyente">N° Resolución Contribuyente Especial:</label>
                <input type="text" id="nro_resolution_contribuyente" name="nro_resolution_contribuyente">
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="obligado_contabilidad" name="obligado_contabilidad">
                <label for="obligado_contabilidad">Obligado a llevar contabilidad</label>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="exportador_bienes" name="exportador_bienes">
                <label for="exportador_bienes">Exportador de Bienes</label>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="contribuyente_rimpe" name="contribuyente_rimpe">
                <label for="contribuyente_rimpe">Contribuyente RIMPE</label>
            </div>
            
            <div class="form-group">
                <label for="agente_de_retencion">Agente de Retención:</label>
                <input type="text" id="agente_de_retencion" name="agente_de_retencion">
            </div>
            
            <div class="form-group">
                <label for="nro_resolution_agente">N° Resolución Agente de Retención:</label>
                <input type="text" id="nro_resolution_agente" name="nro_resolution_agente">
            </div>
            
            <div class="form-group">
                <label for="logo">Logo (Imagen):</label>
                <input type="file" id="logo" name="logo" accept="image/jpeg, image/png, image/gif">
                <small>Formatos aceptados: JPEG, PNG, GIF</small>
            </div>
            
            <div class="form-group">
                <label for="tipo_ambiente" class="required">Tipo de Ambiente:</label>
                <select id="tipo_ambiente" name="tipo_ambiente" required>
                    <option value="PRUEBAS">Pruebas</option>
                    <option value="PRODUCCION">Producción</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="token_firma">Token de Firma Electrónica:</label>
                <input type="text" id="token_firma" name="token_firma">
            </div>
            
            <button type="submit" class="btn">Guardar Empresa</button>
        </form>
    </div>
</body>
</html>