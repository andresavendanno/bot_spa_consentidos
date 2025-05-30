<?php
//errores php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Constantes de configuración (considera moverlas a un archivo .env o config.php en producción)
const TOKEN_SPACONSENTIDOS = "CONSENTIDOSPORMAYMETA"; // Tu token de verificación de Webhook
const WEBHOOK_URL = "whatsappapi.spaconsentidos.website/webhook.php"; // URL de la API de WhatsApp

function verificarToken($req,$res){
    try{
        $token = $req['hub_verify_token'];
        $challenge = $req['hub_challenge'];

        if (isset($challenge) && isset($token) && $token == TOKEN_SPACONSENTIDOS){
            $res->send($challenge);
        }else{
            $res ->status(400)->send();
        }

    }catch(Exception $e){
            $res ->status(400)->send();
    }
}
// Definición de servicios principales
const SERVICIOS_PRINCIPALES = [
    "Baño (corte higiénico, uñas, oídos)",
    "Baño y deslanado",
    "Baño y corte de manto",
    "Baño y desenredado"
];

// Definición de servicios adicionales (por ahora texto, luego quizás objetos con precio)
const SERVICIOS_ADICIONALES_LISTA = [   
    "Baño pulguicida",
    "Baño hipoalergenico",
    "Mascarilla de Argan",
    "Recuperación de manto"
];

// --- Funciones de Utilidad y Manejo de Archivos (con flock) ---

// Asegura que los directorios existan
function asegurarDirectorios() {
    if (!is_dir('estados')) {
        mkdir('estados');
    }
    if (!is_dir('clientes')) {
        mkdir('clientes');
    }
}

// Función para obtener el estado del usuario (con bloqueo de archivo)
function obtenerEstado($numero){
    $archivo = "estados/$numero.json";
    // 'c+' crea el archivo si no existe, lo abre para lectura/escritura, y posiciona el puntero al principio.
    $fp = fopen($archivo, 'c+');
    if (!$fp) {
        error_log("ERROR: No se pudo abrir/crear el archivo de estado para el número $numero.");
        return ["paso" => "inicio"]; // Fallback si no se puede abrir el archivo
    }

    $estado = ["paso" => "inicio"]; // Estado por defecto
    if (flock($fp, LOCK_EX)) { // Obtener un bloqueo exclusivo
        $contenido = stream_get_contents($fp); // Leer todo el contenido del archivo
        if (!empty($contenido)) {
            $decodificado = json_decode($contenido, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $estado = $decodificado;
            } else {
                error_log("ERROR: JSON malformado en el archivo de estado para $numero: " . json_last_error_msg());
            }
        }
        flock($fp, LOCK_UN); // Liberar el bloqueo
    } else {
        error_log("ADVERTENCIA: No se pudo obtener el bloqueo del archivo de estado para el número $numero (lectura).");
    }
    fclose($fp);
    return $estado;
}

// Función para guardar el estado del usuario (con bloqueo de archivo)
function guardarEstado($numero, $estado){
    $archivo = "estados/$numero.json";
    $fp = fopen($archivo, 'c+');
    if (!$fp) {
        error_log("ERROR: No se pudo abrir/crear el archivo de estado para el número $numero para guardar.");
        return;
    }

    if (flock($fp, LOCK_EX)) { // Obtener un bloqueo exclusivo
        ftruncate($fp, 0); // Borrar el contenido existente
        rewind($fp); // Volver al principio del archivo
        fwrite($fp, json_encode($estado)); // Escribir el nuevo estado
        flock($fp, LOCK_UN); // Liberar el bloqueo
    } else {
        error_log("ADVERTENCIA: No se pudo obtener el bloqueo del archivo de estado para el número $numero (escritura).");
    }
    fclose($fp);
}

// Función para verificar si el cliente existe en la "base de datos" (archivos JSON)
function clienteExiste($numero) {
    return file_exists("clientes/$numero.json");
}

// Función para registrar un nuevo cliente (con bloqueo de archivo)
function registrarCliente($numero, $datosCliente) {
    $archivo = "clientes/$numero.json";
    $fp = fopen($archivo, 'c+');
    if (!$fp) {
        error_log("ERROR: No se pudo abrir/crear el archivo de cliente para el número $numero para registrar.");
        return;
    }

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($datosCliente));
        flock($fp, LOCK_UN);
    } else {
        error_log("ADVERTENCIA: No se pudo obtener el bloqueo del archivo de cliente para el número $numero (registro).");
    }
    fclose($fp);
}

// Función para actualizar los datos de un cliente existente (con bloqueo de archivo)
function actualizarCliente($numero, $datosCliente) {
    $archivo = "clientes/$numero.json";
    $fp = fopen($archivo, 'c+');
    if (!$fp) {
        error_log("ERROR: No se pudo abrir/crear el archivo de cliente para el número $numero para actualizar.");
        return;
    }

    if (flock($fp, LOCK_EX)) {
        $clienteExistente = [];
        $contenido = stream_get_contents($fp);
        if (!empty($contenido)) {
            $decodificado = json_decode($contenido, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $clienteExistente = $decodificado;
            } else {
                error_log("ERROR: JSON malformado al leer cliente para actualización: " . json_last_error_msg());
            }
        }

        $clienteFusionado = array_merge($clienteExistente, $datosCliente);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($clienteFusionado));
        flock($fp, LOCK_UN);
    } else {
        error_log("ADVERTENCIA: No se pudo obtener el bloqueo del archivo de cliente para el número $numero (actualización).");
    }
    fclose($fp);
}

// Función para buscar razas similares (simulación de búsqueda en DB)
function buscarRaza($nombreRaza) {
    // Razas comunes incluyendo "mestizo"
    $razasDisponibles = [
        "golden retriever", "labrador", "poodle", "pastor aleman", "bulldog",
        "chihuahua", "pug", "beagle", "shihtzu", "yorkshire terrier", "mestizo",
        "salchicha", "husky", "border collie", "schnauzer"
    ];

    $nombreRaza = strtolower(trim($nombreRaza));
    $mejorCoincidencia = null;
    $mayorSimilitud = 0;

    foreach ($razasDisponibles as $raza) {
        similar_text($nombreRaza, $raza, $porcentaje);
        if ($porcentaje > $mayorSimilitud) {
            $mayorSimilitud = $porcentaje;
            $mejorCoincidencia = $raza;
        }
    }

    // Consideramos una coincidencia si es al menos un 70% similar
    if ($mayorSimilitud >= 70) {
        return $mejorCoincidencia;
    }
    return null; // No se encontró una raza similar
}

// Función auxiliar para obtener los datos completos del cliente (incluyendo consentidos)
// Se usa para asegurar que se obtienen los datos con bloqueo antes de modificarlos.
function obtenerClienteDatos($numero){
    $archivo = "clientes/$numero.json";
    $fp = fopen($archivo, 'c+');
    if (!$fp) {
        error_log("ERROR: No se pudo abrir el archivo de cliente para el número $numero (obtener datos).");
        return ["consentidos" => []];
    }

    $datos = ["consentidos" => []];
    if (flock($fp, LOCK_EX)) {
        $contenido = stream_get_contents($fp);
        if (!empty($contenido)) {
            $decodificado = json_decode($contenido, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $datos = $decodificado;
            } else {
                error_log("ERROR: JSON malformado en el archivo de cliente para $numero: " . json_last_error_msg());
            }
        }
        flock($fp, LOCK_UN);
    } else {
        error_log("ADVERTENCIA: No se pudo obtener el bloqueo del archivo de cliente para el número $numero (obtener datos).");
    }
    fclose($fp);
    return $datos;
}


// --- Funciones de Envío de Mensajes de WhatsApp ---

// Función base para enviar cualquier mensaje de WhatsApp
function enviarWhatsApp($data){
    $data_string = json_encode($data);
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/json\r\nAuthorization: Bearer ".WHATSAPP_TOKEN."\r\n",
            'content' => $data_string,
            'ignore_errors' => true // Permite capturar errores de la respuesta HTTP
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents(WHATSAPP_URL, false, $context); // @ para suprimir warnings y manejar manualmente

    // Manejo de errores de la API de WhatsApp
    if ($response === FALSE) {
        error_log("ERROR: Fallo al conectar con la API de WhatsApp para enviar mensaje.");
    } else {
        $http_response_header = $http_response_header ?? []; // PHP 7.1+ safety
        $http_status_line = $http_response_header[0] ?? '';
        if (strpos($http_status_line, '200 OK') === false) {
            error_log("ERROR: API de WhatsApp respondió con error: " . $http_status_line . " - Respuesta: " . $response);
        }
    }
}

// Función para enviar un mensaje de texto
function enviarTexto($numero, $texto){
    enviarWhatsApp([
        "messaging_product" => "whatsapp",
        "to" => $numero,
        "type" => "text",
        "text" => ["preview_url" => false, "body" => $texto]
    ]);
}

// Función para enviar un mensaje con botones interactivos
function enviarMensajeBoton($numero, $texto, $botones){
    $buttonData = [
        "messaging_product" => "whatsapp",
        "recipient_type" => "individual",
        "to" => $numero,
        "type" => "interactive",
        "interactive" => [
            "type" => "button",
            "body" => ["text" => $texto],
            "action" => [
                "buttons" => array_map(function($b, $i){
                    return [
                        "type" => "reply",
                        "reply" => [
                            "id" => "btn_" . str_replace([" ", "(", ")", ","], "_", strtolower($b)) . "_$i", // ID más robusto
                            "title" => $b
                        ]
                    ];
                }, $botones, array_keys($botones))
            ]
        ]
    ];
    enviarWhatsApp($buttonData);
}

// --- Lógica del Bot (Refactorizada y con Validaciones) ---

// Función para mostrar los consentidos de un cliente
function mostrarConsentidos($numero){
    $clienteFile = "clientes/$numero.json";
    // Si clienteExiste() ya verificó el archivo, esta parte solo lee.
    $cliente = obtenerClienteDatos($numero); // Usa la función segura con flock

    if (!isset($cliente["consentidos"]) || !is_array($cliente["consentidos"]) || empty($cliente["consentidos"])) {
        enviarTexto($numero, "Parece que no tienes consentidos registrados. ¿Cuál es el nombre de tu consentido?");
        $estado = obtenerEstado($numero);
        $estado["paso"] = "nombre";
        guardarEstado($numero, $estado);
        return false;
    }

    $botones = [];
    foreach ($cliente["consentidos"] as $c) {
        $botones[] = $c["nombre"] . " 🐶";
    }
    if (count($cliente["consentidos"]) > 1) {
        $botones[] = "Ambos 🐾";
    }
    $botones[] = "Otro nuevo ➕";
    enviarMensajeBoton($numero, "¿Para cuál consentido querés pedir el turno?", $botones);
    
    $estado = obtenerEstado($numero);
    $estado["paso"] = "esperando_consentido";
    $estado["consentidos"] = $cliente["consentidos"];
    guardarEstado($numero, $estado);
    return true;
}

// --- Handlers de Pasos ---

function handleInicio($numero, &$estado) {
    if (clienteExiste($numero)) {
        enviarTexto($numero, "Hola, soy BOTitas buen día! \n \nGracias por comunicarte con Spa Consentidos ");
        mostrarConsentidos($numero);
    } else {
        enviarTexto($numero, "Hola, soy BOTitas buen día!\n \n me alegra ver que es la primera vez de tu consentido en el Spa, por favor responder las siguientes preguntas para agregarte, \n \ncual es el nombre de tu consentido? ");
        $estado["paso"] = "nombre";
    }
}

function handleEsperandoConsentido($comentario, $numero, &$estado) {
    if (strpos($comentario, "otro nuevo") !== false) {
        enviarTexto($numero, "¿Cuál es el nombre del nuevo consentido?");
        $estado["paso"] = "nombre";
    } elseif (strpos($comentario, "ambos") !== false) {
        if (!isset($estado["consentidos"]) || !is_array($estado["consentidos"]) || count($estado["consentidos"]) < 2) {
             enviarTexto($numero, "No tienes suficientes consentidos registrados para elegir 'Ambos'.\n \nPor favor, selecciona uno u 'Otro nuevo'.");
        } else {
            $estado["consentido_actual"] = 0;
            $estado["servicios"] = []; // Usamos 'servicios' para el flujo de ambos
            $estado["paso"] = "servicio_ambos";
            $nombre = $estado["consentidos"][0]["nombre"];
            enviarMensajeBoton($numero, "¿Qué servicio desea para $nombre?", SERVICIOS_PRINCIPALES); // Enviar botones de servicio aquí
        }
    } else {
        $consentidoEncontrado = false;
        if (isset($estado["consentidos"]) && is_array($estado["consentidos"])) {
            foreach ($estado["consentidos"] as $c) {
                if (strtolower($c["nombre"] . " 🐶") === $comentario || strtolower($c["nombre"]) === $comentario) {
                    $estado["consentido_seleccionado"] = $c["nombre"];
                    // Ahora, enviar los botones de servicio principal para este consentido
                    enviarMensajeBoton($numero, "¿Qué servicio desea para " . $c["nombre"] . "?", SERVICIOS_PRINCIPALES);
                    $estado["paso"] = "servicio"; // Va al handler de servicio
                    $consentidoEncontrado = true;
                    break;
                }
            }
        }
        if (!$consentidoEncontrado) {
            enviarTexto($numero, "Estoy aprendiendo y no entendí para qué consentido es.\n \nPor favor, selecciona una de las opciones o escribe 'Otro nuevo'.");
        }
    }
}

function handleServicioAmbos($comentario, $numero, &$estado) {
    // Verifica si la entrada del usuario es uno de los servicios principales
    if (in_array($comentario, SERVICIOS_PRINCIPALES)) {
        $estado["servicios"][] = $comentario; // Guarda el servicio para el consentido actual
        $estado["consentido_actual"]++; // Pasa al siguiente consentido

        if ($estado["consentido_actual"] < count($estado["consentidos"])) {
            $nombre = $estado["consentidos"][$estado["consentido_actual"]]["nombre"];
            enviarMensajeBoton($numero, "¿Qué servicio desea para $nombre?", SERVICIOS_PRINCIPALES);
        } else {
            // Todos los consentidos tienen servicio, ahora preguntar por servicios adicionales para ambos
            $estado["paso"] = "esperando_servicios_adicionales_ambos"; // Nuevo paso específico para ambos
            $botones_adicionales = array_merge(SERVICIOS_ADICIONALES_LISTA, ["Ninguno"]); //acá quite finalizar.
            enviarMensajeBoton($numero, "¿Desean servicios adicionales para ambos consentidos?", $botones_adicionales);
            // El estado de servicios adicionales para "ambos" se manejará de forma unificada en el siguiente paso.
        }
    } else {
        $nombre = $estado["consentidos"][$estado["consentido_actual"]]["nombre"];
        enviarMensajeBoton($numero, "Por favor, selecciona un servicio válido para $nombre.", SERVICIOS_PRINCIPALES);
    }
}

function handleEsperandoServiciosAdicionalesAmbos($comentario, $numero, &$estado) {
    // Si 'servicios_adicionales_ambos' no existe, inicialízalo
    if (!isset($estado["servicios_adicionales_ambos"]) || !is_array($estado["servicios_adicionales_ambos"])) {
        $estado["servicios_adicionales_ambos"] = [];
    }

    $comentarioLower = strtolower($comentario);
    $adicionales_lower = array_map('strtolower', SERVICIOS_ADICIONALES_LISTA);

    if (in_array($comentarioLower, $adicionales_lower)) {
        // Añadir el servicio adicional
        $estado["servicios_adicionales_ambos"][] = $comentario;
        enviarTexto($numero, "Se ha añadido " . $comentario . " para ambos. ¿Desean añadir otro servicio adicional o finalizar?");
        $botones_adicionales = array_merge(SERVICIOS_ADICIONALES_LISTA, ["Finalizar Adicionales"]);
        enviarMensajeBoton($numero, "¿Más servicios adicionales para ambos?", $botones_adicionales);
    } elseif ($comentarioLower === "ninguno" || $comentarioLower === "finalizar adicionales") {
        enviarTexto($numero, "¡Perfecto! Hemos registrado los servicios principales y adicionales para ambos.");
        // Conectar al flujo de selección de turno (Punto 5 del diagrama)
        enviarTexto($numero, "Consultando opciones de agenda..."); // acá puede ir sticker de consultando agenda
        enviarMensajeBoton($numero, "¿Cuál de las siguientes opciones te viene mejor para el turno de ambos?", ["Opción 1: Lunes 10am", "Opción 2: Martes 3pm", "Opción 3: Viernes 11am"]);
        $estado["paso"] = "esperando_fecha_calendario_ambos"; // Nuevo paso específico para turno de ambos
    } else {
        enviarTexto($numero, "Por favor, selecciona una opción válida o 'Ninguno'.");
        $botones_adicionales = array_merge(SERVICIOS_ADICIONALES_LISTA, ["Ninguno"]);
        enviarMensajeBoton($numero, "¿Desean servicios adicionales para ambos consentidos?", $botones_adicionales);
    }
}


function handleFechaTurnoAmbos($comentario, $numero, &$estado) {
    // Este handler ya no debería ser alcanzado si el flujo de ambos converge en esperando_servicios_adicionales_ambos
    // y luego en esperando_fecha_calendario_ambos.
    // Se mantiene como fallback o si el flujo de "ambos" no tiene servicios adicionales.
    
    $estado["fecha"] = $comentario;
    $msg = "Resumen de turnos para:\n";
    foreach ($estado["consentidos"] as $i => $c) {
        $msg .= "🐾 " . $c["nombre"] . ": " . ($estado["servicios"][$i] ?? "No especificado") . "\n";
    }
    if (isset($estado["servicios_adicionales_ambos"]) && !empty($estado["servicios_adicionales_ambos"])) {
        $msg .= "✨ Adicionales (ambos): " . implode(", ", $estado["servicios_adicionales_ambos"]) . "\n";
    }
    $msg .= "📅 Día: " . $estado["fecha"];
    enviarTexto($numero, $msg);
    
    // Conectar a la confirmación/cierre (Punto 6)
    enviarMensajeBoton($numero, "¿Si no deseas el recordatorio, pulsa aquí?", ["Desactivar recordatorio"]);
    $estado["paso"] = "confirmacion_cierre";
}

function handleServicio($comentario, $numero, &$estado) {
    // Verifica si la entrada del usuario es uno de los servicios principales
    if (in_array($comentario, SERVICIOS_PRINCIPALES)) {
        $estado["servicio_principal"] = $comentario; // Guardamos el servicio principal
        // Ahora, preguntar por servicios adicionales
        $botones_adicionales = array_merge(SERVICIOS_ADICIONALES_LISTA, ["Ninguno", "Finalizar Adicionales"]);
        enviarMensajeBoton($numero, "¿Desea servicios adicionales?", $botones_adicionales);
        $estado["paso"] = "esperando_servicios_adicionales";
    } else {
        enviarMensajeBoton($numero, "Por favor, selecciona un servicio válido para " . ($estado["consentido_seleccionado"] ?? $estado["nombre"]) . "?", SERVICIOS_PRINCIPALES);
    }
}

function handleEsperandoServiciosAdicionales($comentario, $numero, &$estado) {
    if (!isset($estado["servicios_adicionales"]) || !is_array($estado["servicios_adicionales"])) {
        $estado["servicios_adicionales"] = [];
    }
    
    $comentarioLower = strtolower($comentario);
    $adicionales_lower = array_map('strtolower', SERVICIOS_ADICIONALES_LISTA);

    if (in_array($comentarioLower, $adicionales_lower)) {
        $estado["servicios_adicionales"][] = $comentario;
        enviarTexto($numero, "Se ha añadido " . $comentario . ". ¿Desea añadir otro servicio adicional o finalizar?");
        $botones_adicionales = array_merge(SERVICIOS_ADICIONALES_LISTA, ["Finalizar Adicionales"]);
        enviarMensajeBoton($numero, "¿Más servicios adicionales?", $botones_adicionales);
    } elseif ($comentarioLower === "ninguno" || $comentarioLower === "finalizar adicionales") {
        enviarTexto($numero, "¡Perfecto! Hemos registrado tu servicio principal y los servicios adicionales.");
        // Conectar al flujo de selección de turno (Punto 5 del diagrama)
        enviarTexto($numero, "Consultando opciones de agenda..."); // Simula consulta al calendario
        enviarMensajeBoton($numero, "¿Cuál de las siguientes opciones te viene mejor para tu turno?", ["Opción 1: Lunes 10am", "Opción 2: Martes 3pm", "Opción 3: Viernes 11am"]);
        $estado["paso"] = "esperando_fecha_calendario"; // Nuevo paso para la selección del calendario
    } else {
        enviarTexto($numero, "Por favor, selecciona una de las opciones de servicio adicional o 'Ninguno'/'Finalizar Adicionales'.");
        $botones_adicionales = array_merge(SERVICIOS_ADICIONALES_LISTA, ["Ninguno", "Finalizar Adicionales"]);
        enviarMensajeBoton($numero, "¿Más servicios adicionales?", $botones_adicionales);
    }
}

function handleEsperandoFechaCalendario($comentario, $numero, &$estado) {
    $opciones_validas = ["opción 1: lunes 10am", "opción 2: martes 3pm", "opción 3: viernes 11am"];
    $comentarioLower = strtolower($comentario);

    if (in_array($comentarioLower, $opciones_validas)) {
        $estado["turno_seleccionado"] = $comentario;
        $consentidoNombre = $estado["consentido_seleccionado"] ?? $estado["nombre"];
        $servicioPrincipal = $estado["servicio_principal"] ?? "No especificado";
        $serviciosAdicionales = isset($estado["servicios_adicionales"]) && !empty($estado["servicios_adicionales"]) ? "\n✨ Adicionales: " . implode(", ", $estado["servicios_adicionales"]) : "";

        // Mensaje de confirmación del turno
        enviarTexto($numero, "Super, tu turno para " . $consentidoNombre . " con servicio de " . $servicioPrincipal . $serviciosAdicionales . " ha sido agendado exitosamente para " . $comentario . ". Te enviaremos un recordatorio 2 horas antes. Esperamos verte pronto. Gracias.");
        
        // Simulación del botón para desactivar recordatorio (Punto 6 del diagrama)
        enviarMensajeBoton($numero, "¿Si no deseas el recordatorio, pulsa aquí?", ["Desactivar recordatorio"]);
        $estado["paso"] = "confirmacion_cierre";
    } else {
        enviarTexto($numero, "Por favor, selecciona una de las opciones de turno válidas.");
        enviarMensajeBoton($numero, "¿Cuál de las siguientes opciones te viene mejor para tu turno?", ["Opción 1: Lunes 10am", "Opción 2: Martes 3pm", "Opción 3: Viernes 11am"]);
    }
}

// Nuevo handler para el flujo de "ambos" después de servicios adicionales
function handleEsperandoFechaCalendarioAmbos($comentario, $numero, &$estado) {
    $opciones_validas = ["opción 1: lunes 10am", "opción 2: martes 3pm", "opción 3: viernes 11am"];
    $comentarioLower = strtolower($comentario);

    if (in_array($comentarioLower, $opciones_validas)) {
        $estado["turno_seleccionado"] = $comentario;
        $msg = "Super, tu turno para:\n";
        foreach ($estado["consentidos"] as $i => $c) {
            $msg .= "🐾 " . $c["nombre"] . ": " . ($estado["servicios"][$i] ?? "No especificado") . "\n";
        }
        if (isset($estado["servicios_adicionales_ambos"]) && !empty($estado["servicios_adicionales_ambos"])) {
            $msg .= "✨ Adicionales (ambos): " . implode(", ", $estado["servicios_adicionales_ambos"]) . "\n";
        }
        $msg .= "ha sido agendado exitosamente para " . $comentario . ". Te enviaremos un recordatorio 2 horas antes. Esperamos verte pronto. Gracias.";
        enviarTexto($numero, $msg);
        
        // Simulación del botón para desactivar recordatorio (Punto 6 del diagrama)
        enviarMensajeBoton($numero, "¿Si no deseas el recordatorio, pulsa aquí?", ["Desactivar recordatorio"]);
        $estado["paso"] = "confirmacion_cierre";
    } else {
        enviarTexto($numero, "Por favor, selecciona una de las opciones de turno válidas.");
        enviarMensajeBoton($numero, "¿Cuál de las siguientes opciones te viene mejor para el turno de ambos?", ["Opción 1: Lunes 10am", "Opción 2: Martes 3pm", "Opción 3: Viernes 11am"]);
    }
}


function handleConfirmacionCierre($comentario, $numero, &$estado) {
    if (strpos(strtolower($comentario), "desactivar recordatorio") !== false) {
        enviarTexto($numero, "El recordatorio ha sido desactivado. ¡Gracias!");
    } else {
        enviarTexto($numero, "Gracias por tu confirmación. ¡Nos vemos pronto!"); // Mensaje genérico si no pulsa el botón específico
    }
    // Aquí podrías guardar la elección del recordatorio en la base de datos del cliente
    $estado["paso"] = "fin";
}

function handleNombre($comentario, $numero, &$estado) {
    $estado["nombre"] = $comentario;
    enviarTexto($numero, "¿Qué raza tiene " . $comentario . "?");
    $estado["paso"] = "raza";
}

function handleRaza($comentario, $numero, &$estado) {
    $razaEncontrada = buscarRaza($comentario);
    if ($razaEncontrada) {
        $estado["raza"] = $razaEncontrada;
        enviarTexto($numero, "Ok, la raza es " . ucfirst($razaEncontrada) . ". ¿Cuál es el peso aproximado de " . $estado["nombre"] . "? (Solo el número en Kg, ej. 14)");
        $estado["paso"] = "peso_aproximado";
    } else {
        enviarTexto($numero, "No pude identificar esa raza. Por favor, intenta escribirla de nuevo o escribe 'No sé' si no estás seguro.");
    }
}

function handlePesoAproximado($comentario, $numero, &$estado) {
    preg_match('/(\d+)/', $comentario, $matches);

    if (isset($matches[1]) && is_numeric($matches[1])) {
        $peso = (int)$matches[1];
        $estado["peso_aproximado"] = $peso . " Kg";
        $botones_banio = ["Menos de 1 mes", "1 a 2 meses", "Más de 2 meses"];
        enviarMensajeBoton($numero, "¿Cuándo fue su último baño o peluquería?", $botones_banio);
        $estado["paso"] = "ultima_visita";
    } else {
        enviarTexto($numero, "Por favor, ingresa el peso solo con números (ej. 14).");
    }
}

function handleUltimaVisita($comentario, $numero, &$estado) {
    // Validar si el comentario es una de las opciones de botón
    $opciones_validas = ["menos de 1 mes", "1 a 2 meses", "más de 2 meses"];
    if (in_array(strtolower($comentario), $opciones_validas)) {
        $estado["ultima_visita"] = $comentario;
        enviarTexto($numero, "¿Qué edad tiene " . $estado["nombre"] . "? (Solo el número de años, ej. 5)");
        $estado["paso"] = "edad";
    } else {
        // Si no es una opción válida, reenviar los botones
        $botones_banio = ["Menos de 1 mes", "1 a 2 meses", "Más de 2 meses"];
        enviarMensajeBoton($numero, "Por favor, selecciona cuándo fue su último baño:", $botones_banio);
    }
}

function handleEdad($comentario, $numero, &$estado) {
    if (is_numeric($comentario) && (int)$comentario > 0) {
        $edad = (int)$comentario;
        $estado["edad"] = $edad . " años";
        enviarTexto($numero, "📸 Por favor, enviá una foto reciente de " . $estado["nombre"] . " para ver el manto.");
        $estado["paso"] = "foto";
    } else {
        enviarTexto($numero, "Por favor, ingresa la edad solo con números (ej. 5).");
    }
}

function handleFoto($comentario, $numero, &$estado) {
    // Asumimos que "foto enviada" o similar es la señal para continuar
    // En un bot real, se procesaría el media ID de la foto recibida.
    // Aquí no se almacena la foto, solo se marca como recibida para avanzar el flujo.

    $nuevoConsentido = [
        "nombre" => $estado["nombre"],
        "raza" => $estado["raza"] ?? "Desconocida",
        "peso_aproximado" => $estado["peso_aproximado"] ?? "Desconocido",
        "ultima_visita" => $estado["ultima_visita"] ?? "Desconocida",
        "edad" => $estado["edad"] ?? "Desconocida",
    ];

    if (clienteExiste($numero)) {
        $clienteActual = obtenerClienteDatos($numero);
        $consentidoYaExiste = false;
        if (isset($clienteActual["consentidos"]) && is_array($clienteActual["consentidos"])) {
            foreach ($clienteActual["consentidos"] as $c) {
                if ($c["nombre"] === $nuevoConsentido["nombre"]) {
                    $consentidoYaExiste = true;
                    break;
                }
            }
        }
        if (!$consentidoYaExiste) {
            $clienteActual["consentidos"][] = $nuevoConsentido;
            actualizarCliente($numero, ["consentidos" => $clienteActual["consentidos"]]);
        }
    } else {
        $datosCliente = ["numero" => $numero, "consentidos" => [$nuevoConsentido]];
        registrarCliente($numero, $datosCliente);
    }

    $estado["consentido_seleccionado"] = $estado["nombre"];
    enviarMensajeBoton($numero, "¿Qué servicio desea para " . $estado["consentido_seleccionado"] . "?", SERVICIOS_PRINCIPALES);
    $estado["paso"] = "servicio";
}


// --- Función Principal de Procesamiento ---

function procesarMensaje($comentario, $numero){
    $comentario = strtolower(trim($comentario));
    $estado = obtenerEstado($numero); // Obtiene el estado actual del usuario

    // Mapa de funciones de manejo de pasos
    $handlers = [
        "inicio" => "handleInicio",
        "esperando_consentido" => "handleEsperandoConsentido",
        "servicio_ambos" => "handleServicioAmbos",
        "esperando_servicios_adicionales_ambos" => "handleEsperandoServiciosAdicionalesAmbos", // Nuevo
        "esperando_fecha_calendario_ambos" => "handleEsperandoFechaCalendarioAmbos", // Nuevo
        "fecha_turno_ambos" => "handleFechaTurnoAmbos", // Puede que este ya no se use directamente para el flujo de "ambos"
        "servicio" => "handleServicio",
        "esperando_servicios_adicionales" => "handleEsperandoServiciosAdicionales", // Nuevo
        "esperando_fecha_calendario" => "handleEsperandoFechaCalendario", // Nuevo
        "confirmacion_cierre" => "handleConfirmacionCierre", // Nuevo
        "fecha_turno" => "handleFechaTurno", // Se mantiene si hay caminos que aún lleguen aquí sin adicionales
        "nombre" => "handleNombre",
        "raza" => "handleRaza",
        "peso_aproximado" => "handlePesoAproximado",
        "ultima_visita" => "handleUltimaVisita",
        "edad" => "handleEdad",
        "foto" => "handleFoto",
    ];

    if (isset($handlers[$estado["paso"]])) {
        $handlers[$estado["paso"]]($comentario, $numero, $estado);
    } else {
        enviarTexto($numero, "¿Querés pedir un turno? Escribí 'hola'.");
        $estado["paso"] = "inicio";
    }

    guardarEstado($numero, $estado); // Guarda el estado actualizado
}

// --- Lógica del Webhook (Puntos de Entrada HTTP) ---

// Asegurar que los directorios existan al inicio de la ejecución del script
asegurarDirectorios();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $mensaje = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    if ($mensaje) {
        $texto = '';
        $numero = $mensaje['from'] ?? '';

        if (isset($mensaje['text']['body'])) {
            $texto = $mensaje['text']['body'];
        } elseif (isset($mensaje['interactive']['button_reply']['title'])) {
            $texto = $mensaje['interactive']['button_reply']['title'];
        } elseif (isset($mensaje['interactive']['list_reply']['title'])) {
            $texto = $mensaje['interactive']['list_reply']['title'];
        }
        // Puedes añadir más tipos de mensajes si tu bot los va a manejar (imágenes, etc.)
        // Para imágenes, la API de WhatsApp envía un message_type 'image' y un 'id' de media.
        // Tendrías que usar el ID para descargar la imagen de Meta.

        if (!empty($numero)) {
            procesarMensaje($texto, $numero);
        }
    }
    echo "EVENT_RECEIVED";
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe' && isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] === TOKEN_SPACONSENTIDOS) {
        echo $_GET['hub_challenge'];
    } else {
        http_response_code(403);
    }
}
?>