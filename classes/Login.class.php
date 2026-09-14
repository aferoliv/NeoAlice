<?php

/**
 * Autenticacao e controle de sessao.
 *
 * Revisao de seguranca 2026-09:
 *  - senhas passam a usar password_hash()/password_verify() (bcrypt), com
 *    aceite dos hashes SHA-1 antigos e reescrita transparente no login;
 *  - session_regenerate_id() no login (fixacao de sessao);
 *  - logout limpa de fato a sessao e o cookie;
 *  - mensagens de erro do banco nao sao mais devolvidas ao cliente.
 */
class Login
{
    static $permissao_usuario;

    /**
     * Senhas que o sistema distribui de fabrica. Quem entra com uma delas e
     * obrigado a trocar antes de usar o laboratorio (ver $_SESSION['trocar_senha']).
     * O usuario "admin" do dump quimica.sql nasce com "123456".
     */
    const SENHAS_PADRAO = array('123456');

    /** Hash descartavel para igualar o tempo de resposta de usuario inexistente. */
    const HASH_DUMMY = '$2y$10$o23TFLQnb609iN3zw3VJcOeknFpe4Ro7VousZ1eBucUteInqY.7mi';

    // Funcao de validacao de login
    public static function logar($user, $pass)
    {
        global $banco;

        header('Content-Type: application/json; charset=utf-8');

        try {
            // Freio de forca bruta, antes de qualquer consulta de senha.
            $bloqueio = LimiteLogin::motivoBloqueio($user);
            if ($bloqueio !== null) {
                echo json_encode(array('sucesso' => false, 'log' => $bloqueio));
                return;
            }

            // Busca usuario no banco pelo login apenas; a senha e conferida em PHP.
            $consulta = $banco->prepare('SELECT nome, id_tipo_usuario, usuario, id_usuario, email, senha FROM usuarios_cadastrados WHERE usuario = :usuario');
            $consulta->execute(array(':usuario' => $user));
            $resultado = $consulta->fetch(PDO::FETCH_ASSOC);

            if (empty($resultado)) {
                // Gasta o mesmo tempo de um usuario existente.
                password_verify((string) $pass, self::HASH_DUMMY);
                LimiteLogin::registrarFalha($user);
                echo json_encode(array('sucesso' => false, 'log' => 'Usuario ou senha invalidos.'));
                return;
            }

            $armazenado = (string) $resultado['senha'];
            if (!self::conferirSenha($pass, $armazenado)) {
                LimiteLogin::registrarFalha($user);
                echo json_encode(array('sucesso' => false, 'log' => 'Usuario ou senha invalidos.'));
                return;
            }

            LimiteLogin::limparSucesso($user);

            // Senha correta. Se ainda estiver no formato antigo, reescreve.
            self::migrarHashSeNecessario($resultado['id_usuario'], $pass, $armazenado);

            // Novo id de sessao para nao reaproveitar um id fornecido pelo atacante.
            session_regenerate_id(true);

            $tipo = intval($resultado['id_tipo_usuario']);
            $_SESSION['nome'] = $resultado['nome'];
            $_SESSION['usuario'] = $resultado['usuario'];
            $_SESSION['email'] = $resultado['email'];
            $_SESSION['id_usuario'] = $resultado['id_usuario'];
            $_SESSION['tipo_usuario'] = $tipo;
            $_SESSION['administrador'] = ($tipo === Perfil::PROFESSOR);

            // Entrou com senha de fabrica: so sai da tela de perfil depois de trocar.
            $_SESSION['trocar_senha'] = self::senhaEhPadrao($pass);

            echo json_encode(array('sucesso' => true, 'log' => 'Login realizado com sucesso.', 'tipo' => $tipo));
        } catch (PDOException $e) {
            error_log('Login::logar - ' . $e->getMessage());
            echo json_encode(array('sucesso' => false, 'log' => 'Nao foi possivel validar o login. Tente novamente.'));
        }
    }

    /**
     * Aceita o hash novo (bcrypt) e o legado (SHA-1 sem sal, 40 hex).
     */
    private static function conferirSenha($senha, $armazenado)
    {
        $senha = (string) $senha;

        if (password_verify($senha, $armazenado)) {
            return true;
        }

        // Formato legado: sha1 hexadecimal de 40 caracteres.
        if (preg_match('/^[0-9a-f]{40}$/i', $armazenado)) {
            return hash_equals(strtolower($armazenado), sha1($senha));
        }

        return false;
    }

    /**
     * Reescreve o hash legado (ou desatualizado) apos um login bem sucedido.
     *
     * Nao e fatal: se a coluna `senha` ainda estiver como varchar(45) a
     * gravacao falha, o erro vai para o log e o usuario segue autenticado com
     * o hash antigo. Rode banco/migracoes/2026-09-13-seguranca.sql para
     * ampliar a coluna e concluir a migracao.
     */
    private static function migrarHashSeNecessario($idUsuario, $senha, $armazenado)
    {
        global $banco;

        $legado = (bool) preg_match('/^[0-9a-f]{40}$/i', $armazenado);
        if (!$legado && !password_needs_rehash($armazenado, PASSWORD_DEFAULT)) {
            return;
        }

        try {
            $novo = password_hash((string) $senha, PASSWORD_DEFAULT);
            $stmt = $banco->prepare('UPDATE usuarios_cadastrados SET senha = :senha WHERE id_usuario = :id');
            $stmt->execute(array(':senha' => $novo, ':id' => $idUsuario));
        } catch (PDOException $e) {
            error_log('Login: falha ao migrar hash do usuario ' . $idUsuario
                . ' (rode banco/migracoes/2026-09-13-seguranca.sql) - ' . $e->getMessage());
        }
    }

    /** A senha informada e uma das que o sistema distribui de fabrica? */
    public static function senhaEhPadrao($senha)
    {
        return in_array((string) $senha, self::SENHAS_PADRAO, true);
    }

    /** Precisa trocar a senha antes de usar o resto do sistema? */
    public static function precisaTrocarSenha()
    {
        return !empty($_SESSION['trocar_senha']);
    }

    /** Gera o hash de uma senha nova. Ponto unico usado por todo o sistema. */
    public static function gerarHash($senha)
    {
        return password_hash((string) $senha, PASSWORD_DEFAULT);
    }

    // Verifica se o tipo do usuario da sessao esta entre os perfis permitidos
    public static function ckeckTipoUser()
    {
        if (!is_array(self::$permissao_usuario) || empty(self::$permissao_usuario)) {
            // Nenhum perfil declarado pela pagina: nega por padrao.
            return false;
        }
        if (!isset($_SESSION['tipo_usuario'])) {
            return false;
        }
        return in_array(intval($_SESSION['tipo_usuario']), self::$permissao_usuario, true);
    }

    public static function logado()
    {
        if (!self::ckeckTipoUser() || empty($_SESSION['usuario'])) {
            return false;
        }
        return true;
    }

    public static function checkUser()
    {
        if (!self::logado()) {
            self::redirect();
        }
    }

    public static function logout()
    {
        $_SESSION = array();

        // Expira o cookie de sessao no navegador.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }

        session_destroy();
        self::redirect();
    }

    public static function redirect()
    {
        header('location:' . URL_SITE);
        exit();
    }

    public static function getSession()
    {
        return array(
            'nome' => isset($_SESSION['nome']) ? $_SESSION['nome'] : '',
            'usuario' => isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '',
            'email' => isset($_SESSION['email']) ? $_SESSION['email'] : '',
            'id_usuario' => isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : null,
            'tipo_usuario' => isset($_SESSION['tipo_usuario']) ? $_SESSION['tipo_usuario'] : null
        );
    }
}
