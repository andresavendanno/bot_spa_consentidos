<?php
require_once("models/Registro.php");
require_once("models/Usuario.php");
require_once("helpers/funciones.php");

function recibirMensajes($req) {
    try {
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Payload recibido: ".json_encode($req).PHP_EOL, FILE_APPEND);

        if (!isset($req['entry'][0]['changes'][0]['value']['messages'])) return;

        $mensaje = $req['entry'][0]['changes'][0]['value']['messages'][0];
        $idMensaje = $mensaje['id'] ?? '';
        $comentario = strtolower(trim($mensaje['text']['body'] ?? ''));
        $numero = $mensaje['from'] ?? '';

        if (empty($comentario) || empty($numero) || empty($idMensaje)) return;

        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Mensaje de $numero: $comentario".PHP_EOL, FILE_APPEND);

        file_put_contents("log.txt", "[DEBUG] Entrando a verificación de duplicados" . PHP_EOL, FILE_APPEND);
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

        file_put_contents("log.txt", "[DEBUG] Antes de conectar a BD".PHP_EOL, FILE_APPEND);
        $conectar = new Conectar();
        $conexion = $conectar->conexion();
        file_put_contents("log.txt", "[DEBUG] Conectando a base de datos...".PHP_EOL, FILE_APPEND);
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios_final WHERE numero = ?");
        $stmt->execute([$numero]);
        $esRegistrado = $stmt->fetchColumn() > 0;

        if ($esRegistrado) {
            $usuario = new Usuario();
            $respuesta = $usuario->procesarPaso($numero, $comentario);
        } else {
            $registro = new Registro();
            $respuesta = $registro->procesarPaso($numero, $comentario);
            $registro->insert_registro($numero, $comentario);
        }

        EnviarMensajeWhatsApp($respuesta, $numero);

    } catch (Exception $e) {
        file_put_contents("error_log.txt", "[Webhook ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}