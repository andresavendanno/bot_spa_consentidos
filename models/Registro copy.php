<?php
require_once("config/conexion.php");

class Registro extends Conectar {

    public function insert_log($numero, $texto) {
        try {
            $conectar = parent::conexion();
            parent::set_names();
            $sql = "INSERT INTO tm_log (log_numero, log_texto, fech_crea) VALUES (?, ?, now())";
            $stmt = $conectar->prepare($sql);
            $stmt->bindValue(1, $numero);
            $stmt->bindValue(2, $texto);
            $stmt->execute();
            file_put_contents("error_log.txt", "[insert_log][EXITO] Numero: $numero, Texto: $texto" . PHP_EOL, FILE_APPEND); // DEBUG
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[insert_log][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    public function procesarPaso($numero, $mensaje) {
        try {
            $this->insert_log($numero, "Mensaje recibido: " . $mensaje); // Log de cada mensaje recibido
            $conectar = parent::conexion();
            parent::set_names();

            $stmt = $conectar->prepare("SELECT * FROM usuarios_temp WHERE numero = ?");
            $stmt->execute([$numero]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                // Nuevo usuario: Inserta y pide el nombre del consentido
                $stmt = $conectar->prepare("INSERT INTO usuarios_temp (numero, paso, fecha_creacion) VALUES (?, 1, now())");
                $stmt->execute([$numero]);
                $this->insert_log($numero, "Nuevo usuario detectado. Iniciando paso 1."); // DEBUG
                return "¡Hola soy Botita! ¡Bienvenido a Spa Consentidos! Veo que eres nuevo. Para registrar a tu consentido, por favor dime su nombre.";
            }

            $paso = (int)$usuario['paso'];
            $this->insert_log($numero, "Paso actual para el usuario: " . $paso . ". Datos actuales: " . json_encode($usuario)); // DEBUG

            // Lógica específica para el paso 1 (nombre del consentido)
            if ($paso === 1 && empty($usuario['consentido'])) {
                $this->actualizarPaso($numero, 'consentido', $mensaje, 2);
                $this->insert_log($numero, "Actualizado 'consentido' a '" . $mensaje . "', moviendo a paso 2."); // DEBUG
                return "¿Qué raza es {$mensaje}?";
            }

            // A partir de aquí, el switch maneja los pasos 2 en adelante
            switch ($paso) {
                case 2:
                    $this->actualizarPaso($numero, 'raza', $mensaje, 3);
                    $this->insert_log($numero, "Actualizado 'raza' a '" . $mensaje . "', moviendo a paso 3."); // DEBUG
                    return "¿Cuál es su peso aproximado? (Ej: 5kg, 10kg, 15kg+)";
                case 3:
                    $this->actualizarPaso($numero, 'peso', $mensaje, 4);
                    $this->insert_log($numero, "Actualizado 'peso' a '" . $mensaje . "', moviendo a paso 4."); // DEBUG
                    return "¿Cuánto tiempo ha pasado desde su último baño?\nResponde con una opción:\n• Menos de 1 mes\n• Entre 1 y 3 meses\n• Más de 3 meses";
                case 4:
                    $this->actualizarPaso($numero, 'ultimo_bano', $mensaje, 5);
                    $this->insert_log($numero, "Actualizado 'ultimo_bano' a '" . $mensaje . "', moviendo a paso 5."); // DEBUG
                    return "¿Qué edad tiene?\nResponde con una opción:\n• Menos de 2 años\n• Entre 2 y 9 años\n• Más de 9 años";
                case 5:
                    $this->actualizarPaso($numero, 'edad', $mensaje, 6);
                    $this->insert_log($numero, "Actualizado 'edad' a '" . $mensaje . "', moviendo a paso 6."); // DEBUG
                    $aviso = (strpos($mensaje, 'Más de 9') !== false)
                        ? "Para consentidos gerontes solo atendemos en el horario de las 10h debido a que suelen estresarse más y nuestra prioridad es que tengan muy buena experiencia.\n"
                        : "";
                    return $aviso . "¿Tienes algún comentario adicional? (Alergias, heridas, etc)";
                case 6:
                    $this->actualizarPaso($numero, 'comentario', $mensaje, 7);
                    $this->insert_log($numero, "Actualizado 'comentario' a '" . $mensaje . "', moviendo a paso 7."); // DEBUG
                    return "¿Cuál es tu nombre?";
                case 7:
                    $this->actualizarPaso($numero, 'tutor', $mensaje, 8);
                    $this->insert_log($numero, "Actualizado 'tutor' a '" . $mensaje . "', moviendo a paso 8."); // DEBUG
                    $this->moverAFinal($numero);
                    $this->insert_log($numero, "Datos movidos a usuarios_final y eliminados de usuarios_temp."); // DEBUG
                    return "¡Gracias por registrar a tu consentido 🐶! Hemos guardado toda la información. Te contactaremos pronto 🛁.";
                case 8: // Caso final, si el usuario sigue hablando después del registro
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
        $conectar = parent::conexion();
        try {
            $sql = "UPDATE usuarios_temp SET $campo = ?, paso = ? WHERE numero = ?";
            $stmt = $conectar->prepare($sql);
            $stmt->execute([$valor, $siguientePaso, $numero]);
            file_put_contents("error_log.txt", "[actualizarPaso][EXITO] Numero: $numero, Campo: $campo, Valor: $valor, SiguientePaso: $siguientePaso" . PHP_EOL, FILE_APPEND); // DEBUG
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[actualizarPaso][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND); // MÁS DETALLE
        }
    }

    private function moverAFinal($numero) {
        $conectar = parent::conexion();
        try {
            // Asegúrate de que las columnas en usuarios_final coincidan con las de usuarios_temp
            $conectar->prepare("
                INSERT INTO usuarios_final (numero, consentido, raza, peso, ultimo_bano, edad, comentario, tutor, fecha_creacion)
                SELECT numero, consentido, raza, peso, ultimo_bano, edad, comentario, tutor, fecha_creacion
                FROM usuarios_temp WHERE numero = ?
            ")->execute([$numero]);

            $conectar->prepare("DELETE FROM usuarios_temp WHERE numero = ?")->execute([$numero]);
            file_put_contents("error_log.txt", "[moverAFinal][EXITO] Datos movidos para numero: $numero" . PHP_EOL, FILE_APPEND); // DEBUG
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[moverAFinal][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND); // MÁS DETALLE
        }
    }
}
?>