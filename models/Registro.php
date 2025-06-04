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
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[insert_log] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    public function procesarPaso($numero, $mensaje) {
        try {
            $conectar = parent::conexion();
            parent::set_names();

            $stmt = $conectar->prepare("SELECT * FROM usuarios_temp WHERE numero = ?");
            $stmt->execute([$numero]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $stmt = $conectar->prepare("INSERT INTO usuarios_temp (numero, paso, fecha_creacion) VALUES (?, 1, now())");
                $stmt->execute([$numero]);
                return "¡Hola soy Botita! ¡Bienvenido a Spa Consentidos! Veo que eres nuevo. Para registrar a tu consentido, por favor dime su nombre.";
            } elseif ((int)$usuario['paso'] === 1 && empty($usuario['consentido'])) {
                $this->actualizarPaso($numero, 'consentido', $mensaje, 2);
                return "¿Qué raza es {$mensaje}?";
            }

            $paso = (int)$usuario['paso'];

            switch ($paso) {
                case 2:
                    $this->actualizarPaso($numero, 'raza', $mensaje, 3);
                    return "¿Cuál es su peso aproximado? (Ej: 5kg, 10kg, 15kg+)";
                case 3:
                    $this->actualizarPaso($numero, 'peso', $mensaje, 4);
                    return "¿Cuánto tiempo ha pasado desde su último baño?\nResponde con una opción:\n• Menos de 1 mes\n• Entre 1 y 3 meses\n• Más de 3 meses";
                case 4:
                    $this->actualizarPaso($numero, 'ultimo_bano', $mensaje, 5);
                    return "¿Qué edad tiene?\nResponde con una opción:\n• Menos de 2 años\n• Entre 2 y 9 años\n• Más de 9 años";
                case 5:
                    $this->actualizarPaso($numero, 'edad', $mensaje, 6);
                    $aviso = (strpos($mensaje, 'Más de 9') !== false)
                        ? "Para consentidos gerontes solo atendemos en el horario de las 10h debido a que suelen estresarse más y nuestra prioridad es que tengan muy buena experiencia.\n"
                        : "";
                    return $aviso . "¿Tienes algún comentario adicional? (Alergias, heridas, etc)";
                case 6:
                    $this->actualizarPaso($numero, 'comentario', $mensaje, 7);
                    return "¿Cuál es tu nombre?";
                case 7:
                    $this->actualizarPaso($numero, 'tutor', $mensaje, 8);
                    $this->moverAFinal($numero);
                    return "¡Gracias por registrar a tu consentido 🐶! Hemos guardado toda la información. Te contactaremos pronto 🛁.";
                default:
                    return "¡Gracias! Ya hemos terminado. Si necesitas actualizar algo, escríbenos nuevamente.";
            }
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[procesarPaso] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return "Ocurrió un error. Por favor intenta más tarde.";
        }
    }

    private function actualizarPaso($numero, $campo, $valor, $siguientePaso) {
        $conectar = parent::conexion();
        $sql = "UPDATE usuarios_temp SET $campo = ?, paso = ? WHERE numero = ?";
        $stmt = $conectar->prepare($sql);
        $stmt->execute([$valor, $siguientePaso, $numero]);
    }

    private function moverAFinal($numero) {
        $conectar = parent::conexion();

        $conectar->prepare("
            INSERT INTO usuarios_final (numero, consentido, raza, peso, ultimo_bano, edad, comentario, tutor, fecha_creacion)
            SELECT numero, consentido, raza, peso, ultimo_bano, edad, comentario, tutor, fecha_creacion
            FROM usuarios_temp WHERE numero = ?
        ")->execute([$numero]);

        $conectar->prepare("DELETE FROM usuarios_temp WHERE numero = ?")->execute([$numero]);
    }
}
?>
