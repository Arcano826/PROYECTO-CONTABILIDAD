<?php
session_start();
require_once './php/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

$empresa = null;

// Obtener datos de la empresa a editar
if (isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if ($id) {
        try {
            $pdo = conectarDB();
            $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
            $stmt->execute([$id]);
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$empresa) {
                $_SESSION['error_message'] = 'Empresa no encontrada';
                header('Location: listar_empresas.php');
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error al obtener empresa: " . $e->getMessage();
            header('Location: listar_empresas.php');
            exit;
        }
    }
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = conectarDB();
        
        // Inicializar array de datos
        $datos = [
            'id' => filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT),
            'ruc' => htmlspecialchars(trim($_POST['ruc'] ?? '')),
            'razon_social' => htmlspecialchars(trim($_POST['razon_social'] ?? '')),
            'nombre_comercial' => htmlspecialchars(trim($_POST['nombre_comercial'] ?? '')),
            'direccion_matriz' => htmlspecialchars(trim($_POST['direccion_matriz'] ?? '')),
            'direccion_establecimiento' => htmlspecialchars(trim($_POST['direccion_establecimiento'] ?? '')),
            'codigo_establecimiento' => htmlspecialchars(trim($_POST['codigo_establecimiento'] ?? '')),
            'codigo_punto_emision' => htmlspecialchars(trim($_POST['codigo_punto_emision'] ?? '')),
            'contribuyente_especial' => !empty($_POST['contribuyente_especial']) ? htmlspecialchars(trim($_POST['contribuyente_especial'])) : null,
            'obligado_contabilidad' => isset($_POST['obligado_contabilidad']) ? 'SI' : 'NO',
            'exportador_bienes' => isset($_POST['exportador_bienes']) ? 'SI' : 'NO',
            'contribuyente_rimpe' => isset($_POST['contribuyente_rimpe']) ? 'SI' : 'NO',
            'agente_de_retencion' => !empty($_POST['agente_de_retencion']) ? htmlspecialchars(trim($_POST['agente_de_retencion'])) : null,
            'tipo_ambiente' => htmlspecialchars($_POST['tipo_ambiente'] ?? 'PRUEBAS'),
            'logo' => $empresa['logo'] ?? null // Mantener el logo existente por defecto
        ];

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

        if (!preg_match('/^[0-9]{3}$/', $datos['codigo_establecimiento'])) {
            throw new Exception("El Código de Establecimiento debe tener 3 dígitos");
        }

        if (!preg_match('/^[0-9]{3}$/', $datos['codigo_punto_emision'])) {
            throw new Exception("El Código de Punto de Emisión debe tener 3 dígitos");
        }

        // Procesar nuevo logo si se subió
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $permitidos = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['logo']['type'], $permitidos)) {
                throw new Exception("Solo se permiten imágenes JPEG, PNG o GIF");
            }

            $nombreArchivo = uniqid() . '_' . basename($_FILES['logo']['name']);
            $rutaDestino = './uploads/logos/' . $nombreArchivo;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $rutaDestino)) {
                // Eliminar el logo anterior si existe
                if (!empty($empresa['logo']) && file_exists($empresa['logo'])) {
                    unlink($empresa['logo']);
                }
                $datos['logo'] = $rutaDestino;
            } else {
                throw new Exception("Error al subir el archivo de logo");
            }
        }

        // Actualizar en la base de datos
        $sql = "UPDATE empresas SET 
                ruc = :ruc, 
                razon_social = :razon_social, 
                nombre_comercial = :nombre_comercial, 
                direccion_matriz = :direccion_matriz,
                direccion_establecimiento = :direccion_establecimiento,
                codigo_establecimiento = :codigo_establecimiento,
                codigo_punto_emision = :codigo_punto_emision,
                contribuyente_especial = :contribuyente_especial,
                obligado_contabilidad = :obligado_contabilidad,
                exportador_bienes = :exportador_bienes,
                contribuyente_rimpe = :contribuyente_rimpe,
                agente_de_retencion = :agente_de_retencion,
                logo = :logo,
                tipo_ambiente = :tipo_ambiente
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($datos);
        
        $_SESSION['success_message'] = 'Empresa actualizada correctamente';
        header('Location: ver_empresa.php?id=' . $datos['id']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/style_editar_empre.css">
    <title>Editar Empresa</title>
    
</head>
<body>
    <div class="container">
        <a href="listar_empresas.php" class="btn back-btn">← Volver al Listado</a>
        
        <h1>Editar Empresa</h1>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error_message']); ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($empresa): ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $empresa['id'] ?>">
            
            <div class="form-group">
                <label for="ruc" class="required">RUC:</label>
                <input type="text" id="ruc" name="ruc" required 
                       pattern="[0-9]{13}" title="13 dígitos numéricos"
                       value="<?= htmlspecialchars($empresa['ruc']) ?>">
                <small>Formato: 13 dígitos numéricos</small>
            </div>
            
            <div class="form-group">
                <label for="razon_social" class="required">Razón Social:</label>
                <input type="text" id="razon_social" name="razon_social" required
                       value="<?= htmlspecialchars($empresa['razon_social']) ?>">
            </div>
            
            <div class="form-group">
                <label for="nombre_comercial" class="required">Nombre Comercial:</label>
                <input type="text" id="nombre_comercial" name="nombre_comercial" required
                       value="<?= htmlspecialchars($empresa['nombre_comercial']) ?>">
            </div>
            
            <div class="form-group">
                <label for="direccion_matriz" class="required">Dirección Matriz:</label>
                <textarea id="direccion_matriz" name="direccion_matriz" required><?= 
                    htmlspecialchars($empresa['direccion_matriz']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="direccion_establecimiento" class="required">Dirección Establecimiento:</label>
                <textarea id="direccion_establecimiento" name="direccion_establecimiento" required><?= 
                    htmlspecialchars($empresa['direccion_establecimiento']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="codigo_establecimiento" class="required">Código Establecimiento:</label>
                <input type="text" id="codigo_establecimiento" name="codigo_establecimiento" 
                       required pattern="[0-9]{3}" title="3 dígitos numéricos"
                       value="<?= htmlspecialchars($empresa['codigo_establecimiento']) ?>">
                <small>Formato: 3 dígitos numéricos</small>
            </div>
            
            <div class="form-group">
                <label for="codigo_punto_emision" class="required">Código Punto de Emisión:</label>
                <input type="text" id="codigo_punto_emision" name="codigo_punto_emision" 
                       required pattern="[0-9]{3}" title="3 dígitos numéricos"
                       value="<?= htmlspecialchars($empresa['codigo_punto_emision']) ?>">
                <small>Formato: 3 dígitos numéricos</small>
            </div>
            
            <div class="form-group">
                <label for="contribuyente_especial">Contribuyente Especial (Nro. Resolución):</label>
                <input type="text" id="contribuyente_especial" name="contribuyente_especial"
                       value="<?= htmlspecialchars($empresa['contribuyente_especial'] ?? '') ?>">
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="obligado_contabilidad" name="obligado_contabilidad" 
                       <?= $empresa['obligado_contabilidad'] === 'SI' ? 'checked' : '' ?>>
                <label for="obligado_contabilidad">Obligado a llevar contabilidad</label>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="exportador_bienes" name="exportador_bienes"
                       <?= $empresa['exportador_bienes'] === 'SI' ? 'checked' : '' ?>>
                <label for="exportador_bienes">Exportador de Bienes</label>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="contribuyente_rimpe" name="contribuyente_rimpe"
                       <?= $empresa['contribuyente_rimpe'] === 'SI' ? 'checked' : '' ?>>
                <label for="contribuyente_rimpe">Contribuyente RIMPE</label>
            </div>
            
            <div class="form-group">
                <label for="agente_de_retencion">Agente de Retención (Nro. Resolución):</label>
                <input type="text" id="agente_de_retencion" name="agente_de_retencion"
                       value="<?= htmlspecialchars($empresa['agente_de_retencion'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="logo">Logo (Imagen):</label>
                <input type="file" id="logo" name="logo" accept="image/jpeg, image/png, image/gif">
                <?php if (!empty($empresa['logo'])): ?>
                    <img src="<?= htmlspecialchars($empresa['logo']) ?>" alt="Logo actual" class="logo-preview">
                    <small>Subir nueva imagen para reemplazar</small>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="tipo_ambiente" class="required">Tipo de Ambiente:</label>
                <select id="tipo_ambiente" name="tipo_ambiente" required>
                    <option value="PRUEBAS" <?= $empresa['tipo_ambiente'] === 'PRUEBAS' ? 'selected' : '' ?>>Pruebas</option>
                    <option value="PRODUCCION" <?= $empresa['tipo_ambiente'] === 'PRODUCCION' ? 'selected' : '' ?>>Producción</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-success">Guardar Cambios</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>