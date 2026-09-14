<?php
/**
 * @author Wellerson
 */
include('../lab-config.php');
Login::$permissao_usuario = Perfil::professores();
Login::checkUser();
Seguranca::exigirCsrfEmPost();
include_once(URL_SYSTEM.'banco/conexao.php');

// Roteador XHR da area do professor (usado por js/abas/editaula.js).
// A aba vinha da URL e era concatenada direto no include (path traversal / LFI).
$aba_s = isset($_REQUEST['aba']) ? $_REQUEST['aba'] : '';
Seguranca::incluirRota(URL_SYSTEM . 'area_professor/abas', array($aba_s));
?>