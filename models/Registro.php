<?php
require_once("config/conexion.php");

class Registro extends Conectar {

   function insert_log($numero, $texto) {
    file_put_contents("error_log.txt", "[insert_log][INFO] Numero: $numero, Texto: $texto" . PHP_EOL, FILE_APPEND);

    try {
        $conectar = parent::conexion();
        parent::set_names();

        $sql = "INSERT INTO tm_log (log_numero, log_texto, fech_crea) VALUES (?, ?, now())";
        $stmt = $conectar->prepare($sql);
        $stmt->bindValue(1, $numero);
        $stmt->bindValue(2, $texto);
        $stmt->execute();

        file_put_contents("error_log.txt", "[insert_log][OK] Insert ejecutado correctamente\n", FILE_APPEND);

    } catch (Throwable $e) {
        file_put_contents("error_log.txt", "[insert_log][ERROR] " . $e->getMessage() . " en línea " . $e->getLine() . PHP_EOL, FILE_APPEND);
    }
}
    function procesarPaso($numero, $mensaje) {
        try {
            file_put_contents("error_log.txt", "[procesarPaso][DEBUG] Iniciado con numero: $numero, mensaje: $mensaje\n", FILE_APPEND);
            $this->insert_log($numero, "Mensaje recibido: " . $mensaje);

            $conectar = parent::conexion();
            parent::set_names();

            // Verificar si ya completó el flujo
            $stmt = $conectar->prepare("SELECT 1 FROM usuarios_final WHERE numero = ?");
            $stmt->execute([$numero]);
            if ($stmt->fetch()) {
                $this->insert_log($numero, "Ya estaba registrado (usuarios_final)");
                return "¡Hola nuevamente! Ya registramos a tu consentido. Si quieres actualizar algo, por favor háznoslo saber.";
            }

            $stmt = $conectar->prepare("SELECT * FROM usuarios_temp WHERE numero = ?");
            $stmt->execute([$numero]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                file_put_contents("error_log.txt", "[procesarPaso][DEBUG] Usuario nuevo, creando registro en usuarios_temp\n", FILE_APPEND);
                $stmt = $conectar->prepare("INSERT INTO usuarios_temp (numero, paso, fecha_creacion) VALUES (?, 1, now())");
                $stmt->execute([$numero]);
                $this->insert_log($numero, "Paso 1 iniciado");
                return "¡Hola soy BOTita 🐾! ¡Gracias por comunicarte con Spa Consentidos! Veo que eres nuevo. Para registrar a tu consentido, nos gustaría saber su nombre 😊";
            }

            $paso = (int)$usuario['paso'];
            $this->insert_log($numero, "Paso actual: $paso. Datos: " . json_encode($usuario));

            switch ($paso) {
                case 1:
                    $this->actualizarPaso($numero, 'consentido', $mensaje, 2);
                    $this->insert_log($numero, "Nombre recibido: $mensaje");
                    return "¿Qué raza es {$mensaje}?";

                case 2:
                    $this->actualizarPaso($numero, 'raza', $mensaje, 3);
                    $this->insert_log($numero, "Raza recibida: $mensaje");
                    return "¿Qué edad tiene?";

                case 3:
                    $this->actualizarPaso($numero, 'edad', $mensaje, 4);
                    $this->insert_log($numero, "Edad recibida: $mensaje");
                    return "¿Qué servicio deseas agendar?";

                case 4:
                    $this->actualizarPaso($numero, 'servicio', $mensaje, 5);
                    $this->insert_log($numero, "Servicio recibido: $mensaje");
                    return "¡Gracias! ¿Deseas agendar una cita para este servicio?";

                default:
                    $this->insert_log($numero, "Paso desconocido: $paso");
                    return "Parece que hubo un problema con tu registro. Por favor escribe 'reiniciar' para comenzar de nuevo.";
            }

        } catch (Throwable $e) {
            file_put_contents("error_log.txt", "[procesarPaso][ERROR] " . $e->getMessage() . " en línea " . $e->getLine() . "\n", FILE_APPEND);
            return "Lo siento, ocurrió un error al procesar tu información. Intenta más tarde.";
        }
    }

    private function actualizarSoloPaso($numero, $siguientePaso) {
        try {
            $conectar = parent::conexion();
            $sql = "UPDATE usuarios_temp SET paso = ? WHERE numero = ?";
            $stmt = $conectar->prepare($sql);
            $stmt->execute([$siguientePaso, $numero]);
            file_put_contents("log.txt", "[DEBUG][Registro] Solo paso actualizado a $siguientePaso para $numero\n", FILE_APPEND);
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[actualizarSoloPaso][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }
    private function actualizarPaso($numero, $campo, $valor, $siguientePaso) {
        try {
            $conectar = parent::conexion();
            parent::set_names();

            $sql = "UPDATE usuarios_temp SET $campo = ?, paso = ? WHERE numero = ?";
            $stmt = $conectar->prepare($sql);
            $stmt->execute([$valor, $siguientePaso, $numero]);

            file_put_contents("log.txt", "[DEBUG][Registro] Paso actualizado ($campo = $valor, paso = $siguientePaso) para $numero\n", FILE_APPEND);
        } catch (Throwable $e) {
            file_put_contents("error_log.txt", "[actualizarPaso][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    private function moverAFinal($numero) {
        try {
            $conectar = parent::conexion();
            $conectar->prepare("
                INSERT INTO usuarios_final (numero, consentido, raza, peso, ultimo_bano, edad, comentario, tutor, fecha_creacion)
                SELECT numero, consentido, raza, peso, ultimo_bano, edad, comentario, tutor, fecha_creacion
                FROM usuarios_temp WHERE numero = ?
            ")->execute([$numero]);

            $conectar->prepare("DELETE FROM usuarios_temp WHERE numero = ?")->execute([$numero]);

            file_put_contents("log.txt", "[DEBUG][Registro] Datos movidos a usuarios_final y limpiado usuarios_temp para $numero\n", FILE_APPEND);
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[moverAFinal][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }
}
