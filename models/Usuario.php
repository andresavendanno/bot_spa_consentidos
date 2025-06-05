<?php
require_once("config/conexion.php");

class Usuario extends Conectar {

    public function procesarPaso($numero, $mensaje) {
        file_put_contents("log.txt", "[DEBUG] Usuario::procesarPaso ejecutado con mensaje: $mensaje\n", FILE_APPEND);
    try {
        $conectar = parent::conexion();
        parent::set_names();

        // Obtener consentidos
        $stmt = $conectar->prepare("SELECT DISTINCT consentido FROM usuarios_final WHERE numero = ?");
        $stmt->execute([$numero]);
        $consentidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Paso inicial con botones
        if (strtolower($mensaje) === "hola" || strtolower($mensaje) === "menu") {
            $botones = [];
            foreach ($consentidos as $i => $c) {
                $botones[] = [
                    "type" => "reply",
                    "reply" => [
                        "id" => "consentido_" . ($i + 1),
                        "title" => $c
                    ]
                ];
            }

            // Botón para nuevo consentido
            $botones[] = [
                "type" => "reply",
                "reply" => [
                    "id" => "nuevo",
                    "title" => "➕ Nuevo consentido"
                ]
            ];

            return [
                "type" => "interactive",
                "interactive" => [
                    "type" => "button",
                    "body" => [
                        "text" => "¿Con qué consentido deseas continuar?"
                    ],
                    "action" => [
                        "buttons" => $botones
                    ]
                ]
            ];
        }

        // Procesar selección de botón
        if (str_starts_with($mensaje, "consentido_")) {
            $index = (int) str_replace("consentido_", "", $mensaje) - 1;
            if (isset($consentidos[$index])) {
                return "Has seleccionado a *{$consentidos[$index]}*. (Aquí iría el flujo de pedir turno 🕒)";
            }
        }

        if ($mensaje === "nuevo") {
            $registro = new Registro();
            return $registro->procesarPaso($numero, ""); // Iniciar nuevo flujo
        }

        return "No entendí tu mensaje. Escribe 'menu' para ver tus consentidos.";

    } catch (Exception $e) {
        file_put_contents("error_log.txt", "[Usuario][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        return "Ocurrió un error al procesar tu solicitud.";
    }
}
}
