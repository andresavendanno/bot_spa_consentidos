<?php
require_once("config/conexion.php");
require_once("helpers/funciones.php");

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

            // Obtener el registro más reciente para este número
            $stmt = $conectar->prepare("SELECT * FROM usuarios_temp WHERE numero = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$numero]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario || $mensaje === "inicio_manual") {
                file_put_contents("log.txt", "[Registro][DEBUG] Usuario nuevo o inicio_manual. Insertando...\n", FILE_APPEND);

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
                    return "¿Cuál es su peso aproximado?\n(Ingresa solo un número entre 3 y 60, sin kg)";

                case 3:
                    if (!is_numeric($mensaje) || $mensaje < 3 || $mensaje > 60) {
                        return "Por favor, ingresa un peso válido entre 3 y 60 (sin letras ni 'kg').";
                    }
                    $this->actualizarPaso($numero, 'peso', $mensaje, 4);
                    return "¿Cuánto tiempo ha pasado desde su último baño?\nResponde con un número en meses.";

                case 4:
                    if (!is_numeric($mensaje) || $mensaje < 0 || $mensaje > 6) {
                        return "Por favor, indica el tiempo en meses con un número válido (0 a 6).";
                    }
                    $this->actualizarPaso($numero, 'ultimo_bano', $mensaje, 5);
                    return "¿Qué edad tiene tu consentido?\n(Ingresa solo un número entre 1 y 15, sin años)";

                case 5:
                    if (!is_numeric($mensaje) || $mensaje < 1 || $mensaje > 15) {
                        return "Por favor, indica una edad válida entre 1 y 15 años.";
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
                        $this->actualizarSoloPaso($numero, 8);
                        return "¿Cuál es tu nombre?";
                    }

                    return "Si deseas continuar sin foto, responde con *Sin foto*. O bien, envía una imagen.";

                case 8:
                    $consentido = $usuario['consentido']; 
                    $this->actualizarPaso($numero, 'tutor', $mensaje, 9);
                    $this->moverAFinal($numero);
                    

                // 1. Guardar consentido en la tabla temporal
                    
                    $sql1 = "INSERT INTO servicio_temp (numero, consentido, paso)
                            VALUES (:numero, :consentido, 9)
                            ON DUPLICATE KEY UPDATE consentido = VALUES(consentido), paso = 9";
                    $stmt1 = $conectar->prepare($sql1);
                    $stmt1->execute([
                        ':numero' => $numero,
                        ':consentido' => $consentido
                    ]);

                    $sql2 = "UPDATE usuarios_temp SET paso = 9, consentido = :consentido WHERE numero = :numero";
                    $stmt2 = $conectar->prepare($sql2);
                    $stmt2->execute([
                        ':consentido' => $consentido,
                        ':numero' => $numero
                    ]);

                    file_put_contents("log.txt", "[DEBUG][Usuario.php] Consentido guardado y paso actualizado a 9\n", FILE_APPEND);

                    // 3. No devolver mensaje aquí: lo maneja webhook.php → Servicios.php
                    require_once("models/Servicios.php");
                    $servicios = new Servicios();
                    $respuesta = $servicios->manejar("inicio", [ // puedes enviar "" o algo que dispare el paso 9
                        'numero' => $numero,
                        'consentido' => $consentido,
                        'paso' => 9
                    ]);
                    return $respuesta;
                    
                    //return "✅ Registro completado, por favor espere mientras lo procesamos.";
                    
                    



                default:
                    return "Hubo un error al identificar el paso. Escribe *reiniciar* para comenzar de nuevo.";
            }

                        // 🔁 Verificar si el usuario está en flujo de Servicios (paso 9, 10, 11)
            $stmtPaso = $conectar->prepare("SELECT paso, consentido FROM servicio_temp WHERE numero = ?");
            $stmtPaso->execute([$numero]);
            $enFlujo = $stmtPaso->fetch(PDO::FETCH_ASSOC);

            if ($enFlujo) {
                require_once("models/Servicios.php");
                $servicios = new Servicios();
                return $servicios->manejar($mensaje, [
                    'numero' => $numero,
                    'consentido' => $enFlujo['consentido'],
                    'paso' => $enFlujo['paso']
                ]);
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
                INSERT INTO usuarios_final (numero, consentido, raza, peso, size, ultimo_bano, edad, comentario, tutor, fecha_creacion)
                SELECT numero, consentido, raza, peso, 
                CASE
                    WHEN peso <= 5 THEN 'pequeño'
                    WHEN peso > 5 AND peso < 20 THEN 'mediano'
                    WHEN peso >20  AND peso < 30 THEN 'grande'
                    ELSE 'especial'
                END as size, ultimo_bano, edad, comentario, tutor, fecha_creacion
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