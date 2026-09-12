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

O projeto contém componentes legados, incluindo hashes de senha SHA-1. Não publique uma instância de desenvolvimento na internet. Use credenciais próprias no arquivo `.env` e troque a senha da conta inicial imediatamente.
