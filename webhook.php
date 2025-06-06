<?php
require_once("config/constantes.php");
require_once("helpers/mensajes.php");

// Log inicial de entrada
file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] 🚀 Webhook llamado\n", FILE_APPEND);

// Validación del tipo de petición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(200);
    echo "EVENT_RECEIVED";
    flush();

    // Leer contenido crudo
    $input = file_get_contents('php://input');
    file_put_contents("log.txt", "[DEBUG] 🔍 Contenido bruto recibido: " . $input . "\n", FILE_APPEND);

    // Decodificar JSON
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents("error_log.txt", "[" . date("Y-m-d H:i:s") . "] ❌ JSON malformado: " . json_last_error_msg() . PHP_EOL, FILE_APPEND);
        return;
    }

    file_put_contents("log.txt", "[DEBUG] ✅ JSON decodificado correctamente\n", FILE_APPEND);

    try {
        file_put_contents("log.txt", "[DEBUG] A punto de llamar a recibirMensajes()\n", FILE_APPEND);
        recibirMensajes($data);
        file_put_contents("log.txt", "[DEBUG] ✅ recibirMensajes() ejecutado correctamente\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents("error_log.txt", "[Webhook][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verificación del token en modo desarrollo
    if (
        isset($_GET['hub_mode']) &&
        isset($_GET['hub_verify_token']) &&
        isset($_GET['hub_challenge']) &&
        $_GET['hub_mode'] === 'subscribe' &&
        $_GET['hub_verify_token'] === TOKEN_SPACONSENTIDOS
    ) {
        file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] 🔓 Verificación exitosa con challenge\n", FILE_APPEND);
        echo $_GET['hub_challenge'];
    } else {
        http_response_code(403);
        file_put_contents("error_log.txt", "[" . date("Y-m-d H:i:s") . "] ❌ Verificación fallida o token incorrecto\n", FILE_APPEND);
    }

} else {
    http_response_code(405); // Método no permitido
    file_put_contents("error_log.txt", "[" . date("Y-m-d H:i:s") . "] ❌ Método no permitido: " . $_SERVER['REQUEST_METHOD'] . PHP_EOL, FILE_APPEND);
}
