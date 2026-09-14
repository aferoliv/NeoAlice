<?php
/**
 * @author Wellerson
 */
include_once ("../lab-config.php");
Login::$permissao_usuario = Perfil::professores();
Login::checkUser();
Seguranca::exigirCsrfEmPost();

// "app" e "file" vinham da URL e eram concatenados direto no include
// (path traversal / LFI). Agora precisam resolver para um arquivo real
// dentro de area_professor/app.
Seguranca::incluirRota(URL_SYSTEM . 'area_professor/app', array(
    isset($_GET['app']) ? $_GET['app'] : '',
    isset($_GET['file']) ? $_GET['file'] : ''
));
?>