<?php
class InstalacaoLab
{
    public static $config_file = 'lab-config.php';
    public static function criarConfiguracoes()
    {
        $config_file  = self::$config_file;

        $db_name = $_REQUEST['db_name'];
        $db_user = $_REQUEST['db_user'];
        $db_password = $_REQUEST['db_password'];
        $db_host = $_REQUEST['db_host'];

        $file = sprintf(
            "<?php
/**
* As configurações básicas do Laboratorio Virtual de Quimica
*/

// Cookie de sessão: HttpOnly impede leitura por JavaScript, SameSite=Lax
// reduz CSRF, Secure só quando a requisição já veio por HTTPS.
\$httpsAtivo = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off')
    || (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(array(
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => \$httpsAtivo,
    'httponly' => true,
    'samesite' => 'Lax',
));

session_start();

// Erros vão para o log do servidor, nunca para a resposta HTTP.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('DB_NAME', '%s');
define('DB_USER', '%s');
define('DB_PASSWORD', '%s');
define('DB_HOST', '%s');
define('LAB_DEBUG', false);

/** Caminho absoluto para o diretório raiz */
if ( !defined('ABSPATH') ) define('ABSPATH', dirname(__FILE__) . '/');

define('URL_SITE', '%s');
define('URL_SYSTEM', '%s');
include('register.php');
?>",
            addslashes($db_name),
            addslashes($db_user),
            addslashes($db_password),
            addslashes($db_host),
            (str_replace('install.php', '', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'])),
            (str_replace('classes/', '', dirname(__FILE__)) . '/')
        );

        if (file_exists($config_file)) {
            return array(
                'msg' => "Já existe o arquivo '" . $config_file . "' delete ele por favor, ou recrie ele",
                'success' => false,
                'file' => $file
            );
        }

        // Cria o arquivo de configuracao
        $fp = fopen($config_file, "w+");
        $fwrite = fwrite($fp, $file);
        fclose($fp);
        if ($fwrite) {
            return array(
                'msg' => '',
                'file' => '',
                'success' => true
            );
        } else {
            return array(
                'msg' => "O arquivo '" . $config_file . "'não foi criado corretamente, verifique 
                as permissões de arquivos e pastas.",
                "file" => $file,
                "success" => false
            );
        }
    }

    public static function  verificaConexaoDB()
    {
        $db_name = $_REQUEST['db_name'];
        $db_user = $_REQUEST['db_user'];
        $db_password = $_REQUEST['db_password'];
        $db_host = $_REQUEST['db_host'];

        // Guardas: lab-config.php pode ja ter sido incluido (instalacao
        // interrompida no meio), e redefinir constante e erro.
        if (!defined('DB_NAME')) define('DB_NAME', $db_name);
        if (!defined('DB_USER')) define('DB_USER', $db_user);
        if (!defined('DB_PASSWORD')) define('DB_PASSWORD', $db_password);
        if (!defined('DB_HOST')) define('DB_HOST', $db_host);

        $db = Conexao::getInstance();
        // Se a conexao falhar
        if (!$db) {
            return false;
        } else
            return true;
    }

    public static function verificaInstalacao()
    {
        $config_file  = self::$config_file;
        // Verifica se o arquivo lab-config.php existe e se possui as constantes esperadas
        if (file_exists($config_file)) {
            if (DB_NAME && DB_USER && DB_HOST) { } else {
                return array(
                    'msg' => 'Erro no arquivo config ' . $config_file,
                    'success' => false
                );
            }
        } else {
            return array(
                'msg' => 'Erro no arquivo config ' . $config_file,
                'success' => false
            );
        }

        $db = Conexao::getInstance();
        if (!$db) {
            return array(
                'msg' => 'Erro na conexão com o banco de dados',
                'success' => false
            );
        }

        return array(
            'msg' => '',
            'success' => true
        );
    }

    public static function  instalarBDProjeto()
    {
        $db = Conexao::getInstance();
        $stmt = $db->prepare('show tables');
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($tables){
            return array(
                'msg' => 'O banco de dados já existe tabelas, remova para continuar',
                'success' => false
            );
        }

        $sql = file_get_contents("quimica.sql");
        //$new_password = str_pad(rand(0, 99999), 5, "0", STR_PAD_LEFT);
        //$new_password = '123456';
        //$sql = str_replace("__MYSENHA__", sha1($new_password), $sql);
        try {
            $resp = $db->exec($sql);
            $resp = $db->exec("
                TRUNCATE TABLE modelo_pratica_u_usuario;
                TRUNCATE TABLE modelo_pratica;
                TRUNCATE TABLE modelo_pratica_arquivo;
                TRUNCATE TABLE modelo_pratica_solucao;
                TRUNCATE TABLE disciplinas;
                TRUNCATE TABLE usuarios_cadastrados;
                COMMIT;
            ");
            // Antes gravava sha1('123456') fixo aqui. Agora a senha do
            // administrador e sorteada e devolvida para ser exibida uma vez.
            $senhaAdmin = Usuario::gerarSenhaTemporaria();
            $stmt = $db->prepare("
                INSERT INTO usuarios_cadastrados
                (nome,email,senha,id_tipo_usuario,usuario)
                VALUES
                ('ADMIN','admin@gmail.com',:senha,:tipo,'admin')
            ");
            $stmt->bindValue(':senha', Login::gerarHash($senhaAdmin));
            $stmt->bindValue(':tipo', Perfil::PROFESSOR, PDO::PARAM_INT);
            $stmt->execute();

            return array(
                'msg' => '',
                'senha_admin' => $senhaAdmin,
                'success' => true
            );
        } catch (PDOException $e) {
            // Antes havia um die(2) aqui, que matava a requisicao antes de
            // qualquer tratamento e escondia a causa real.
            error_log('InstalacaoLab::instalarBDProjeto - ' . $e->getMessage());
            return array(
                'msg' => 'Erro ao tentar escrever no banco de dados: ' . $e->getMessage(),
                'success' => false
            );
        }
    }
}
