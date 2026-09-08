# Setup e operação

## Subir o ambiente

Pré-requisitos: Docker e Docker Compose.

```bash
docker-compose up -d --build
# ou, com o Compose como plugin:  docker compose up -d --build
```

- Portal: <http://localhost:8080>
- MySQL exposto no host em `localhost:3307` (usuário `portal`, senha `portal`)

O serviço `web` só inicia depois que o `db` responde ao healthcheck, então a
primeira subida leva alguns segundos a mais enquanto o schema é criado.

## Ciclo de vida

```bash
docker-compose ps                 # estado dos serviços
docker-compose logs -f web        # logs do Apache/PHP (erros do PHP saem aqui)
docker-compose logs -f db         # logs do MySQL
docker-compose restart web        # reinicia só o PHP
docker-compose down               # para tudo, preserva o banco
docker-compose down -v            # para tudo e apaga o volume do banco
```

O código em `src/` é montado como volume no container. **Editar um arquivo PHP,
CSS ou JS reflete no próximo request**, sem rebuild. Rebuild só é necessário ao
mexer no `Dockerfile` ou no vhost do Apache.

## Inspecionar o banco

```bash
docker-compose exec db mysql -uportal -pportal portal_cartas

# ou direto:
docker-compose exec db mysql -uportal -pportal portal_cartas \
  -e "SELECT id, nome_en, card_game, edicao_nome, raridade FROM cartas;"
docker-compose exec db mysql -uportal -pportal portal_cartas \
  -e "SELECT id, nome, email, created_at FROM usuarios;"
```

## Reaplicar schema e seeds

O `docker/mysql/init.sql` roda **apenas na primeira inicialização** do volume do
MySQL. Para reexecutá-lo depois de alterá-lo:

```bash
docker-compose down -v
docker-compose up -d
```

## Testar a API pela linha de comando

```bash
# login, guardando o cookie de sessão
curl -s -c cookies.txt -X POST http://localhost:8080/api/login.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@cards.com","senha":"admin123password"}'

curl -s 'http://localhost:8080/api/edicoes.php?game=yugioh'
curl -s -b cookies.txt 'http://localhost:8080/api/cartas.php?game=magic'

# criar com upload de imagem
curl -s -b cookies.txt -X POST http://localhost:8080/api/cartas.php \
  -F 'nome_en=Black Lotus' -F 'card_game=magic' -F 'edicao_id=dom' \
  -F 'raridade=Mítica' -F 'imagem=@/caminho/para/carta.png'
```

## Problemas comuns

**A porta 8080 já está em uso.** Altere o mapeamento em `docker-compose.yml`
(`"8081:80"`) e suba de novo.

**A porta 3307 já está em uso.** Mesma ideia, no serviço `db`. Essa porta só
existe para inspeção externa; o `web` fala com o banco pela rede interna.

**"Não foi possível salvar a imagem. Verifique as permissões de src/uploads."**
O Apache grava em `src/uploads` através do bind mount, então o `www-data` do
container precisa ter o mesmo UID do dono dos arquivos no host. O `Dockerfile`
faz esse alinhamento com os build args `APP_UID`/`APP_GID`, que assumem `1000`.
Se o seu usuário tem outro UID:

```bash
APP_UID=$(id -u) APP_GID=$(id -g) docker-compose up -d --build
```

**Erros de PHP não aparecem na tela.** É proposital (`display_errors = Off`).
Eles vão para o log: `docker-compose logs -f web`.

**O login não aceita as credenciais.** Confirme que o seed rodou:
`docker-compose exec db mysql -uportal -pportal portal_cartas -e "SELECT email FROM usuarios;"`.
Se a tabela estiver vazia, o volume foi criado antes do `init.sql` existir —
resolva com `docker-compose down -v && docker-compose up -d`.
