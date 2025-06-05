<?php
require_once("config/conexion.php");

// ✅ Verifica si un mensaje ya fue procesado
function mensajeYaProcesado($id) {
    $archivo = 'mensajes_procesados.txt';
    if (!file_exists($archivo)) return false;
    $procesados = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($id, $procesados);
}

// ✅ Marca un mensaje como procesado
function marcarMensajeComoProcesado($id) {
    file_put_contents('mensajes_procesados.txt', $id . PHP_EOL, FILE_APPEND);
}

// ✅ Mantiene el archivo de mensajes limpios
function limpiarMensajesProcesados($max = 5000) {
    $archivo = 'mensajes_procesados.txt';
    if (!file_exists($archivo)) return;

    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lineas) > $max) {
        $lineasRecortadas = array_slice($lineas, -$max);
        file_put_contents($archivo, implode(PHP_EOL, $lineasRecortadas) . PHP_EOL);
    }
}

// ✅ Enviar mensaje a WhatsApp
function EnviarMensajeWhatsApp($respuesta, $numero) {
    if (!$respuesta) return;

    if (is_string($respuesta)) {
        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $numero,
            "type" => "text",
            "text" => [
                "preview_url" => false,
                "body" => $respuesta
            ]
        ];
    } elseif (is_array($respuesta)) {
        $respuesta['to'] = $numero;
        $respuesta['messaging_product'] = "whatsapp";
        $respuesta['recipient_type'] = "individual";
        $data = $respuesta;
    } else {
        file_put_contents("error_log.txt", "[".date("Y-m-d H:i:s")."] Tipo de respuesta desconocido".PHP_EOL, FILE_APPEND);
        return;
    }

    file_put_contents("debug_whatsapp.json", json_encode($data, JSON_PRETTY_PRINT));

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/json\r\nAuthorization: Bearer " . WHATSAPP_TOKEN . "\r\n",
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents(WHATSAPP_URL, false, $context);

    file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] Mensaje enviado a $numero: " . print_r($data, true) . PHP_EOL, FILE_APPEND);
}