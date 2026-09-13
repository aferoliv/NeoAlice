<?php
// Remove um aluno. O acesso e garantido por area_professor/index-app.php
// (perfil professor) e Usuario::deleteUser so apaga contas de aluno.
header('Content-Type: application/json; charset=utf-8');

$objUsuario = new Usuario();
$removido = $objUsuario->deleteUser(isset($_POST['cod_usuario']) ? $_POST['cod_usuario'] : 0);

if (!$removido) {
    echo json_encode(array('success' => false, 'msg' => 'Não foi possível remover esse usuário.'));
    exit();
}

echo json_encode(array('success' => true, 'msg' => ''));
