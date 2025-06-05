<?php
require_once("helpers/mensajes.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(200);
    echo "EVENT_RECEIVED";
    flush();
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    recibirMensajes($data);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (
        isset($_GET['hub_mode']) &&
        isset($_GET['hub_verify_token']) &&
        isset($_GET['hub_challenge']) &&
        $_GET['hub_mode'] === 'subscribe' &&
        $_GET['hub_verify_token'] === TOKEN_SPACONSENTIDOS
    ) {
        echo $_GET['hub_challenge'];
    } else {
        http_response_code(403);
    }
}
