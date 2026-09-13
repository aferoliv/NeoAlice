<?php
// Reseta a senha de um aluno para uma senha temporaria ALEATORIA e devolve
// essa senha ao professor. Antes voltava sempre para "123456".
// O acesso e garantido por area_professor/index-app.php (perfil professor).
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['resetar']) || $_POST['resetar'] !== 'S') {
    echo json_encode(array('success' => false, 'msg' => 'Requisição inválida.'));
    exit();
}

$objUsuario = new Usuario();
$senha = $objUsuario->resetarSenhaAluno(isset($_POST['cod_usuario']) ? $_POST['cod_usuario'] : 0);

// null = id inexistente ou que nao pertence a um aluno (Usuario::resetarSenhaAluno
// nao reseta senha de professor/administrador).
if ($senha === null) {
    echo json_encode(array('success' => false, 'msg' => 'Não foi possível resetar a senha desse usuário.'));
    exit();
}

echo json_encode(array('success' => true, 'senha' => $senha, 'msg' => ''));
