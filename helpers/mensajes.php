<?php
require_once("models/Registro.php");
require_once("models/Usuario.php");
require_once("helpers/funciones.php");
file_put_contents("log.txt", "[MENSAJES] Iniciando recepción de mensaje\n", FILE_APPEND);

function recibirMensajes($req) {
    try {

        if (!isset($req['entry'][0]['changes'][0]['value']['messages'])) {
            file_put_contents("log.txt", "[MENSAJES][DEBUG] No hay mensajes en payload.\n", FILE_APPEND);
            return;
        }

        $mensajeRaw = $req['entry'][0]['changes'][0]['value']['messages'][0];
        $timestamp = intval($mensajeRaw['timestamp'] ?? time());
        $idMensaje = $mensajeRaw['id'] ?? '';
        $tipoMensaje = $mensajeRaw['type'] ?? 'text';
        $numero = $mensajeRaw['from'] ?? '';

        if (mensajeYaProcesado($idMensaje)) {
            file_put_contents("log.txt", "[MENSAJES][".date("Y-m-d H:i:s"."] Duplicado ignorado: $idMensaje\n", FILE_APPEND));
            return;
        }
        if ($timestamp < time() - 300) {
            file_put_contents("log.txt", "[MENSAJES][DEBUG] ⏱️ Mensaje antiguo ignorado: $idMensaje\n", FILE_APPEND);
            return;
        }
        marcarMensajeComoProcesado($idMensaje);
        //file_put_contents("log.txt", "[MENSAJES][DEBUG] Marcado OK\n", FILE_APPEND);
        limpiarMensajesProcesados();

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
                $comentario = '';
                break;
        }

        $mensaje = $comentario;

       // file_put_contents("log.txt", "[MENSAJES][DEBUG] Datos extraídos -> ID: $idMensaje, Comentario: $comentario, Número: $numero\n", FILE_APPEND);

        if (empty($comentario) || empty($numero) || empty($idMensaje)) {
            file_put_contents("log.txt", "[MENSAJES][DEBUG] Mensaje inválido o incompleto, saliendo...\n", FILE_APPEND);
            return;
        }

        //file_put_contents("log.txt", "[MENSAJES][DEBUG] Entrando a verificación de duplicados\n", FILE_APPEND);

        $conectar = new Conectar();
        $conexion = $conectar->conexion();
        //file_put_contents("log.txt", "[MENSAJES][DEBUG] Conexión a base de datos OK\n", FILE_APPEND);

        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios_final WHERE numero = ?");
        $stmt->execute([$numero]);
        $esRegistrado = $stmt->fetchColumn() > 0;

        //file_put_contents("log.txt", "[MENSAJES][DEBUG] Usuario registrado? " . ($esRegistrado ? "Sí" : "No") . "\n", FILE_APPEND);

        if ($esRegistrado) {
          //  file_put_contents("log.txt", "[MENSAJES][DEBUG] Usuario registrado. Revisando si está en flujo...\n", FILE_APPEND);

            $stmt = $conexion->prepare("SELECT * FROM usuarios_temp WHERE numero = ?");
            $stmt->execute([$numero]);
            $usuarioTemp = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuarioTemp) {
                $paso = (int)($usuarioTemp['paso'] ?? 0);
                $usuarioTemp['numero'] = $numero;

                file_put_contents("log.txt", "[MENSAJES][DEBUG] Paso detectado: $paso\n", FILE_APPEND);

                try {
                    if ($paso >= 0 && $paso <= 4) {
                        $registro = new Registro();
                        $respuesta = $registro->procesarPaso($numero, $comentario, $tipoMensaje);
                    } elseif ($paso >= 5 && $paso <= 8) {
                        $usuario = new Usuario();
                        $respuesta = $usuario->procesarPaso($numero, $comentario, $tipoMensaje);
                    } elseif ($paso >= 9 && $paso <= 10) {
                        require_once("models/Servicios.php");
                        $servicios = new Servicios();
                        $respuesta = $servicios->manejar($comentario, $usuarioTemp);
                    } elseif ($paso >= 11) {
                        require_once("models/Agenda.php");
                        $respuesta = guardarTurnoSeleccionado($numero, $usuarioTemp['consentido'], $comentario);
                    } else {
                        $respuesta = "❓ No se pudo determinar en qué parte del proceso estás. Escribí 'menu' para comenzar.";
                    }
                } catch (Throwable $e) {
                    file_put_contents("logs/error.log", "[ERROR][Usuario/Servicios] " . $e->getMessage() . " en línea " . $e->getLine() . PHP_EOL, FILE_APPEND);
                }
            } else {
                $usuario = new Usuario();
                $respuesta = $usuario->procesarPaso($numero, $comentario, $tipoMensaje);
            }

        } else {
            file_put_contents("log.txt", "[MENSAJES][DEBUG] Usuario no registrado. Enviando a Registro.php\n", FILE_APPEND);
            try {
                $registro = new Registro();
                $respuesta = $registro->procesarPaso($numero, $mensaje, $tipoMensaje);
                $registro->insert_log($numero, $comentario);
            } catch (Throwable $e) {
                file_put_contents("logs/error.log", "[MENSAJES][ERROR][Registro] " . $e->getMessage() . " en línea " . $e->getLine() . PHP_EOL, FILE_APPEND);
            }
        }

        EnviarMensajeWhatsApp($respuesta, $numero);

    } catch (Exception $e) {
        file_put_contents("error_log.txt", "[MENSAJES][Webhook ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}
