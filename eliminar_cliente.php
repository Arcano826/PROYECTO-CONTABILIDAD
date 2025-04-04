<?php
session_start();
require_once './php/config.php';

$cliente_id = $_GET['id'] ?? '';
$empresa_id = $_GET['empresa_id'] ?? '';

if (empty($cliente_id) || empty($empresa_id)) {
    $_SESSION['error_message'] = 'Datos incompletos para eliminar el cliente';
    header("Location: facturacion.php?empresa_id=$empresa_id");
    exit;
}

try {
    $pdo = conectarDB();
    
    // Verificar que el cliente pertenece a la empresa
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$cliente_id, $empresa_id]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error_message'] = 'Cliente no encontrado o no pertenece a esta empresa';
    } else {
        // Eliminar el cliente
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        
        $_SESSION['success_message'] = 'Cliente eliminado correctamente';
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error al eliminar cliente: " . $e->getMessage();
}

header("Location: facturacion.php?empresa_id=$empresa_id");
exit;
?>