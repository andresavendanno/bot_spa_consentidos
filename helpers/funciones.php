<?php
require_once("config/conexion.php");
require_once("config/constantes.php");

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
    file_put_contents("log.txt", "[FUNCIONES][DEBUG] Entrando a EnviarMensajeWhatsApp()\n", FILE_APPEND);
    file_put_contents("log.txt", "[FUNCIONES][DEBUG] Tipo de respuesta: " . gettype($respuesta) . "\n", FILE_APPEND);
    file_put_contents("log.txt", "[FUNCIONES][DEBUG] Contenido de respuesta: " . print_r($respuesta, true) . "\n", FILE_APPEND);

    if (!$respuesta) {
        file_put_contents("log.txt", "[FUNCIONES][DEBUG] Respuesta vacía, no se enviará nada.\n", FILE_APPEND);
        return;
    }

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
        file_put_contents("error_log.txt", "[FUNCIONES][".date("Y-m-d H:i:s")."] Tipo de respuesta desconocido\n", FILE_APPEND);
        return;
    }

    $jsonData = json_encode($data);
    file_put_contents("debug_whatsapp.json", $jsonData);

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/json\r\nAuthorization: Bearer " . WHATSAPP_TOKEN . "\r\n",
            'content' => $jsonData,
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents(WHATSAPP_URL, false, $context);

    file_put_contents("log.txt", "[FUNCIONES][DEBUG] Respuesta de WhatsApp API: $response\n", FILE_APPEND);
    file_put_contents("log.txt", "[FUNCIONES][" . date("Y-m-d H:i:s") . "] Mensaje enviado a $numero: " . print_r($data, true) . PHP_EOL, FILE_APPEND);

    if ($http_response_header) {
        file_put_contents("log.txt", "[FUNCIONES][DEBUG] Encabezados HTTP: " . print_r($http_response_header, true) . "\n", FILE_APPEND);
    }
}