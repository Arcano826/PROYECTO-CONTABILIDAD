<?php
session_start();
require_once './php/config.php';
if (isset($_SESSION['nuevo_cliente'])) {
    $_POST = array_merge($_POST, $_SESSION['nuevo_cliente']);
    unset($_SESSION['nuevo_cliente']);
}
// Configurar para mostrar errores (solo en desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y limpiar datos
    $empresa_id = trim($_POST['empresa_id'] ?? '');
    $tipo_identificacion = trim($_POST['tipo_identificacion'] ?? '');
    $identificacion = trim($_POST['identificacion'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validar datos requeridos
    $errores = [];
    
    if (empty($empresa_id)) {
        $errores[] = "Empresa no especificada";
    }
    
    if (empty($tipo_identificacion) || !in_array($tipo_identificacion, ['04', '05', '06', '07', '08'])) {
        $errores[] = "Tipo de identificación no válido";
    }
    
    if (empty($identificacion)) {
        $errores[] = "Identificación es requerida";
    }
    
    if (empty($nombre)) {
        $errores[] = "Nombre/Razón Social es requerido";
    }
    
    if (empty($direccion)) {
        $errores[] = "Dirección es requerida";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Email no válido";
    }

    if (empty($errores)) {
        try {
            $pdo = conectarDB();
            
            // Verificar si el cliente ya existe
            $stmt = $pdo->prepare("SELECT id FROM clientes 
                                  WHERE empresa_id = ? 
                                  AND tipo_identificacion = ? 
                                  AND identificacion = ?");
            $stmt->execute([$empresa_id, $tipo_identificacion, $identificacion]);
            
            if ($stmt->fetch()) {
                $_SESSION['error_message'] = 'Ya existe un cliente con esta identificación para esta empresa';
            } else {
                // Insertar nuevo cliente
                $stmt = $pdo->prepare("INSERT INTO clientes 
                                      (empresa_id, tipo_identificacion, identificacion, 
                                       nombre, direccion, telefono, email)
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $empresa_id,
                    $tipo_identificacion,
                    $identificacion,
                    $nombre,
                    $direccion,
                    $telefono,
                    $email
                ]);
                
                // Guardar datos en sesión para autocompletar en facturación
                $_SESSION['nuevo_cliente'] = [
                    'comprador_tipo' => $tipo_identificacion,
                    'comprador_identificacion' => $identificacion,
                    'comprador_nombre' => $nombre,
                    'comprador_direccion' => $direccion,
                    'comprador_telefono' => $telefono,
                    'comprador_email' => $email,
                    'comprador_id' => $pdo->lastInsertId()
                ];
                
                $_SESSION['success_message'] = 'Cliente creado exitosamente';
                
                // Redireccionar a facturación
                header("Location: facturacion.php?empresa_id=$empresa_id");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error al guardar cliente: " . $e->getMessage());
            $_SESSION['error_message'] = "Error al guardar el cliente. Por favor intente nuevamente.";
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errores);
    }
}

// Obtener parámetros de la URL
$empresa_id = $_GET['empresa_id'] ?? '';
$tipo_identificacion = $_GET['tipo'] ?? '04';
$identificacion = $_GET['identificacion'] ?? '';

// Validar empresa_id
if (empty($empresa_id) || !is_numeric($empresa_id)) {
    die("Empresa no especificada o inválida");
}

// Obtener nombre de la empresa para mostrar
$nombre_empresa = '';
try {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT razon_social FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    $nombre_empresa = $empresa['razon_social'] ?? '';
} catch (PDOException $e) {
    error_log("Error al obtener empresa: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cliente</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: white;
            padding: 25px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .form-group { 
            margin-bottom: 15px;
        }
        label { 
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        input[type="text"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .btn {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Nuevo Cliente</h1>
        
        <?php if (!empty($nombre_empresa)): ?>
            <p><strong>Empresa:</strong> <?= htmlspecialchars($nombre_empresa) ?></p>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <form method="POST" action="nuevo_cliente.php">
            <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa_id) ?>">
            
            <div class="form-group">
                <label for="tipo_identificacion" class="required-field">Tipo de Identificación</label>
                <select id="tipo_identificacion" name="tipo_identificacion" required>
                    <?php 
                    $tipos = [
                        '04' => 'RUC',
                        '05' => 'Cédula',
                        '06' => 'Pasaporte', 
                        '07' => 'Consumidor Final',
                        '08' => 'Identificación del Exterior'
                    ];
                    foreach ($tipos as $valor => $texto): ?>
                        <option value="<?= $valor ?>" 
                            <?= $tipo_identificacion == $valor ? 'selected' : '' ?>>
                            <?= $texto ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="identificacion" class="required-field">Identificación</label>
                <input type="text" id="identificacion" name="identificacion" 
                       value="<?= htmlspecialchars($identificacion) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="nombre" class="required-field">Nombre/Razón Social</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            
            <div class="form-group">
                <label for="direccion" class="required-field">Dirección</label>
                <input type="text" id="direccion" name="direccion" required>
            </div>
            
            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email">
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Guardar Cliente
                </button>
                <a href="facturacion.php?empresa_id=<?= htmlspecialchars($empresa_id) ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</body>
</html>