-- ---------------------------------------------------------------------------
-- NeoAlice - migração da revisão de segurança de 2026-09-13
--
-- LEIA ANTES DE RODAR. Faça backup:
--   docker compose exec db mysqldump -u root -p quimica > backup-antes.sql
--
-- Os passos 1 e 2 são seguros e obrigatórios.
-- O passo 3 MEXE EM PERMISSÃO DE ACESSO: rode a consulta de verificação
-- primeiro e confirme o resultado antes de executá-lo.
-- ---------------------------------------------------------------------------


-- ---------------------------------------------------------------------------
-- 1. OBRIGATÓRIO - amplia a coluna de senha
--
-- As senhas passaram de SHA-1 (40 caracteres) para bcrypt (60 caracteres).
-- Com varchar(45) o MySQL em modo estrito recusa a gravação, e o sistema
-- continua usando o hash antigo para sempre (o erro vai para o log do PHP).
-- ---------------------------------------------------------------------------
ALTER TABLE usuarios_cadastrados MODIFY senha VARCHAR(255) NOT NULL;


-- ---------------------------------------------------------------------------
-- 2. OBRIGATÓRIO - corrige a tabela de apoio tipo_usuario
--
-- O dump trazia (1,'Admin'), mas o código nunca tratou 1 como administrador:
-- quem é administrador/professor é o tipo 2 (ver Login.class.php e o próprio
-- usuário "admin" do dump). O tipo 1 era gravado por caminhos antigos de
-- cadastro de ALUNO.
-- ---------------------------------------------------------------------------
UPDATE tipo_usuario SET nome_tipo = 'Aluno (legado)' WHERE id_tipo_usuario = 1;
UPDATE tipo_usuario SET nome_tipo = 'Professor'      WHERE id_tipo_usuario = 2;
UPDATE tipo_usuario SET nome_tipo = 'Aluno'          WHERE id_tipo_usuario = 3;


-- ---------------------------------------------------------------------------
-- 3. VERIFIQUE ANTES DE RODAR - normaliza os usuários do tipo 1
--
-- Até esta revisão, Login::$permissao_usuario = [1,2] na área do professor,
-- ou seja, TODA conta de tipo 1 tinha acesso de professor. Agora só o tipo 2
-- tem. Antes de converter, veja QUEM são essas contas:
--
--     SELECT id_usuario, usuario, nome, email, data_cadastro
--     FROM usuarios_cadastrados
--     WHERE id_tipo_usuario = 1
--     ORDER BY data_cadastro;
--
-- Se forem todas de alunos (o esperado), rode o UPDATE abaixo.
-- Se houver algum PROFESSOR de verdade nessa lista, promova-o para o tipo 2
-- ANTES de rodar, senão ele perde o acesso à área do professor:
--
--     UPDATE usuarios_cadastrados SET id_tipo_usuario = 2
--     WHERE id_usuario IN (<ids dos professores de verdade>);
--
-- Só depois:
-- ---------------------------------------------------------------------------
-- UPDATE usuarios_cadastrados SET id_tipo_usuario = 3 WHERE id_tipo_usuario = 1;


-- ---------------------------------------------------------------------------
-- 4. CONFERÊNCIA - distribuição final dos perfis
-- ---------------------------------------------------------------------------
-- SELECT t.id_tipo_usuario, t.nome_tipo, COUNT(u.id_usuario) AS total
-- FROM tipo_usuario t
-- LEFT JOIN usuarios_cadastrados u ON u.id_tipo_usuario = t.id_tipo_usuario
-- GROUP BY t.id_tipo_usuario, t.nome_tipo;
