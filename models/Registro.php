<?php
require_once("config/conexion.php");

class Registro extends Conectar {

    public function insert_log($numero, $texto) {
        file_put_contents("error_log.txt", "[insert_log][INFO] Numero: $numero, Texto: $texto" . PHP_EOL, FILE_APPEND);
        try {
            $conectar = parent::conexion();
            parent::set_names();
            $sql = "INSERT INTO tm_log (log_numero, log_texto, fech_crea) VALUES (?, ?, now())";
            $stmt = $conectar->prepare($sql);
            $stmt->bindValue(1, $numero);
            $stmt->bindValue(2, $texto);
            $stmt->execute();
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[insert_log][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    public function procesarPaso($numero, $mensaje, $tipoMensaje = "text") {
        try {
            if (!is_string($mensaje)) {
                $mensaje = '';
            }

            $this->insert_log($numero, "Mensaje recibido: " . $mensaje);

            $conectar = parent::conexion();
            parent::set_names();

            // Si ya está registrado definitivamente
            $stmt = $conectar->prepare("SELECT 1 FROM usuarios_final WHERE numero = ?");
            $stmt->execute([$numero]);
            if ($stmt->fetch()) {
                $this->insert_log($numero, "Ya estaba registrado (usuarios_final)");
                return "¡Hola nuevamente! Ya registramos a tu consentido. Si necesitas hacer cambios, háznoslo saber.";
            }

            // Buscar si tiene registro temporal en curso
            $stmt = $conectar->prepare("SELECT * FROM usuarios_temp WHERE numero = ?");
            $stmt->execute([$numero]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Nuevo registro o reinicio manual
            if (!$usuario || $mensaje === "inicio_manual") {
                file_put_contents("error_log.txt", "[procesarPaso][DEBUG] Usuario nuevo o inicio manual, creando registro en usuarios_temp\n", FILE_APPEND);

                // Siempre insertamos uno nuevo (permitiendo múltiples consentidos)
                $stmt = $conectar->prepare("INSERT INTO usuarios_temp (numero, paso, fecha_creacion) VALUES (?, 1, now())");
                $stmt->execute([$numero]);

                $this->insert_log($numero, "Paso 1 iniciado");

                if ($mensaje === "inicio_manual") {
                    return "¡Perfecto! Vamos a registrar a otro consentido 🐶🐱. ¿Cuál es su nombre?";
                } else {
                    return "¡Hola soy BOTita 🐾! ¡Gracias por comunicarte con Spa Consentidos! Veo que eres nuevo. Para registrar a tu consentido, nos gustaría saber su nombre 😊";
                }
            }

            $paso = (int)$usuario['paso'];

            switch ($paso) {
                case 1:
                    $this->actualizarPaso($numero, 'consentido', $mensaje, 2);
                    return "¿Qué raza es {$mensaje}?";

                case 2:
                    $this->actualizarPaso($numero, 'raza', $mensaje, 3);
                    return "¿Cuál es su peso aproximado?\n(Ingresa solo un número entre 5 y 30, sin kg)";

                case 3:
                    if (!is_numeric($mensaje) || $mensaje < 5 || $mensaje > 30) {
                        return "Por favor, ingresa un peso válido entre 5 y 30 (sin letras ni 'kg').";
                    }
                    $this->actualizarPaso($numero, 'peso', $mensaje, 4);
                    return "¿Cuánto tiempo ha pasado desde su último baño?\nResponde con un número en meses.";

                case 4:
                    if (!is_numeric($mensaje) || $mensaje < 0 || $mensaje > 24) {
                        return "Por favor, indica el tiempo en meses con un número válido (0 a 24).";
                    }
                    $this->actualizarPaso($numero, 'ultimo_bano', $mensaje, 5);
                    return "¿Qué edad tiene tu consentido?\n(Ingresa solo un número entre 1 y 25, sin años)";

                case 5:
                    if (!is_numeric($mensaje) || $mensaje < 1 || $mensaje > 25) {
                        return "Por favor, indica una edad válida entre 1 y 25 años.";
                    }
                    $this->actualizarPaso($numero, 'edad', $mensaje, 6);
                    $aviso = ($mensaje > 9) ? "Para consentidos gerontes solo atendemos en el horario de las 10h para garantizar una experiencia tranquila. 🕙\n" : "";
                    return $aviso . "¿Tienes algún comentario adicional? (Alergias, heridas, etc.)";

                case 6:
                    $this->actualizarPaso($numero, 'comentario', $mensaje, 7);
                    return "¿Deseas enviar una foto para ver el estado de su manto?\nPuedes enviarla ahora, o responde con *Sin foto* si no deseas enviar una.";

                case 7:
                    $mensaje_limpio = strtolower(trim($mensaje));
                    if ($mensaje_limpio === "sin foto" || $tipoMensaje === "image") {
                        $this->actualizarSoloPaso($numero, 8); // No se guarda imagen/texto
                        return "¿Cuál es tu nombre?";
                    }
                    return "Si deseas continuar sin foto, responde con *Sin foto*. O bien, envía una imagen.";

                case 8:
                    $this->actualizarPaso($numero, 'tutor', $mensaje, 9);
                    $this->moverAFinal($numero);
                    return "✅ Registro completado, por favor espere mientras lo procesamos.";

                default:
                    return "Hubo un error al identificar el paso. Escribe *reiniciar* para comenzar de nuevo.";
            }

        } catch (Throwable $e) {
            file_put_contents("error_log.txt", "[procesarPaso][ERROR] " . $e->getMessage() . " en línea " . $e->getLine() . PHP_EOL, FILE_APPEND);
            return "Lo siento, ocurrió un error al procesar tu información. Intenta más tarde.";
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

    public function reiniciarRegistro($numero) {
            try {
                $conectar = parent::conexion();
                parent::set_names();
                $conectar->prepare("DELETE FROM usuarios_temp WHERE numero = ?")->execute([$numero]);
                $conectar->prepare("INSERT INTO usuarios_temp (numero, paso, fecha_creacion) VALUES (?, 1, now())")->execute([$numero]);
                file_put_contents("log.txt", "[DEBUG][Registro] Registro reiniciado para $numero\n", FILE_APPEND);
            } catch (Throwable $e) {
                file_put_contents("error_log.txt", "[reiniciarRegistro][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            }
        }
}
