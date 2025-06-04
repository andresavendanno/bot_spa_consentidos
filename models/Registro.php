<?php
class Registro extends Conectar {

    public function insert_registro($log_numero, $log_texto) {
        try {
            // Log para depurar
            file_put_contents("log.txt", "[".date("Y-m-d H:i:s")."] insert_registro llamado con: $log_numero - $log_texto" . PHP_EOL, FILE_APPEND);

            $conectar = parent::conexion();
            parent::set_names();

            $sql = "INSERT INTO tm_log (log_numero, log_texto, fech_crea) VALUES (?, ?, now())";
            $stmt = $conectar->prepare($sql);
            $stmt->bindValue(1, $log_numero);
            $stmt->bindValue(2, $log_texto);
            $stmt->execute();
        } catch (Exception $e) {
            file_put_contents("error_log.txt", "Error insertando en DB: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }
}
?>