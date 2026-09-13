<?php
  $objUsuario = new Usuario();
  $senhaTemporaria = null;
  $erroCadastro = null;

  if (isset($_POST["acao"]) && $_POST["acao"] == 'salvar') {
    // insertAluno devolve a senha temporária gerada para este aluno.
    // Antes a senha era sempre "123456" e o erro de cadastro era silencioso.
    $senhaTemporaria = $objUsuario->insertAluno($_POST);
    if ($senhaTemporaria === null) {
      $erroCadastro = 'Não foi possível cadastrar o aluno. Confira os dados: '
        . 'o login deve ter de 3 a 16 letras ou números e não pode já existir, '
        . 'e o e-mail precisa ser válido.';
    }
  }
?>
<?php if ($senhaTemporaria !== null) { ?>
  <div class="alert alert-success" role="alert">
    Aluno salvo com sucesso! A senha temporária é
    <strong><?php echo Seguranca::h($senhaTemporaria); ?></strong> &mdash;
    anote e entregue ao aluno, porque ela não será exibida de novo.
    Oriente-o a trocá-la no primeiro acesso.
  </div>
<?php } ?>
<?php if ($erroCadastro !== null) { ?>
  <div class="alert alert-danger" role="alert"><?php echo Seguranca::h($erroCadastro); ?></div>
<?php } ?>

<div class="container">
  <div class="row">
    <div class="col-md-12">
      <h3><span>Meus Alunos</span></h3>
      <div class="aluno">
        <button class="btn azul" type=button data-toggle="collapse" href="#adicionarAluno"><i class="fas fa-plus-circle"></i> Cadastrar novo aluno</button>
        <br>
        <br>
        <div class="collapse" id="adicionarAluno">
          <form id="aluno" name="aluno" method="post" action="<?php echo URL_SITE; ?>area_professor/index.php?aba=alunos&cadastro=ok" enctype="multipart/form-data">
            <table class="table">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Login do Usuário</th>
                  <th>E-mail</th>
                </tr>
              </thead>
              <tbody>
                <td><input id="nome_aluno" name="nome_aluno" class="form-control input-disciplina" type="text" placeholder="Insira o nome" focus required></td>
                <td><input id="usuario_aluno" name="usuario_aluno" class="form-control input-disciplina valida_login" type="text" placeholder="Insira o login..." required maxlength="15"></td>
                <td><input type="email" id="email_aluno" name="email_aluno" class="form-control input-disciplina" type="email" placeholder="Digite o email" required></td>
              </tbody>
            </table>
            <input type="submit" class="btn btn-primary" value="Salvar">
            <input type="hidden" name="acao" value="salvar">
          </form>
        </div>
      </div>
      <br>
      <br>

      <table id="tabela" class="table table-striped table-bordered table-data" style="width:100%">
        <thead>
          <tr>
            <th scope="col">Usuário</th>
            <th scope="col">Nome</th>
            <th scope="col">Email</th>
            <th scope="col">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php
            // Lista as praticas disponiveis para o aluno
            $dados = $objUsuario->getAlunos();

            if (!empty($dados)) {
              foreach ($dados as $row) {
          ?>
              <tr>
                <td><?php echo Seguranca::h($row['usuario']) ?></td>
                <td><?php echo Seguranca::h($row['nome']) ?></td>
                <td><?php echo Seguranca::h($row['email']) ?></td>
                <td>
                  <button class='btn btn-warning reset_senha' cod-usuario='<?php echo Seguranca::h($row["id_usuario"]) ?>'>Resetar</button>
                  <button class='btn btn-danger delete_user' cod-usuario='<?php echo Seguranca::h($row["id_usuario"]) ?>'>Deletar</button>
                </td>
              </tr>
          <?php
            }
          } else {
            echo "Nenhum aluno cadastrado";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script type="text/javascript" src="../plugins/vendor/bootbox/bootbox.js"></script>
<script type="text/javascript" src="js/abas/alunos.js"></script>