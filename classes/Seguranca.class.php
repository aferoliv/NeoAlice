<?php
/**
 * Funcoes de apoio a seguranca.
 *
 * Introduzida na revisao de seguranca de 2026-09. Concentra a validacao de
 * caminhos vindos da requisicao (antes concatenados direto em include) e o
 * escape de saida HTML.
 */
class Seguranca
{
    /** Segmentos de rota aceitos: letras, digitos, "_" e "-". Sem ".", sem "/". */
    const PADRAO_SEGMENTO = '/^[A-Za-z0-9_-]+$/';

    /**
     * Monta "$base/$segmentos[0]/$segmentos[1].php" a partir de dados do
     * usuario e so devolve o caminho se ele realmente existir DENTRO de $base.
     * Bloqueia "../", caminhos absolutos e bytes nulos.
     *
     * @return string|null caminho real do arquivo, ou null se invalido
     */
    public static function resolverArquivo($base, array $segmentos)
    {
        $baseReal = realpath($base);
        if ($baseReal === false) {
            return null;
        }

        foreach ($segmentos as $segmento) {
            if (!is_string($segmento) || !preg_match(self::PADRAO_SEGMENTO, $segmento)) {
                return null;
            }
        }

        $caminho = $baseReal . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, $segmentos) . '.php';

        $alvo = realpath($caminho);
        if ($alvo === false || !is_file($alvo)) {
            return null;
        }

        // Cinto e suspensorio: mesmo com o regex acima, confirma que o arquivo
        // resolvido continua sob $base (protege contra symlink apontando fora).
        $prefixo = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($alvo, $prefixo) !== 0) {
            return null;
        }

        return $alvo;
    }

    /**
     * Inclui uma rota validada ou encerra a requisicao com 404.
     */
    public static function incluirRota($base, array $segmentos)
    {
        $alvo = self::resolverArquivo($base, $segmentos);
        if ($alvo === null) {
            self::abortar(404, 'Pagina nao encontrada.');
        }
        include $alvo;
    }

    /**
     * Encerra a requisicao com um codigo HTTP e uma mensagem neutra.
     * Nao ecoa nada vindo do usuario.
     */
    public static function abortar($codigo, $mensagem = 'Requisicao invalida.')
    {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $mensagem;
        exit();
    }

    /** Escape para saida em HTML. */
    public static function h($texto)
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }

    // ---------------------------------------------------------------------
    // CSRF
    //
    // Antes desta revisao o projeto nao tinha token nenhum: qualquer POST
    // (apagar aluno, resetar senha, trocar perfil, subir arquivo) podia ser
    // forjado por uma pagina de terceiros visitada por quem estava logado.
    //
    // O token e por sessao. Ele viaja de duas formas:
    //  - campo oculto "_csrf" nos formularios HTML comuns;
    //  - cabecalho "X-CSRF-Token" nas chamadas jQuery (injetado por
    //    js/csrf.js em todo POST, o que cobre tambem FormData de upload).
    // ---------------------------------------------------------------------

    const CAMPO_CSRF = '_csrf';
    const HEADER_CSRF = 'HTTP_X_CSRF_TOKEN';

    /** Devolve o token da sessao, criando na primeira chamada. */
    public static function tokenCsrf()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Campo oculto para embutir num <form>. */
    public static function campoCsrf()
    {
        return '<input type="hidden" name="' . self::CAMPO_CSRF . '" value="'
            . self::h(self::tokenCsrf()) . '">';
    }

    /** Token valido na requisicao atual? */
    public static function csrfValido()
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        $enviado = '';
        if (isset($_POST[self::CAMPO_CSRF])) {
            $enviado = (string) $_POST[self::CAMPO_CSRF];
        } elseif (isset($_SERVER[self::HEADER_CSRF])) {
            $enviado = (string) $_SERVER[self::HEADER_CSRF];
        }

        if ($enviado === '') {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $enviado);
    }

    /**
     * Em requisicao POST, exige token valido. Metodos de leitura passam.
     * Chamada nos roteadores, logo depois de Login::checkUser().
     */
    public static function exigirCsrfEmPost()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (!self::csrfValido()) {
            error_log('CSRF invalido em ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?'));
            self::abortar(403, 'Requisicao invalida ou expirada. Recarregue a pagina e tente de novo.');
        }
    }

    /** Bloco <script> com o token, para o js/csrf.js usar. */
    public static function scriptCsrf()
    {
        return '<script>const CSRF_TOKEN = ' . json_encode(
            self::tokenCsrf(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ';</script>';
    }
}
