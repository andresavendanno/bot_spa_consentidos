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
    
    // 🛠️ Consulta filtrando por mes actual
    $stmt = $pdo->prepare("
        SELECT 
            id,raza,tamaño,servicio,forma_pago,precio
        FROM precios
        ORDER BY tamaño
    ");

    $stmt->execute();

    $precios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($precios, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
