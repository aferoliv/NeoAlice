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
}
