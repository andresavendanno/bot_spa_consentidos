<?php

require_once __DIR__ . '/../config/conexion.php';

$conexion = new Conectar();
$pdo = $conexion->conexion();

// 🔐 Clave de seguridad
$CLAVE_SEGURA = 'SpaConsentidos';
if (!isset($_GET['clave']) || $_GET['clave'] !== $CLAVE_SEGURA) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

try {
    // 📅 Obtener primer y último día del mes actual
    $inicioMes = date('Y-m-01'); // 1° del mes
    $finMes    = date('Y-m-t');  // Último día del mes

    // 🛠️ Consulta filtrando por mes actual
    $stmt = $pdo->prepare("
        SELECT 
            id,fecha, hora, consentido, size, servicio, 
            precio, forma_pago, pago_cliente, cliente, telefono, notas, peluquero
        FROM turnos
        WHERE fecha BETWEEN :inicio AND :fin
        ORDER BY fecha, hora, peluquero
    ");

    $stmt->execute([
        ':inicio' => $inicioMes,
        ':fin' => $finMes
    ]);

    $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($turnos, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
