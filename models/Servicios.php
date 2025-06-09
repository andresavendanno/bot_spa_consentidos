<?php

require_once __DIR__ . '/../config/conexion.php';

class Servicios {

    public function manejar($mensaje, $usuario) {
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
        global $mysqli;

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

            $stmt = $mysqli->prepare("UPDATE servicio_temp SET tipo_servicio = ?, paso = 10 WHERE numero = ?");
            $stmt->bind_param("ss", $servicio, $numero);
            $stmt->execute();

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
        global $mysqli;

        $numero = $usuario['numero'];
        $consentido = $usuario['consentido'];
        $opciones = [
            '1' => 'Shampoo pulguicida',
            '2' => 'Shampoo hipoalergénico',
            '3' => 'Ninguno'
        ];

        if (isset($opciones[$mensaje]) || in_array(strtolower($mensaje), array_map('strtolower', $opciones))) {
            $adicional = isset($opciones[$mensaje]) ? $opciones[$mensaje] : $mensaje;

            $stmt = $mysqli->prepare("UPDATE servicio_temp SET servicio_adicional = ?, paso = 11 WHERE numero = ?");
            $stmt->bind_param("ss", $adicional, $numero);
            $stmt->execute();

            return "✅ Servicio configurado. ¿Deseas confirmar el agendamiento para *$consentido*? (Sí / No)";
        }

        return "Selecciona una opción válida:
            1. Shampoo pulguicida
            2. Shampoo hipoalergénico
            3. Ninguno";
    }

    private function confirmarYGuardar($mensaje, $usuario) {
        global $mysqli;

        $numero = $usuario['numero'];
        $consentido = $usuario['consentido'];

        if (strtolower($mensaje) === 'sí' || strtolower($mensaje) === 'si') {
            $query = $mysqli->prepare("SELECT * FROM servicio_temp WHERE numero = ?");
            $query->bind_param("s", $numero);
            $query->execute();
            $resultado = $query->get_result();
            $temp = $resultado->fetch_assoc();

            if (!$temp) {
                return "❌ No se encontró información temporal. Inicia nuevamente el proceso.";
            }

            $insert = $mysqli->prepare("INSERT INTO usuarios_servicio (numero, consentido, servicio, adicionales, tutor, comentario) VALUES (?, ?, ?, ?, ?, ?)");
            $tutor = $usuario['tutor'] ?? '';
            $comentario = '';
            $insert->bind_param("ssssss", $temp['numero'], $temp['consentido'], $temp['tipo_servicio'], $temp['servicio_adicional'], $tutor, $comentario);
            $insert->execute();

            $delete = $mysqli->prepare("DELETE FROM servicio_temp WHERE numero = ?");
            $delete->bind_param("s", $numero);
            $delete->execute();

            return "🎉 Tu servicio ha sido agendado exitosamente para *$consentido*. ¡Gracias!";
        }

        return "❗ Por favor responde 'Sí' si deseas confirmar el servicio, o reinicia el proceso.";
    }
}