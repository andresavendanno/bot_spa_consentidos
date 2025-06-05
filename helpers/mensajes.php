<?php
require_once("models/Registro.php");
require_once("models/Usuario.php");
require_once("helpers/funciones.php");

function recibirMensajes($req) {
    try {
        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Payload recibido: ".json_encode($req).PHP_EOL, FILE_APPEND);

        if (!isset($req['entry'][0]['changes'][0]['value']['messages'])) {
            file_put_contents("log.txt", "[DEBUG] No hay mensajes en payload.\n", FILE_APPEND);
            return;
        }

        $mensaje = $req['entry'][0]['changes'][0]['value']['messages'][0];
        $idMensaje = $mensaje['id'] ?? '';
        $comentario = strtolower(trim($mensaje['text']['body'] ?? ''));
        $numero = $mensaje['from'] ?? '';
        $tipoMensaje = $mensaje['type'] ?? 'text';


        file_put_contents("log.txt", "[DEBUG] Datos extraídos -> ID: $idMensaje, Comentario: $comentario, Número: $numero\n", FILE_APPEND);

        if (empty($comentario) || empty($numero) || empty($idMensaje)) {
            file_put_contents("log.txt", "[DEBUG] Mensaje inválido o incompleto, saliendo...\n", FILE_APPEND);
            return;
        }

        file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Mensaje de $numero: $comentario".PHP_EOL, FILE_APPEND);
        file_put_contents("log.txt", "[DEBUG] Entrando a verificación de duplicados\n", FILE_APPEND);

        if (mensajeYaProcesado($idMensaje)) {
            file_put_contents("log.txt", "[DEBUG] Ya procesado, ignorando\n", FILE_APPEND);
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] Duplicado ignorado: $idMensaje\n", FILE_APPEND);
            return;
        }

        file_put_contents("log.txt", "[DEBUG] No es duplicado, marcando como procesado\n", FILE_APPEND);
        marcarMensajeComoProcesado($idMensaje);
        file_put_contents("log.txt", "[DEBUG] Marcado OK\n", FILE_APPEND);
        limpiarMensajesProcesados();
        file_put_contents("log.txt", "[DEBUG] Limpieza OK\n", FILE_APPEND);

        file_put_contents("log.txt", "[DEBUG] Antes de conectar a BD\n", FILE_APPEND);
        $conectar = new Conectar();
        $conexion = $conectar->conexion();
        file_put_contents("log.txt", "[DEBUG] Conexión a base de datos OK\n", FILE_APPEND);

        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios_final WHERE numero = ?");
        $stmt->execute([$numero]);
        $esRegistrado = $stmt->fetchColumn() > 0;

        file_put_contents("log.txt", "[DEBUG] Usuario registrado? " . ($esRegistrado ? "Sí" : "No") . "\n", FILE_APPEND);

        if ($esRegistrado) {
    file_put_contents("log.txt", "[DEBUG] Procesando paso con Usuario.php\n", FILE_APPEND);
    try {
            $usuario = new Usuario();
            $respuesta = $usuario->procesarPaso($numero, $comentario, $tipoMensaje);
        } catch (Throwable $e) {
            file_put_contents("logs/error.log", "[ERROR][Usuario] " . $e->getMessage() . " en línea " . $e->getLine() . PHP_EOL, FILE_APPEND);
        }
    } else {
        file_put_contents("log.txt", "[DEBUG] Procesando paso con Registro.php\n", FILE_APPEND);
        try {
            $registro = new Registro();
            $respuesta = $registro->procesarPaso($numero, $mensaje, $tipoMensaje);
            file_put_contents("error_log.txt", "[DEBUG] Llamando a insert_log...\n", FILE_APPEND);
            $registro->insert_log($numero, $comentario);  // <- este puede ser problemático
            file_put_contents("error_log.txt", "[DEBUG] insert_log ejecutado\n", FILE_APPEND);

        } catch (Throwable $e) {
            file_put_contents("logs/error.log", "[ERROR][Registro] " . $e->getMessage() . " en línea " . $e->getLine() . PHP_EOL, FILE_APPEND);
        }
    }

        file_put_contents("log.txt", "[DEBUG] Respuesta recibida para envío: ".print_r($respuesta, true)."\n", FILE_APPEND);

        EnviarMensajeWhatsApp($respuesta, $numero);

    } catch (Exception $e) {
        file_put_contents("error_log.txt", "[Webhook ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}
