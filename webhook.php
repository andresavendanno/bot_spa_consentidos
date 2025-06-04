<?php

// Constantes de configuración (considera moverlas a un archivo .env o config.php en producción)
    const TOKEN_SPACONSENTIDOS = "CONSENTIDOSPORMAYMETA"; // Tu token de verificación de Webhook
    const WEBHOOK_URL = "whatsappapi.spaconsentidos.website/webhook.php"; // URL de la API de WhatsApp
    const WHATSAPP_TOKEN = "EAAUZAHdaMZB7sBOyH4Xpakb17bhqV9FaZC9C9PlO9NSZAJeE2leEGK7x8ufZC5Vc23JtXBQKKk1DyxKFzTUX1J8xc5peicJpZC5w8kgZBKG2z90VCoDrk5YMDoEKLEgZA0cq85jEWvRoGu3WfM50t2gju3gAB0xML1VW4qMbiYZCOcgpsxAJoFoJWI4HdWyKZCMgZDZD";
 // pendiente de token permanente   
    const WHATSAPP_URL = "https://graph.facebook.com/v22.0/646389751893147/messages";
    const TU_CLAVE = "CONSENTIDOSPORMAmeta05";

// Funciones de control de duplicados y limpieza de base
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

    // Si hay más del máximo permitido, recorta los más antiguos
    if (count($lineas) > $max) {
        $lineasRecortadas = array_slice($lineas, -$max); // conserva solo los más recientes
        file_put_contents($archivo, implode(PHP_EOL, $lineasRecortadas) . PHP_EOL);
    }
}

//BASES DE DATOS CONECTADAS, CLIENTES (CLIENTE Y ESTADO) prueba ahora solo con log
   // require_once("config/conexion.php");
    //require_once("models/Registro.php");
    //require_once("models/Usuario.php");

//verificar token
    function verificarToken($req, $res){
    try {
        $token = $req['hub_verify_token'];
        $challenge = $req['hub_challenge'];

        if (isset($challenge) && isset($token) && $token == TOKEN_SPACONSENTIDOS){
            $res->send($challenge);
        } else {
            $res->status(400)->send();
        }
    } catch(Exception $e){
        $res->status(400)->send();
    }
}
// Recibir mensjae (acá se construye la logica de consultar la base y tomar los datos del usuario para registrarlo en bases de datos)
function recibirMensajes($req) {
    try {
        if (!isset($req['entry'][0]['changes'][0]['value']['messages'])) return;

        $entry = $req['entry'][0];
        $changes = $entry['changes'][0];
        $value = $changes['value'];
        $objetomensaje = $value['messages'];
        $mensaje = $objetomensaje[0];

        $idMensaje = $mensaje['id'] ?? null;
        $comentario = $mensaje['text']['body'] ?? '';
        $numero = $mensaje['from'] ?? '';

        // Validación básica
        if (empty($comentario) || empty($numero) || empty($idMensaje)) return;

        // Verificar si ya se procesó este mensaje
        if (mensajeYaProcesado($idMensaje)) {
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Duplicado ignorado: $idMensaje".PHP_EOL, FILE_APPEND);
            return;
        }

        // Marcar mensaje como procesado
        marcarMensajeComoProcesado($idMensaje);
        limpiarMensajesProcesados();

        // Log del mensaje recibido
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Mensaje de $numero: $comentario".PHP_EOL, FILE_APPEND);

        // Procesar mensaje
        EnviarMensajeWhastapp($comentario, $numero);
        // agregar a base de datos
        $registro = new Registro();
        $registro->insert_registro($numero,$comentario);

    } 
        //plan B si sigue el blucle de event recibed 
    
    catch (Exception $e) {
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Error: ".$e->getMessage().PHP_EOL, FILE_APPEND);
    }
}

// Enviar mensaje por WhatsApp
function EnviarMensajeWhastapp($comentario, $numero){
    $comentario = strtolower($comentario);

    if (strpos($comentario, 'hola') !== false){
        $data = json_encode([
            "messaging_product" => "whatsapp",    
            "recipient_type" => "individual",
            "to" => $numero,
            "type" => "text",
            "text" => [
                "preview_url" => false,
                "body" => "¡Hola! ¡Bienvenido a Spa Consentidos! Veo que eres nuevo. Para registrar a tu consentido, por favor dime su nombre"
            ]
        ]);
    } else {
        return;
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
        file_put_contents("error_log.txt", "Error al enviar mensaje a $numero\n", FILE_APPEND);
    }
}

// pendiente de enteneder 
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Responde inmediatamente a Meta para evitar reintentos
    http_response_code(200);
    echo "EVENT_RECEIVED";
    flush(); // 🔁 Importante para liberar al cliente (Meta)

    // 2. Luego procesas el mensaje
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    recibirMensajes($data);
}else if($_SERVER['REQUEST_METHOD']==='GET'){
        if(isset($_GET['hub_mode']) && isset($_GET['hub_verify_token']) && isset($_GET['hub_challenge']) && $_GET['hub_mode'] === 'subscribe' && $_GET['hub_verify_token'] === TOKEN_SPACONSENTIDOS){
            echo $_GET['hub_challenge'];
        }else{
            http_response_code(403);
        }
    }
?>