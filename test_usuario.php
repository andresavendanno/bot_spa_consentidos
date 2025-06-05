<?php
require_once("config/conexion.php");
require_once("models/Usuario.php");

// Número de prueba registrado en usuarios_final
$numero = "573506117767";
$mensaje = "hola"; // Puedes cambiar a "menu", "nuevo", etc.

$usuario = new Usuario();
$respuesta = $usuario->procesarPaso($numero, $mensaje);

// Mostrar respuesta en consola o navegador
echo "<pre>";
var_dump($respuesta);
echo "</pre>";

// También puedes guardar en log
file_put_contents("log.txt", "[TEST] Tipo de respuesta: " . gettype($respuesta) . PHP_EOL, FILE_APPEND);
file_put_contents("log.txt", "[TEST] Contenido respuesta: " . print_r($respuesta, true) . PHP_EOL, FILE_APPEND);
?>
