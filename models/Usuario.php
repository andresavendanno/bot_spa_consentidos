<?php
require_once("config/conexion.php");

class Usuario extends Conectar {

    public function esUsuarioRegistrado($numero) {
        $conectar = parent::conexion();
        $stmt = $conectar->prepare("SELECT COUNT(*) as total FROM usuarios_final WHERE numero = ?");
        $stmt->execute([$numero]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['total'] > 0;
    }

    public function procesarPaso($numero, $mensaje) {
        try {
            $conectar = parent::conexion();
            parent::set_names();

            // Obtener lista de consentidos
            $stmt = $conectar->prepare("SELECT DISTINCT consentido FROM usuarios_final WHERE numero = ?");
            $stmt->execute([$numero]);
            $consentidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Paso inicial: mostrar opciones
            if (strtolower($mensaje) === "hola" || strtolower($mensaje) === "menu") {
                $opciones = "";
                foreach ($consentidos as $index => $nombre) {
                    $opciones .= ($index + 1) . ". $nombre\n";
                }
                $opciones .= (count($consentidos) > 0 ? (count($consentidos)+1) . ". Otro / Agregar nuevo\n" : "");

                return "¡Hola nuevamente! ¿Con qué consentido deseas continuar?\n\n$opciones\nResponde con el número de la opción.";
            }

            // Interpretar selección
            if (is_numeric($mensaje)) {
                $indice = (int)$mensaje - 1;

                if (isset($consentidos[$indice])) {
                    $seleccionado = $consentidos[$indice];
                    return "Has seleccionado a *$seleccionado*. (Aquí iría el flujo de pedir turno 🕒)";
                }

                if ($indice === count($consentidos)) {
                    return "¿Deseas registrar un *nuevo* consentido o revisar *otros* ya registrados?\n\n1. Registrar nuevo\n2. Ver todos";
                }

                return "Opción no válida. Por favor responde con un número válido.";
            }

            if (strtolower($mensaje) === "1" || strtolower($mensaje) === "nuevo") {
                // Reiniciar flujo de registro
                $registro = new Registro();
                return $registro->procesarPaso($numero, ""); // Se dispara desde paso 1
            }

            if (strtolower($mensaje) === "2" || strtolower($mensaje) === "otros") {
                $texto = "Estos son tus consentidos registrados:\n";
                foreach ($consentidos as $c) {
                    $texto .= "- $c\n";
                }
                return $texto . "\nResponde con el nombre si quieres continuar con alguno.";
            }

            return "No entendí tu mensaje. Puedes escribir 'menu' para ver tus opciones.";

        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[Usuario][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return "Ocurrió un error al procesar tu solicitud. Intenta más tarde.";
        }
    }
}
