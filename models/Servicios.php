<?php

require_once("config/conexion.php");

class Servicios extends Conectar {

    public function manejar($mensaje, $usuario) {
        file_put_contents("log.txt", "[DEBUG][Servicios.php] Entró a manejar con paso: {$usuario['paso']}, mensaje: '{$mensaje}'\n", FILE_APPEND);

        $paso = $usuario['paso'];
        $consentido = $usuario['consentido'];

        switch ($paso) {
            case 9:
                return $this->seleccionarServicio($mensaje, $usuario);

            case 10:
                return $this->serviciosAdicionales($mensaje, $usuario);

            case 11:
                return $this->confirmarYGuardar($mensaje, $usuario);

            default:
                return "🐾 Bienvenido al sistema de agendamiento. Por favor selecciona un consentido para comenzar.";
        }
    }

    private function seleccionarServicio($mensaje, $usuario) {
        $conectar = parent::conexion();

        $numero = $usuario['numero'];
        $consentido = $usuario['consentido'];

        $opciones = [
            '1' => 'Baño',
            '2' => 'Baño y corte',
            '3' => 'Baño y deslanado',
            '4' => 'Baño y desenredado'
        ];

        if (isset($opciones[$mensaje]) || in_array(strtolower($mensaje), array_map('strtolower', $opciones))) {
            $servicio = isset($opciones[$mensaje]) ? $opciones[$mensaje] : $mensaje;

            $sql = "UPDATE servicio_temp SET tipo_servicio = :servicio, paso = 10 WHERE numero = :numero";
            $stmt = $conectar->prepare($sql);
            $stmt->execute([
                ':servicio' => $servicio,
                ':numero' => $numero
            ]);

            return "¿Deseas agregar algún servicio adicional para *$consentido*?
        1. Shampoo pulguicida
        2. Shampoo hipoalergénico
        3. Ninguno";
                }

            return "Por favor selecciona un servicio válido para *$consentido*:
        1. Baño
        2. Baño y corte
        3. Baño y deslanado
        4. Baño y desenredado";
    }

    private function serviciosAdicionales($mensaje, $usuario) {
        file_put_contents("log.txt", "[DEBUG][Servicios.php] Paso 10: servicio adicional recibido: '$mensaje' para {$usuario['consentido']}\n", FILE_APPEND);

        $conectar = parent::conexion();

        $numero = $usuario['numero'];
        $consentido = $usuario['consentido'];
        $opciones = [
            '1' => 'Shampoo pulguicida',
            '2' => 'Shampoo hipoalergénico',
            '3' => 'Ninguno'
        ];

        if (isset($opciones[$mensaje]) || in_array(strtolower($mensaje), array_map('strtolower', $opciones))) {
            $adicional = isset($opciones[$mensaje]) ? $opciones[$mensaje] : $mensaje;

            $sql = "UPDATE servicio_temp SET servicio_adicional = :adicional, paso = 11 WHERE numero = :numero";
            $stmt = $conectar->prepare($sql);
            $stmt->execute([
                ':adicional' => $adicional,
                ':numero' => $numero
            ]);

            return "✅ Servicio configurado. ¿Deseas confirmar el agendamiento para *$consentido*? (Sí / No)";
        }

            return "Selecciona una opción válida:
        1. Shampoo pulguicida
        2. Shampoo hipoalergénico
        3. Ninguno";
    }

    private function confirmarYGuardar($mensaje, $usuario) {
        file_put_contents("log.txt", "[DEBUG][Servicios.php] Paso 11: confirmación recibida: '$mensaje' para {$usuario['consentido']}\n", FILE_APPEND);

        //$conectar = parent::conexion();

        $numero = $usuario['numero'];
        $consentido = $usuario['consentido'];
        if (strtolower($mensaje) === 'sí' || strtolower($mensaje) === 'si') {
            $conectar = parent::conexion();

            // 1. Obtener los datos temporales de servicio
            $query = $conectar->prepare("SELECT * FROM servicio_temp WHERE numero = :numero");
            $query->execute([':numero' => $numero]);
            $temp = $query->fetch(PDO::FETCH_ASSOC);

            if (!$temp) {
                file_put_contents("log.txt", "[ERROR][Servicios.php] No se encontró registro en servicio_temp para $numero\n", FILE_APPEND);
                return "❌ No se encontró información temporal. Inicia nuevamente el proceso.";
            }

            // 2. Obtener tutor y comentario desde usuarios_final
            $stmt = $conectar->prepare("SELECT tutor, comentario FROM usuarios_final WHERE numero = :numero AND consentido = :consentido ORDER BY id DESC LIMIT 1");
            $stmt->execute([
                ':numero' => $numero,
                ':consentido' => $consentido
            ]);
            $datos = $stmt->fetch(PDO::FETCH_ASSOC);

            $tutor = $datos['tutor'] ?? '';
            $comentario = $datos['comentario'] ?? '';

            // 3. Guardar servicio confirmado
            $sql = "INSERT INTO usuarios_servicio (numero, consentido, servicio, adicionales, tutor, comentario)
                    VALUES (:numero, :consentido, :servicio, :adicionales, :tutor, :comentario)";
            $stmtInsert = $conectar->prepare($sql);
            $stmtInsert->execute([
                ':numero' => $temp['numero'],
                ':consentido' => $temp['consentido'],
                ':servicio' => $temp['tipo_servicio'],
                ':adicionales' => $temp['servicio_adicional'],
                ':tutor' => $tutor,
                ':comentario' => $comentario
            ]);

            // 4. Limpiar temporal
            $delete = $conectar->prepare("DELETE FROM servicio_temp WHERE numero = :numero");
            $delete->execute([':numero' => $numero]);

            file_put_contents("log.txt", "[DEBUG][Servicios.php] Servicio guardado exitosamente para $numero\n", FILE_APPEND);

            return "🎉 Tu servicio ha sido agendado exitosamente para *$consentido*. ¡Gracias!";
        }
        return "❗ Por favor responde 'Sí' si deseas confirmar el servicio, o reinicia el proceso.";
    }
}