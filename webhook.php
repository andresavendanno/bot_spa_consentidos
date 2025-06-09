<?php
require_once("config/constantes.php");
require_once("helpers/mensajes.php");

//file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook] Método: " . $_SERVER['REQUEST_METHOD'] . PHP_EOL, FILE_APPEND); //entró a webhook

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(200);
    echo "EVENT_RECEIVED";
    flush();

    $input = file_get_contents('php://input');
    //file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook] Cuerpo POST recibido: " . $input . PHP_EOL, FILE_APPEND); // este me dice que me esta enviando whatsapp

    $data = json_decode($input, true);
    file_put_contents("log.txt", " [Webhook]  JSON decodificado correctamente\n", FILE_APPEND);
    //file_put_contents("log.txt", "[Webhook] Payload recibido: " . json_encode($data) . "\n", FILE_APPEND); mensaje Json que mandamos al usuario

    // ✅ Filtro para evitar procesar actualizaciones de estado (sent, delivered, read, etc.)
    $hasMessages = isset($data['entry'][0]['changes'][0]['value']['messages']);

    if (!$hasMessages) {
        //file_put_contents("log.txt", "[WEBHOOK][DEBUG] ⚠️ Payload sin mensajes. Probablemente es un status.\n", FILE_APPEND);
        return;
    }
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents("error_log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook][ERROR] JSON malformado: " . json_last_error_msg() . PHP_EOL, FILE_APPEND);
    } else {
        //file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook] JSON decodificado correctamente" . PHP_EOL, FILE_APPEND);
        recibirMensajes($data);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    //file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook] Petición GET recibida" . PHP_EOL, FILE_APPEND);

    if (
        isset($_GET['hub_mode']) &&
        isset($_GET['hub_verify_token']) &&
        isset($_GET['hub_challenge']) &&
        $_GET['hub_mode'] === 'subscribe' &&
        $_GET['hub_verify_token'] === TOKEN_SPACONSENTIDOS
    ) {
        file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook] Verificación exitosa. Respondido con challenge" . PHP_EOL, FILE_APPEND);
        echo $_GET['hub_challenge'];
    } else {
        http_response_code(403);
        file_put_contents("error_log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook][ERROR] Verificación fallida o token incorrecto" . PHP_EOL, FILE_APPEND);
    }
} else {
    http_response_code(405); // Método no permitido
    file_put_contents("error_log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook][ERROR] Método no permitido: " . $_SERVER['REQUEST_METHOD'] . PHP_EOL, FILE_APPEND);
}