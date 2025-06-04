<?php

// Constantes de configuración (considera moverlas a un archivo .env o config.php en producción)
    const TOKEN_SPACONSENTIDOS = "CONSENTIDOSPORMAYMETA"; // Tu token de verificación de Webhook
    const WEBHOOK_URL = "whatsappapi.spaconsentidos.website/webhook.php"; // URL de la API de WhatsApp
    const WHATSAPP_TOKEN = "EAAUZAHdaMZB7sBOyH4Xpakb17bhqV9FaZC9C9PlO9NSZAJeE2leEGK7x8ufZC5Vc23JtXBQKKk1DyxKFzTUX1J8xc5peicJpZC5w8kgZBKG2z90VCoDrk5YMDoEKLEgZA0cq85jEWvRoGu3WfM50t2gju3gAB0xML1VW4qMbiYZCOcgpsxAJoFoJWI4HdWyKZCMgZDZD";
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
   require_once("config/conexion.php");
   require_once("models/Registro.php");
   require_once("models/Usuario.php");

//verificar token
    function verificarToken($req, $res){
    try {
        $token = $req['hub_verify_token']; [cite: 9]
        $challenge = $req['hub_challenge']; [cite: 9]

        if (isset($challenge) && isset($token) && $token == TOKEN_SPACONSENTIDOS){
            // Usar echo para enviar el challenge en PHP, no $res->send() que es de otros frameworks
            echo $challenge; // [cite: 10]
            exit; // Importante para detener la ejecución aquí
        } else {
            // Usar http_response_code y exit para enviar el estado en PHP
            http_response_code(400); // [cite: 11]
            exit;
        }
    } catch(Exception $e){
        http_response_code(400); // [cite: 12]
        exit;
    }
    }
// Recibir mensaje (acá se construye la logica de consultar la base y tomar los datos del usuario para registrarlo en bases de datos)
    function recibirMensajes($req) {
    try {
        // Asegúrate de que el path sea el correcto para los mensajes
        if (!isset($req['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'])) { // [cite: 13, 14]
            // Si no hay mensaje de texto, podríamos no querer procesarlo.
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] No es un mensaje de texto o estructura incorrecta.".PHP_EOL, FILE_APPEND);
            return;
        }
        $entry = $req['entry'][0]; [cite: 13]
        $changes = $entry['changes'][0];
        $value = $changes['value'];
        $objetomensaje = $value['messages'][0]; // Acceder directamente al primer mensaje [cite: 13]
        $mensaje_id = $objetomensaje['id'] ?? null; // Usar una variable clara para el ID del mensaje [cite: 14]
        $comentario_usuario = strtolower($objetomensaje['text']['body'] ?? ''); // Contenido del mensaje del usuario [cite: 14]
        $numero_remitente = $objetomensaje['from'] ?? ''; // Número del remitente [cite: 15]

        // Validación básica
        if (empty($comentario_usuario) || empty($numero_remitente) || empty($mensaje_id)) { // [cite: 16]
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Datos de mensaje incompletos (comentario, numero o id).".PHP_EOL, FILE_APPEND);
            return;
        }
        // Verificar si ya se procesó este mensaje
        if (mensajeYaProcesado($mensaje_id)) { // [cite: 17]
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Duplicado ignorado: $mensaje_id".PHP_EOL, FILE_APPEND); // [cite: 17]
            return; // [cite: 17]
        }

        // Marcar mensaje como procesado
        marcarMensajeComoProcesado($mensaje_id); // [cite: 18]
        limpiarMensajesProcesados(); // [cite: 18]
        
        // Log del mensaje recibido
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Mensaje de $numero_remitente: $comentario_usuario".PHP_EOL, FILE_APPEND); // [cite: 19]

        // Procesar mensaje
        // Instancia la clase Registro (ya tienes un require_once para ella)
        $registro = new Registro(); // [cite: 20]
        // Llama al método procesarPaso que devuelve la respuesta del bot
        $respuesta_bot = $registro->procesarPaso($numero_remitente, $comentario_usuario); // [cite: 20]
        
        // ¡ESTE ES EL PUNTO CLAVE! Envía la respuesta generada por procesarPaso
        if (!empty($respuesta_bot)) {
            EnviarMensajeWhastapp($respuesta_bot, $numero_remitente);
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Solicitado envio de respuesta a $numero_remitente: '$respuesta_bot'".PHP_EOL, FILE_APPEND);
        } else {
             file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] procesarPaso devolvio respuesta vacia para $numero_remitente".PHP_EOL, FILE_APPEND);
        }

        // El insert_registro que está aquí parece redundante si ya se guarda en Registro::procesarPaso
        // Si 'insert_registro' guarda los mensajes o interacciones, déjalo.
        // Si es para guardar el registro final del cliente, debería ir en 'moverAFinal' o similar.
        // Asumiendo que insert_registro es para un log general de interacciones.
        $registro->insert_log($numero_remitente, "Bot: " . $respuesta_bot); // Puedes registrar la respuesta del bot también
        $registro->insert_log($numero_remitente, "Usuario: " . $comentario_usuario); // Ya lo registras al inicio, esto es redundante
        
    }
    catch (Exception $e) {
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Error general en recibirMensajes: ".$e->getMessage().PHP_EOL, FILE_APPEND); // [cite: 22]
    }
    }

// Enviar mensaje por WhatsApp
    function EnviarMensajeWhastapp($comentario, $numero){ // Aquí $comentario es la respuesta GENERADA por el bot
    // NO PONER EL IF DE 'hola' AQUÍ. Debe enviar CUALQUIER $comentario
    $data = json_encode([
        "messaging_product" => "whatsapp",    
        "recipient_type" => "individual",
        "to" => $numero,
        "type" => "text",
        "text" => [
            "preview_url" => false,
            "body" => $comentario // <-- Envía el comentario que se recibe
        ]
    ]);

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/json\r\nAuthorization: Bearer ".WHATSAPP_TOKEN."\r\n",
            'content' => $data,
            'ignore_errors' => true
        ]
    ]; [cite: 27]
    $context = stream_context_create($options); [cite: 27]
    $response = file_get_contents(WHATSAPP_URL, false, $context); [cite: 27]

    if ($response === false) {
        file_put_contents("error_log.txt", "Error al enviar mensaje a $numero - Respuesta HTTP: ". ($http_response_header[0] ?? 'N/A') . "\n", FILE_APPEND); // [cite: 28]
    } else {
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Mensaje enviado OK a $numero. Respuesta Meta: $response".PHP_EOL, FILE_APPEND);
    }
    }

// pendiente de enteneder 
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Responde inmediatamente a Meta para evitar reintentos
    http_response_code(200); [cite: 29]
    echo "EVENT_RECEIVED"; [cite: 29]
    flush(); // 🔁 Importante para liberar al cliente (Meta) [cite: 29]

    // 2. Luego procesas el mensaje
    $input = file_get_contents('php://input'); [cite: 30]
    $data = json_decode($input, true); [cite: 30]

    recibirMensajes($data);
    }else if($_SERVER['REQUEST_METHOD']==='GET'){
        if(isset($_GET['hub_mode']) && isset($_GET['hub_verify_token']) && isset($_GET['hub_challenge']) && $_GET['hub_mode'] === 'subscribe' && $_GET['hub_verify_token'] === TOKEN_SPACONSENTIDOS){
            echo $_GET['hub_challenge']; [cite: 31]
        }else{
            http_response_code(403);
        }
    }
?>