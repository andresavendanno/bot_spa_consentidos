<?php

// Constantes de configuración (considera moverlas a un archivo .env o config.php en producción)
const TOKEN_SPACONSENTIDOS = "CONSENTIDOSPORMAYMETA";
const WEBHOOK_URL = "whatsappapi.spaconsentidos.website/webhook.php";
const WHATSAPP_TOKEN = "EAAUZAHdaMZB7sBOyH4Xpakb17bhqV9FaZC9C9PlO9NSZAJeE2leEGK7x8ufZC5Vc23JtXBQKKk1DyxKFzTUX1J8xc5peicJpZC5w8kgZBKG2z90VCoDrk5YMDoEKLEgZA0cq85jEWvRoGu3WfM50t2gju3gAB0xML1VW4qMbiYZCOcgpsxAJoFoJWI4HdWyKZCMgZDZD";
const WHATSAPP_URL = "https://graph.facebook.com/v22.0/646389751893147/messages";
const TU_CLAVE = "CONSENTIDOSPORMAmeta05";

// Conexión y modelos
require_once("config/conexion.php");
require_once("models/Registro.php");
require_once("models/Usuario.php");

// Funciones de control de duplicados
function mensajeYaProcesado($id) {
    $archivo = 'mensajes_procesados.txt';
    if (!file_exists($archivo)) return false;
    $procesados = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($id, $procesados);
}

function marcarMensajeComoProcesado($id) {
    file_put_contents('mensajes_procesados.txt', $id . PHP_EOL, FILE_APPEND);
}

function limpiarMensajesProcesados($max = 5000) {
    $archivo = 'mensajes_procesados.txt';
    if (!file_exists($archivo)) return;

    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lineas) > $max) {
        $lineasRecortadas = array_slice($lineas, -$max);
        file_put_contents($archivo, implode(PHP_EOL, $lineasRecortadas) . PHP_EOL);
    }
}

// Verificar token de webhook
function verificarToken($req, $res) {
    try {
        $token = $req['hub_verify_token'];
        $challenge = $req['hub_challenge'];

        if (isset($challenge) && isset($token) && $token == TOKEN_SPACONSENTIDOS) {
            $res->send($challenge);
        } else {
            $res->status(400)->send();
        }
    } catch(Exception $e) {
        $res->status(400)->send();
    }
}

// Recibir mensajes
function recibirMensajes($req) {
    try {
        if (!isset($req['entry'][0]['changes'][0]['value']['messages'])) return;

        $entry = $req['entry'][0];
        $changes = $entry['changes'][0];
        $value = $changes['value'];
        $objetomensaje = $value['messages'];
        $mensaje = $objetomensaje[0];

        $idMensaje = $mensaje['id'] ?? null;
        $comentario = strtolower($mensaje['text']['body'] ?? '');
        $numero = $mensaje['from'] ?? '';

        if (empty($comentario) || empty($numero) || empty($idMensaje)) return;

        if (mensajeYaProcesado($idMensaje)) {
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Duplicado ignorado: $idMensaje".PHP_EOL, FILE_APPEND);
            return;
        }

        marcarMensajeComoProcesado($idMensaje);
        limpiarMensajesProcesados();

        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Mensaje de $numero: $comentario".PHP_EOL, FILE_APPEND);

        // Verificar si el usuario ya está registrado
        $conectar = (new Conectar())->conexion();
        $stmt = $conectar->prepare("SELECT 1 FROM usuarios_final WHERE numero = ?");
        $stmt->execute([$numero]);
        $esRegistrado = $stmt->fetch();

        if ($esRegistrado) {
            $usuario = new Usuario();
            $respuesta = $usuario->procesarPaso($numero, $comentario);
        } else {
            $registro = new Registro();
            $respuesta = $registro->procesarPaso($numero, $comentario);
            $registro->insert_registro($numero, $comentario);
        }

        EnviarMensajeWhastapp($respuesta, $numero);

    } catch (Exception $e) {
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Error: ".$e->getMessage().PHP_EOL, FILE_APPEND);
    }
}



// Enviar mensaje por WhatsApp
function EnviarMensajeWhastapp($respuesta, $numero) {
    if (!$respuesta) return;

    if (is_array($respuesta)) {
        $respuesta['to'] = $numero;
        $respuesta['messaging_product'] = 'whatsapp';
        $data = json_encode($respuesta);
    } else {
        $data = json_encode([
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $numero,
            "type" => "text",
            "text" => [
                "preview_url" => false,
                "body" => $respuesta
            ]
        ]);
    }

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/json\r\nAuthorization: Bearer ".WHATSAPP_TOKEN."\r\n",
            'content' => $data,
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents(WHATSAPP_URL, false, $context);

    if ($response === false) {
        file_put_contents("error_log.txt", "[".date("Y-m-d H:i:s")."] Error al enviar mensaje a $numero".PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Mensaje enviado a $numero: $data".PHP_EOL, FILE_APPEND);
    }
}


// Lógica principal
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
