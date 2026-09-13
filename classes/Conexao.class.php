<?php
class Conexao
{
    private static $db;
    private static $error;

    public static function getInstance(){
        self::conectar();
        return self::$db;
    }

    public static function getError(){
        return self::$error;
    }

    private static function conectar()
    {
        if(self::$db)
            return true;

        try {
            self::$db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASSWORD,
                array(
                    // Sem isto o PDO falha em silencio: os varios
                    // catch (PDOException) espalhados pelo projeto nunca
                    // disparavam e erro de INSERT virava "sucesso".
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                )
            );
        } catch (PDOException $e) {
            self::$error = $e->getMessage();
            self::$db = null;
            return false;
        }
    }
}
?>