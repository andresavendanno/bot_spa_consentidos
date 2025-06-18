<?php
require_once("config/conexion.php");
require_once("models/Registro.php"); // Asegurate de incluir esto si vas a usar Registro


class Usuario extends Conectar {

    public function procesarPaso($numero, $mensaje, $tipoMensaje = "text") {
        file_put_contents("log.txt", "[DEBUG][Usuario.php] Entró a procesarPaso con mensaje bruto: '$mensaje'\n", FILE_APPEND);
        try {
            $conectar = parent::conexion();
            parent::set_names();

            //file_put_contents("log.txt", "[DEBUG][Usuario.php] Conectado a BD\n", FILE_APPEND);

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

            $mensaje = trim(strtolower($mensaje));
            //file_put_contents("log.txt", "[DEBUG][Usuario.php] Mensaje normalizado: '$mensaje'\n", FILE_APPEND);

            // 🔹 Si el mensaje es "hola" o "menu", mostrar botones
            if ($mensaje === "hola" || $mensaje === "menu") {
                file_put_contents("log.txt", "[DEBUG][Usuario.php] Entró al if de 'hola' o 'menu'\n", FILE_APPEND);
              
                $conectar->prepare("DELETE FROM usuarios_temp WHERE numero = ?")->execute([$numero]);
                $conectar->prepare("DELETE FROM servicio_temp WHERE numero = ?")->execute([$numero]);
                $conectar->prepare("DELETE FROM agenda_temp WHERE numero = ?")->execute([$numero]);
                
                $deleteTemp = $conectar->prepare("DELETE FROM servicio_temp WHERE numero = ?");
                $deleteTemp->execute([$numero]);

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

                //file_put_contents("log.txt", "[DEBUG][Usuario.php] Respuesta tipo botones generada: " . print_r($respuesta, true), FILE_APPEND); como se ven los botones  
                return $respuesta;
            }

            // 🔹 Si seleccionó un consentido existente
            if (str_starts_with($mensaje, "consentido_")) {
                $index = (int) str_replace("consentido_", "", $mensaje) - 1;
                if (isset($consentidos[$index])) {
                    $consentido = $consentidos[$index];

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
                }
            }

            // 🔹 Si eligió agregar nuevo
            if ($mensaje === "nuevo") {
                file_put_contents("log.txt", "[DEBUG][Usuario.php] Entró a opción 'nuevo'\n", FILE_APPEND);

                $registro = new Registro();
                return $registro->procesarPaso($numero, "inicio_manual", $tipoMensaje);
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

            file_put_contents("log.txt", "[DEBUG][Usuario.php] Mensaje no reconocido, devolviendo respuesta default\n", FILE_APPEND);
            return "No entendí tu mensaje. Escribe 'menu' para ver tus consentidos.";

        } catch (Exception $e) {
            file_put_contents("error_log.txt", "[Usuario][ERROR] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return "Ocurrió un error al procesar tu solicitud.";
        }
    }
}