# Contribuindo

Obrigado por ajudar a manter o NeoAlice. O repositório usa Docker Compose para que todos desenvolvam contra a mesma versão de PHP e banco de dados.

## Começar

```bash
git clone https://github.com/aferoliv/NeoAlice.git
cd NeoAlice
cp .env.example .env
docker compose up --build
```

Abra `http://localhost:8080/`. Para acompanhar os serviços, use `docker compose logs -f web` ou `docker compose logs -f db`.

O código-fonte é montado no contêiner automaticamente: edite os arquivos localmente e atualize o navegador. Quando modificar `Dockerfile`, execute novamente `docker compose up --build`.

## Fluxo de Pull Request

1. Faça um fork no GitHub e clone-o.
2. Crie uma branch a partir de `main`: `git switch -c descricao-curta`.
3. Mantenha cada Pull Request focado em uma alteração.
4. Teste a alteração localmente e descreva no PR como ela foi verificada.
5. Não envie `.env`, `lab-config.php`, backups de banco de dados ou arquivos em `uploads/`.

## Dados locais

O primeiro início cria volumes Docker para o MySQL e os uploads. Para parar os serviços sem apagar seus dados, execute:

```bash
docker compose down
```

Para apagar **todos** os dados locais e reiniciar do zero, execute o comando destrutivo abaixo:

```bash
docker compose down -v
```

## Segurança

Não publique uma instância de desenvolvimento na internet. Use credenciais próprias no arquivo `.env` e troque a senha da conta inicial (`admin` / `123456`) no primeiro acesso — o sistema prende você na aba *Meu perfil* até que isso seja feito.

O projeto passou por uma revisão de segurança em setembro de 2026, descrita em [SECURITY-FIXES.md](SECURITY-FIXES.md). Vale ler antes de mexer em autenticação, roteamento ou upload. Em resumo, o que mudou para quem desenvolve:

- As senhas usam `password_hash()`/`password_verify()` (bcrypt). Nunca gere hash direto: use `Login::gerarHash()`.
- Rotas nunca são concatenadas dentro de `include`. Para incluir um arquivo cujo caminho venha da requisição, use `Seguranca::incluirRota()` ou `Seguranca::resolverArquivo()`.
- Todo `POST` exige token CSRF. Em formulário HTML, inclua `<?php echo Seguranca::campoCsrf(); ?>`; chamadas jQuery já recebem o cabeçalho automaticamente por `js/csrf.js`.
- Escape toda saída vinda do banco ou da requisição com `Seguranca::h()`.
- Os perfis ficam em `Perfil::` — não escreva `1`, `2` ou `3` soltos no código.
- O PDO agora lança exceção em erro. Um `INSERT` que falha não passa mais despercebido; trate ou deixe subir.

Erros não aparecem mais na tela: vão para o log. Use `docker compose logs -f web` ao investigar uma página em branco.

### Atualizando um banco local já existente

Quem já tinha o projeto rodando antes da revisão precisa aplicar as migrações uma vez:

```bash
docker compose exec -T db mysql -u root -p quimica < banco/migracoes/2026-09-13-seguranca.sql
docker compose exec -T db mysql -u root -p quimica < banco/migracoes/2026-09-14-csrf-e-rate-limit.sql
```

Um banco criado do zero (`docker compose down -v` seguido de `up`) já vem com tudo e não precisa disso.
