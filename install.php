<?php

/**
 * Arquivo de configuração
 * @version 1.0.0
 */
// Antes: error_reporting(0). Erros vao para o log, nunca para a resposta.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

include_once "classes/Conexao.class.php";
include_once "classes/InstalacaoLab.class.php";
include_once "classes/Seguranca.class.php";
include_once "classes/Login.class.php";
include_once "classes/Usuario.class.php";
include_once "classes/Perfil.class.php";

/**
 * O instalador ficava acessivel para sempre, mesmo com o laboratorio ja no ar:
 * dava para varrer a rede interna pelo formulario de conexao (as mensagens de
 * erro do PDO voltavam para a tela) e reexecutar o processo de instalacao.
 * Agora, uma vez que exista lab-config.php E o banco ja tenha tabelas, esta
 * pagina nao faz mais nada.
 */
function instalacaoConcluida()
{
    if (!file_exists(InstalacaoLab::$config_file)) {
        return false;
    }
    include_once InstalacaoLab::$config_file;
    $db = Conexao::getInstance();
    if (!$db) {
        return false;
    }
    try {
        $stmt = $db->query('SHOW TABLES');
        return (bool) $stmt->fetchAll();
    } catch (PDOException $e) {
        return false;
    }
}

if (instalacaoConcluida()) {
    Seguranca::abortar(
        410,
        "O laboratorio ja esta instalado. Se precisar reinstalar, remova "
        . "lab-config.php e esvazie o banco de dados pelo servidor."
    );
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$error_message = isset($_REQUEST['error_message']) ? $_REQUEST['error_message'] : '';
$error_title = isset($_REQUEST['error_title']) ? $_REQUEST['error_title'] : '';
$file_criar = '';
$new_password = '';
// Estados
$estado = "index";
// Verifica o estado atual do processo de instalacao
switch ($action) {
  case "criar-config":
    if (!InstalacaoLab::verificaConexaoDB()) {
      $error_title = 'Erro ao tentar acessar o banco de dados';
      $error_message = Conexao::getError();
    } else {
      $resp = InstalacaoLab::criarConfiguracoes();
      if ($resp['success']) {
        header("location:install.php?action=instalar");
        exit;
      } else {
        $error_title = 'Erro';
        $error_message = $resp['msg'];
        $file_criar = $resp['file'];
        $estado = 'criar-file';
      }
    }
    break;
  case "instalar":
    include_once (InstalacaoLab::$config_file);
    $resi = InstalacaoLab::verificaInstalacao();
    if ($resi['success']) {
      $resp = InstalacaoLab::instalarBDProjeto();
      if ($resp['success']) {
        $estado = 'instalado';
        // Antes era fixo em '123456'. Agora o instalador sorteia a senha do
        // administrador e a exibe uma unica vez, nesta tela.
        $new_password = $resp['senha_admin'];
      } else {
        header("location:install.php?error_title=Erro&error_message=" . urlencode($resp['msg']));
        exit;
      }
    }else {
      header("location:install.php?error_title=Erro&error_message=" . urlencode($resi['msg']));
      exit;
    }
    break;
}
?>
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset="UTF-8">
    <title>NeoAlice - Instalação</title>
    <!-- Arquivos externos -->
    <!-- jquery -->
    <script src="plugins/vendor/jquery/3.4/jquery-3.4.1.min.js"></script>
    <script src="plugins/vendor/jquery/3.4/jquery-migrate-1.4.1.min.js"></script>

    <!-- bootstrap -->
    <script src="plugins/vendor/bootstrap/4.3.1/dist/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="plugins/vendor/bootstrap/4.3.1/dist/css/bootstrap.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css">

    <!-- Arquivos próprios -->
    <link rel="stylesheet" href="estilos/basicos.css">
    <link rel="stylesheet" href="estilos/style.css">
    <link rel="shortcut icon" type="image/png" href="imagens/icons/favicon.png" />
  </head>

  <body>
    <div class="section login">
      <div class="container">
        <div class="row d-flex justify-content-center align-items-center">

          <div class="caixinha interna">
            <h1><br />Laboratório Virtual de Química</h1>
            <h2>Universidade Federal de Viçosa</h2>

            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <polygon points="0,0 0,100 100,100" />
            </svg>
            <div class="content">
              <?php
              if ($error_message) {
                ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <?php // Vinham da query string e eram ecoados crus (XSS refletido). ?>
                  <?php echo "<strong>" . Seguranca::h($error_title) . ":</strong> " . Seguranca::h($error_message); ?>
                  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
              <?php
              }
              ?>
              <?php
              if ($estado == "index") :
                ?>
                <form class="opcoeslogin" method="post" action="install.php?action=criar-config">

                  <h3>Instalação do Banco de Dados</h3>
                  <p>
                    <small>O laboratório virtual precisa de um banco de dados MySQL para funcionar. Informe a seguir os dados para criação do banco</small>
                  </p>

                  <div class="">
                    <div class="input-group input-group-sm margem-inferior-p1">
                      <div class="input-group-prepend icone-formulario-principal">
                        <div class="input-group-text texto-icone icone-formulario-secundario"><i class="fas fa-server"></i></div>
                      </div>
                      <input required type="text" value="<?php echo Seguranca::h(isset($_REQUEST['db_host']) ? $_REQUEST['db_host'] : ''); ?>" name="db_host" class="form-control texto-icone" placeholder="Servidor">
                    </div>
                  </div>

                  <div class="">
                    <div class="input-group input-group-sm margem-inferior-p1">
                      <div class="input-group-prepend icone-formulario-principal">
                        <div class="input-group-text texto-icone icone-formulario-secundario"><i class="fas fa-database"></i></div>
                      </div>
                      <input required type="text" name="db_name" value="<?php echo Seguranca::h(isset($_REQUEST['db_name']) ? $_REQUEST['db_name'] : ''); ?>" class="form-control texto-icone" placeholder="Nome do banco de dados">
                    </div>
                  </div>

                  <div class="">
                    <div class="input-group input-group-sm margem-inferior-p1">
                      <div class="input-group-prepend icone-formulario-principal">
                        <div class="input-group-text texto-icone icone-formulario-secundario"><i class="fas fa-user-tag"></i></div>
                      </div>
                      <input value="<?php echo Seguranca::h(isset($_REQUEST['db_user']) ? $_REQUEST['db_user'] : ''); ?>" required type="text" name="db_user" class="form-control texto-icone" placeholder="Usuário" id="usuarioLogin">
                    </div>
                    <div class="input-group input-group-sm margem-inferior-p1">
                      <div class="input-group-prepend icone-formulario-principal">
                        <div class="input-group-text texto-icone icone-formulario-secundario"><i class="fas fa-key"></i></div>
                      </div>
                      <input type="password" name="db_password" class="form-control texto-icone" placeholder="Senha" id="senhaLogin">
                    </div>
                  </div>

                  <button type="submit" class="btn btn-success btn-block">Instalar Laboratório</button>

                  <div class="text-center texto-icone-p margem-superior-p1" id="logLogin">
                    <p class="log-login"></p>
                  </div>
                </form>
              <?php
              endif;

              if ($estado == 'criar-file') {
                ?>
                <form class="opcoeslogin" method="post" action="install.php?action=instalar">
                  <div class="form-group">
                    <strong></strong>
                    <label for="">Não foi possível criar o arquivo "<?php echo InstalacaoLab::$config_file;?>". Por gentileza, copie o conteudo abaixo e crie o arquivo manualmente no seguinte caminho: <?php                                                                                                                                                      ?></label>
                    <textarea class="form-control" rows="7" style="font-family: courier; font-weight: normal"><?php echo Seguranca::h($file_criar); ?></textarea>
                    <button type="submit" class="btn btn-success btn-block">Continuar instalando</button>
                  </div>
                </form>
              <?php
              }

              if ($estado == "instalado") {
                ?>
                <form class="opcoeslogin" method="post" action="index.php">
                  <div class="form-group">
                    <strong>Laboratório instalado com sucesso!</strong>
                    Anote as credenciais abaixo. Você irá precisar delas para acessar o laboratório<br /><br />
                    <div class="alert alert-success" role="alert">
                      <strong>Usuário:</strong> admin<br />
                      <strong>Senha:</strong> <?php echo Seguranca::h($new_password); ?>
                    </div>
                    <button type="submit" class="btn btn-success btn-block">Acessar o laboratório</button>
                  </div>
                </form>
              <?php
              }
              ?>
              <div class="logomarcas">
              </div>

            </div>

          </div>
        </div>

      </div>
    </div>
<?php
  define('URL_SITE', '');
  include('views/footer.php');
?>