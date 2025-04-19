<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Verificar que el usuario_id está disponible
if (!isset($_SESSION['usuario']['id'])) {
    $_SESSION['error_message'] = 'Información de usuario incompleta';
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario']['id'];

// Obtener listado de empresas del usuario actual
require_once './php/config.php';
$empresas = [];
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
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú Principal</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
            background-image: url('./images/fondo_de_pantalla.jpeg');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .contenedor-texto {
            background: rgba(0, 0, 0, 0.75);
            padding: 30px 50px;
            border-radius: 15px;
            color: white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            margin-bottom: 20px;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            width: 50%;
            max-width: 600px;
        }

        .empresas-container {
            background: rgba(0, 0, 0, 0.75);
            padding: 20px;
            border-radius: 15px;
            color: white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            margin-bottom: 30px;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            width: 80%;
            max-width: 900px;
        }

        .empresas-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .empresas-table th, .empresas-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .empresas-table th {
            background-color: rgba(52, 152, 219, 0.3);
            color: #fff;
            font-weight: 600;
        }

        .empresas-table tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .ambiente-produccion {
            color: #ff6b6b;
        }

        .ambiente-pruebas {
            color: #4ecdc4;
        }

        .actions a {
            color: #3498db;
            text-decoration: none;
            margin: 0 0px;
            transition: color 0.3s;
        }

        .actions a:hover {
            color: #4ecdc4;
            text-decoration: underline;
        }

        .user-role {
            font-size: 16px;
            color: #4ecdc4;
            margin: 8px 0 0;
            font-weight: 600;
        }

        .last-login {
            font-size: 14px;
            color: #aaa;
            margin: 10px 0 0;
            font-style: italic;
        }
        
        h2 {
            margin: 5px;
            font-size: 2rem;
            margin-bottom: 8px;
            color: #fff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            font-weight: 600;
        }

        h3 {
            margin: 5px;
            font-size: 1.4rem;
            margin-bottom: 5px;
            color: #eee;
            font-weight: 400;
        }

        .menu-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 25px;
            max-width: 900px;
            margin-bottom: 30px;
        }

        .menu-item {
            padding: 18px 35px;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white !important;
            text-decoration: none;
            font-size: 18px;
            font-weight: bold;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            min-width: 200px;
            border: none;
            cursor: pointer;
        }

        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 7px 20px rgba(0, 0, 0, 0.3);
            background: linear-gradient(135deg, #2980b9, #1a252f);
        }

        .btn-logout {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white !important;
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #c0392b, #992d22);
            transform: translateY(-5px);
        }

        @media (max-width: 768px) {
            .menu-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .contenedor-texto, .empresas-container {
                padding: 20px;
                width: 90%;
            }
            
            .empresas-table {
                display: block;
                overflow-x: auto;
            }
        }
        </style>
</head>
<body>

<div class="contenedor-texto">
    <h2>Bienvenido, <?php 
        if(isset($_SESSION['usuario']['nombre_completo'])) {
            echo htmlspecialchars($_SESSION['usuario']['nombre_completo']);
        } 
        elseif(isset($_SESSION['usuario']['correo'])) {
            echo htmlspecialchars($_SESSION['usuario']['correo']);
        }
        else {
            echo 'Invitado';
        }
    ?></h2>
    <h3>Seleccione una opción</h3>
    <?php if(isset($_SESSION['usuario']['rol'])): ?>
        <p class="user-role">Rol: <?php echo htmlspecialchars(ucfirst($_SESSION['usuario']['rol'])); ?></p>
    <?php endif; ?>
</div>

<div class="menu-container">
    <a href="rol_de_pagos.php" class="menu-item">Rol de Pagos</a>
    <!-- Enlace directo a facturación sin parámetros -->
    <a href="facturacion.php" class="menu-item">Facturación</a>
    <a href="./php/cerrar_sesion.php" class="menu-item btn-logout">Cerrar Sesión</a>
</div>

<div class="empresas-container">
    <h3>Empresas</h3> <!-- Cambiado de "Empresas Registradas" a "Mis Empresas" -->
    <?php if(empty($empresas)): ?>
        <p>No tienes empresas registradas.</p> <!-- Mensaje más personalizado -->
    <?php else: ?>
        <table class="empresas-table">
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
                    <tr>
                        <td><?= htmlspecialchars($empresa['ruc']) ?></td>
                        <td><?= htmlspecialchars($empresa['razon_social']) ?></td>
                        <td><?= htmlspecialchars($empresa['nombre_comercial']) ?></td>
                        <td class="<?= $empresa['tipo_ambiente'] === 'PRODUCCION' ? 'ambiente-produccion' : 'ambiente-pruebas' ?>">
                            <?= htmlspecialchars($empresa['tipo_ambiente']) ?>
                        </td>
                        <td class="actions">
                            <a href="ver_empresa.php?id=<?= $empresa['id'] ?>">Ver</a>
                            <a href="editar_empresa.php?id=<?= $empresa['id'] ?>">Editar</a>
                            <a href="eliminar_empresa.php?id=<?= $empresa['id'] ?>" onclick="return confirm('¿Está seguro de eliminar esta empresa?')">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div style="margin-top: 20px;">
        <a href="nueva_empresa.php" class="menu-item" style="display: inline-block; padding: 10px 20px; font-size: 16px;">
            Agregar nueva empresa
        </a>
    </div>
</div>

</body>
</html>