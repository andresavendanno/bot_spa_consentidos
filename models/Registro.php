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
    public function procesarPaso($numero, $mensaje) {
        try {
            file_put_contents("log.txt", "[DEBUG][Registro] procesarPaso iniciado con numero: $numero y mensaje: $mensaje\n", FILE_APPEND);
            $this->insert_log($numero, "Mensaje recibido: " . $mensaje);

            $conectar = parent::conexion();
            parent::set_names();

            // Verificar si ya completó el flujo
            $stmt = $conectar->prepare("SELECT 1 FROM usuarios_final WHERE numero = ?");
            $stmt->execute([$numero]);
            if ($stmt->fetch()) {
                file_put_contents("log.txt", "[DEBUG][Registro] Usuario ya registrado en usuarios_final\n", FILE_APPEND);
                return "¡Hola nuevamente! Ya registramos a tu consentido. Si quieres actualizar algo, por favor háznoslo saber.";
            }

            $stmt = $conectar->prepare("SELECT * FROM usuarios_temp WHERE numero = ?");
            $stmt->execute([$numero]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                file_put_contents("log.txt", "[DEBUG][Registro] Usuario nuevo, iniciando paso 1\n", FILE_APPEND);
                $stmt = $conectar->prepare("INSERT INTO usuarios_temp (numero, paso, fecha_creacion) VALUES (?, 1, now())");
                $stmt->execute([$numero]);
                return "¡Hola soy BOTita 🐾! ¡Gracias por comunicarte con Spa Consentidos! Veo que eres nuevo. Para registrar a tu consentido, nos gustaría saber su nombre 😊";
            }

            $paso = (int)$usuario['paso'];
            file_put_contents("log.txt", "[DEBUG][Registro] Paso actual del usuario: $paso\n", FILE_APPEND);
            $this->insert_log($numero, "Paso actual: $paso. Datos: " . json_encode($usuario));

            switch ($paso) {
                case 1:
                    $this->actualizarPaso($numero, 'consentido', $mensaje, 2);
                    return "¿Qué raza es {$mensaje}?";

                case 2:
                    $this->actualizarPaso($numero, 'raza', $mensaje, 3);
                    return "¿Cuál es su peso aproximado?\n(Ingresa solo un número entre 5 y 30, sin kg)";

                case 3:
                    if (!is_numeric($mensaje) || $mensaje < 5 || $mensaje > 30) {
                        return "Por favor ingresa un número válido entre 5 y 30 para el peso (sin 'kg').";
                    }
                    $this->actualizarPaso($numero, 'peso', $mensaje, 4);
                    return "¿Cuánto tiempo ha pasado desde su último baño?\n(Ingresa solo el número de meses, entre 0 y 12)";

                case 4:
                    if (!is_numeric($mensaje) || $mensaje < 0 || $mensaje > 12) {
                        return "Por favor ingresa un número válido entre 0 y 12 para los meses desde su último baño.";
                    }
                    $this->actualizarPaso($numero, 'ultimo_bano', $mensaje, 5);
                    return "¿Qué edad tiene tu consentido?\n(Ingresa solo un número entre 1 y 25, sin años)";

                case 5:
                    if (!is_numeric($mensaje) || $mensaje < 1 || $mensaje > 25) {
                        return "Por favor ingresa un número válido entre 1 y 25 para la edad.";
                    }
                    $this->actualizarPaso($numero, 'edad', $mensaje, 6);
                    $aviso = ($mensaje > 9) ? "Para consentidos gerontes solo atendemos en el horario de las 10h para garantizar una experiencia tranquila. 🕙\n" : "";
                    return $aviso . "¿Tienes algún comentario adicional? (Alergias, heridas, etc)";

                case 6:
                    $this->actualizarPaso($numero, 'comentario', $mensaje, 7);
                    return "¿Deseas enviar una foto para ver el estado de su manto?\nPuedes enviarla ahora, o responde con *Sin foto* si no deseas enviar una.";

                case 7:
                    if (strtolower($mensaje) !== "sin foto" && strpos(strtolower($mensaje), "image") === false) {
                        return "Si deseas continuar sin foto, responde con *Sin foto*. O bien, envía una imagen.";
                    }
                    $this->actualizarSoloPaso($numero, 8);
                    $this->insert_log($numero, "Foto aceptada (sin guardar), avanzando a paso 8");
                    return "¿Cuál es tu nombre?";

                case 8:
                    $this->actualizarPaso($numero, 'tutor', $mensaje, 9);
                    $this->moverAFinal($numero);
                    return "¡Gracias por registrar a tu consentido 🐶! Hemos guardado toda la información. Te contactaremos pronto 🛁.";

                case 9:
                    return "¡Gracias! Ya hemos terminado. Si necesitas actualizar algo, escríbenos nuevamente.";

                default:
                    return "¡Gracias! Ya hemos terminado. Si necesitas actualizar algo, escríbenos nuevamente.";
            }

        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[procesarPaso][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return "Ocurrió un error. Por favor intenta más tarde.";
        }
    }

    private function actualizarPaso($numero, $campo, $valor, $siguientePaso) {
        try {
            $conectar = parent::conexion();
            $sql = "UPDATE usuarios_temp SET $campo = ?, paso = ? WHERE numero = ?";
            $stmt = $conectar->prepare($sql);
            $stmt->execute([$valor, $siguientePaso, $numero]);
            file_put_contents("log.txt", "[DEBUG][Registro] Actualizado campo $campo a '$valor' y paso a $siguientePaso para $numero\n", FILE_APPEND);
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[actualizarPaso][ERROR] $campo: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
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
