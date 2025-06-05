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

// Funciones duplicados
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

// Lógica principal
function recibirMensajes($req) {
    try {
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Payload recibido: ".json_encode($req).PHP_EOL, FILE_APPEND);

        if (!isset($req['entry'][0]['changes'][0]['value']['messages'])) return;

        $mensaje = $req['entry'][0]['changes'][0]['value']['messages'][0];
        $idMensaje = $mensaje['id'] ?? '';
        $comentario = strtolower($mensaje['text']['body'] ?? '');
        $numero = $mensaje['from'] ?? '';

        if (empty($comentario) || empty($numero) || empty($idMensaje)) return;

        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Mensaje de $numero: $comentario".PHP_EOL, FILE_APPEND);

        file_put_contents("log.txt", "[DEBUG] Entrando a verificación de duplicados".PHP_EOL, FILE_APPEND);

        if (mensajeYaProcesado($idMensaje)) {
            file_put_contents("log.txt", "[DEBUG] Ya procesado, ignorando".PHP_EOL, FILE_APPEND);
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Duplicado ignorado: $idMensaje".PHP_EOL, FILE_APPEND);
            return;
        }

        file_put_contents("log.txt", "[DEBUG] No es duplicado, marcando como procesado".PHP_EOL, FILE_APPEND);
        marcarMensajeComoProcesado($idMensaje);
        file_put_contents("log.txt", "[DEBUG] Marcado OK".PHP_EOL, FILE_APPEND);

        limpiarMensajesProcesados();
        file_put_contents("log.txt", "[DEBUG] Limpieza OK".PHP_EOL, FILE_APPEND);

        // Verificar si ya está registrado
        file_put_contents("log.txt", "[DEBUG] Antes de conectar a BD".PHP_EOL, FILE_APPEND);

        $conectar = new Conectar();
        file_put_contents("log.txt", "[DEBUG] Conectando a base de datos...\n", FILE_APPEND);
        $conexion = $conectar->conexion();
        file_put_contents("log.txt", "[DEBUG] Conexión a BD OK\n", FILE_APPEND);

        if ($conexion instanceof PDO) {
            file_put_contents("log.txt", "[DEBUG] Conexión OK\n", FILE_APPEND);
        } else {
            file_put_contents("log.txt", "[ERROR] Conexión FALLIDA\n", FILE_APPEND);
        }

        file_put_contents("log.txt", "[DEBUG] Ejecutando set_names...\n", FILE_APPEND);
        $set = $conectar->set_names();
        if ($set) {
            file_put_contents("log.txt", "[DEBUG] set_names OK\n", FILE_APPEND);
        } else {
            file_put_contents("log.txt", "[ERROR] set_names FALLÓ\n", FILE_APPEND);
        }

        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios_final WHERE numero = ?");
        file_put_contents("log.txt", "[DEBUG] Preparación de consulta OK\n", FILE_APPEND);
        $stmt->execute([$numero]);
        $esRegistrado = $stmt->fetchColumn() > 0;
        file_put_contents("log.txt", "[DEBUG] Resultado consulta: " . ($esRegistrado ? "Registrado" : "No registrado") . PHP_EOL, FILE_APPEND);



        // Redirigir a la clase correspondiente
        file_put_contents("log.txt", "[DEBUG] Es registrado: ".($esRegistrado ? 'sí' : 'no').PHP_EOL, FILE_APPEND);
        file_put_contents("log.txt", "[DEBUG] Resultado COUNT usuarios_final: $esRegistrado\n", FILE_APPEND);

        if ($esRegistrado) {
            $usuario = new Usuario();
            $respuesta = $usuario->procesarPaso($numero, $comentario);
        } else {
            $registro = new Registro();
            $respuesta = $registro->procesarPaso($numero, $comentario);
            $registro->insert_registro($numero, $comentario);
        }

        file_put_contents("log.txt", "[DEBUG] Respuesta generada: ".print_r($respuesta, true).PHP_EOL, FILE_APPEND);


        EnviarMensajeWhastapp($respuesta, $numero);

    } catch (Exception $e) {
        file_put_contents("error_log.txt", "[Webhook ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}

// Enviar mensaje a WhatsApp
function EnviarMensajeWhastapp($respuesta, $numero) {
    if (!$respuesta) return;
    file_put_contents("log.txt", "[Check] Enviando respuesta tipo: " . gettype($respuesta) . PHP_EOL, FILE_APPEND);

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

    $jsonData = json_encode($data);

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/json\r\nAuthorization: Bearer ".WHATSAPP_TOKEN."\r\n",
            'content' => $jsonData,
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents(WHATSAPP_URL, false, $context);
    file_put_contents("log.txt", "[Check] Respuesta de API WhatsApp: " . $response . PHP_EOL, FILE_APPEND);

    file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Mensaje enviado a $numero: ".print_r($data, true).PHP_EOL, FILE_APPEND);
}

// Webhook endpoint
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
