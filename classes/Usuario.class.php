<?php
/**
 * @author Marcelo
 *
 * Revisao de seguranca 2026-09:
 *  - senhas passam a usar Login::gerarHash() (bcrypt) no lugar de sha1();
 *  - alunos novos e resets recebem senha temporaria ALEATORIA, devolvida ao
 *    professor, em vez do "123456" fixo para todo mundo;
 *  - alunos sao gravados com Perfil::ALUNO e as listagens aceitam o tipo legado.
 */

class Usuario {

    /** Tamanho da senha temporaria gerada para alunos novos e resets. */
    const TAMANHO_SENHA_TEMP = 10;

    /**
     * Gera uma senha temporaria aleatoria, legivel em voz alta.
     * Sem caracteres ambiguos (0/O, 1/l/I).
     */
    public static function gerarSenhaTemporaria()
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $limite = strlen($alfabeto) - 1;
        $senha = '';
        for ($i = 0; $i < self::TAMANHO_SENHA_TEMP; $i++) {
            $senha .= $alfabeto[random_int(0, $limite)];
        }
        return $senha;
    }

    public function getPraticasAluno(){

        $db = Conexao::getInstance();
        $sql = "SELECT *, id_modelo_pratica AS id
                FROM modelo_pratica mp
                INNER JOIN disciplinas d ON d.id_disciplina  = mp.id_disciplina
                WHERE disponivel_mopr = 'S'
                ORDER BY nome_pratica";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }


    //Pega os alunos cadastrados no banco de dados
    public function getAlunos()
    {
        $db = Conexao::getInstance();
        // Aceita o tipo 3 (aluno) e o tipo 1 (aluno legado). Antes so o 3,
        // o que escondia da listagem os alunos criados pelos caminhos antigos.
        $sql = "SELECT id_usuario, nome, email, usuario
                FROM usuarios_cadastrados
                WHERE id_tipo_usuario IN (:aluno, :legado)";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":aluno", Perfil::ALUNO, PDO::PARAM_INT);
        $stmt->bindValue(":legado", Perfil::ALUNO_LEGADO, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }


    //Pega os alunos cadastrados no banco de dados
    public function getAlunoEspecifico($id_usuario)
    {
        $db = Conexao::getInstance();
        $sql = "SELECT *
                FROM usuarios_cadastrados 
                WHERE id_usuario = :id_usuario";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":id_usuario", $id_usuario);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }


    /**
     * Cadastra um aluno e devolve a senha temporaria gerada.
     * Antes gravava sha1('123456') para todos.
     *
     * @return string|null a senha temporaria, ou null se o cadastro falhou
     */
    function insertAluno($dados)
    {
        $db = Conexao::getInstance();

        $nomeUsuario = trim((string) $dados["nome_aluno"]);
        $emailUsuario = trim((string) $dados["email_aluno"]);
        $loginUsuario = trim((string) $dados["usuario_aluno"]);

        // Validacao no servidor (o formulario so validava no navegador)
        if ($nomeUsuario === '' || mb_strlen($nomeUsuario) > 45) {
            return null;
        }
        if (!filter_var($emailUsuario, FILTER_VALIDATE_EMAIL) || mb_strlen($emailUsuario) > 45) {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9]{3,16}$/', $loginUsuario)) {
            return null;
        }

        $senhaTemporaria = self::gerarSenhaTemporaria();

        $sql = "INSERT INTO usuarios_cadastrados 
                (nome, 
                email, 
                usuario, 
                senha, 
                id_tipo_usuario) 
                VALUES 
                (:nome,
                :email,
                :usuario,
                :senha, 
                :id_tipo_usuario)";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":nome", $nomeUsuario);
        $stmt->bindValue(":email", $emailUsuario);
        $stmt->bindValue(":usuario", $loginUsuario);
        $stmt->bindValue(":senha", Login::gerarHash($senhaTemporaria));
        $stmt->bindValue(":id_tipo_usuario", Perfil::ALUNO, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            // Login duplicado cai aqui (indice UNIQUE em usuarios_cadastrados.usuario)
            error_log('Usuario::insertAluno - ' . $e->getMessage());
            return null;
        }

        return $senhaTemporaria;
    }


    /**
     * Reseta a senha de um aluno e devolve a nova senha temporaria.
     * Antes gravava sha1('123456') para todos.
     *
     * @return string|null a senha temporaria, ou null se o reset falhou
     */
    function resetarSenhaAluno($id_usuario)
    {
        $db = Conexao::getInstance();

        $senhaTemporaria = self::gerarSenhaTemporaria();

        // O filtro por perfil impede que um professor resete a senha de outro
        // professor ou do administrador passando o id_usuario dele.
        $sql = "UPDATE usuarios_cadastrados
                SET senha = :senha
                WHERE id_usuario = :id_usuario
                  AND id_tipo_usuario IN (:aluno, :legado)";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":senha", Login::gerarHash($senhaTemporaria));
        $stmt->bindValue(":id_usuario", $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(":aluno", Perfil::ALUNO, PDO::PARAM_INT);
        $stmt->bindValue(":legado", Perfil::ALUNO_LEGADO, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            error_log('Usuario::resetarSenhaAluno - ' . $e->getMessage());
            return null;
        }

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $senhaTemporaria;
    }

    /**
     * Remove um aluno. O filtro por perfil impede que um professor apague
     * outro professor ou o administrador passando o id_usuario dele.
     *
     * @return bool true se algum registro foi removido
     */
    function deleteUser($id_usuario)
    {
        $db = Conexao::getInstance();

        $sql = "DELETE FROM usuarios_cadastrados
                WHERE id_usuario = :id_usuario
                  AND id_tipo_usuario IN (:aluno, :legado)";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":id_usuario", $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(":aluno", Perfil::ALUNO, PDO::PARAM_INT);
        $stmt->bindValue(":legado", Perfil::ALUNO_LEGADO, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    function atualizarDadosPerfil($dados) {

        $db = Conexao::getInstance();

        $sql = "UPDATE usuarios_cadastrados
                SET 
                nome = :nome,
                email = :email,
                senha = :senha,
                usuario = :usuario
                WHERE id_usuario = :id_usuario";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(":nome", $dados["nome"]);
        $stmt->bindValue(":email", $dados["email"]);
        $stmt->bindValue(":senha", Login::gerarHash($dados["senha"]));
        $stmt->bindValue(":id_usuario", $dados["id_usuario"]);
        $stmt->bindValue(":usuario", $dados["usuario"]);
        if($stmt->execute())
            return true;

    }
}
