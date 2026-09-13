<?php
// Upload de material didático da prática (caderno / roteiro).
// Revisão de segurança 2026-09:
//  - id_pratica virava diretório direto do $_POST (path traversal);
//  - a extensão vinha do nome enviado pelo cliente, sem lista de permitidos,
//    e uploads/ fica dentro do webroot: dava para subir .php e executá-lo;
//  - o MIME gravado era o declarado pelo cliente e depois ecoado em
//    ModeloPraticaArquivo::getItensHtml() sem escape (XSS armazenado);
//  - mkdir() criava o diretório com permissão 0777.
// O acesso é garantido por area_professor/index_new.php (perfil professor).

header('Content-Type: application/json; charset=utf-8');

// Extensão => MIME real exigido para ela.
$extensoesPermitidas = array(
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
);
$tamanhoMaximo = 10 * 1024 * 1024; // 10 MB
$tiposPratica = array('CADERNO', 'ROTEIRO');

$acao = isset($_POST['acao']) ? $_POST['acao'] : '';

if ($acao == 'deletar') {
    //não estou deletando o arquivo somente do BD
    $objModeloPraticaArquivo = new ModeloPraticaArquivo();
    $objModeloPraticaArquivo->delete($_POST['cod_moprar']);
    $result = array('success' => true, 'msg' => 'Deletado com successo!');
    echo json_encode($result);
    exit();
}

if ($acao == 'salvar') {
    if (empty($_FILES['imagem'])) {
        echo json_encode(array('success' => false, 'msg' => 'Arquivo invalido!'));
        exit();
    }

    if ($_FILES['imagem']['error'] != 0) {
        echo json_encode(array('success' => false, 'msg' => 'Error'));
        exit();
    }

    // (int) elimina "../" e qualquer outro caractere de caminho.
    $idPratica = isset($_POST['id_pratica']) ? (int) $_POST['id_pratica'] : 0;
    if ($idPratica <= 0) {
        echo json_encode(array('success' => false, 'msg' => 'Prática inválida!'));
        exit();
    }

    $tipo = isset($_POST['tipo']) ? (string) $_POST['tipo'] : '';
    if (!in_array($tipo, $tiposPratica, true)) {
        echo json_encode(array('success' => false, 'msg' => 'Tipo de material inválido!'));
        exit();
    }

    if ($_FILES['imagem']['size'] <= 0 || $_FILES['imagem']['size'] > $tamanhoMaximo) {
        echo json_encode(array('success' => false, 'msg' => 'O arquivo deve ter até 10 MB.'));
        exit();
    }

    $extensao = strtolower((string) pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
    if (!isset($extensoesPermitidas[$extensao])) {
        echo json_encode(array(
            'success' => false,
            'msg' => 'Tipo de arquivo não permitido. Envie PDF, PNG, JPG ou GIF.'
        ));
        exit();
    }

    // Confere o conteúdo real do arquivo, não só o nome dele.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($_FILES['imagem']['tmp_name']);
    if ($mimeReal !== $extensoesPermitidas[$extensao]) {
        echo json_encode(array(
            'success' => false,
            'msg' => 'O conteúdo do arquivo não corresponde à extensão informada.'
        ));
        exit();
    }

    $dir = URL_SYSTEM . 'uploads/praticas/' . $idPratica . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        error_log('xhr-arquivos-pratica: falha ao criar ' . $dir);
        echo json_encode(array('success' => false, 'msg' => 'Não foi possível salvar o arquivo.'));
        exit();
    }

    // Nome gerado no servidor: nada que veio do cliente entra no caminho.
    $nomeArquivo = date('Y-m-d--H-i-s') . '--' . bin2hex(random_bytes(8)) . '.' . $extensao;
    $src_img = 'uploads/praticas/' . $idPratica . '/' . $nomeArquivo;

    if (move_uploaded_file($_FILES['imagem']['tmp_name'], URL_SYSTEM . $src_img)) {
        chmod(URL_SYSTEM . $src_img, 0644);
        $objModeloPraticaArquivo = new ModeloPraticaArquivo();
        $args = array(
            'nome_moprar' => basename($_FILES['imagem']['name']),
            'scr_img_moprar' => $src_img,
            'type_img_moprar' => $mimeReal,
            'tipo_moprar' => $tipo,
            'fk_cod_mopr' => $idPratica
        );
        $id = $objModeloPraticaArquivo->insert($args);
        $arquivos = $objModeloPraticaArquivo->getArquivosPratica($idPratica, $tipo);
        $html = $objModeloPraticaArquivo->getItensHtml($arquivos);
        $result = array('success' => true, 'msg' => 'Salvo com successo!!!', 'html' => $html);
    } else {
        $result = array('success' => false, 'msg' => 'Error');
    }
    echo json_encode($result);
    exit();
}
