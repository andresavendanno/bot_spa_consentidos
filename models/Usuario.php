<?php
require_once("config/conexion.php");
require_once("models/Registro.php"); 
require_once("models/servicio.php");

class Usuario extends Conectar {

    public function procesarPaso($numero, $mensaje, $tipoMensaje = "text") {
        file_put_contents("log.txt", "[DEBUG][Usuario.php] Entró a procesarPaso con mensaje bruto: '$mensaje'\n", FILE_APPEND);
        try {
            $conectar = parent::conexion();
            parent::set_names();

            file_put_contents("log.txt", "[DEBUG][Usuario.php] Conectado a BD\n", FILE_APPEND);

            // 🔄 Si está en medio de un registro, redirigir a Registro.php
            $stmt = $conectar->prepare("SELECT * FROM usuarios_temp WHERE numero = ?");
            $stmt->execute([$numero]);
            $enRegistro = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($enRegistro) {
                file_put_contents("log.txt", "[Usuario.php][DEBUG] Usuario está en registro. Redirigiendo a Registro.php\n", FILE_APPEND);
                $registro = new Registro();
                return $registro->procesarPaso($numero, $mensaje, $tipoMensaje);
            }

            // 🔹 Obtener consentidos
            $stmt = $conectar->prepare("SELECT DISTINCT consentido FROM usuarios_final WHERE numero = ?");
            $stmt->execute([$numero]);
            $consentidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            file_put_contents("log.txt", "[DEBUG][Usuario.php] Consentidos obtenidos: " . print_r($consentidos, true), FILE_APPEND);

            $mensaje = trim(strtolower($mensaje));
            file_put_contents("log.txt", "[DEBUG][Usuario.php] Mensaje normalizado: '$mensaje'\n", FILE_APPEND);

            // 🔹 Si el mensaje es "hola" o "menu", mostrar botones
            if ($mensaje === "hola" || $mensaje === "menu") {
                file_put_contents("log.txt", "[DEBUG][Usuario.php] Entró al if de 'hola' o 'menu'\n", FILE_APPEND);

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

                file_put_contents("log.txt", "[DEBUG][Usuario.php] Respuesta tipo botones generada: " . print_r($respuesta, true), FILE_APPEND);
                return $respuesta;
            }

            // 🔹 Si seleccionó un consentido existente
            if (str_starts_with($mensaje, "consentido_")) {
                $index = (int) str_replace("consentido_", "", $mensaje) - 1;
                file_put_contents("log.txt", "[DEBUG][Usuario.php] Seleccionó consentido index: $index\n", FILE_APPEND);

                if (isset($consentidos[$index])) {
                    $consentido = $consentidos[$index];
                    file_put_contents("log.txt", "[DEBUG][Usuario.php] Consentido seleccionado: $consentido\n", FILE_APPEND);

                    // 🔹 Llamamos al nuevo flujo de selección de servicio
                    $servicio = new Servicio();
                    return $servicio->procesarPaso($numero, "inicio");
                } else {
                    file_put_contents("log.txt", "[ERROR][Usuario.php] Consentido index no encontrado\n", FILE_APPEND);
                    return "El consentido seleccionado no existe. Intenta de nuevo.";
                }
            }

            // 🔹 Si eligió agregar nuevo
            if ($mensaje === "nuevo") {
                file_put_contents("log.txt", "[DEBUG][Usuario.php] Entró a opción 'nuevo'\n", FILE_APPEND);

                $registro = new Registro();
                return $registro->procesarPaso($numero, "inicio_manual", $tipoMensaje);
            }

            file_put_contents("log.txt", "[DEBUG][Usuario.php] Mensaje no reconocido, devolviendo respuesta default\n", FILE_APPEND);
            return "No entendí tu mensaje. Escribe 'menu' para ver tus consentidos.";

        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[Usuario][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return "Ocurrió un error al procesar tu solicitud.";
        }
    }
}