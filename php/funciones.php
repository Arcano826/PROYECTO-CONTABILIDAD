<?php
function generarClaveAcceso($empresa) {
    // Formato de fecha actual: DDMMAAAA
    $fecha = date('dmY');
    
    // Tipo de comprobante (01 para factura)
    $tipoComprobante = '01';
    
    // RUC de la empresa
    $ruc = $empresa['ruc'];
    
    // Tipo de ambiente (1=Pruebas, 2=Producción)
    $ambiente = $empresa['tipo_ambiente'] == 'PRUEBAS' ? '1' : '2';
    
    // Serie (establecimiento(3) + punto emisión(3) + secuencial(9))
    $serie = $empresa['codigo_establecimiento'] . 
             $empresa['codigo_punto_emision'] . 
             str_pad($empresa['ultimo_secuencial'] + 1, 9, '0', STR_PAD_LEFT);
    
    // Código numérico (puede ser aleatorio o incremental)
    $codigoNumerico = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
    
    // Tipo de emisión (1=Normal)
    $tipoEmision = '1';
    
    // Dígito verificador (se calcula con módulo 11)
    $claveSinDigito = $fecha . $tipoComprobante . $ruc . $ambiente . $serie . 
                     $codigoNumerico . $tipoEmision;
    
    $digitoVerificador = calcularDigitoVerificador($claveSinDigito);
    
    return $claveSinDigito . $digitoVerificador;
}

function calcularDigitoVerificador($claveSinDigito) {
    $factores = [7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    
    $suma = 0;
    for ($i = 0; $i < strlen($claveSinDigito); $i++) {
        $suma += $claveSinDigito[$i] * $factores[$i];
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
?>