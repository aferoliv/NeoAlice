# Laboratório Virtual de Química (NeoAlice)

## Desenvolvimento local com Docker

O único pré-requisito é o [Docker Desktop](https://www.docker.com/products/docker-desktop/) (ou Docker Engine com o plugin Docker Compose). Não é necessário instalar PHP, MySQL, Composer ou npm no computador.

```bash
git clone https://github.com/aferoliv/NeoAlice.git
cd NeoAlice
cp .env.example .env
docker compose up --build
```

Abra <http://localhost:8080>. Na primeira execução, o banco de dados é criado a partir de `quimica.sql`. Para encerrar, use `docker compose down`.

Em instalações antigas que disponibilizam `docker-compose` em vez de `docker compose`, substitua o comando ao longo deste guia.

O login inicial é `admin` / `123456`; altere essa senha assim que entrar. Os comandos detalhados de operação e deploy estão em [DEPLOYMENT.md](DEPLOYMENT.md).

## Como contribuir

1. Crie um fork e uma branch com uma descrição curta: `git switch -c corrigir-login`.
2. Faça a alteração e teste-a com `docker compose up --build`.
3. Registre o trabalho: `git add <arquivos>` e `git commit -m "Corrige login"`.
4. Envie a branch ao seu fork e abra um Pull Request para `main`.

Consulte [CONTRIBUTING.md](CONTRIBUTING.md) para o fluxo completo e cuidados com dados locais.

## Sobre o projeto

Laboratório Virtual produzido durante o proejto "Laboratório Virtual de Quimica", financiado pelo edital 03/2015 da CAPES/UAB

## Licença
Todo o conteúdo artistico do laboratório (ilustrações, vídeos) está licenciado sob licença creative commons "Attribution-NonCommercial-ShareAlike 3.0 Unported (CC BY-NC-SA 3.0)". E o código fonte do laboratório é distribuído sob a licença "GNU Affero General Public License v3.0"

## Equipe
### Briefing/Definição do MVP
Andre Fernando Oliveira/DEQ-UFV  
Sergio Olivo  
Pedro de Almeida Sacramento/CEAD-UFV  
Thaynara Rocha Mendonça/DEQ-UFV  

### Equipe de desenvolvimento
Bruno de Lima Santos/DEQ-UFV  
Carolina Castro Freitas Prado/DEQ-UFV  
Cesar Ruben Francisco Gennaro Campos/DPI-UFV  
Gustavo Freire da Silva/DEQ-UFV  
Maria Eduarda Oliveira Miranda/DPI-UFV  
Pedro de Almeida Sacramento/CEAD-UFV  
Thaynara Rocha Mendonça/DEQ-UFV  
Wellerson David Cardoso Vieira/CEAD-UFV  
Marcelo dos Santos Teixeira/CEAD-UFV  

### Equipe de testes
Angélica Lorena dos Santos Oliveira/DEQ-UFV  
Laísa Bullerjahn/DEQ-UFV  
Stefania Mora Güezguán/DEQ-UFV  

### Estudo ISO17025
Anna Luisa Ribeiro Miguel/DEQ-UFV  
Algoritmos para Equilíbrios Químicos Simultâneos  
Carolina Castro Freitas Prado/DEQ-UFV  
Thaynara Rocha Mendonça/DEQ-UFV  
Andre Fernando Oliveira/DEQ-UFV  

### Comportamento Experimental dos Instrumentos
Alessandra Zinatto Rodrigues/DEQ-UFV  
Stefania Mora Güezguán/DEQ-UFV  

### Práticas realizadas Experimentalmente
Carolina Teixeira Costa Alpino/DEQ-UFV  
Felipe Santana/DEQ-UFV  

### Ilustração e design
Cristian de Aguiar Silva/CEAD-UFV  
Edson Ney Duarte Nogueira/CEAD-UFV  
Wildson Lima Paiva Osório/CEAD-UFV  
