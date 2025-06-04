<?php
class Registro extends Conectar {

    public function insert_registro($log_numero, $log_texto) {
        try {
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] insert_registro llamado con: $log_numero - $log_texto" . PHP_EOL, FILE_APPEND);

            $conectar = parent::conexion();
            parent::set_names();

            $sql = "INSERT INTO tm_log (log_numero, log_texto, fech_crea) VALUES (?, ?, now())";
            $stmt = $conectar->prepare($sql);
            $stmt->bindValue(1, $log_numero);
            $stmt->bindValue(2, $log_texto);
            $stmt->execute();
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "Error insertando en DB: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    public function procesarPaso($numero, $mensaje) {
        try {
            $conectar = parent::conexion();
            parent::set_names();

            $stmt = $conectar->prepare("SELECT * FROM usuario WHERE numero = ?");
            $stmt->execute([$numero]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $stmt = $conectar->prepare("INSERT INTO usuario (numero, paso, fecha_creacion) VALUES (?, 1, now())");
                $stmt->execute([$numero]);
                return "¿Cuál es el nombre de tu consentido?";
            }

            $paso = intval($usuario['paso']);

            switch ($paso) {
                case 1:
                    $stmt = $conectar->prepare("UPDATE usuario SET consentido = ?, paso = 2 WHERE numero = ?");
                    $stmt->execute([$mensaje, $numero]);
                    return "¿Qué raza es *{$mensaje}*?";

                case 2:
                    $stmt = $conectar->prepare("UPDATE usuario SET raza = ?, paso = 3 WHERE numero = ?");
                    $stmt->execute([$mensaje, $numero]);
                    return "¿Qué peso tiene *{$usuario['consentido']}*? (por favor en kg)";

                case 3:
                    $stmt = $conectar->prepare("UPDATE usuario SET peso = ?, paso = 4 WHERE numero = ?");
                    $stmt->execute([$mensaje, $numero]);
                    return "¿Hace cuánto fue su último baño?\n\n1. Menos de 1 mes\n2. Entre 1 y 3 meses\n3. Más de 3 meses\n\nEscribe el número de opción.";

                case 4:
                    $opciones_bano = ["1" => "Menos de 1 mes", "2" => "Entre 1 y 3 meses", "3" => "Más de 3 meses"];
                    $respuesta = $opciones_bano[$mensaje] ?? null;
                    if (!$respuesta) return "Por favor responde con 1, 2 o 3.";
                    $stmt = $conectar->prepare("UPDATE usuario SET ultimo_bano = ?, paso = 5 WHERE numero = ?");
                    $stmt->execute([$respuesta, $numero]);
                    return "¿Qué edad tiene?\n\n1. Menos de 5 años\n2. Entre 5 y 9 años\n3. 10 años o más\n\nEscribe el número de opción.";

                case 5:
                    $opciones_edad = ["1" => "Menos de 5 años", "2" => "Entre 5 y 9 años", "3" => "10 años o más"];
                    $respuesta = $opciones_edad[$mensaje] ?? null;
                    if (!$respuesta) return "Por favor responde con 1, 2 o 3.";

                    $stmt = $conectar->prepare("UPDATE usuario SET edad = ?, paso = 6 WHERE numero = ?");
                    $stmt->execute([$respuesta, $numero]);

                    if ($mensaje == "3") {
                        return "🐾 Para consentidos gerontes solo atendemos en el horario de las *10h* debido a que suelen estresarse más. Nuestra prioridad es que tengan una muy buena experiencia. 😊\n\n¿Deseas contarnos algo adicional? Alergias, heridas, etc.";
                    } else {
                        return "¿Deseas contarnos algo adicional? Alergias, heridas, etc.";
                    }

                case 6:
                    $stmt = $conectar->prepare("UPDATE usuario SET comentario = ?, paso = 7 WHERE numero = ?");
                    $stmt->execute([$mensaje, $numero]);
                    return "Por último, ¿cuál es tu nombre como tutor/a?";

                case 7:
                    $stmt = $conectar->prepare("UPDATE usuario SET tutor = ?, paso = 8 WHERE numero = ?");
                    $stmt->execute([$mensaje, $numero]);

                    return "¡Gracias! Hemos registrado todos los datos de tu consentido 🐶🐾. En breve nos pondremos en contacto contigo. 🙌";

                default:
                    return "¡Hola! Ya tenemos tus datos. Si deseas reiniciar el proceso, escribe *reiniciar*.";
            }
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "Error procesando paso: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return "Lo sentimos, ha ocurrido un error. Intenta nuevamente más tarde.";
        }
    }
}
?>
