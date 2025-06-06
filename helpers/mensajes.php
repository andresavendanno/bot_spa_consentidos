<?php
file_put_contents("log.txt", "[DEBUG] mensajes.php fue incluido correctamente\n", FILE_APPEND);

require_once("models/Registro.php");
require_once("models/Usuario.php");
require_once("helpers/funciones.php");


file_put_contents("log.txt", "[DEBUG] A punto de llamar a recibirMensajes()\n", FILE_APPEND);
recibirMensajes($data);

function recibirMensajes($req) {
        file_put_contents("log.txt", "[DEBUG] Entrando a recibirMensajes()\n", FILE_APPEND);// ayuda a ver si al menos está llegando acá
    try {
        file_put_contents("log.txt", "[DEBUG] Entrando a recibirMensajes() try(1)\n", FILE_APPEND);// ayuda a ver si al menos está llegando acá
        file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] 🚀 Webhook llamado\n", FILE_APPEND);
        $input = json_encode($req);
        file_put_contents("log.txt", "[" . date("Y-m-d H:i:s") . "] 🔍 Contenido bruto recibido: $input\n", FILE_APPEND);

        if (!isset($req['entry'][0]['changes'][0]['value']['messages'])) {
            file_put_contents("log.txt", "[DEBUG] No hay mensajes en payload.\n", FILE_APPEND);
            return;
        }

        $mensajeRaw = $req['entry'][0]['changes'][0]['value']['messages'][0];
        $tipoMensaje = $mensajeRaw['type'] ?? 'text';
        $numero = $mensajeRaw['from'] ?? '';
        $idMensaje = $mensajeRaw['id'] ?? '';

        // Interpretar mensaje
        switch ($tipoMensaje) {
            case 'text':
                $comentario = strtolower(trim($mensajeRaw['text']['body'] ?? ''));
                break;
            case 'interactive':
                $comentario = strtolower(trim(
                    $mensajeRaw['interactive']['button_reply']['id'] ??
                    $mensajeRaw['interactive']['list_reply']['id'] ?? ''
                ));
                break;
            default:
                $comentario = '';
        }

        if (empty($comentario) || empty($numero) || empty($idMensaje)) {
            file_put_contents("log.txt", "[DEBUG] Mensaje inválido o incompleto.\n", FILE_APPEND);
            return;
        }

        file_put_contents("log.txt", "[DEBUG] Mensaje recibido de $numero: $comentario\n", FILE_APPEND);

        if (mensajeYaProcesado($idMensaje)) {
            file_put_contents("log.txt", "[DEBUG] Duplicado ignorado: $idMensaje\n", FILE_APPEND);
            return;
        }

        marcarMensajeComoProcesado($idMensaje);
        limpiarMensajesProcesados();

        $conectar = new Conectar();
        $conexion = $conectar->conexion();

        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios_final WHERE numero = ?");
        $stmt->execute([$numero]);
        $esRegistrado = $stmt->fetchColumn() > 0;

        if ($esRegistrado) {
            $usuario = new Usuario();
            $respuesta = $usuario->procesarPaso($numero, $comentario, $tipoMensaje);
        } else {
            $registro = new Registro();
            $respuesta = $registro->procesarPaso($numero, $comentario, $tipoMensaje);
            $registro->insert_log($numero, $comentario);
        }

        EnviarMensajeWhatsApp($respuesta, $numero);

    } catch (Exception $e) {
        file_put_contents("error_log.txt", "[Webhook ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}

