<?php

require_once("config/conexion.php");
require_once("models/Agenda.php");

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

            return proponerTurnos($numero, $consentido);
        }

        return "Selecciona una opción válida:
        1. Shampoo pulguicida
        2. Shampoo hipoalergénico
        3. Ninguno";
    }
}