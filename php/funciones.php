<?php
function generarClaveAcceso($fechaEmision, $tipoComprobante, $ruc, $ambiente, $serie, $secuencial, $tipoEmision = '1') {
    // Formatear fecha DDMMAAAA
    $fecha = date('dmY', strtotime($fechaEmision));
    
    // Construir clave sin dígito verificador (43 caracteres)
    $claveSinDV = $fecha . $tipoComprobante . $ruc . $ambiente . $serie . str_pad($secuencial, 9, '0', STR_PAD_LEFT) . '12345678' . $tipoEmision;
    
    // Calcular dígito verificador
    $digitoVerificador = calcularDigitoVerificador($claveSinDV);
    
    return $claveSinDV . $digitoVerificador;
}

function calcularDigitoVerificador($claveSinDV) {
    $factores = [7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    
    for ($i = 0; $i < strlen($claveSinDV); $i++) {
        $suma += $claveSinDV[$i] * $factores[$i];
    }
    
    $modulo = $suma % 11;
    $digito = 11 - $modulo;
    
    if ($digito == 11) return 0;
    if ($digito == 10) return 1;
    return $digito;
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