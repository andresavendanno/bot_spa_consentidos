<?php

require_once __DIR__ . '/../config/conexion.php';

function obtenerTurnosDisponibles($numero, $consentido, $limite = 2) {
    $conexion = new Conectar();
    $pdo = $conexion->conexion();

    $turnos = [];
    $diasSemana = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];

    $hoy = new DateTime();

    try {
        // Obtener servicio desde usuarios_servicio
        $stmtServicio = $pdo->prepare("SELECT servicio FROM usuarios_servicio WHERE numero = :numero AND consentido = :consentido ORDER BY fecha_creacion DESC LIMIT 1");
        $stmtServicio->execute([
            ':numero' => $numero,
            ':consentido' => $consentido
        ]);
        $servicio = $stmtServicio->fetchColumn();

        // Obtener tamaño desde usuarios_final
        $stmtsize = $pdo->prepare("SELECT size FROM usuarios_final WHERE numero = :numero AND consentido = :consentido ORDER BY id DESC LIMIT 1");
        $stmtsize->execute([
            ':numero' => $numero,
            ':consentido' => $consentido
        ]);
        $size = $stmtsize->fetchColumn();

        // Obtener precio estimado (en efectivo)
        $stmtPrecio = $pdo->prepare("SELECT precio FROM precios WHERE servicio = :servicio AND size = :size AND forma_pago = 'efectivo' LIMIT 1");
        $stmtPrecio->execute([':servicio' => $servicio, ':size' => $size]);
        $precio = $stmtPrecio->fetchColumn();

        for ($i = 0; $i < 7 && count($turnos) < $limite; $i++) {
            $fecha = clone $hoy;
            $fecha->modify("+{$i} day");
            $dia = ucfirst($fecha->format('l'));
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
    $turnos = obtenerTurnosDisponibles($numero, $consentido);

    if (isset($turnos['error'])) {
        return "❌ Error al obtener turnos: " . $turnos['error'];
    }

    if (count($turnos)) {
        $mensaje = "🎉 Tu servicio fue registrado para *$consentido*\n";
        $mensaje .= "📅 Aquí hay opciones de agenda disponibles:\n";
        foreach ($turnos as $i => $turno) {
            $mensaje .= ($i + 1) . ". " . $turno['fecha'] . " a las " . $turno['hora'] ."\n";
        }
        $mensaje .= "\nResponde con el número de opción para reservar, o escribe 'Cancelar' si querés dejarlo pendiente.";
    } else {
        $mensaje = "🎉 Tu servicio fue registrado para *$consentido*, pero no hay turnos disponibles esta semana. Te contactaremos pronto.";
    }

    return $mensaje;
}
