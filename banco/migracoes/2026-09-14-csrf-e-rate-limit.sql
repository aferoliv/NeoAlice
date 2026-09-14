-- ---------------------------------------------------------------------------
-- NeoAlice - migração do limite de tentativas de login (2026-09-14)
--
-- Complementa banco/migracoes/2026-09-13-seguranca.sql. Rode depois daquela.
-- Faça backup antes:
--   docker compose exec db mysqldump -u root -p quimica > backup-antes.sql
--
-- Esta migração é aditiva: só cria uma tabela nova, não altera nada existente.
-- Se ela não for executada, o login continua funcionando normalmente, porém
-- SEM freio de força bruta (classes/LimiteLogin.class.php libera a tentativa e
-- registra o motivo no log do PHP, para não trancar o acesso de todo mundo).
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `login_tentativas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(45) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario_criado` (`usuario`,`criado_em`),
  KEY `idx_ip_criado` (`ip`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Conferência: quem está bloqueado agora
-- ---------------------------------------------------------------------------
-- SELECT usuario, ip, COUNT(*) AS falhas, MAX(criado_em) AS ultima
-- FROM login_tentativas
-- WHERE criado_em > (NOW() - INTERVAL 15 MINUTE)
-- GROUP BY usuario, ip
-- ORDER BY falhas DESC;

-- Para desbloquear alguém manualmente (ex.: aluno travado por engano):
-- DELETE FROM login_tentativas WHERE usuario = 'nome_do_usuario';
