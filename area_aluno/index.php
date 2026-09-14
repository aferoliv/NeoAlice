<?php
include('../lab-config.php');
include_once(URL_SYSTEM . 'banco/conexao.php');
Login::$permissao_usuario = Perfil::todos();
Login::checkUser();
Seguranca::exigirCsrfEmPost();

// A aba vem da URL. So e aceita se resolver para um arquivo real dentro de
// area_aluno/abas; qualquer outra coisa volta para o inicio. Antes esta
// variavel ia direta para o include (path traversal / LFI).
$aba_s = (empty($_REQUEST['aba'])) ? "inicio" : $_REQUEST['aba'];
$aba_arquivo = Seguranca::resolverArquivo(URL_SYSTEM . 'area_aluno/abas', array($aba_s));
if ($aba_arquivo === null) {
    $aba_s = "inicio";
    $aba_arquivo = Seguranca::resolverArquivo(URL_SYSTEM . 'area_aluno/abas', array($aba_s));
}

// Conta ainda com a senha de fabrica: prende na aba de perfil ate trocar.
if (Login::precisaTrocarSenha() && $aba_s !== 'perfil') {
    $aba_s = 'perfil';
    $aba_arquivo = Seguranca::resolverArquivo(URL_SYSTEM . 'area_aluno/abas', array($aba_s));
}
include(URL_SYSTEM . 'area_aluno/header.php');
$sessao = Login::getSession();
?>

<body>
  <div class="interno criacaopraticas">
    <div class="container">
      <div class="row menu p-2">
        <div class="col">
          <h1><span>Laboratório de Química</span></h1>
          <h2 id="nomedoaluno"> <?php echo $sessao['nome'] ?></h2>
        </div>
      </div>
      
      <section class="menu">
        <div class="botoes">
          <button class="opcoes tab-inicio tab-disciplina" onclick="window.location ='index.php?aba=inicio'">Início</button>
          <button class="opcoes tab-aulas" onclick="window.location='index.php?aba=aulas'">Práticas</button>
          <button class="opcoes tab-registros" onclick="window.location='index.php?aba=registros'">Minhas ações</button>
          <button class="opcoes tab-perfil" onclick="window.location='index.php?aba=perfil'">Meu perfil</button>
          <button class="opcoes tab-sobre" onclick="window.location.href='index.php?aba=sobre'">Sobre o projeto</button>
          <button class="opcoes tab-sair" onclick="logoff()">Sair</button>
        </div>
      </section>

      <section class="conteudoabas">
        <div class="section">
          <?php include($aba_arquivo); ?>
        </div>
      </section>
    </div>
  </div>

  <script>
    $(function() {
      aba('<?php echo $aba_s; ?>');
    });
  </script>
 <?php include('../views/footer.php'); ?>