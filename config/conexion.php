<?php
    
    
    class Conectar{
        protected $dbh;

        protected function conexion(){
            try {
                $host = 'localhost';
                $dbname = 'u268007922_clientes';
                $usuario = 'u268007922_spaconsentidos';
                $clave = 'CONSENTIDOSPORMAmeta05';

                file_put_contents("error_log.txt", "[conexion][DEBUG] Intentando conexión a la BD\n", FILE_APPEND);

                $this->dbh = new PDO("mysql:host=$host;dbname=$dbname", $usuario, $clave);
                file_put_contents("error_log.txt", "[conexion][DEBUG] Conexión establecida con éxito\n", FILE_APPEND);

                return $this->dbh;

            } catch (Throwable $e) {
                file_put_contents("error_log.txt", "[conexion][ERROR] " . $e->getMessage() . " en línea " . $e->getLine() . "\n", FILE_APPEND);
                return null; // evita cortar el flujo bruscamente
            }
        }

        public function set_names(){
            if ($this->dbh instanceof PDO) {
                file_put_contents("error_log.txt", "[conexion][DEBUG] set_names ejecutado\n", FILE_APPEND);
                return $this->dbh->query("SET NAMES 'utf8'");
            } else {
                file_put_contents("error_log.txt", "[conexion][ERROR] DBH no inicializado en set_names()\n", FILE_APPEND);
                return false;
            }
        }

    }