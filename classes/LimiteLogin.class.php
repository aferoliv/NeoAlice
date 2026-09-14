<?php
/**
 * Limite de tentativas de login (revisao de seguranca 2026-09).
 *
 * Antes nao havia nenhum freio: dava para testar senhas indefinidamente.
 *
 * O controle principal e POR USUARIO, nao por IP. Numa universidade uma turma
 * inteira costuma sair pelo mesmo IP (NAT do laboratorio de informatica), e um
 * limite apertado por IP trancaria a turma toda por causa de um aluno
 * distraido. O limite por IP existe so como freio largo contra varredura.
 *
 * Contrapartida conhecida do limite por usuario: alguem pode travar o login de
 * um colega de proposito, errando a senha dele 5 vezes. Por isso a janela e
 * curta (15 min) e a mensagem diz claramente o que houve.
 *
 * Depende da tabela `login_tentativas`, criada por
 * banco/migracoes/2026-09-14-csrf-e-rate-limit.sql. Se a tabela nao existir,
 * esta classe LIBERA a tentativa e registra o motivo no log: travar o login de
 * todo mundo porque a migracao nao rodou seria pior que o problema.
 */
class LimiteLogin
{
    /** Janela de tempo considerada, em minutos. */
    const JANELA_MINUTOS = 15;

    /** Falhas no mesmo usuario dentro da janela antes de bloquear. */
    const MAX_POR_USUARIO = 5;

    /** Falhas no mesmo IP dentro da janela. Folgado de proposito (NAT). */
    const MAX_POR_IP = 60;

    /**
     * IP de origem. Atras do Caddy, REMOTE_ADDR e o proxy, entao usa o
     * X-Forwarded-For quando a conexao vem da rede interna do Compose.
     */
    public static function ip()
    {
        $remoto = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        $interno = $remoto !== '' && !filter_var(
            $remoto,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($interno && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $partes = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $primeiro = trim($partes[0]);
            if (filter_var($primeiro, FILTER_VALIDATE_IP)) {
                return substr($primeiro, 0, 45);
            }
        }

        return substr($remoto, 0, 45);
    }

    /**
     * Devolve a mensagem de bloqueio, ou null se a tentativa pode seguir.
     */
    public static function motivoBloqueio($usuario)
    {
        global $banco;

        try {
            $sql = 'SELECT
                        SUM(usuario = :usuario) AS por_usuario,
                        SUM(ip = :ip) AS por_ip
                    FROM login_tentativas
                    WHERE criado_em > (NOW() - INTERVAL ' . (int) self::JANELA_MINUTOS . ' MINUTE)';
            $stmt = $banco->prepare($sql);
            $stmt->bindValue(':usuario', (string) $usuario);
            $stmt->bindValue(':ip', self::ip());
            $stmt->execute();
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('LimiteLogin: tabela login_tentativas indisponivel, tentativa liberada'
                . ' (rode banco/migracoes/2026-09-14-csrf-e-rate-limit.sql) - ' . $e->getMessage());
            return null;
        }

        if (!$linha) {
            return null;
        }

        if ((int) $linha['por_usuario'] >= self::MAX_POR_USUARIO) {
            return 'Muitas tentativas para este usuário. Espere '
                . self::JANELA_MINUTOS . ' minutos e tente de novo.';
        }

        if ((int) $linha['por_ip'] >= self::MAX_POR_IP) {
            return 'Muitas tentativas a partir desta rede. Espere '
                . self::JANELA_MINUTOS . ' minutos e tente de novo.';
        }

        return null;
    }

    /** Registra uma tentativa que falhou. */
    public static function registrarFalha($usuario)
    {
        global $banco;

        try {
            $stmt = $banco->prepare(
                'INSERT INTO login_tentativas (usuario, ip) VALUES (:usuario, :ip)'
            );
            $stmt->bindValue(':usuario', substr((string) $usuario, 0, 45));
            $stmt->bindValue(':ip', self::ip());
            $stmt->execute();
        } catch (PDOException $e) {
            error_log('LimiteLogin::registrarFalha - ' . $e->getMessage());
        }

        self::limparAntigos();
    }

    /** Zera o contador do usuario e do IP depois de um login bem sucedido. */
    public static function limparSucesso($usuario)
    {
        global $banco;

        try {
            $stmt = $banco->prepare(
                'DELETE FROM login_tentativas WHERE usuario = :usuario OR ip = :ip'
            );
            $stmt->bindValue(':usuario', (string) $usuario);
            $stmt->bindValue(':ip', self::ip());
            $stmt->execute();
        } catch (PDOException $e) {
            error_log('LimiteLogin::limparSucesso - ' . $e->getMessage());
        }
    }

    /**
     * Remove registros fora da janela. Chamado de vez em quando (1 em 20) para
     * a tabela nao crescer sem limite, sem custo em toda tentativa.
     */
    private static function limparAntigos()
    {
        global $banco;

        if (random_int(1, 20) !== 1) {
            return;
        }

        try {
            $stmt = $banco->prepare(
                'DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL ' . (int) self::JANELA_MINUTOS . ' MINUTE)'
            );
            $stmt->execute();
        } catch (PDOException $e) {
            error_log('LimiteLogin::limparAntigos - ' . $e->getMessage());
        }
    }
}
