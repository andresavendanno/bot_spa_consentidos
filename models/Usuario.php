<?php
require_once("config/conexion.php");

class Usuario extends Conectar {

    public function procesarPaso($numero, $mensaje) {
        file_put_contents("log.txt", "[DEBUG][Usuario.php] Entró a procesarPaso con mensaje: '$mensaje'\n", FILE_APPEND);
        try {
            // 🔹 CONEXIÓN Y NOMBRE DE COLUMNA
            
            file_put_contents("log.txt", "[DEBUG] Entrando a Usuario.php con mensaje: $mensaje\n", FILE_APPEND);

            $conectar = parent::conexion();
            parent::set_names();

            // 🔹 Obtener consentidos asociados a este número
            $stmt = $conectar->prepare("SELECT DISTINCT consentido FROM usuarios_final WHERE numero = ?");
            $stmt->execute([$numero]);
            $consentidos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            file_put_contents("log.txt", "[DEBUG][Usuario.php] Comparando mensaje: '".strtolower($mensaje)."'\n", FILE_APPEND);
            $mensaje = trim(strtolower($mensaje));
            
            // 🔹 Si dice "hola" o "menu", responder con botones
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

                // Botón adicional
                $botones[] = [
                    "type" => "reply",
                    "reply" => [
                        "id" => "nuevo",
                        "title" => "➕ Nuevo consentido"
                    ]
                ];

                $respuesta = [
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

                // 🔹 LOG justo antes de devolver la respuesta
                file_put_contents("log.txt", "[DEBUG] Respuesta generada en Usuario.php: " . print_r($respuesta, true) . PHP_EOL, FILE_APPEND);
                return $respuesta;
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
