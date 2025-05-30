<?php

// Constantes de configuración (considera moverlas a un archivo .env o config.php en producción)
const TOKEN_SPACONSENTIDOS = "CONSENTIDOSPORMAYMETA"; // Tu token de verificación de Webhook
const WEBHOOK_URL = "whatsappapi.spaconsentidos.website/webhook.php"; // URL de la API de WhatsApp
const WHATSAPP_TOKEN = "EAAUZAHdaMZB7sBO60GaxGb3mZAyJEpAOehuZCJamZAwOkqhEM0MRcZA6CrTc5HQiQaiyIQ26AKoagKzMZBesmHDuYH8ai2BcxoZBXUd55ab556TzIoi07xFKntC4pHwDjU04n7sy45SS4kZBZBUPThWHbjHtMwqRQqKnN8lflJjKZAeN3CWsCQQrDYNtXzKuqKZAcZCHkS2U9aMxl3AAoZAZCmZAbyVf5U8ZCpiQ1LzpdjYxbCAdpWf"; // Tu token de la API de WhatsApp
const WHATSAPP_URL = "https://graph.facebook.com/v22.0/646389751893147/messages";
const TU_CLAVE = "CONSENTIDOSPORMAmeta05"; // ¡IMPORTANTE: Reemplaza con la contraseña REAL de tu base de datos!

// BASES DE DATOS CONECTADAS, CLIENTES (CLIENTE Y ESTADO)
function conectarBD() {
    $host = 'spaconsentidos.website';         // o mysql.hostinger.com
    $dbname = 'u268007922_clientes';
    $usuario = 'u268007922_spaconsentidos';
    $clave = TU_CLAVE; // Usa la constante definida arriba

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $usuario, $clave);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error de conexión a BD: " . $e->getMessage());
        // En un entorno de producción, podrías querer enviar una notificación o manejar el error de otra manera.
        return null;
    }
}

// CONSULTA Y REGISTRO DE USUARIOS
function obtenerEstado($numero) {
    $pdo = conectarBD();
    if (!$pdo) return ["paso" => "inicio"];

    $stmt = $pdo->prepare("SELECT estado FROM estados WHERE numero = ?");
    $stmt->execute([$numero]);

    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fila) {
        $estado = json_decode($fila['estado'], true);
        return is_array($estado) ? $estado : ["paso" => "inicio"];
    } else {
        return ["paso" => "inicio"];
    }
}

function guardarEstado($numero, $estado) {
    $pdo = conectarBD();
    if (!$pdo) return;

    $json = json_encode($estado);
    $stmt = $pdo->prepare("
        INSERT INTO estados (numero, estado) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE estado = VALUES(estado)
    ");
    $stmt->execute([$numero, $json]);
}

function clienteExiste($numero) {
    $pdo = conectarBD();
    if (!$pdo) return false;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE numero = ?");
    $stmt->execute([$numero]);
    return $stmt->fetchColumn() > 0;
}

function registrarCliente($numero, $datosCliente) {
    $pdo = conectarBD();
    if (!$pdo) return;

    $json = json_encode($datosCliente);
    $stmt = $pdo->prepare("INSERT INTO clientes (numero, datos) VALUES (?, ?)");
    $stmt->execute([$numero, $json]);
}

function obtenerClienteDatos($numero) {
    $pdo = conectarBD();
    if (!$pdo) return ["consentidos" => []];

    $stmt = $pdo->prepare("SELECT datos FROM clientes WHERE numero = ?");
    $stmt->execute([$numero]);

    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fila) {
        $datos = json_decode($fila['datos'], true);
        return is_array($datos) ? $datos : ["consentidos" => []];
    }
    return ["consentidos" => []];
}

function actualizarCliente($numero, $datosCliente) {
    $actual = obtenerClienteDatos($numero);
    $fusion = array_merge($actual, $datosCliente);
    $json = json_encode($fusion);

    $pdo = conectarBD();
    if (!$pdo) return;

    $stmt = $pdo->prepare("
        INSERT INTO clientes (numero, datos) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE datos = VALUES(datos)
    ");
    $stmt->execute([$numero, $json]);
}

// Función para enviar mensajes de WhatsApp
function sendWhatsAppMessage($to, $message) {
    $ch = curl_init(WHATSAPP_URL);
    $headers = [
        'Authorization: Bearer ' . WHATSAPP_TOKEN,
        'Content-Type: application/json',
    ];
    $postData = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => $message],
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log("Error al enviar mensaje de WhatsApp: " . curl_error($ch));
    } else {
        error_log("Mensaje enviado a $to con respuesta: " . $response . " (HTTP Status: $http_code)");
    }
    curl_close($ch);
}

// --- Lógica principal del Webhook ---

// Verificación del Webhook de Meta
if (!empty($_GET["hub_mode"]) && $_GET["hub_mode"] == "subscribe" && $_GET["hub_verify_token"] == TOKEN_SPACONSENTIDOS) {
    echo $_GET["hub_challenge"];
    exit;
}

// Recibir y decodificar el JSON de Meta
$input = json_decode(file_get_contents('php://input'), true);

// Verificar si es un mensaje de WhatsApp entrante y si es de tipo texto
if (!empty($input['entry'][0]['changes'][0]['value']['messages'][0]) && $input['entry'][0]['changes'][0]['value']['messages'][0]['type'] === 'text') {
    $message = $input['entry'][0]['changes'][0]['value']['messages'][0];
    $from = $message['from']; // Número de WhatsApp del remitente
    $msg_body = $message['text']['body'] ?? ''; // Contenido del mensaje

    $estado_usuario = obtenerEstado($from);
    $cliente_existente = clienteExiste($from);

    $respuesta = "";

    // --- Lógica del Bot para la conversación ---
    switch ($estado_usuario['paso']) {
        case "inicio":
            // Si el cliente no existe, empezamos el proceso de registro
            if (!$cliente_existente) {
                $respuesta = "¡Hola! ¡Bienvenido a Spa Consentidos! Veo que eres nuevo. Para registrar a tu consentido, por favor dime su nombre.";
                // Transición al siguiente paso: esperando el nombre
                guardarEstado($from, ["paso" => "esperando_nombre"]);
            } else {
                // Si el cliente ya existe, saludamos y ofrecemos el menú principal
                $datos_cliente = obtenerClienteDatos($from);
                $nombre_consentido = $datos_cliente['nombre_consentido'] ?? 'tu consentido';
                $respuesta = "¡Bienvenido de nuevo, " . $nombre_consentido . "! ¿En qué puedo ayudarte hoy? (Puedes decir 'citas', 'servicios' o 'información')";
                // Transición al menú principal
                guardarEstado($from, ["paso" => "menu_principal"]);
            }
            break;

        case "esperando_nombre":
            // El usuario acaba de enviar el nombre del consentido
            $temp_data = $estado_usuario['temp_data'] ?? [];
            $temp_data['nombre_consentido'] = $msg_body;
            // Transición al siguiente paso: esperando la raza
            guardarEstado($from, ["paso" => "esperando_raza", "temp_data" => $temp_data]);
            $respuesta = "Excelente, ¿cuál es la raza de " . $msg_body . "?";
            break;

        case "esperando_raza":
            // El usuario acaba de enviar la raza
            $temp_data = $estado_usuario['temp_data'] ?? [];
            $temp_data['raza'] = $msg_body;
            // Transición al siguiente paso: esperando el peso
            guardarEstado($from, ["paso" => "esperando_peso", "temp_data" => $temp_data]);
            $respuesta = "Gracias. Ahora, ¿cuánto pesa " . $temp_data['nombre_consentido'] . " (solo el número, sin 'kg')?";
            break;

        case "esperando_peso":
            // El usuario acaba de enviar el peso
            if (is_numeric($msg_body) && (float)$msg_body > 0) {
                $temp_data = $estado_usuario['temp_data'] ?? [];
                $temp_data['peso'] = (float)$msg_body;
                // Transición al siguiente paso: esperando el último servicio
                guardarEstado($from, ["paso" => "esperando_ultimo_servicio", "temp_data" => $temp_data]);
                $respuesta = "¿Cuándo fue la última vez que " . $temp_data['nombre_consentido'] . " tuvo un servicio de peluquería? (Responde con '1 mes', 'entre 1 y 2 meses' o 'mas de 2 meses')";
            } else {
                // Entrada inválida, se mantiene en el mismo paso
                $respuesta = "Parece que no es un peso válido. Por favor, ingresa solo el número (ej. '5' para 5kg). Inténtalo de nuevo.";
            }
            break;

        case "esperando_ultimo_servicio":
            // El usuario acaba de enviar la fecha del último servicio
            $opciones_servicio_validas = ['1 mes', 'entre 1 y 2 meses', 'mas de 2 meses'];
            $msg_normalizado = strtolower(trim($msg_body)); // Normalizar la entrada para la validación

            if (in_array($msg_normalizado, $opciones_servicio_validas)) {
                $temp_data = $estado_usuario['temp_data'] ?? [];
                $temp_data['ultimo_servicio'] = $msg_body; // Guardar la entrada original del usuario
                // Transición al siguiente paso: esperando la edad
                guardarEstado($from, ["paso" => "esperando_edad", "temp_data" => $temp_data]);
                $respuesta = "Entendido. Finalmente, ¿cuántos años tiene " . $temp_data['nombre_consentido'] . "? (Solo el número)";
            } else {
                // Entrada inválida, se mantiene en el mismo paso
                $respuesta = "Por favor, elige una de las opciones: '1 mes', 'entre 1 y 2 meses' o 'mas de 2 meses'.";
            }
            break;

        case "esperando_edad":
            // El usuario acaba de enviar la edad
            if (is_numeric($msg_body) && (int)$msg_body >= 0) {
                $temp_data = $estado_usuario['temp_data'] ?? [];
                $temp_data['edad'] = (int)$msg_body;

                // Todos los datos de registro han sido recopilados, registramos al cliente
                registrarCliente($from, $temp_data);
                // Transición al menú principal y reseteamos el estado temporal
                guardarEstado($from, ["paso" => "menu_principal"]);
                $respuesta = "¡Excelente! " . $temp_data['nombre_consentido'] . " ha sido registrado. ¿En qué más puedo ayudarte? (Puedes decir 'citas', 'servicios' o 'información')";
            } else {
                // Entrada inválida, se mantiene en el mismo paso
                $respuesta = "Parece que no es una edad válida. Por favor, ingresa solo el número (ej. '3' para 3 años). Inténtalo de nuevo.";
            }
            break;

        case "menu_principal":
            // El usuario está en el menú principal y elige una opción
            $msg_normalizado = strtolower(trim($msg_body));
            if ($msg_normalizado === 'citas') {
                $respuesta = "Para agendar una cita, por favor visita nuestro sitio web: [URL_DE_TU_SITIO_DE_CITAS] o llama al [TU_NUMERO_TELEFONO].";
                // Después de dar la información, podemos volver al menú principal o a un estado de inicio si lo prefieres
                guardarEstado($from, ["paso" => "menu_principal"]); // Se queda en el menú principal
            } elseif ($msg_normalizado === 'servicios') {
                $respuesta = "Ofrecemos: baño y peluquería, corte de uñas, limpieza de oídos y desparasitación. ¿Quieres saber más de alguno en específico?";
                // Transición a un sub-menú de servicios
                guardarEstado($from, ["paso" => "sub_menu_servicios"]);
            } elseif ($msg_normalizado === 'informacion') {
                $respuesta = "Estamos ubicados en [TU_DIRECCION] y nuestro horario es de [HORARIO].";
                // Después de dar la información, volvemos al menú principal
                guardarEstado($from, ["paso" => "menu_principal"]);
            } else {
                // Opción no reconocida en el menú principal, se mantiene en el mismo paso
                $respuesta = "No te he entendido. Por favor, dime 'citas', 'servicios' o 'información'.";
            }
            break;

        case "sub_menu_servicios":
            // El usuario está en el sub-menú de servicios
            $msg_normalizado = strtolower(trim($msg_body));
            if (strpos($msg_normalizado, 'baño') !== false || strpos($msg_normalizado, 'peluqueria') !== false) {
                $respuesta = "Nuestro servicio de baño y peluquería incluye cepillado, corte, baño con productos especiales y secado. El precio varía según la raza y tamaño. ¿Te gustaría agendar?";
                // Después de dar la información, volvemos al menú principal
                guardarEstado($from, ["paso" => "menu_principal"]);
            } elseif (strpos($msg_normalizado, 'uñas') !== false) {
                $respuesta = "El corte de uñas es un servicio rápido y seguro realizado por profesionales. Puedes venir sin cita o agendarlo con otro servicio.";
                guardarEstado($from, ["paso" => "menu_principal"]);
            } elseif (strpos($msg_normalizado, 'limpieza') !== false || strpos($msg_normalizado, 'oidos') !== false) {
                $respuesta = "La limpieza de oídos es fundamental para la salud de tu mascota. Usamos productos suaves y seguros.";
                guardarEstado($from, ["paso" => "menu_principal"]);
            } elseif (strpos($msg_normalizado, 'desparasitacion') !== false) {
                $respuesta = "Ofrecemos desparasitación interna y externa. Consulta a nuestros expertos para el tratamiento adecuado.";
                guardarEstado($from, ["paso" => "menu_principal"]);
            } elseif ($msg_normalizado === 'volver') {
                // Permite al usuario volver al menú principal
                $respuesta = "Volviendo al menú principal. ¿En qué más puedo ayudarte? (Puedes decir 'citas', 'servicios' o 'información')";
                guardarEstado($from, ["paso" => "menu_principal"]);
            } else {
                // Opción no reconocida en el sub-menú, se mantiene en el mismo paso
                $respuesta = "No te he entendido. Por favor, especifica qué servicio te interesa (ej. 'baño', 'uñas', 'limpieza', 'desparasitación') o escribe 'volver' para el menú principal.";
            }
            break;

        default:
            // Si el estado es desconocido o no manejado, resetear a inicio y pedir al usuario que reinicie
            guardarEstado($from, ["paso" => "inicio"]);
            $respuesta = "Ha ocurrido un error en la conversación. Por favor, intenta de nuevo escribiendo 'Hola'.";
            break;
    }

    // Enviar la respuesta de WhatsApp
    sendWhatsAppMessage($from, $respuesta);
}

?>