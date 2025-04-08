<?php
function generarClaveAcceso($fechaEmision, $tipoComprobante, $ruc, $ambiente, $estabPtoEmision, $secuencial, $codigoNumerico = '12345678') {
    // Formatear fecha (DDMMAAAA)
    $fecha = str_replace('-', '', $fechaEmision);
    $fecha = substr($fecha, 6, 2) . substr($fecha, 4, 2) . substr($fecha, 0, 4);
    
    // Asegurar que los valores tengan el formato correcto
    $tipoComprobante = str_pad($tipoComprobante, 2, '0', STR_PAD_LEFT);
    $ruc = str_pad($ruc, 13, '0', STR_PAD_LEFT);
    $estabPtoEmision = str_pad($estabPtoEmision, 6, '0', STR_PAD_LEFT);
    $secuencial = str_pad($secuencial, 9, '0', STR_PAD_LEFT);
    $codigoNumerico = str_pad($codigoNumerico, 8, '0', STR_PAD_LEFT);
    
    // Construir clave de acceso
    $clave = $fecha . $tipoComprobante . $ruc . $ambiente . $estabPtoEmision . $secuencial . $codigoNumerico . '1';
    
    // Calcular dígito verificador
    $digitoVerificador = calcularDigitoVerificador($clave);
    
    return $clave . $digitoVerificador;
}

function calcularDigitoVerificador($clave) {
    $factores = [7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    
    $suma = 0;
    for ($i = 0; $i < strlen($clave); $i++) {
        $suma += $clave[$i] * $factores[$i];
    }
    
    $modulo = $suma % 11;
    $digito = 11 - $modulo;
    
    if ($digito == 11) {
        return '0';
    } elseif ($digito == 10) {
        return '1';
    } else {
        return (string)$digito;
    }
}

function calcularSubtotal0($items) {
    $subtotal = 0;
    foreach ($items as $item) {
        if (empty($item['iva']) || $item['iva'] == 0) {
            $subtotal += $item['cantidad'] * $item['precio'] * (1 - ($item['descuento'] / 100));
        }
    }
    return $subtotal;
}

function calcularSubtotal12($items) {
    $subtotal = 0;
    foreach ($items as $item) {
        if (!empty($item['iva']) && $item['iva'] != 0) {
            $subtotal += $item['cantidad'] * $item['precio'] * (1 - ($item['descuento'] / 100));
        }
    }
    return $subtotal;
}

function calcularIVA($items) {
    $iva = 0;
    foreach ($items as $item) {
        if (!empty($item['iva']) && $item['iva'] != 0) {
            $subtotal = $item['cantidad'] * $item['precio'] * (1 - ($item['descuento'] / 100));
            $iva += $subtotal * 0.12;
        }
    }
    return $iva;
}