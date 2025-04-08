<?php
session_start();
require_once './php/config.php';

// Verificar si hay datos de cliente en sesión
if (!isset($_SESSION['nuevo_cliente'])) {
    die("No hay datos de cliente para mostrar");
}

$cliente = $_SESSION['nuevo_cliente'];
$empresa_id = $_GET['empresa_id'] ?? '';

// Obtener datos de la empresa
try {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener datos de la empresa: " . $e->getMessage());
}

// Aquí deberías también obtener los productos de la factura desde tu base de datos
// Esto es un ejemplo, ajusta según tu estructura real
$productos = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM factura_detalle WHERE factura_id = ?");
    $stmt->execute([$factura_id]); // Necesitarías tener el ID de la factura
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Puedes manejar el error o dejar el array vacío
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .factura-container { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .empresa-info, .cliente-info { width: 48%; }
        h1 { color: #333; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total { text-align: right; font-weight: bold; font-size: 1.2em; }
        .fecha { text-align: right; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="factura-container">
        <div class="header">
            <div class="empresa-info">
                <h2><?= htmlspecialchars($empresa['razon_social']) ?></h2>
                <p><?= htmlspecialchars($empresa['direccion']) ?></p>
                <p>Tel: <?= htmlspecialchars($empresa['telefono']) ?></p>
                <p>RUC: <?= htmlspecialchars($empresa['ruc']) ?></p>
            </div>
            
            <div class="cliente-info">
                <h3>Factura a:</h3>
                <p><?= htmlspecialchars($cliente['comprador_nombre']) ?></p>
                <p><?= htmlspecialchars($cliente['comprador_direccion']) ?></p>
                <p>Tel: <?= htmlspecialchars($cliente['comprador_telefono']) ?></p>
                <p>ID: <?= htmlspecialchars($cliente['comprador_identificacion']) ?></p>
            </div>
        </div>
        
        <div class="fecha">
            <p>Fecha: <?= date('d/m/Y') ?></p>
            <p>Factura #: <?= str_pad($factura_id, 8, '0', STR_PAD_LEFT) ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>P. Unitario</th>
                    <th>Descuento</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $producto): ?>
                <tr>
                    <td><?= htmlspecialchars($producto['codigo']) ?></td>
                    <td><?= htmlspecialchars($producto['descripcion']) ?></td>
                    <td><?= htmlspecialchars($producto['cantidad']) ?></td>
                    <td>$<?= number_format($producto['precio_unitario'], 2) ?></td>
                    <td>$<?= number_format($producto['descuento'], 2) ?></td>
                    <td>$<?= number_format($producto['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total">
            <p>SUBTOTAL: $<?= number_format($subtotal, 2) ?></p>
            <p>IVA (12%): $<?= number_format($iva, 2) ?></p>
            <p>TOTAL: $<?= number_format($total, 2) ?></p>
        </div>
    </div>
</body>
</html>