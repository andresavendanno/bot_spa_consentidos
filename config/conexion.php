<?php
    $host = 'spaconsentidos.website';         // o mysql.hostinger.com o localhost
    $dbname = 'u268007922_clientes';
    $usuario = 'u268007922_spaconsentidos';
    $clave = 'CONSENTIDOSPORMAmeta05';
    
    class Conectar{
        protected $dbh;

        protected function conexion(){
            try{
                $conectar = $this->dbh = new PDO("mysql:local=localhost;dbname=$dbname","$usuario","$clave");
                return $conectar;
            }catch(Exception $e){
                print "Error BD:" . $e->getMessage() . "<br>";
                die();
            }
        }
        public function set_names(){
            return $this->dbh->query("SET NAMES 'utf8'");
        }
    }
?>