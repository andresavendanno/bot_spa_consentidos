<?php
require_once("config/conexion.php");

class Servicio extends Conectar {

    public function procesarPaso($numero, $mensaje) {
        $mensaje = strtolower(trim($mensaje));

        try {
            $conectar = parent::conexion();
            parent::set_names();

            // Obtener servicio temporal
            $stmt = $conectar->prepare("SELECT * FROM servicio_temp WHERE numero = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$numero]);
            $servicio = $stmt->fetch(PDO::FETCH_ASSOC);

            // Si no hay registro, iniciar flujo
            if (!$servicio) {
                $stmt = $conectar->prepare("INSERT INTO servicio_temp (numero, paso, fecha_creacion) VALUES (?, 1, now())");
                $stmt->execute([$numero]);
                return "🙏 Indica el servicio principal que deseas para tu consentido:
* Baño
* Baño y corte
* Baño y deslanado
* Baño y desenredado";
            }

            $paso = (int)$servicio['paso'];

            switch ($paso) {
                case 1:
                    $this->actualizarPaso($numero, 'tipo_servicio', $mensaje, 2);
                    return "✨ ¿Deseas añadir algún servicio adicional?
* Shampoo pulgicida
* Shampoo analergénico
* Recuperación de manto (mascarilla de argán)
Responde con uno o escribe *ninguno*";

                case 2:
                    $adicional = ($mensaje === 'ninguno') ? null : $mensaje;
                    $this->actualizarPaso($numero, 'servicio_adicional', $adicional, 3);
                    $this->moverAFinal($numero);
                    return "📅 Perfecto. Hemos registrado el servicio. Muy pronto te confirmaremos el turno disponible.";

                default:
                    return "⚠ Hubo un error en el flujo. Escribe 'menu' para volver a empezar.";
            }

        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[Servicio][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return "Ocurrió un error al procesar el servicio. Intenta más tarde.";
        }
    }

    private function actualizarPaso($numero, $campo, $valor, $siguientePaso) {
        $conectar = parent::conexion();
        $sql = "UPDATE servicio_temp SET $campo = ?, paso = ? WHERE numero = ?";
        $stmt = $conectar->prepare($sql);
        $stmt->execute([$valor, $siguientePaso, $numero]);
    }

    private function moverAFinal($numero) {
        try {
            $conectar = parent::conexion();

            // Extraer info de servicio temporal
            $temp = $conectar->prepare("SELECT * FROM servicio_temp WHERE numero = ? ORDER BY id DESC LIMIT 1");
            $temp->execute([$numero]);
            $datos = $temp->fetch(PDO::FETCH_ASSOC);

            // Obtener tutor y comentario desde usuarios_final
            $extra = $conectar->prepare("SELECT tutor, comentario FROM usuarios_final WHERE numero = ? ORDER BY id DESC LIMIT 1");
            $extra->execute([$numero]);
            $info = $extra->fetch(PDO::FETCH_ASSOC);

            // Insertar en usuarios_servicio
            $stmt = $conectar->prepare("INSERT INTO usuarios_servicio (numero, tipo_servicio, servicio_adicional, tutor, comentario, fecha_creacion)
                                        VALUES (?, ?, ?, ?, ?, now())");
            $stmt->execute([
                $numero,
                $datos['tipo_servicio'],
                $datos['servicio_adicional'],
                $info['tutor'] ?? '',
                $info['comentario'] ?? ''
            ]);

            // Limpiar temp
            $conectar->prepare("DELETE FROM servicio_temp WHERE numero = ?")->execute([$numero]);

        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[moverAFinal Servicio][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }
}