<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['error_message'] = 'Debe iniciar sesión primero';
    header('Location: ./php/login.php');
    exit;
}

require_once './php/config.php';

// Inicializar variables
$empresa_seleccionada = null;
$numero_factura = '';
$productos = [];
$mensaje_error = '';
$mensaje_info = '';

// Obtener empresas del usuario
try {
    $pdo = conectarDB();
    $sql_empresas = "SELECT id, ruc, razon_social, nombre_comercial, 
                    codigo_establecimiento, codigo_punto_emision, ultimo_secuencial
                    FROM empresas 
                    WHERE usuario_id = ?
                    ORDER BY razon_social";
    $stmt_empresas = $pdo->prepare($sql_empresas);
    $stmt_empresas->execute([$_SESSION['usuario_id']]);
    $empresas = $stmt_empresas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje_error = "Error al obtener empresas: " . $e->getMessage();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Selección de empresa
    if (isset($_POST['empresa_id'])) {
        $empresa_id = $_POST['empresa_id'];
        
        if ($empresa_id) {
            // Buscar la empresa seleccionada
            foreach ($empresas as $empresa) {
                if ($empresa['id'] == $empresa_id) {
                    $empresa_seleccionada = $empresa;
                    break;
                }
            }
            
            if (!$empresa_seleccionada) {
                $mensaje_error = 'Empresa no encontrada';
                // Continuar con el flujo normal para mostrar el error
            } else {
                // Procesamiento de productos (manteniendo tu lógica original)
                try {
                    $sql_productos = "SELECT id, codigo, descripcion, precio, iva, ice, irbpnr 
                                    FROM productos 
                                    WHERE empresa_id = ?";
                    $stmt_productos = $pdo->prepare($sql_productos);
                    $stmt_productos->execute([$empresa_seleccionada['id']]);
                    $productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $mensaje_error = "Error al obtener productos: " . $e->getMessage();
                }

                // Verificar si es una solicitud de generación de factura
                if (isset($_POST['generar_factura'])) {
                    try {
                        // Validación básica de campos requeridos
                        if (empty($_POST['fecha_emision']) || empty($_POST['tipo_comprobante'])) {
                            throw new Exception("Faltan campos requeridos para generar la factura");
                        }

                        // Obtener el secuencial actualizado
                        $secuencial = $empresa_seleccionada['ultimo_secuencial'] + 1;
                        $numero_factura = sprintf("%03d-%03d-%09d", 
                            $empresa_seleccionada['codigo_establecimiento'], 
                            $empresa_seleccionada['codigo_punto_emision'], 
                            $secuencial);
                        
                        // Generar clave de acceso inicial
                        $clave_acceso = generarClaveAcceso(
                            date('Y-m-d'),
                            '01', // Tipo comprobante por defecto (Factura)
                            $empresa_seleccionada['ruc'],
                            $empresa_seleccionada['tipo_ambiente'] == 'PRODUCCION' ? '2' : '1',
                            $empresa_seleccionada['codigo_establecimiento'] . $empresa_seleccionada['codigo_punto_emision'],
                            $secuencial
                        );
                
                        // Iniciar transacción
                        $pdo->beginTransaction();
                        
                        // Insertar la factura
                        $sql = "INSERT INTO facturas (
                            empresa_id, 
                            cliente_id, 
                            numero_factura, 
                            fecha_emision, 
                            tipo_comprobante, 
                            subtotal_0, 
                            subtotal_12, 
                            iva, 
                            total, 
                            clave_acceso,
                            estado,
                            moneda,
                            guia_remision,
                            usuario_id
                        ) VALUES (
                            :empresa_id, 
                            :cliente_id, 
                            :numero_factura, 
                            :fecha_emision, 
                            :tipo_comprobante, 
                            :subtotal_0, 
                            :subtotal_12, 
                            :iva, 
                            :total, 
                            :clave_acceso,
                            'PENDIENTE',
                            :moneda,
                            :guia_remision,
                            :usuario_id
                        )";
                        
                        $stmt = $pdo->prepare($sql);
                        
                        // Calcular totales
                        $subtotal_0 = calcularSubtotal0($_POST['items'] ?? []);
                        $subtotal_12 = calcularSubtotal12($_POST['items'] ?? []);
                        $iva = calcularIVA($_POST['items'] ?? []);
                        $total = $subtotal_0 + $subtotal_12 + $iva;
                        
                        $stmt->execute([
                            ':empresa_id' => $empresa_seleccionada['id'],
                            ':cliente_id' => $_POST['comprador_id'] ?? null,
                            ':numero_factura' => $numero_factura,
                            ':fecha_emision' => $_POST['fecha_emision'],
                            ':tipo_comprobante' => $_POST['tipo_comprobante'],
                            ':subtotal_0' => $subtotal_0,
                            ':subtotal_12' => $subtotal_12,
                            ':iva' => $iva,
                            ':total' => $total,
                            ':clave_acceso' => $clave_acceso,
                            ':moneda' => $_POST['moneda'] ?? 'USD',
                            ':guia_remision' => $_POST['guia_remision'] ?? null,
                            ':usuario_id' => $_SESSION['usuario_id']
                        ]);
                        
                        $factura_id = $pdo->lastInsertId();
                        
                        // Insertar detalles de la factura
                        if (!empty($_POST['items'])) {
                            foreach ($_POST['items'] as $item) {
                                $sql = "INSERT INTO factura_detalles (
                                    factura_id, 
                                    producto_id, 
                                    codigo, 
                                    descripcion, 
                                    cantidad, 
                                    precio_unitario, 
                                    desuento, 
                                    subtotal, 
                                    iva, 
                                    ice, 
                                    irbpnr
                                ) VALUES (
                                    :factura_id, 
                                    :producto_id, 
                                    :codigo, 
                                    :descripcion, 
                                    :cantidad, 
                                    :precio_unitario, 
                                    :descuento, 
                                    :subtotal, 
                                    :iva, 
                                    :ice, 
                                    :irbpnr
                                )";
                                
                                $stmt = $pdo->prepare($sql);
                                
                                $subtotal = $item['cantidad'] * $item['precio'] * (1 - ($item['descuento'] / 100));
                                $iva_item = $item['iva'] ? $subtotal * 0.12 : 0;
                                
                                $stmt->execute([
                                    ':factura_id' => $factura_id,
                                    ':producto_id' => $item['producto_id'],
                                    ':codigo' => $item['codigo'],
                                    ':descripcion' => $item['descripcion'],
                                    ':cantidad' => $item['cantidad'],
                                    ':precio_unitario' => $item['precio'],
                                    ':descuento' => $item['descuento'],
                                    ':subtotal' => $subtotal,
                                    ':iva' => $iva_item,
                                    ':ice' => $item['ice'] ?? 0,
                                    ':irbpnr' => $item['irbpnr'] ?? 0
                                ]);
                            }
                        }
                        
                        // Actualizar el secuencial en la empresa
                        $sql = "UPDATE empresas SET ultimo_secuencial = :secuencial WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':secuencial' => $secuencial, ':id' => $empresa_seleccionada['id']]);
                        
                        // Commit de la transacción
                        $pdo->commit();
                        
                        $_SESSION['mensaje_exito'] = 'Factura generada correctamente con clave de acceso: ' . $clave_acceso;
                        header('Location: ver_factura.php?id=' . $factura_id);
                        exit;
                        
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $mensaje_error = "Error al guardar la factura: " . $e->getMessage();
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $mensaje_error = $e->getMessage();
                    }
                }
            }
        
    

                
                // Obtener productos
                try {
                    $sql_productos = "SELECT id, codigo, descripcion, precio, iva, ice, irbpnr 
                                    FROM productos 
                                    WHERE empresa_id = ?";
                    $stmt_productos = $pdo->prepare($sql_productos);
                    $stmt_productos->execute([$empresa_seleccionada['id']]);
                    $productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $mensaje_error = "Error al obtener productos: " . $e->getMessage();
                }
            } else {
                $mensaje_error = 'Empresa no encontrada';
            }
        }
    
    
    // Búsqueda de cliente
    if (isset($_POST['buscar_cliente']) && $empresa_seleccionada) {
        $tipo_identificacion = $_POST['comprador_tipo'] ?? '';
        $identificacion = $_POST['comprador_identificacion'] ?? '';
        
        if (empty($identificacion)) {
            $mensaje_error = 'Por favor ingrese un número de identificación';
        } else {
            try {
                $sql = "SELECT * FROM clientes 
                       WHERE empresa_id = ? 
                       AND tipo_identificacion = ? 
                       AND identificacion = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $empresa_seleccionada['id'],
                    $tipo_identificacion,
                    $identificacion
                ]);
                
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cliente) {
                    $_POST['comprador_nombre'] = $cliente['nombre'];
                    $_POST['comprador_direccion'] = $cliente['direccion'];
                    $_POST['comprador_telefono'] = $cliente['telefono'];
                    $_POST['comprador_email'] = $cliente['email'];
                    $_POST['comprador_id'] = $cliente['id'];
                } else {
                    $_SESSION['cliente_temporal'] = [
                        'tipo' => $tipo_identificacion,
                        'identificacion' => $identificacion
                    ];
                    $mensaje_info = 'Cliente no encontrado. <a href="nuevo_cliente.php?empresa_id='.$empresa_seleccionada['id'].'&tipo='.$tipo_identificacion.'&identificacion='.$identificacion.'">¿Desea crear uno nuevo?</a>';
                    unset($_POST['comprador_nombre'], $_POST['comprador_direccion'], 
                          $_POST['comprador_telefono'], $_POST['comprador_email'], 
                          $_POST['comprador_id']);
                }
            } catch (PDOException $e) {
                $mensaje_error = "Error al buscar cliente: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Facturación</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .header-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .company-logo {
            max-height: 80px;
        }
        .form-group { 
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }
        label { 
            width: 200px;
            font-weight: bold;
            margin-right: 10px;
        }
        input[type="text"], 
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 200px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f2f2f2;
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
            margin: 5px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .total-section {
            text-align: right;
            margin-top: 20px;
            font-size: 1.2em;
            font-weight: bold;
        }
        .empresa-selector {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .empresa-selector select {
            padding: 10px;
            width: 100%;
            max-width: 500px;
            border-radius: 5px;
            border: 1px solid #ddd;
            background-color: #fff;
            color: #333;
        }
        .empresa-info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        #productModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        @media (max-width: 768px) {
            label {
                width: 100%;
                margin-bottom: 5px;
            }
            input[type="text"], 
            input[type="number"],
            input[type="date"],
            select,
            textarea {
                width: 100%;
            }
            .header-info {
                flex-direction: column;
            }
        }
        .datos-comprador {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #ddd;
}

.datos-comprador h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    text-transform: uppercase;
    font-size: 1.1em;
}

.comprador-tipo-container {
    display: flex;
    align-items: center;
    gap: 15px;
}

.tipo-comprador {
    flex: 1;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-transform: uppercase;
    font-weight: bold;
}

.comprador-warning small {
    color: #dc3545;
    font-style: italic;
}

.busqueda-container {
    display: flex;
    gap: 5px;
}

.busqueda-container input {
    flex: 1;
}

.btn-small {
    padding: 8px 12px;
    font-size: 0.9em;
}

.btn-small i {
    margin-right: 0;
}

/* Estilo para campos de solo lectura */
input[readonly] {
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    cursor: not-allowed;
}
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
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
        <h1>Sistema de Facturación</h1>
        
        <?php if ($mensaje_error): ?>
            <div class="alert alert-danger"><?= $mensaje_error ?></div>
        <?php endif; ?>
        
        <?php if ($mensaje_info): ?>
            <div class="alert alert-info"><?= $mensaje_info ?></div>
        <?php endif; ?>
        
        <!-- Selector de empresa -->
        <div class="empresa-selector">
            <form method="POST">
                <label for="empresa_id">Seleccione una empresa:</label>
                <select id="empresa_id" name="empresa_id" required>
                    <option value="">-- Seleccione --</option>
                    <?php foreach ($empresas as $empresa): ?>
                        <option value="<?= $empresa['id'] ?>" 
                            <?= ($empresa_seleccionada && $empresa['id'] == $empresa_seleccionada['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($empresa['nombre_comercial'] ?: $empresa['razon_social']) ?> 
                            (<?= htmlspecialchars($empresa['ruc']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn">Seleccionar</button>
            </form>
        </div>
        
        <?php if ($empresa_seleccionada): ?>
            <!-- Datos de la empresa -->
            <div class="empresa-info">
                <h2><?= htmlspecialchars($empresa_seleccionada['razon_social']) ?></h2>
                <p>RUC: <?= htmlspecialchars($empresa_seleccionada['ruc']) ?></p>
                <p>N° Factura: <span id="numero_factura_texto"><?= $numero_factura ?></span></p>
    <p>Clave de Acceso: <span id="clave_acceso_texto"><?= $clave_acceso ?></span></p>
</div>
            
            <!-- Formulario de facturación -->
            <form method="POST" id="facturaForm">
    <input type="hidden" name="empresa_id" value="<?= $empresa_seleccionada['id'] ?>">
    <input type="hidden" id="clave_acceso" name="clave_acceso" value="<?= $clave_acceso ?>">
    
    <!-- Campo para editar el secuencial -->
    <div class="form-group">
        <label for="numero_secuencial">Secuencial:</label>
        <input type="number" id="numero_secuencial" name="numero_secuencial" 
               min="1" value="<?= $secuencial ?>" 
               onchange="actualizarDatosFactura()">
    </div>

<div class="form-group">
    <label for="tipo_comprobante">Tipo de Comprobante:</label>
    <select id="tipo_comprobante" name="tipo_comprobante" required onchange="actualizarClaveAcceso()">
        <option value="01">Factura</option>
        <option value="04">Nota de Crédito</option>
        <option value="05">Nota de Débito</option>
    </select>
</div>
                
                <!-- Sección de datos del comprador -->
                <div class="datos-comprador">
    <h3>Datos del comprador</h3>
    
    <div class="form-group">
        <label for="comprador_tipo">Tipo de Comprador:</label>
        <div class="comprador-tipo-container">
            <select id="comprador_tipo" name="comprador_tipo" class="tipo-comprador" required>
                <?php 
                $tipos = [
                    '04' => 'RUC',
                    '05' => 'CÉDULA',
                    '06' => 'PASAPORTE', 
                    '07' => 'CONSUMIDOR FINAL',
                    '08' => 'IDENTIFICACIÓN DEL EXTERIOR'
                ];
                foreach ($tipos as $valor => $texto): ?>
                    <option value="<?= $valor ?>" 
                        <?= (isset($_POST['comprador_tipo']) && $_POST['comprador_tipo'] == $valor) ? 'selected' : '' ?>>
                        <?= $texto ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="comprador-warning">
                <small>Recuerde que a partir de $200 no se puede emitir una factura como Consumidor Final</small>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="comprador_identificacion">Identificación:</label>
        <div class="busqueda-container">
            <input type="text" id="comprador_identificacion" name="comprador_identificacion" 
                   value="<?= isset($_POST['comprador_identificacion']) ? htmlspecialchars($_POST['comprador_identificacion']) : '' ?>" 
                   placeholder="Buscar..." required>
            <button type="submit" name="buscar_cliente" class="btn btn-small">
                <i class="fas fa-search"></i> Buscar
            </button>
            <a href="nuevo_cliente.php?empresa_id=<?= $empresa_seleccionada['id'] ?>" class="btn btn-small btn-secondary">
                <i class="fas fa-plus"></i>
            </a>
            <?php if (isset($_POST['comprador_id']) && $_POST['comprador_id']): ?>
                <a href="eliminar_cliente.php?id=<?= $_POST['comprador_id'] ?>&empresa_id=<?= $empresa_seleccionada['id'] ?>" 
                   class="btn btn-small btn-danger" onclick="return confirm('¿Está seguro de eliminar este cliente?');">
                    <i class="fas fa-trash"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-group">
        <label for="comprador_nombre">RAZÓN SOCIAL/APELLIDOS Y NOMBRES:</label>
        <input type="text" id="comprador_nombre" name="comprador_nombre" 
               value="<?= isset($_POST['comprador_nombre']) ? htmlspecialchars($_POST['comprador_nombre']) : '' ?>" readonly>
    </div>

    <div class="form-group">
        <label for="comprador_direccion">DIRECCIÓN COMPRADOR:</label>
        <input type="text" id="comprador_direccion" name="comprador_direccion" 
               value="<?= isset($_POST['comprador_direccion']) ? htmlspecialchars($_POST['comprador_direccion']) : '' ?>" readonly>
    </div>

    <div class="form-group">
        <label for="comprador_telefono">Teléfono:</label>
        <input type="text" id="comprador_telefono" name="comprador_telefono" 
               value="<?= isset($_POST['comprador_telefono']) ? htmlspecialchars($_POST['comprador_telefono']) : '' ?>" readonly>
    </div>

    <div class="form-group">
        <label for="comprador_email">Email:</label>
        <input type="email" id="comprador_email" name="comprador_email" 
               value="<?= isset($_POST['comprador_email']) ? htmlspecialchars($_POST['comprador_email']) : '' ?>" readonly>
    </div>
    
    <input type="hidden" id="comprador_id" name="comprador_id" 
           value="<?= isset($_POST['comprador_id']) ? htmlspecialchars($_POST['comprador_id']) : '' ?>">
</div>

<!-- Resto del formulario (fecha, tipo de comprobante, etc.) -->
<div class="form-group">
        <label for="fecha_emision">Fecha de Emisión:</label>
        <input type="date" id="fecha_emision" name="fecha_emision" required 
               value="<?= date('Y-m-d') ?>" onchange="actualizarDatosFactura()">
    </div>
    
    <div class="form-group">
        <label for="tipo_comprobante">Tipo de Comprobante:</label>
        <select id="tipo_comprobante" name="tipo_comprobante" required onchange="actualizarDatosFactura()">
            <option value="01">Factura</option>
            <option value="04">Nota de Crédito</option>
            <option value="05">Nota de Débito</option>
        </select>
    </div>

                <h3>Detalle de la Factura</h3>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Cantidad</th>
                            <th>P. Unitario</th>
                            <th>Descuento</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- Filas de productos se añadirán aquí con JavaScript -->
                    </tbody>
                </table>

                <button type="button" class="btn" id="addProductBtn">Añadir Producto</button>

                <div class="total-section">
                    <div>Subtotal 0%: <span id="subtotal0">$0.00</span></div>
                    <div>Subtotal 12%: <span id="subtotal12">$0.00</span></div>
                    <div>IVA 12%: <span id="iva">$0.00</span></div>
                    <div>Total: <span id="total">$0.00</span></div>
                </div>

                <div class="form-group">
                    <label for="observaciones">Datos Adicionales:</label>
                    <textarea id="observaciones" name="observaciones" rows="3"></textarea>
                </div>

                <div style="margin-top: 20px;">
                <button type="submit" name="generar_factura" class="btn">Generar Factura</button>
                </form>
            <div class="form-group">
    <label for="moneda">Moneda:</label>
    <select id="moneda" name="moneda" required>
        <option value="USD">Dólares americanos (USD)</option>
        <option value="EUR">Euros (EUR)</option>
        <option value="LOCAL">Moneda local</option>
    </select>
</div>

<div class="form-group">
    <label for="guia_remision">Guía de Remisión:</label>
    <input type="text" id="guia_remision" name="guia_remision">
</div>

<div class="form-group">
    <label for="comprador_tipo_identificacion">Tipo Identificación Comprador:</label>
    <select id="comprador_tipo_identificacion" name="comprador_tipo_identificacion" required>
        <option value="04">RUC</option>
        <option value="05">Cédula</option>
        <option value="06">Pasaporte</option>
        <option value="07">Consumidor Final</option>
        <option value="08">Identificación del Exterior</option>
    </select>
</div>

<div class="form-group">
    <label for="comprador_direccion">Dirección Comprador:</label>
    <input type="text" id="comprador_direccion" name="comprador_direccion" required>
</div>

<div class="form-group">
    <label for="propina">Propina (10%):</label>
    <input type="number" id="propina" name="propina" min="0" step="0.01" value="0">
</div>

<!-- Sección de formas de pago -->
<h3>Formas de Pago</h3>
<div id="formasPagoContainer">
    <div class="forma-pago">
        <select name="formas_pago[0][codigo]" required>
            <option value="01">Efectivo</option>
            <option value="02">Cheque</option>
            <option value="03">Transferencia</option>
            <option value="04">Tarjeta de Crédito</option>
        </select>
        <input type="text" name="formas_pago[0][descripcion]" placeholder="Descripción">
        <input type="number" name="formas_pago[0][valor]" min="0" step="0.01" placeholder="Valor" required>
        <input type="number" name="formas_pago[0][plazo]" min="0" placeholder="Plazo (días)">
        <button type="button" class="btn btn-danger" onclick="removeFormaPago(this)">Eliminar</button>
    </div>
</div>
<button type="button" class="btn" onclick="addFormaPago()">Agregar Forma de Pago</button>

<!-- Sección de datos adicionales -->
<h3>Datos Adicionales</h3>
<div id="datosAdicionalesContainer">
    <div class="dato-adicional">
        <input type="text" name="datos_adicionales[0][nombre]" placeholder="Nombre" required>
        <textarea name="datos_adicionales[0][descripcion]" placeholder="Descripción"></textarea>
        <button type="button" class="btn btn-danger" onclick="removeDatoAdicional(this)">Eliminar</button>
    </div>
</div>
<button type="button" class="btn" onclick="addDatoAdicional()">Agregar Dato Adicional</button> 
            <!-- Modal para seleccionar producto -->
            <div id="productModal">
                <div class="modal-content">
                    <h3>Seleccionar Producto</h3>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Precio</th>
                                <th>IVA</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?= htmlspecialchars($producto['codigo']) ?></td>
                                    <td><?= htmlspecialchars($producto['descripcion']) ?></td>
                                    <td>$<?= number_format($producto['precio'], 2) ?></td>
                                    <td><?= $producto['iva'] == 1 ? '12%' : '0%' ?></td>
                                    <td><button type="button" onclick="selectProduct(<?= htmlspecialchars(json_encode($producto)) ?>" class="btn">Seleccionar</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" onclick="document.getElementById('productModal').style.display = 'none'" class="btn btn-danger" style="margin-top: 15px;">Cancelar</button>
                    </div>
        <?php endif; ?>
    </div>
</div>
<script>
    function actualizarDatosFactura() {
    const empresa = {
        codigo_establecimiento: '<?= $empresa_seleccionada["codigo_establecimiento"] ?>',
        codigo_punto_emision: '<?= $empresa_seleccionada["codigo_punto_emision"] ?>',
        ruc: '<?= $empresa_seleccionada["ruc"] ?>',
        tipo_ambiente: '<?= $empresa_seleccionada["tipo_ambiente"] ?>'
    };
    
    const secuencial = document.getElementById('numero_secuencial').value;
    const fechaEmision = document.getElementById('fecha_emision').value;
    const tipoComprobante = document.getElementById('tipo_comprobante').value;
    
    // Actualizar número de factura visible (formato 001-001-000000123)
    const numeroFactura = 
        empresa.codigo_establecimiento.padStart(3, '0') + '-' +
        empresa.codigo_punto_emision.padStart(3, '0') + '-' +
        secuencial.padStart(9, '0');
    
    document.getElementById('numero_factura_texto').textContent = numeroFactura;
    
    // Generar clave de acceso (versión simplificada en JavaScript)
    const fechaFormateada = fechaEmision.split('-').reverse().join(''); // DDMMAAAA
    const ambiente = empresa.tipo_ambiente === 'PRODUCCION' ? '2' : '1';
    const serie = empresa.codigo_establecimiento + empresa.codigo_punto_emision;
    const secuencialFormateado = secuencial.padStart(9, '0');
    
    // Parte fija (esto es una simplificación)
    const codigoNumerico = '12345678';
    const tipoEmision = '1';
    
    // Construir clave sin dígito verificador
    const claveSinDV = fechaFormateada + tipoComprobante + empresa.ruc + ambiente + 
                      serie + secuencialFormateado + codigoNumerico + tipoEmision;
    
    // Calcular dígito verificador (versión simplificada)
    const factores = [7,6,5,4,3,2,7,6,5,4,3,2,7,6,5,4,3,2,7,6,5,4,3,2,7,6,5,4,3,2,7,6,5,4,3,2,7,6,5,4,3,2];
    let suma = 0;
    
    for (let i = 0; i < claveSinDV.length; i++) {
        suma += parseInt(claveSinDV[i]) * factores[i];
    }
    
    let digito = 11 - (suma % 11);
    if (digito === 11) digito = 0;
    if (digito === 10) digito = 1;
    
    const claveAcceso = claveSinDV + digito;
    
    // Actualizar en la vista y en el campo oculto
    document.getElementById('clave_acceso_texto').textContent = claveAcceso;
    document.getElementById('clave_acceso').value = claveAcceso;
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    actualizarDatosFactura();
});
// Variables globales
let productos = <?= json_encode($productos, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let items = [];
let formaPagoCount = 1;
let datoAdicionalCount = 1;

// Esperar a que el DOM esté completamente cargado
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar el formulario
    renderItems();
    
    // Configurar event listeners solo si los elementos existen
    const addProductBtn = document.getElementById('addProductBtn');
    if (addProductBtn) {
        addProductBtn.addEventListener('click', function() {
            const productModal = document.getElementById('productModal');
            if (productModal) {
                productModal.style.display = 'flex';
            }
        });
    }


    
    // Configurar botón de cerrar modal si existe
    const closeModalBtn = document.querySelector('#productModal .btn-danger');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            const productModal = document.getElementById('productModal');
            if (productModal) {
                productModal.style.display = 'none';
            }
        });
    }
});

function buscarCliente() {
    const tipo = document.getElementById('comprador_tipo').value;
    const identificacion = document.getElementById('comprador_identificacion').value.trim();
    
    if (!identificacion) {
        alert('Por favor ingrese un número de identificación');
        return;
    }

    // Mostrar indicador de carga
    const buscarBtn = document.querySelector('.busqueda-container .btn');
    buscarBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
    buscarBtn.disabled = true;

    // Configurar los datos a enviar
    const datos = {
        tipo_identificacion: tipo,
        identificacion: identificacion,
        empresa_id: <?= $empresa_seleccionada['id'] ?? 0 ?>
    };

    // Realizar la llamada AJAX
    fetch('buscar_cliente.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(datos)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        if (data.error) {
            throw new Error(data.error);
        }
        
        if (data.encontrado) {
            document.getElementById('comprador_nombre').value = data.cliente.nombre;
            document.getElementById('comprador_direccion').value = data.cliente.direccion;
            document.getElementById('comprador_telefono').value = data.cliente.telefono || '';
            document.getElementById('comprador_email').value = data.cliente.email || '';
            document.getElementById('comprador_id').value = data.cliente.id;
            // Puedes agregar más campos si es necesario
        } else {
            
            alert('Cliente no encontrado. ¿Desea crear uno nuevo?');
            ['nombre', 'direccion', 'telefono', 'email'].forEach(campo => {
        document.getElementById(`comprador_${campo}`).value = '';
    });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al buscar cliente: ' + error.message);
    })
    .finally(() => {
        // Restaurar el botón a su estado original
        buscarBtn.innerHTML = '<i class="fas fa-search"></i> Buscar';
        buscarBtn.disabled = false;
    });
}

// Función corregida para seleccionar producto
function selectProduct(producto) {
    try {
        // Verificar si el producto es un string JSON y parsearlo
        if (typeof producto === 'string') {
            try {
                producto = JSON.parse(producto.replace(/&quot;/g, '"'));
            } catch (e) {
                console.error('Error parsing product:', e);
                return;
            }
        }

        // Verificar si el producto ya está en la lista
        const existe = items.some(item => item.producto_id == producto.id);
        
        if (!existe) {
            items.push({
                producto_id: producto.id,
                codigo: producto.codigo,
                descripcion: producto.descripcion,
                cantidad: 1,
                precio: parseFloat(producto.precio),
                iva: producto.iva ? 1 : 0, // Convertir a 1 o 0 para la base de datos
                ice: parseFloat(producto.ice) || 0,
                irbpnr: parseFloat(producto.irbpnr) || 0,
                descuento: 0
            });
            renderItems();
            
            // Cerrar el modal
            const productModal = document.getElementById('productModal');
            if (productModal) {
                productModal.style.display = 'none';
            }
        } else {
            alert('Este producto ya fue agregado a la factura');
        }
    } catch (error) {
        console.error('Error al agregar producto:', error, 'Producto:', producto);
        alert('Ocurrió un error al agregar el producto: ' + error.message);
    }
}

// Función para renderizar los items en la tabla
function renderItems() {
    const tbody = document.getElementById('itemsBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    let subtotal0 = 0;
    let subtotal12 = 0;
    let iva = 0;
    
    items.forEach((item, index) => {
        const subtotalItem = item.cantidad * item.precio * (1 - item.descuento/100);
        
        if (item.iva == 1) {
            subtotal12 += subtotalItem;
            iva += subtotalItem * 0.12;
        } else {
            subtotal0 += subtotalItem;
        }
        
        const row = document.createElement('tr');
        row.innerHTML = `
    <td>${item.codigo}</td>
    <td>${item.descripcion}</td>
            <td><input type="number" name="items[${index}][cantidad]" value="${item.cantidad}" min="1" step="1" onchange="updateItem(${index}, 'cantidad', this.value)"></td>
            <td><input type="number" name="items[${index}][precio]" value="${item.precio.toFixed(2)}" min="0" step="0.01" onchange="updateItem(${index}, 'precio', this.value)"></td>
            <td><input type="number" name="items[${index}][descuento]" value="${item.descuento}" min="0" max="100" step="1" onchange="updateItem(${index}, 'descuento', this.value)">%</td>
            <td>$${subtotalItem.toFixed(2)}</td>
            <td><button type="button" onclick="removeItem(${index})" class="btn btn-danger">Eliminar</button></td>
            <input type="hidden" name="items[${index}][producto_id]" value="${item.producto_id}">
            <input type="hidden" name="items[${index}][codigo]" value="${item.codigo}">
            <input type="hidden" name="items[${index}][descripcion]" value="${item.descripcion}">
            <input type="hidden" name="items[${index}][iva]" value="${item.iva}">
            <input type="hidden" name="items[${index}][ice]" value="${item.ice}">
            <input type="hidden" name="items[${index}][irbpnr]" value="${item.irbpnr}">
        `;
        tbody.appendChild(row);
    });
    
    // Actualizar totales de manera segura
    const updateElement = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    
    updateElement('subtotal0', `$${subtotal0.toFixed(2)}`);
    updateElement('subtotal12', `$${subtotal12.toFixed(2)}`);
    updateElement('iva', `$${iva.toFixed(2)}`);
    updateElement('total', `$${(subtotal0 + subtotal12 + iva).toFixed(2)}`);
}

// Función para actualizar un item
function updateItem(index, field, value) {
    if (!items[index]) return;
    
    if (field === 'cantidad') {
        items[index][field] = parseInt(value) || 1;
    } else if (field === 'precio' || field === 'descuento') {
        items[index][field] = parseFloat(value) || 0;
    }
    renderItems();
}

// Función para eliminar un item
function removeItem(index) {
    if (confirm('¿Está seguro de eliminar este producto de la factura?')) {
        items.splice(index, 1);
        renderItems();
    }
}

function addFormaPago() {
    const container = document.getElementById('formasPagoContainer');
    if (!container) return;
    
    const newForma = document.createElement('div');
    newForma.className = 'forma-pago';
    newForma.innerHTML = `
        <select name="formas_pago[${formaPagoCount}][codigo]" required>
            <option value="01">Efectivo</option>
            <option value="02">Cheque</option>
            <option value="03">Transferencia</option>
            <option value="04">Tarjeta de Crédito</option>
        </select>
        <input type="text" name="formas_pago[${formaPagoCount}][descripcion]" placeholder="Descripción">
        <input type="number" name="formas_pago[${formaPagoCount}][valor]" min="0" step="0.01" placeholder="Valor" required>
        <input type="number" name="formas_pago[${formaPagoCount}][plazo]" min="0" placeholder="Plazo (días)">
        <button type="button" class="btn btn-danger" onclick="removeFormaPago(this)">Eliminar</button>
    `;
    container.appendChild(newForma);
    formaPagoCount++;
}

function removeFormaPago(button) {
    if (!button || !button.parentElement) return;
    
    if (document.querySelectorAll('.forma-pago').length > 1) {
        button.parentElement.remove();
    } else {
        alert('Debe haber al menos una forma de pago');
    }
}

function addDatoAdicional() {
    const container = document.getElementById('datosAdicionalesContainer');
    if (!container) return;
    
    const newDato = document.createElement('div');
    newDato.className = 'dato-adicional';
    newDato.innerHTML = `
        <input type="text" name="datos_adicionales[${datoAdicionalCount}][nombre]" placeholder="Nombre" required>
        <textarea name="datos_adicionales[${datoAdicionalCount}][descripcion]" placeholder="Descripción"></textarea>
        <button type="button" class="btn btn-danger" onclick="removeDatoAdicional(this)">Eliminar</button>
    `;
    container.appendChild(newDato);
    datoAdicionalCount++;
}

function removeDatoAdicional(button) {
    if (button && button.parentElement) {
        button.parentElement.remove();
    }
}
// Función para actualizar la clave de acceso
function actualizarClaveAcceso() {
    const empresaSeleccionada = <?= $empresa_seleccionada ? json_encode($empresa_seleccionada) : 'null' ?>;
    if (!empresaSeleccionada) return;
    
    // Obtener valores del formulario
    const fechaEmision = document.getElementById('fecha_emision').value;
    const tipoComprobante = document.getElementById('tipo_comprobante').value;
    const numeroSecuencial = document.getElementById('numero_secuencial').value;
    
    // Construir serie (establecimiento + punto emisión)
    const serie = empresaSeleccionada.codigo_establecimiento + empresaSeleccionada.codigo_punto_emision;
    
    // Generar número de factura visible (formato 001-002-000000123)
    const numeroFactura = `${empresaSeleccionada.codigo_establecimiento.padStart(3, '0')}-${empresaSeleccionada.codigo_punto_emision.padStart(3, '0')}-${numeroSecuencial.padStart(9, '0')}`;
    
    // Actualizar el número de factura visible
    const infoFactura = document.querySelector('.empresa-info p:nth-child(3)');
    if (infoFactura) {
        infoFactura.textContent = `N° Factura: ${numeroFactura}`;
    }
    
    // Generar la clave de acceso (llamando a una función PHP desde JavaScript)
    const claveAcceso = generarClaveAccesoPHP(
        fechaEmision, 
        tipoComprobante, 
        empresaSeleccionada.ruc,
        empresaSeleccionada.tipo_ambiente === 'PRODUCCION' ? '2' : '1',
        serie,
        numeroSecuencial
    );
    
    // Actualizar el campo oculto
    document.getElementById('clave_acceso').value = claveAcceso;
    
    console.log('Clave de acceso generada:', claveAcceso);
}

// Esta función simula la generación de la clave de acceso que haría PHP
// En realidad deberías pre-generarla en PHP o implementar el algoritmo completo en JS
function generarClaveAccesoPHP(fecha, tipoComp, ruc, ambiente, serie, secuencial) {
    // Esta es una implementación simplificada del algoritmo en JavaScript
    // En producción, deberías usar el mismo algoritmo que en PHP
    
    // Formatear fecha DDMMAAAA
    const fechaObj = new Date(fecha);
    const dia = String(fechaObj.getDate()).padStart(2, '0');
    const mes = String(fechaObj.getMonth() + 1).padStart(2, '0');
    const año = fechaObj.getFullYear();
    const fechaFormateada = dia + mes + año;
    
    // Construir clave sin dígito verificador
    const claveSinDV = fechaFormateada + tipoComp + ruc + ambiente + serie + String(secuencial).padStart(9, '0') + '12345678' + '1';
    
    // Calcular dígito verificador (implementación simplificada)
    const factores = [7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    let suma = 0;
    
    for (let i = 0; i < claveSinDV.length; i++) {
        suma += parseInt(claveSinDV[i]) * factores[i];
    }
    
    let modulo = suma % 11;
    let digito = 11 - modulo;
    
    if (digito == 11) digito = 0;
    if (digito == 10) digito = 1;
    
    return claveSinDV + digito;
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    actualizarClaveAcceso();
});

</script>

</body>
</html>