<?php
session_start();
require_once './php/config.php';

// Verificar sesión y redireccionar si no está logueado
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['error_message'] = 'Debe iniciar sesión primero';
    header('Location: ../index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Procesar eliminación de empresa si se envió el parámetro
if (isset($_GET['eliminar'])) {
    $id = filter_input(INPUT_GET, 'eliminar', FILTER_VALIDATE_INT);
    
    if ($id) {
        try {
            $pdo = conectarDB();
            // Verificar que la empresa pertenece al usuario antes de eliminar
            $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = 'Empresa eliminada correctamente';
            } else {
                $_SESSION['error_message'] = 'No se pudo eliminar la empresa o no tienes permisos';
            }
            header('Location: menu_principal.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error al eliminar empresa: " . $e->getMessage();
        }
    }
}

// Obtener solo las empresas del usuario logueado
try {
    $pdo = conectarDB();
    $sql = "SELECT id, ruc, razon_social, nombre_comercial, tipo_ambiente 
            FROM empresas 
            WHERE usuario_id = ?
            ORDER BY razon_social";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error al obtener empresas: " . $e->getMessage();
    $empresas = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/style_list_empre.css">
    <title>Gestión de Empresas</title>
    
</head>
<body>
    <div class="container">
        <a href="menu_principal.php" class="btn btn-primary back-btn">← Volver al Menú Principal</a>
        
        <h1>Mis Empresas</h1>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error_message']); ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success_message']); ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <a href="nueva_empresa.php" class="btn btn-success">+ Agregar Nueva Empresa</a>
        
        <div class="table-container">
            <?php if(empty($empresas)): ?>
                <p>No tienes empresas registradas.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>RUC</th>
                            <th>Razón Social</th>
                            <th>Nombre Comercial</th>
                            <th>Ambiente</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empresas as $empresa): ?>
                            <tr class="<?= $empresa['tipo_ambiente'] === 'PRODUCCION' ? 'ambiente-produccion' : 'ambiente-pruebas' ?>">
                                <td><?= htmlspecialchars($empresa['ruc']) ?></td>
                                <td><?= htmlspecialchars($empresa['razon_social']) ?></td>
                                <td><?= htmlspecialchars($empresa['nombre_comercial']) ?></td>
                                <td><?= htmlspecialchars($empresa['tipo_ambiente']) ?></td>
                                <td class="actions">
                                    <a href="ver_empresa.php?id=<?= $empresa['id'] ?>" class="btn btn-primary">Ver</a>
                                    <a href="editar_empresa.php?id=<?= $empresa['id'] ?>" class="btn btn-success">Editar</a>
                                    <a href="listar_empresas.php?eliminar=<?= $empresa['id'] ?>" class="btn btn-danger" 
                                       onclick="return confirm('¿Está seguro de eliminar esta empresa?')">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>