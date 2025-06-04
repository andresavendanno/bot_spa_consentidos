<?php
    
    
    class Conectar{
        protected $dbh;

        protected function conexion(){
            try{
                $host = 'localhost';         // o mysql.hostinger.com o localhost
                $dbname = 'u268007922_clientes';
                $usuario = 'u268007922_spaconsentidos';
                $clave = 'CONSENTIDOSPORMAmeta05';

                $conectar = $this->dbh = new PDO("mysql:host=$host;dbname=$dbname",$usuario,$clave);
                return $conectar;
            }catch(Exception $e){
                file_put_contents("error_log.txt", "Error BD: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
                die();
            }
        }
        public function set_names(){
            return $this->dbh->query("SET NAMES 'utf8'");
        }
    }
?>