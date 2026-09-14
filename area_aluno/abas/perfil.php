<?php
  $objUsuario = new Usuario();
  $avisoPerfil = null;
  $erroPerfil = null;

  if (isset($_POST["acao"]) && $_POST["acao"] == 'atualizar') {
    $_POST["id_usuario"] = $_SESSION['id_usuario'];
    $senhaNova = isset($_POST["senha"]) ? (string) $_POST["senha"] : '';

    if ($senhaNova !== (isset($_POST["senha2"]) ? $_POST["senha2"] : '')) {
        $erroPerfil = 'As senhas digitadas não conferem!';
    } elseif (strlen($senhaNova) < 8) {
        // Antes nao havia exigencia nenhuma de tamanho aqui.
        $erroPerfil = 'A senha deve ter ao menos 8 caracteres.';
    } elseif (Login::senhaEhPadrao($senhaNova)) {
        $erroPerfil = 'Escolha uma senha diferente da senha padrão do sistema.';
    } elseif ($objUsuario->atualizarDadosPerfil($_POST)) {
        // Trocou de verdade: libera o resto do sistema.
        unset($_SESSION['trocar_senha']);
        $_SESSION['nome'] = $_POST["nome"];
        $avisoPerfil = 'Dados atualizados com sucesso.';
    } else {
        $erroPerfil = 'Não foi possível salvar. Verifique se o nome de usuário já está em uso.';
    }
  }
  $dados = $objUsuario->getAlunoEspecifico($_SESSION['id_usuario']);
?>

<?php if (Login::precisaTrocarSenha()) { ?>
  <div class="container">
    <div class="alert alert-warning" role="alert">
      <strong>Troque sua senha para continuar.</strong>
      Esta conta ainda usa a senha padrão do sistema, que é pública.
      As outras áreas ficam bloqueadas até você definir uma senha nova.
    </div>
  </div>
<?php } ?>
<?php if ($erroPerfil !== null) { ?>
  <div class="container">
    <div class="alert alert-danger" role="alert"><?php echo Seguranca::h($erroPerfil); ?></div>
  </div>
<?php } ?>
<?php if ($avisoPerfil !== null) { ?>
  <div class="container">
    <div class="alert alert-success" role="alert"><?php echo Seguranca::h($avisoPerfil); ?></div>
  </div>
<?php } ?>

<div class="container">
  <h3>Meu perfil</h3>
  <p>Aqui é possível atualizar os dados da sua conta.</p>
  <form id="aluno" name="aluno" method="post">
  <?php echo Seguranca::campoCsrf(); ?>
    <div class="form-row">
      <div class="form-group col-md-4">
        <label for="nome">Nome</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="far fa-user"></i></span>
          </div>      
            <input type="text" class="form-control" name="nome" placeholder="Digite seu nome..." value="<?php echo Seguranca::h($dados["nome"]) ?>" required>
        </div>
      </div>
      <div class="form-group col-md-4">
        <label for="usuario">Login</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="far fa-user"></i></span>
          </div>      
          <input type="text" class="form-control valida_login" name="usuario" placeholder="Digite seu nome de usuário..." value="<?php echo Seguranca::h($dados["usuario"]) ?>" maxlength="16" required>
        </div>
      </div>      
      <div class="form-group col-md-4">
        <label for="email">E-mail</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="far fa-envelope"></i></span>
            </div>               
            <input type="email" class="form-control" name="email" placeholder="Digite seu e-mail..." value="<?php echo Seguranca::h($dados["email"]) ?>" required>
          </div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group col-md-4">
        <label for="senha">Senha</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-key"></i></span>
          </div>              
          <input type="password" class="form-control" name="senha" placeholder="Digite sua senha..." required>
        </div>
      </div>
      <div class="form-group col-md-4">
        <label for="senha2">Digite novamente sua senha</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-key"></i></span>
          </div>              
          <input type="password" class="form-control" name="senha2" placeholder="Digite a mesma senha..." required>
        </div>
      </div>
    </div>
    <input type="submit" class="btn btn-primary" value="Salvar">
    <input type="hidden" name="acao" value="atualizar">
  </form>
</div>

<script type="text/javascript" src="js/abas/perfil.js"></script>