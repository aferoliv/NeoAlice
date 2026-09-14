<?php
include('../lab-config.php');
include_once(URL_SYSTEM.'banco/conexao.php');
Login::$permissao_usuario = Perfil::professores();
Login::checkUser();
Seguranca::exigirCsrfEmPost();

// A aba vem da URL. So e aceita se resolver para um arquivo real dentro de
// area_professor/abas; qualquer outra coisa volta para o inicio. Antes esta
// variavel ia direta para o include (path traversal / LFI).
$aba_s = (empty($_REQUEST['aba'])) ? "inicio" : $_REQUEST['aba'];
$aba_arquivo = Seguranca::resolverArquivo(URL_SYSTEM . 'area_professor/abas', array($aba_s));
if ($aba_arquivo === null) {
    $aba_s = "inicio";
    $aba_arquivo = Seguranca::resolverArquivo(URL_SYSTEM . 'area_professor/abas', array($aba_s));
}

// Conta ainda com a senha de fabrica: prende na aba de perfil ate trocar.
if (Login::precisaTrocarSenha() && $aba_s !== 'perfil') {
    $aba_s = 'perfil';
    $aba_arquivo = Seguranca::resolverArquivo(URL_SYSTEM . 'area_professor/abas', array($aba_s));
}
include(URL_SYSTEM.'area_professor/header.php'); 
?>
<body>
  <div class="interno criacaopraticas">
    <div class="container">
      <div class="row menu p-2">
        <div class="col">
          <h1><span>Laboratório de Química</span></h1>
          <!--<h2 id="nomedoprofessor"></h2>-->
        </div>
      </div>

      <section class="menu">
        <div class="botoes">
          <button class="opcoes tab-inicio tab-disciplina <?php echo ($aba_s == 'inicio' ? 'ativo' : '')?>" onclick="window.location = 'index.php?aba=inicio'">Início</button>
          <button class="opcoes tab-alunos <?php echo ($aba_s == 'alunos' ? 'ativo' : '')?>" onclick="window.location = 'index.php?aba=alunos'">Alunos</button>
          <button class="opcoes tab-perfil <?php echo ($aba_s == 'perfil' ? 'ativo' : '')?>" onclick="window.location = 'index.php?aba=perfil'">Meu perfil</button>
          <button class="opcoes tab-sobre <?php echo ($aba_s == 'sobre' ? 'ativo' : '')?>" onclick="window.location = 'index.php?aba=sobre'">Sobre o projeto</button>
          <button class="opcoes tab-sair" onclick="window.location = URL_SITE+'logout.php'">Sair</button>
        </div>
      </section>
      
      <!-- retirando gambiarras -->
      <section class="conteudoabas"> 
          <div class="section">
            <?php include($aba_arquivo); ?>            
          </div>
      </section>
      <!-- /retirando gambiarras -->
    </div>
  </div>            
<script>
const URL_SITE = "<?php echo URL_SITE;?>";
</script>
<?php include('../views/footer.php'); ?>