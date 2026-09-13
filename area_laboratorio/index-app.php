<?php
/**
 * @author Wellerson
 */
include_once ("../lab-config.php");
// Faltava qualquer verificacao de login aqui: todos os endpoints de
// area_laboratorio/app estavam abertos ao publico.
Login::$permissao_usuario = Perfil::todos();
Login::checkUser();

// "app" e "file" vinham da URL e eram concatenados direto no include
// (path traversal / LFI).
Seguranca::incluirRota(URL_SYSTEM . 'area_laboratorio/app', array(
    isset($_GET['app']) ? $_GET['app'] : '',
    isset($_GET['file']) ? $_GET['file'] : ''
));
?>