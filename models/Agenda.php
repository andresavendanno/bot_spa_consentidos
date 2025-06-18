<?php

require_once __DIR__ . '/../config/conexion.php';

function obtenerTurnosDisponibles($numero, $consentido, $limite = 2) {
    $conexion = new Conectar();
    $pdo = $conexion->conexion();

    $turnos = [];
    $mapaDias = [
        'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'
    ];
    $hoy = new DateTime();

    try {
        $stmtServicio = $pdo->prepare("SELECT servicio FROM usuarios_servicio WHERE numero = :numero AND consentido = :consentido ORDER BY fecha_creacion DESC LIMIT 1");
        $stmtServicio->execute([':numero' => $numero, ':consentido' => $consentido]);
        $servicio = $stmtServicio->fetchColumn();

        $stmtsize = $pdo->prepare("SELECT size FROM usuarios_final WHERE numero = :numero AND consentido = :consentido ORDER BY id DESC LIMIT 1");
        $stmtsize->execute([':numero' => $numero, ':consentido' => $consentido]);
        $size = $stmtsize->fetchColumn();

        $stmtPrecio = $pdo->prepare("SELECT precio FROM precios WHERE servicio = :servicio AND size = :size AND forma_pago = 'efectivo' LIMIT 1");
        $stmtPrecio->execute([':servicio' => $servicio, ':size' => $size]);
        $precio = $stmtPrecio->fetchColumn();

        for ($i = 0; $i < 7 && count($turnos) < $limite; $i++) {
            $fecha = clone $hoy;
            $fecha->modify("+{$i} day");
            $dia = $mapaDias[$fecha->format('l')];
            $fechaSQL = $fecha->format('Y-m-d');

            $stmtHorario = $pdo->prepare("SELECT * FROM horarios_peluqueros WHERE dia = :dia");
            $stmtHorario->execute([':dia' => $dia]);
            $horarios = $stmtHorario->fetchAll(PDO::FETCH_ASSOC);

            foreach ($horarios as $horario) {
                $inicio = new DateTime($horario['hora_inicio']);
                $fin = new DateTime($horario['hora_fin']);
                $fin->modify('-1 hour');

                while ($inicio <= $fin && count($turnos) < $limite) {
                    $horaStr = $inicio->format('H:i:s');

                    $stmtOcupado = $pdo->prepare("SELECT COUNT(*) FROM agenda WHERE fecha = :fecha AND hora = :hora AND peluquero = :peluquero");
                    $stmtOcupado->execute([
                        ':fecha' => $fechaSQL,
                        ':hora' => $horaStr,
                        ':peluquero' => $horario['peluquero']
                    ]);

                    if ($stmtOcupado->fetchColumn() == 0) {
                        $turnos[] = [
                            'fecha' => $fechaSQL,
                            'hora' => substr($horaStr, 0, 5),
                            'peluquero' => $horario['peluquero'],
                            'precio' => $precio,
                            'servicio' => $servicio,
                            'size' => $size
                        ];
                    }

                    $inicio->modify('+1 hour');
                }
            }
        }

        return $turnos;

    } catch (PDOException $e) {
        return ['error' => 'Error en BD: ' . $e->getMessage()];
    }
}

function proponerTurnos($numero, $consentido) {
    $conexion = new Conectar();
    $pdo = $conexion->conexion();

    $turnos = obtenerTurnosDisponibles($numero, $consentido);
    if (isset($turnos['error'])) return "❌ Error al obtener turnos: " . $turnos['error'];

    if (empty($turnos)) return "🎉 Tu servicio fue registrado para *$consentido*, pero no hay turnos disponibles esta semana. Te contactaremos pronto.";

    $mensaje = "🎉 Tu servicio fue registrado para *$consentido*\n";
    $mensaje .= "📅 Aquí hay opciones de agenda disponibles:\n";

    foreach ($turnos as $i => $turno) {
        $fecha = DateTime::createFromFormat('Y-m-d', $turno['fecha']);
        $diaNombre = ucfirst(strftime('%A', $fecha->getTimestamp()));
        $diaNumero = $fecha->format('d');
        $mensaje .= ($i + 1) . ". $diaNombre $diaNumero a las " . $turno['hora'] . "\n";
    }

    $mensaje .= "\nResponde con el número de opción para reservar, o escribe 'Cancelar' si querés dejarlo pendiente.";

    $stmtTemp = $pdo->prepare("INSERT INTO agenda_temp (numero, consentido, turnos_json)
        VALUES (:numero, :consentido, :json)
        ON DUPLICATE KEY UPDATE turnos_json = VALUES(turnos_json), opcion_seleccionada = NULL");
    $stmtTemp->execute([
        ':numero' => $numero,
        ':consentido' => $consentido,
        ':json' => json_encode($turnos)
    ]);

    $pdo->prepare("UPDATE usuarios_temp SET paso = 11 WHERE numero = :numero")
        ->execute([':numero' => $numero]);

    return $mensaje;
}

function guardarTurnoSeleccionado($numero, $consentido, $opcion) {
    if (strtolower(trim($opcion)) === 'cancelar') {
        $pdo = (new Conectar())->conexion();
        $pdo->prepare("DELETE FROM agenda_temp WHERE numero = :numero AND consentido = :consentido")
            ->execute([':numero' => $numero, ':consentido' => $consentido]);
        $pdo->prepare("UPDATE usuarios_temp SET paso = 0 WHERE numero = :numero")
            ->execute([':numero' => $numero]);

        return "🛑 Agendamiento cancelado. Si querés iniciar nuevamente, escribí 'menu'.";
    }

    $conexion = new Conectar();
    $pdo = $conexion->conexion();

    try {
        $stmt = $pdo->prepare("SELECT turnos_json FROM agenda_temp WHERE numero = :numero AND consentido = :consentido");
        $stmt->execute([':numero' => $numero, ':consentido' => $consentido]);
        $json = $stmt->fetchColumn();
        if (!$json) return "❌ No se encontró ninguna agenda activa para confirmar.";

        $turnos = json_decode($json, true);
        $index = (int)$opcion - 1;
        if (!isset($turnos[$index])) return "❌ Opción inválida. Por favor elige una opción de la lista.";

        $turno = $turnos[$index];

        $verificar = $pdo->prepare("SELECT COUNT(*) FROM agenda WHERE fecha = :fecha AND hora = :hora AND peluquero = :peluquero");
        $verificar->execute([
            ':fecha' => $turno['fecha'],
            ':hora' => $turno['hora'],
            ':peluquero' => $turno['peluquero']
        ]);
        if ($verificar->fetchColumn() > 0) {
            return "❌ Ese turno ya fue tomado. Por favor elegí otra opción o escribí 'menu' para comenzar de nuevo.";
        }

        $stmtTutor = $pdo->prepare("SELECT tutor FROM usuarios_final WHERE numero = :numero AND consentido = :consentido ORDER BY id DESC LIMIT 1");
        $stmtTutor->execute([':numero' => $numero, ':consentido' => $consentido]);
        $tutor = $stmtTutor->fetchColumn();

        $insert = $pdo->prepare("INSERT INTO agenda 
            (fecha, hora, peluquero, consentido, size, servicio, adicional, precio, forma_pago, pago_cliente, tutor, numero, notas)
            VALUES (:fecha, :hora, :peluquero, :consentido, :size, :servicio, :adicional, :precio, 'efectivo', 0, :tutor, :numero, '')");

        $insert->execute([
            ':fecha' => $turno['fecha'],
            ':hora' => $turno['hora'],
            ':peluquero' => $turno['peluquero'],
            ':consentido' => $consentido,
            ':size' => $turno['size'],
            ':servicio' => $turno['servicio'],
            ':adicional' => $turno['adicional'] ?? '',
            ':precio' => $turno['precio'],
            ':tutor' => $tutor ?? '',
            ':numero' => $numero
        ]);

        $pdo->prepare("DELETE FROM agenda_temp WHERE numero = :numero AND consentido = :consentido")
            ->execute([':numero' => $numero, ':consentido' => $consentido]);

        $pdo->prepare("UPDATE usuarios_temp SET paso = 0 WHERE numero = :numero")
            ->execute([':numero' => $numero]);

        return "✅ ¡Listo! Turno reservado el *{$turno['fecha']}* a las *{$turno['hora']}* con *{$turno['peluquero']}* para *$consentido*.";

    } catch (PDOException $e) {
        return "❌ Error al guardar turno: " . $e->getMessage();
    }
}
