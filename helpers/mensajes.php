<?php
require_once("models/Registro.php");
require_once("models/Usuario.php");
require_once("helpers/funciones.php");
file_put_contents("log.txt", "[MENSAJES] Iniciando recepción de mensaje\n", FILE_APPEND);

function recibirMensajes($req) {
    try {
        file_put_contents("log.txt", "[MENSAJES][".date("Y-m-d H:i:s")."] Payload recibido: ".json_encode($req).PHP_EOL, FILE_APPEND); // entro a flujo mensajes

        if (!isset($req['entry'][0]['changes'][0]['value']['messages'])) {
            file_put_contents("log.txt", "[MENSAJES][DEBUG] No hay mensajes en payload.\n", FILE_APPEND);
            return;
        }

        $mensajeRaw = $req['entry'][0]['changes'][0]['value']['messages'][0]; // No usar $mensaje aquí para evitar colisiones
        $timestamp = intval($mensajeRaw['timestamp'] ?? time());
        $idMensaje = $mensajeRaw['id'] ?? '';
        $tipoMensaje = $mensajeRaw['type'] ?? 'text';
        $numero = $mensajeRaw['from'] ?? '';

        if (mensajeYaProcesado($idMensaje)) {
            file_put_contents("log.txt", "[MENSAJES][".date("Y-m-d H:i:s")."] Duplicado ignorado: $idMensaje\n", FILE_APPEND);
            return;
        }
        if ($timestamp < time() - 300) {
            file_put_contents("log.txt", "[MENSAJES][DEBUG] ⏱️ Mensaje antiguo ignorado: $idMensaje\n", FILE_APPEND);
            return;
        }
        marcarMensajeComoProcesado($idMensaje);
        file_put_contents("log.txt", "[MENSAJES][DEBUG] Marcado OK\n", FILE_APPEND);
        limpiarMensajesProcesados();

        // Detectar correctamente el contenido del mensaje según tipo
        switch ($tipoMensaje) {
        case 'text':
            $comentario = strtolower(trim($mensajeRaw['text']['body'] ?? ''));
            break;

        case 'interactive':
            if (isset($mensajeRaw['interactive']['button_reply'])) {
                $comentario = strtolower(trim($mensajeRaw['interactive']['button_reply']['id'] ?? ''));
            } elseif (isset($mensajeRaw['interactive']['list_reply'])) {
                $comentario = strtolower(trim($mensajeRaw['interactive']['list_reply']['id'] ?? ''));
            } else {
                $comentario = '';
            }
            break;

        default:
            $comentario = ''; // No manejado aún, como imágenes, audios, etc.
            break;
    }

    // También puedes definir esto si lo necesitas en otra parte
    $mensaje = $comentario;

        
        file_put_contents("log.txt", "[MENSAJES][DEBUG] Datos extraídos -> ID: $idMensaje, Comentario: $comentario, Número: $numero\n", FILE_APPEND);

        if (empty($comentario) || empty($numero) || empty($idMensaje)) {
            file_put_contents("log.txt", "[MENSAJES][DEBUG] Mensaje inválido o incompleto, saliendo...\n", FILE_APPEND);
            return;
        }

        file_put_contents("log.txt", "[MENSAJES][DEBUG] Entrando a verificación de duplicados\n", FILE_APPEND);

        $conectar = new Conectar();
        $conexion = $conectar->conexion();
        file_put_contents("log.txt", "[MENSAJES][DEBUG] Conexión a base de datos OK\n", FILE_APPEND);

        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios_final WHERE numero = ?");
        $stmt->execute([$numero]);
        $esRegistrado = $stmt->fetchColumn() > 0;

        file_put_contents("log.txt", "[MENSAJES][DEBUG] Usuario registrado? " . ($esRegistrado ? "Sí" : "No") . "\n", FILE_APPEND);

        if ($esRegistrado) {
        file_put_contents("log.txt", "[MENSAJES][DEBUG] Consultando paso actual del usuario...\n", FILE_APPEND);

        try {
            // Obtener paso desde usuarios_temp
            $stmt = $conexion->prepare("SELECT * FROM usuarios_temp WHERE numero = ?");
            $stmt->execute([$numero]);
            $usuarioTemp = $stmt->fetch(PDO::FETCH_ASSOC);

            $paso = (int)($usuarioTemp['paso'] ?? 1);
            $usuarioTemp['numero'] = $numero; // por si no viene
            file_put_contents("log.txt", "[MENSAJES][DEBUG] Prelogica de pasos $paso \n", FILE_APPEND);
            if ($paso >= 0 && $paso <= 4) {
                file_put_contents("log.txt", "[MENSAJES][DEBUG] Paso $paso: Registro.php\n", FILE_APPEND);
                $registro = new Registro();
                $respuesta = $registro->procesarPaso($numero, $comentario, $tipoMensaje);
            } elseif ($paso >= 5 && $paso <= 8) {
                file_put_contents("log.txt", "[MENSAJES][DEBUG] Paso $paso: Usuario.php\n", FILE_APPEND);
                $usuario = new Usuario();
                $respuesta = $usuario->procesarPaso($numero, $comentario, $tipoMensaje);
            } elseif ($paso >= 9 && $paso <= 10) {
                file_put_contents("log.txt", "[MENSAJES][DEBUG] Paso $paso: Servicios.php\n", FILE_APPEND);
                require_once("models/Servicios.php");
                $servicios = new Servicios();
                $respuesta = $servicios->manejar($comentario, $usuarioTemp);
            } elseif ($paso >= 11) {
                file_put_contents("log.txt", "[MENSAJES][DEBUG] Paso $paso: Agenda.php\n", FILE_APPEND);
                require_once("models/Agenda.php");
                $respuesta = guardarTurnoSeleccionado($numero, $usuarioTemp['consentido'], $comentario);
            } else {
                $respuesta = "❓ No se pudo determinar en qué parte del proceso estás. Escribí 'hola' para comenzar.";
            }

         }catch (Throwable $e) {
            file_put_contents("logs/error.log", "[ERROR][Usuario/Servicios] " . $e->getMessage() . " en línea " . $e->getLine() . PHP_EOL, FILE_APPEND);
        }
    } else {
        file_put_contents("log.txt", "[MENSAJES][DEBUG] Procesando paso con Registro.php\n", FILE_APPEND);
        try {
            $registro = new Registro();
            $respuesta = $registro->procesarPaso($numero, $mensaje, $tipoMensaje);
            file_put_contents("error_log.txt", "[MENSAJES][DEBUG] Llamando a insert_log...\n", FILE_APPEND);
            $registro->insert_log($numero, $comentario);  // <- este puede ser problemático
            file_put_contents("error_log.txt", "[MENSAJES][DEBUG] insert_log ejecutado\n", FILE_APPEND);

        } catch (Throwable $e) {
            file_put_contents("logs/error.log", "[MENSAJES][ERROR][Registro] " . $e->getMessage() . " en línea " . $e->getLine() . PHP_EOL, FILE_APPEND);
        }
    }

        //file_put_contents("log.txt", "[MENSAJES][DEBUG] Respuesta recibida para envío: ".print_r($respuesta, true)."\n", FILE_APPEND);

        EnviarMensajeWhatsApp($respuesta, $numero);

    } catch (Exception $e) {
        file_put_contents("error_log.txt", "[MENSAJES][Webhook ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}