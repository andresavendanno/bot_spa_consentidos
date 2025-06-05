<?php
    
    
    class Conectar{
        protected $dbh;

        protected function conexion(){
            try {
                $host = 'localhost';
                $dbname = 'u268007922_clientes';
                $usuario = 'u268007922_spaconsentidos';
                $clave = 'CONSENTIDOSPORMAmeta05';

                $this->dbh = new PDO("mysql:host=$host;dbname=$dbname", $usuario, $clave);
                file_put_contents("log.txt", "[DEBUG] CONEXIÓN ESTABLECIDA\n", FILE_APPEND);

                // Verificamos retorno explícitamente
                if (!$this->dbh) {
                    file_put_contents("log.txt", "[ERROR] CONEXIÓN NO RETORNÓ\n", FILE_APPEND);
                }

                return $this->dbh;

            } catch (Exception $e) {
                file_put_contents("error_log.txt", "[ERROR][BD] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
                die();
            }
        }
        public function set_names(){
            if ($this->dbh instanceof PDO) {
                return $this->dbh->query("SET NAMES 'utf8'");
            } else {
                file_put_contents("error_log.txt", "[ERROR] DBH no inicializado en set_names()" . PHP_EOL, FILE_APPEND);
                return false;
            }
        }
    }
?>