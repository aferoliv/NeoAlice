<?php
try {
    $banco = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASSWORD,
        array(
            // Sem isto o PDO falha em silencio: os varios catch (PDOException)
            // espalhados pelo projeto nunca disparavam.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        )
    );
    $dbh = $banco;
} catch (PDOException $e) {
    $error_message = $e->getMessage();
}

function labRootPath()
{
    $old = getcwd();
    $path = pathinfo(__FILE__);
    chdir(__DIR__);
    $real = realpath("../");
    chdir($old);
    return $real;
}

// Define uma constante para facilitar a cricacao de includes
define('LAB_ROOT', labRootPath());
// Inclui as classes para manipulacao de dados
include(LAB_ROOT . "/classes/LabJogo.class.php");

// Deixa uma instancia da classe jogo preparada para o usuario
$lab = new LabJogo();
?>