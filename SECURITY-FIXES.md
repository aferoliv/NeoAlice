# Revisão de segurança — setembro/2026

Branch: `corrigir-login`. Este documento descreve o que foi encontrado, o que
foi alterado e o que ainda falta, para avaliação antes do merge em `main`.

**Resumo:** o laboratório tinha uma cadeia de falhas que levava de visitante
anônimo a execução de código no servidor, além de escalada de privilégio no
cadastro público e exposição do `.env` e do `.git/` pela web. Todas foram
corrigidas e verificadas com o ambiente rodando.

---

## 1. Antes de subir isto em produção

Três passos manuais. Sem eles, parte das correções não tem efeito.

### 1.1 Rodar a migração do banco (obrigatório)

```bash
# backup primeiro
docker compose exec db mysqldump -u root -p quimica > backup-antes.sql

docker compose exec -T db mysql -u root -p quimica < banco/migracoes/2026-09-13-seguranca.sql
```

O arquivo está comentado passo a passo. **O passo 3 mexe em permissão de
acesso e está desativado de propósito** — leia a seção 2.1 antes.

Sem o passo 1 da migração (`ALTER TABLE ... senha VARCHAR(255)`), a coluna
`senha` continua `varchar(45)` e não cabe um hash bcrypt (60 caracteres). O
sistema continua funcionando com os hashes SHA-1 antigos e registra o motivo
no log — não quebra, mas a migração de senha nunca se completa.

### 1.2 Trocar a senha do administrador

O usuário `admin` nasce com `123456` no `quimica.sql`. Entre no laboratório e
troque em *Meu perfil* imediatamente. Ao fazer login a senha é automaticamente
reconvertida para bcrypt, mas o valor continua sendo `123456` até ser trocado.

### 1.3 Conferir `APP_BIND_ADDRESS` no `.env` do servidor

O padrão passou de `0.0.0.0` para `127.0.0.1`. Com `compose.server.yaml`, quem
atende a internet é o Caddy (HTTPS) — ele fala com o serviço `web` pela rede
interna do Compose. Com `0.0.0.0` o laboratório ficava acessível **também** em
HTTP puro na porta 8080, contornando o TLS.

---

## 2. Mudanças de comportamento (o que pode incomodar usuários)

### 2.1 O perfil `id_tipo_usuario = 1` perdeu o acesso à área do professor

Esta é a mudança mais delicada. **Confira contra o banco de produção.**

A tabela `tipo_usuario` do dump dizia `(1,'Admin'), (2,'Professor'), (3,'Aluno')`,
mas o código nunca seguiu isso:

- `Login.class.php` tratava (e trata) **2** como professor/administrador, e o
  usuário `admin` do dump é tipo 2;
- `area_professor/app/usuario/insert-aluno.php` e `LabJogo::insertAluno`
  cadastravam **alunos** com tipo **1**;
- `Usuario::insertAluno` cadastrava alunos com tipo **3**;
- e `Login::$permissao_usuario = [1,2]` na área do professor fazia com que
  **todo usuário tipo 1 tivesse acesso de professor**.

Ou seja, aluno cadastrado pelo caminho antigo entrava na área do professor.
Agora a área do professor aceita **somente o tipo 2**.

Antes de rodar o passo 3 da migração, liste quem são essas contas:

```sql
SELECT id_usuario, usuario, nome, email, data_cadastro
FROM usuarios_cadastrados WHERE id_tipo_usuario = 1 ORDER BY data_cadastro;
```

Se houver algum professor de verdade nessa lista, promova-o para o tipo 2
**antes**, senão ele perde o acesso.

### 2.2 Senha temporária de aluno deixou de ser `123456`

Cadastro de aluno e reset de senha agora **sorteiam** uma senha de 10
caracteres e a exibem ao professor **uma única vez**, na tela. O professor
precisa anotar e repassar. Isso muda a rotina de quem usa o painel.

### 2.3 Senha mínima no cadastro público subiu de 5 para 8 caracteres

Só afeta cadastros novos. Ninguém é forçado a trocar.

### 2.4 Erros do banco deixaram de ser silenciosos

`PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION` foi ligado nas duas conexões. Antes o
PDO falhava em silêncio — os vários `catch (PDOException)` espalhados pelo
código **nunca disparavam**, e um `INSERT` que falhava era reportado como
sucesso (era literalmente o caso do cadastro de aluno).

Consequência: bugs latentes que antes passavam despercebidos agora aparecem.
`error_reporting(0)` foi substituído por log ativo com `display_errors` **off**
— nada vaza na resposta HTTP, tudo vai para `docker compose logs web`.

Isso já revelou dois defeitos **pré-existentes** (ver seção 5.4).

---

## 3. Falhas corrigidas

### 3.1 CRÍTICO — Qualquer visitante se cadastrava como professor

`banco/data.php` repassava o campo `acesso` **da requisição** direto para
`id_tipo_usuario`. Um POST com `acesso=2` criava um administrador.

Pior: `index.php` tinha dois elementos com o mesmo `id="listaTipoUsuario"` —
um `<input type="hidden" value="1">` e o `<select value="3">`. Como
`$('#listaTipoUsuario').val()` devolve o primeiro do documento, **todo cadastro
público já era enviado com `acesso=1`**, que dava acesso à área do professor
(ver 2.1). Não era hipótese: era o comportamento padrão.

**Correção:** o perfil não vem mais da requisição. `cadastrarUsuario()` perdeu
o parâmetro `$acesso` e grava sempre `Perfil::ALUNO`. O `<input hidden>`
duplicado foi removido.

### 3.2 CRÍTICO — Path traversal / LFI em seis roteadores

```php
// area_professor/index-app.php, antes
include_once URL_SYSTEM."area_professor/app/".$_GET['app'].'/'.$_GET['file'].'.php';
```

O mesmo padrão em `area_aluno/index-app.php`, `area_laboratorio/index-app.php`,
`area_aluno/index.php`, `area_professor/index.php` e `area_professor/index_new.php`
(estes últimos com `$_REQUEST['aba']`).

**Correção:** classe nova `Seguranca` (`classes/Seguranca.class.php`). Cada
segmento de rota precisa casar com `^[A-Za-z0-9_-]+$` (sem ponto, sem barra), e
o caminho resolvido por `realpath()` precisa continuar dentro do diretório base
— o que também barra symlink apontando para fora. Rota inválida devolve 404; no
caso das abas, cai no "início".

### 3.3 CRÍTICO — Upload sem restrição de tipo

`area_professor/abas/xhr-arquivos-pratica.php` tirava a extensão do nome
enviado pelo cliente, sem lista de permitidos, e gravava dentro do webroot com
`mkdir(..., 0777)`. Subir um `.php` e acessá-lo era execução de código. O
`id_pratica` ia direto do `$_POST` para o caminho do diretório (traversal). E o
MIME gravado era o **declarado pelo cliente**, depois ecoado sem escape em
`ModeloPraticaArquivo::getItensHtml()` (XSS armazenado).

**Correção:** lista de extensões permitidas (pdf, png, jpg, jpeg, gif),
conferência do conteúdo real com `finfo`, limite de 10 MB, `id_pratica`
convertido com `(int)`, `tipo` restrito a CADERNO/ROTEIRO, nome de arquivo
gerado no servidor com `random_bytes()`, diretório 0755 e arquivo 0644.

Além disso, **execução de PHP foi desligada em `uploads/`** via
`docker/security.conf` (e `uploads/.htaccess` para instalação fora do Docker).
Mesmo que algum dia passe um arquivo indevido, ele não roda.

### 3.4 CRÍTICO — Endpoints do laboratório sem verificação de login

`area_laboratorio/index-app.php` não chamava `Login::checkUser()` — todos os
endpoints de `app/jogo/` estavam abertos. `save-acao-aluno-pratica-jogo.php`
ainda aceita `id_aluno` do POST, então dava para forjar histórico de qualquer
aluno sem sequer ter conta.

`banco/data.php?acao=carregar_alunos` devolvia **nome e e-mail de todos os
alunos** sem login.

**Correção:** login exigido no roteador do laboratório; `carregar_alunos` exige
perfil de professor; `nome_disciplina` (que escreve na sessão) exige login.

### 3.5 CRÍTICO — `.env`, `.git/` e o dump servidos pela web

`compose.yaml` monta o repositório inteiro em `/var/www/html` e não havia
nenhuma regra de bloqueio. `http://servidor/.env` entregava as senhas do MySQL;
`/.git/` entregava o histórico completo.

`.htaccess` não resolveria sozinho: a imagem `php:7.4-apache` usa
`AllowOverride None` em `/var/www/`, então **arquivos `.htaccess` ali são
ignorados**. Por isso a regra vai em `docker/security.conf`, copiado para
`/etc/apache2/conf-enabled/` pelo `Dockerfile`. O `.htaccess` na raiz ficou
como rede de segurança para instalações fora do Docker.

Bloqueados: qualquer arquivo iniciado por ponto, `*.sql`, `*.md`, `*.yml`,
`*.sh`, `Dockerfile`, `LICENSE`, `info.php`, e os diretórios `.git`, `.github`,
`.vscode`, `__lixo`, `docker`, `banco/migracoes`. Listagem de diretório off.

### 3.6 ALTO — Senhas em SHA-1 sem sal

`sha1($senha)` é quebrado por rainbow table em segundos.

**Correção:** `password_hash()` / `password_verify()` (bcrypt). A migração é
transparente: no login, se o hash armazenado for SHA-1 (40 hex), ele é
conferido do jeito antigo e **reescrito em bcrypt na hora**. Ninguém precisa
trocar de senha. Se a coluna ainda estiver estreita, a reescrita falha, o
motivo vai para o log e o usuário segue autenticado com o hash antigo — não
quebra o login (ver 1.1).

Todos os pontos de geração de hash passaram a usar `Login::gerarHash()`.

### 3.7 ALTO — Senha padrão `123456` para todo aluno

Cadastro e reset gravavam `sha1('123456')` para todo mundo. Agora sorteiam
senha aleatória (ver 2.2). O instalador também sorteia a senha do `admin` em
vez de fixar `123456`.

### 3.8 ALTO — Professor podia resetar/apagar a conta do administrador

`resetarSenhaAluno()` e `deleteUser()` aceitavam qualquer `id_usuario`,
inclusive o do `admin`. Um professor tomava a conta do administrador.

**Correção:** os dois filtram por `id_tipo_usuario IN (aluno, aluno legado)` na
própria query.

### 3.9 ALTO — Fixação de sessão e logout incompleto

Não havia `session_regenerate_id()` no login. O `logout()` fazia
`unset($_SESSION)` — que destrói a variável local, não os dados da sessão — e
não expirava o cookie.

**Correção:** `session_regenerate_id(true)` no login; logout com
`$_SESSION = array()`, expiração do cookie e `session_destroy()`. Cookie de
sessão agora com `HttpOnly`, `SameSite=Lax` e `Secure` quando a requisição
chega por HTTPS (detectado também por `X-Forwarded-Proto`, por causa do Caddy).
`session.use_strict_mode` ligado.

### 3.10 ALTO — Instalador acessível para sempre

`install.php` funcionava mesmo com o laboratório no ar. O formulário de conexão
servia como scanner da rede interna (as mensagens de erro do PDO voltavam para
a tela), e `$_REQUEST['error_title']` / `error_message` eram ecoados crus
(XSS refletido).

**Correção:** se `lab-config.php` existe **e** o banco já tem tabelas, a página
devolve 410 e não faz mais nada. Toda saída passou a ser escapada. O `die(2)`
que escondia a causa real de erro de instalação foi removido.

### 3.11 MÉDIO — XSS

- `area_laboratorio/lab.php` injetava `$_REQUEST['tipo_acesso']` direto dentro
  de um literal JavaScript (refletido). Agora passa por `json_encode()` com
  `JSON_HEX_*`.
- `area_aluno/abas/perfil.php`, `area_professor/abas/perfil.php` e
  `area_professor/abas/alunos.php` ecoavam dados do banco sem escape
  (armazenado). Agora usam `Seguranca::h()`.

### 3.12 MÉDIO — Login e cadastro aceitavam GET

`banco/data.php` usava `$_REQUEST` para tudo — o próprio `TODO` no topo do
arquivo já reconhecia o risco. Login por GET deixa a senha no log do Apache, no
histórico do navegador e no cabeçalho `Referer`. Agora `validar_login` e
`cadastrar_usuario` exigem POST (405 caso contrário).

### 3.13 MÉDIO — Validação só no navegador

O servidor só checava `strlen($pass) < 5`. Nome, e-mail e formato de login eram
validados apenas em JavaScript. Agora há validação equivalente no servidor, no
cadastro público e no cadastro de aluno pelo professor.

### 3.14 MÉDIO — `userValido()` carregava a tabela inteira

`SELECT usuario FROM usuarios_cadastrados` sem `WHERE`, comparando em PHP, com
race condition entre dois cadastros simultâneos. Virou `SELECT ... WHERE
usuario = :usuario LIMIT 1`, e a corrida agora bate no índice `UNIQUE` e é
tratada.

### 3.15 Código morto removido

Arquivos sem nenhuma chamada no projeto, todos alcançáveis por URL:

| Arquivo | Por que saiu |
|---|---|
| `area_aluno/data.php` | Sem verificação de login. Também estava **quebrado**: incluía `banco/conexao.php` sem `lab-config.php`, então `DB_HOST` e companhia nem existiam. |
| `area_aluno/busca_resumo.php` | Mesmo problema, mesma ausência de login. |
| `area_professor/app/usuario/insert-aluno.php` | Duplicata mais fraca de `Usuario::insertAluno` — gravava `sha1('123456')` com tipo 1. O formulário nunca apontou para ele. |
| `area_aluno/app/usuario/atualiza-perfil.php` | Chamava `LabJogo::setPerfil()`, que está dentro de um bloco comentado desde antes desta revisão — retornava 500 sempre. O `perfil.js` que o chamaria procura campos (`#nome_novo`, `.atualizar`) que não existem mais no formulário. O caminho que funciona é o POST do formulário para `index.php?aba=perfil`. |

Em `LabJogo`, `insertAluno()` e `resetarSenhaAluno()` (sem chamadas, gravavam
`sha1('123456')` com tipo 1) foram removidas, com comentário apontando para as
equivalentes em `Usuario`.

---

## 4. Arquivos novos

| Arquivo | Para quê |
|---|---|
| `classes/Seguranca.class.php` | Resolução segura de rota (`resolverArquivo`, `incluirRota`), `abortar()` e `h()` para escape de HTML. |
| `classes/Perfil.class.php` | Constantes de perfil, com a divergência histórica documentada no cabeçalho. |
| `docker/security.conf` | Endurecimento do Apache (é o que vale na imagem Docker). |
| `.htaccess`, `uploads/.htaccess` | Mesmas regras para instalação fora do Docker. |
| `banco/migracoes/2026-09-13-seguranca.sql` | Migração descrita na seção 1.1. |

---

## 5. NÃO corrigido — pendências

### 5.1 Não existe proteção CSRF (o maior item em aberto)

Não há token em nenhum formulário do projeto. Deletar aluno, resetar senha,
alterar perfil e subir arquivo continuam forjáveis por um link visitado por um
professor logado. O `SameSite=Lax` no cookie reduz bastante o alcance, mas não
substitui token.

Ficou de fora porque é uma mudança transversal, que toca todo formulário e todo
`$.ajax` do projeto — merece um PR próprio, para poder ser testada em separado.

### 5.2 Sem verificação de propriedade entre professores

Um professor ainda pode apagar prática ou disciplina de outro
(`delete-pratica.php`, `delete-disciplina.php`, `xhr-arquivos-pratica.php` na
ação `deletar`). Exige decidir o modelo de propriedade — não é só código.

### 5.3 Sem limite de tentativas de login

Força bruta continua livre. Não há bloqueio nem atraso progressivo.

### 5.4 Dois defeitos pré-existentes que o log agora expõe

Ambos **anteriores** a esta revisão, causados pelo mesmo bloco comentado em
`classes/LabJogo.class.php` (linhas 358–500 no arquivo original), que desativa
sete métodos: `getDisciplinasProfessor`, `insertDisciplina`, `setDisciplina`,
`codificarSenha`, `setPerfil`, `getRegistros` e `getRegistrosAluno`.

- `area_professor/abas/registros.php:18` chama `LabJogo::getRegistros()` →
  erro fatal. A aba "Meus registros" do professor está quebrada.
- `LabJogo::setPerfil()` era o motivo do 500 descrito em 3.15.

Não mexi nisso: descomentar código não testado é decisão de quem conhece a
intenção original. Fica registrado para um próximo PR.

### 5.5 Notices espalhados

Com o log ligado aparecem vários `Undefined index` em
`area_professor/abas/steps_aula/*`, `aulas.php` e `inicio.php`. Nenhum quebra
página (todas respondem 200), são ruído no log. Limpeza para depois.

### 5.6 Stack fora de suporte

PHP 7.4 (fim de vida 11/2022), MySQL 5.7 (10/2023), jQuery 3.4.1
(CVE-2020-11022/11023), Bootstrap 4.3.1 (CVE-2019-8331). Sem `composer.json`
nem `package.json`, atualizar é trocar arquivo à mão.

Atenção para a atualização de PHP: `Login::ckeckTipoUser()` fazia
`in_array($x, null)` quando `$permissao_usuario` não era definido — aviso no
7.4, **`TypeError` no PHP 8**, o que derrubaria a autenticação inteira. Isso já
foi corrigido (nega por padrão), mas há outros pontos do projeto no mesmo
estilo.

### 5.7 `PDO::ATTR_EMULATE_PREPARES`

Deixei ligado (padrão). Desligar é boa prática, mas muda o comportamento de
binding em código legado que não consigo testar por completo. Sugestão para um
PR separado.

---

## 6. Como foi verificado

Ambiente completo no ar (`docker compose up --build`), com o banco criado do
zero a partir do `quimica.sql`.

Confirmado que **bloqueia**:

- `GET /.env`, `/.git/config`, `/quimica.sql`, `/__lixo/index.php` → 403
- `GET /install.php` com o laboratório instalado → 410
- login por GET → 405
- cadastro com `acesso=2` → conta criada como tipo 3
- `?app=../../&file=lab-config`, `?app=usuario/../../..`, `?app=..%2f..`,
  `?file=../../../lab-config` → 404
- `?aba=../../lab-config`, `?aba=../index` → 404
- upload de `.php` → recusado pela extensão
- `.php` renomeado para `.png` → recusado pelo `finfo`
- `id_pratica=../../` no upload → recusado
- `.php` colocado à força em `uploads/` → 403 (não executa)
- endpoint do laboratório sem login → 302 para o login
- `carregar_alunos` sem login e como aluno → 403
- aluno acessando área do professor → 302
- reset de senha e exclusão do `admin` por um professor → recusados
- `tipo_acesso` com `'><script>` → sai escapado (`'><script>`)

Confirmado que **continua funcionando**:

- login do `admin` com o hash SHA-1 antigo, e o hash **reescrito em bcrypt**
  (verificado no banco: 60 caracteres, `$2y$10$`)
- segundo login, já com o hash bcrypt
- todas as abas de professor e de aluno → 200
- `lab.php` e os três endpoints de `app/jogo/` → 200
- cadastro de aluno pelo professor, com senha sorteada exibida na tela, e login
  do aluno com essa senha
- login duplicado recusado com mensagem (antes: falha silenciosa reportada como
  sucesso)
- reset de senha de aluno devolvendo a senha nova
- upload de PNG válido, gravado com nome aleatório e MIME detectado no servidor
- imagem em `uploads/` ainda servida normalmente (200, `image/png`)
- atualização de perfil pelo formulário, com a senha nova gravada em bcrypt
- logout invalidando a sessão
