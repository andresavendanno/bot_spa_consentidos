<?php
file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] 🚀 Webhook llamado\n", FILE_APPEND);

require_once("config/constantes.php");
require_once("helpers/mensajes.php");
require_once("models/Servicio.php");

file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook] Método: " . $_SERVER['REQUEST_METHOD'] . PHP_EOL, FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(200);
    echo "EVENT_RECEIVED";
    flush();

    $input = file_get_contents('php://input');
    file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook] Cuerpo POST recibido: " . $input . PHP_EOL, FILE_APPEND);

    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents("error_log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook][ERROR] JSON malformado: " . json_last_error_msg() . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook] JSON decodificado correctamente" . PHP_EOL, FILE_APPEND);
        recibirMensajes($data);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] [Webhook] Petición GET recibida" . PHP_EOL, FILE_APPEND);

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