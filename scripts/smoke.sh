#!/usr/bin/env bash
#
# Bateria de fumaça do Portal de Cartas.
#
# Exercita o portal pela porta HTTP, do jeito que o navegador faz: páginas,
# arquivos estáticos, blindagem do Apache e os quatro endpoints da API, com
# sessão de verdade. Serve para conferir um deploy recém-subido em um comando.
#
#   docker compose up -d --build
#   ./scripts/smoke.sh
#
# Não depende do estado do seed e limpa o que cria, então pode rodar quantas
# vezes for preciso, inclusive contra um banco já em uso. Requer apenas curl e
# python3 — o python só lê um campo do JSON de resposta, nada entra no projeto.
#
# Aponta para outro host com: BASE=http://meu-servidor:8080 ./scripts/smoke.sh

set -uo pipefail

BASE=${BASE:-http://localhost:8080}
EMAIL=${EMAIL:-admin@cards.com}
SENHA=${SENHA:-admin123password}

command -v curl    >/dev/null || { echo "curl não encontrado."    >&2; exit 2; }
command -v python3 >/dev/null || { echo "python3 não encontrado." >&2; exit 2; }

COOKIE=$(mktemp)
trap 'rm -f "$COOKIE"' EXIT

OK=0
FALHA=0

# Cor só quando a saída é um terminal: em CI ou em pipe o log fica limpo.
if [ -t 1 ]; then VERDE=$'\033[32m'; VERMELHO=$'\033[31m'; NEUTRO=$'\033[0m'
else                VERDE=''; VERMELHO=''; NEUTRO=''; fi

# checa <descrição> <esperado> <obtido>
checa() {
  if [ "$2" = "$3" ]; then
    printf '  %sok%s    %-56s %s\n' "$VERDE" "$NEUTRO" "$1" "$3"
    OK=$((OK + 1))
  else
    printf '  %sFALHA%s %-56s esperado=%s obtido=%s\n' "$VERMELHO" "$NEUTRO" "$1" "$2" "$3"
    FALHA=$((FALHA + 1))
  fi
}

titulo() { printf '\n── %s %s\n' "$1" "$(printf '─%.0s' $(seq 1 $((70 - ${#1}))))"; }

# status <método> <rota> [flags do curl…]  → código HTTP
status() {
  local metodo=$1 rota=$2; shift 2
  curl -s -o /dev/null -w '%{http_code}' -X "$metodo" -b "$COOKIE" -c "$COOKIE" "$@" "$BASE$rota"
}

# corpo <método> <rota> [flags do curl…]  → corpo da resposta
corpo() {
  local metodo=$1 rota=$2; shift 2
  curl -s -X "$metodo" -b "$COOKIE" -c "$COOKIE" "$@" "$BASE$rota"
}

# campo <caminho.pontilhado>  ← JSON na entrada padrão
# "colecao.cartas.0.quantidade" e "edicoes.length" (tamanho da lista) funcionam.
campo() {
  python3 -c '
import json, sys
try:
    atual = json.load(sys.stdin)
except Exception:
    print("JSON_INVALIDO"); sys.exit()
for chave in sys.argv[1].split("."):
    if atual is None:
        break
    if chave == "length" and isinstance(atual, list):
        atual = len(atual)
    elif isinstance(atual, list):
        atual = atual[int(chave)] if chave.isdigit() and int(chave) < len(atual) else None
    else:
        atual = atual.get(chave)
print("" if atual is None else atual)
' "$1"
}

echo "Portal de Cartas — bateria de fumaça contra $BASE"

titulo "Páginas e estáticos"
checa "GET /                              (login)"             200 "$(status GET /)"
checa "GET /dashboard.php sem sessão      (redireciona)"       302 "$(status GET /dashboard.php)"
checa "GET /colecoes.php sem sessão       (redireciona)"       302 "$(status GET /colecoes.php)"
checa "GET /importacoes.php sem sessão    (redireciona)"       302 "$(status GET /importacoes.php)"
checa "GET /assets/css/style.css"                              200 "$(status GET /assets/css/style.css)"
checa "GET /assets/js/common.js"                               200 "$(status GET /assets/js/common.js)"
checa "GET /assets/img/cards/pikachu.webp (imagem do seed)"    200 "$(status GET /assets/img/cards/pikachu.webp)"
checa "GET /assets/img/hero-painel.jpg"                        200 "$(status GET /assets/img/hero-painel.jpg)"

titulo "Blindagem do servidor"
checa "GET /config/database.php           (negado)"            403 "$(status GET /config/database.php)"
checa "GET /config/                       (negado)"            403 "$(status GET /config/)"
checa "GET /partials/cabecalho.php        (negado)"            403 "$(status GET /partials/cabecalho.php)"
checa "GET /partials/documento.php        (negado)"            403 "$(status GET /partials/documento.php)"
checa "GET /api/                          (sem listagem)"      403 "$(status GET /api/)"

titulo "Catálogos públicos"
checa "GET /api/edicoes.php?game=magic"                        200 "$(status GET '/api/edicoes.php?game=magic')"
checa "GET /api/edicoes.php sem game      (422)"               422 "$(status GET /api/edicoes.php)"
checa "GET /api/edicoes.php?game=xyz      (404)"               404 "$(status GET '/api/edicoes.php?game=xyz')"
checa "POST /api/edicoes.php              (405)"               405 "$(status POST /api/edicoes.php)"
checa "GET /api/plataformas.php?game=pokemon"                  200 "$(status GET '/api/plataformas.php?game=pokemon')"
checa "  → 5 edições em magic"                                 5   "$(corpo GET '/api/edicoes.php?game=magic' | campo edicoes.length)"

titulo "Rotas de negócio exigem sessão"
checa "GET /api/cartas.php                (401)"               401 "$(status GET /api/cartas.php)"
checa "GET /api/colecoes.php              (401)"               401 "$(status GET /api/colecoes.php)"
checa "GET /api/importacoes.php           (401)"               401 "$(status GET /api/importacoes.php)"

titulo "Login"
checa "POST senha errada                  (401)"               401 "$(status POST /api/login.php -H 'Content-Type: application/json' -d "{\"email\":\"$EMAIL\",\"senha\":\"errada\"}")"
checa "POST e-mail inexistente            (401)"               401 "$(status POST /api/login.php -H 'Content-Type: application/json' -d "{\"email\":\"ninguem@cards.com\",\"senha\":\"$SENHA\"}")"
checa "POST sem campos                    (422)"               422 "$(status POST /api/login.php -H 'Content-Type: application/json' -d '{}')"
checa "POST JSON quebrado                 (400)"               400 "$(status POST /api/login.php -H 'Content-Type: application/json' -d '{isto nao e json')"
rm -f "$COOKIE"; COOKIE=$(mktemp)   # sessão limpa: as tentativas acima não deixam rastro
checa "POST credenciais boas              (200)"               200 "$(status POST /api/login.php -H 'Content-Type: application/json' -d "{\"email\":\"$EMAIL\",\"senha\":\"$SENHA\"}")"
checa "GET /dashboard.php com sessão      (200)"               200 "$(status GET /dashboard.php)"
checa "GET / com sessão                   (vai ao painel)"     302 "$(status GET /index.php)"

titulo "Cartas"
checa "GET /api/cartas.php                (200)"               200 "$(status GET /api/cartas.php)"
checa "  → 12 cartas no seed"                                  12  "$(corpo GET /api/cartas.php | campo resumo.total)"
checa "  → 5 card games"                                       5   "$(corpo GET /api/cartas.php | campo resumo.jogos)"
checa "GET ?game=magic                    (3 cartas)"          3   "$(corpo GET '/api/cartas.php?game=magic' | campo total)"
checa "GET ?search=pikachu                (1 carta)"           1   "$(corpo GET '/api/cartas.php?search=pikachu' | campo total)"
checa "GET ?search=%25                    (curinga escapado)"  0   "$(corpo GET '/api/cartas.php?search=%25' | campo total)"
checa "GET ?game=invalido                 (422)"               422 "$(status GET '/api/cartas.php?game=invalido')"
checa "GET ?id=99999                      (404)"               404 "$(status GET '/api/cartas.php?id=99999')"
checa "GET ?id=abc                        (422)"               422 "$(status GET '/api/cartas.php?id=abc')"
checa "PATCH /api/cartas.php              (405)"               405 "$(status PATCH /api/cartas.php)"

NOVA=$(corpo POST /api/cartas.php -H 'Content-Type: application/json' \
  -d '{"nome_en":"Smoke Test Card","nome_pt":"Carta de Teste","card_game":"magic","edicao_id":"dom","raridade":"Comum","imagem_url":"https://exemplo.com/x.png"}')
CARTA=$(echo "$NOVA" | campo carta.id)
checa "POST cria carta                    (id novo)"           "sim" "$([ -n "$CARTA" ] && echo sim || echo nao)"
checa "  → edicao_nome resolvida no servidor"                  "Dominaria" "$(echo "$NOVA" | campo carta.edicao_nome)"
checa "POST sem campos                    (422)"               422 "$(status POST /api/cartas.php -H 'Content-Type: application/json' -d '{}')"
checa "POST edição de outro jogo          (422)"               422 "$(status POST /api/cartas.php -H 'Content-Type: application/json' -d '{"nome_en":"X","card_game":"magic","edicao_id":"base1","raridade":"Comum"}')"
checa "POST imagem javascript:            (422 — XSS)"         422 "$(status POST /api/cartas.php -H 'Content-Type: application/json' -d '{"nome_en":"X","card_game":"magic","edicao_id":"dom","raridade":"Comum","imagem_url":"javascript:alert(1)"}')"
checa "POST imagem data:                  (422 — XSS)"         422 "$(status POST /api/cartas.php -H 'Content-Type: application/json' -d '{"nome_en":"X","card_game":"magic","edicao_id":"dom","raridade":"Comum","imagem_url":"data:text/html,<script>"}')"
checa "PUT atualiza a carta               (200)"               200 "$(status PUT "/api/cartas.php?id=$CARTA" -H 'Content-Type: application/json' -d '{"nome_en":"Smoke Test Card 2","card_game":"magic","edicao_id":"war","raridade":"Rara"}')"
checa "  → nome atualizado"                 "Smoke Test Card 2" "$(corpo GET "/api/cartas.php?id=$CARTA" | campo carta.nome_en)"
checa "DELETE remove a carta              (200)"               200 "$(status DELETE "/api/cartas.php?id=$CARTA")"
checa "  → some do acervo                 (404)"               404 "$(status GET "/api/cartas.php?id=$CARTA")"

titulo "SQL Injection"
checa "search com aspa + OR 1=1           (não vaza)"          0   "$(corpo GET "/api/cartas.php?search=%27%20OR%20%271%27%3D%271" | campo total)"
checa "search com DROP TABLE              (inerte)"            0   "$(corpo GET "/api/cartas.php?search=%27%3B%20DROP%20TABLE%20cartas%3B%20--" | campo total)"
checa "  → tabela cartas intacta"                              12  "$(corpo GET /api/cartas.php | campo resumo.total)"

titulo "Coleções"
checa "GET /api/colecoes.php              (200)"               200 "$(status GET /api/colecoes.php)"
checa "GET ?tipo=invalido                 (422)"               422 "$(status GET '/api/colecoes.php?tipo=invalido')"

COL=$(corpo POST /api/colecoes.php -H 'Content-Type: application/json' \
  -d '{"nome":"Smoke Binder","descricao":"criado pela bateria de fumaça","card_game":"pokemon","tipo":"binder"}' | campo colecao.id)
checa "POST cria coleção                  (id novo)"           "sim" "$([ -n "$COL" ] && echo sim || echo nao)"
PKM=$(corpo GET '/api/cartas.php?game=pokemon&limit=1' | campo cartas.0.id)
MTG=$(corpo GET '/api/cartas.php?game=magic&limit=1'   | campo cartas.0.id)
checa "POST carta do mesmo jogo           (200)"               200 "$(status POST "/api/colecoes.php?id=$COL&acao=cartas" -H 'Content-Type: application/json' -d "{\"carta_id\":$PKM,\"quantidade\":3}")"
checa "POST carta de outro jogo           (422)"               422 "$(status POST "/api/colecoes.php?id=$COL&acao=cartas" -H 'Content-Type: application/json' -d "{\"carta_id\":$MTG,\"quantidade\":1}")"
checa "POST quantidade 0                  (422)"               422 "$(status POST "/api/colecoes.php?id=$COL&acao=cartas" -H 'Content-Type: application/json' -d "{\"carta_id\":$PKM,\"quantidade\":0}")"
checa "POST quantidade 100                (422)"               422 "$(status POST "/api/colecoes.php?id=$COL&acao=cartas" -H 'Content-Type: application/json' -d "{\"carta_id\":$PKM,\"quantidade\":100}")"
checa "  → quantidade gravada"                                 3   "$(corpo GET "/api/colecoes.php?id=$COL" | campo colecao.cartas.0.quantidade)"
checa "PUT muda o card game com cartas    (409)"               409 "$(status PUT "/api/colecoes.php?id=$COL" -H 'Content-Type: application/json' -d '{"nome":"Smoke Binder","card_game":"magic","tipo":"binder"}')"
checa "DELETE tira a carta                (200)"               200 "$(status DELETE "/api/colecoes.php?id=$COL&acao=cartas&carta_id=$PKM")"
checa "DELETE a mesma carta de novo       (404)"               404 "$(status DELETE "/api/colecoes.php?id=$COL&acao=cartas&carta_id=$PKM")"
checa "PUT muda o card game já vazia      (200)"               200 "$(status PUT "/api/colecoes.php?id=$COL" -H 'Content-Type: application/json' -d '{"nome":"Smoke Binder","card_game":"magic","tipo":"binder"}')"
checa "GET ?acao=inexistente              (404)"               404 "$(status GET "/api/colecoes.php?id=$COL&acao=xpto")"
checa "DELETE a coleção                   (200)"               200 "$(status DELETE "/api/colecoes.php?id=$COL")"
checa "  → sumiu                          (404)"               404 "$(status GET "/api/colecoes.php?id=$COL")"

titulo "Importações"
checa "GET /api/importacoes.php           (200)"               200 "$(status GET /api/importacoes.php)"
checa "GET ?status=invalido               (422)"               422 "$(status GET '/api/importacoes.php?status=invalido')"

# O vínculo é criado sobre um deck próprio: a bateria não depende do estado do
# seed nem consome os três vínculos que ele traz.
DECK=$(corpo POST /api/colecoes.php -H 'Content-Type: application/json' \
  -d '{"nome":"Smoke Deck","card_game":"yugioh","tipo":"deck"}' | campo colecao.id)
MARCA="smoke-$$"
VINCULO=$(corpo POST /api/importacoes.php -H 'Content-Type: application/json' \
  -d "{\"card_game\":\"yugioh\",\"plataforma\":\"ygoprodeck\",\"identificador\":\"$MARCA\",\"colecao_id\":$DECK}")
VID=$(echo "$VINCULO" | campo importacao.id)
checa "POST cria o vínculo                (pendente)"     "pendente" "$(echo "$VINCULO" | campo importacao.status)"
checa "POST vínculo duplicado             (409)"               409 "$(status POST /api/importacoes.php -H 'Content-Type: application/json' -d "{\"card_game\":\"yugioh\",\"plataforma\":\"ygoprodeck\",\"identificador\":\"$MARCA\",\"colecao_id\":$DECK}")"
checa "POST plataforma de outro jogo      (422)"               422 "$(status POST /api/importacoes.php -H 'Content-Type: application/json' -d "{\"card_game\":\"yugioh\",\"plataforma\":\"ptcglive\",\"identificador\":\"Z9\",\"colecao_id\":$DECK}")"
checa "POST em coleção inexistente        (422)"               422 "$(status POST /api/importacoes.php -H 'Content-Type: application/json' -d '{"card_game":"yugioh","plataforma":"ygoprodeck","identificador":"Z9","colecao_id":999999}')"
checa "POST apontando para um binder      (422)"               422 "$(status POST /api/importacoes.php -H 'Content-Type: application/json' -d "{\"card_game\":\"yugioh\",\"plataforma\":\"ygoprodeck\",\"identificador\":\"Z9\",\"colecao_id\":$(corpo GET '/api/colecoes.php?tipo=binder' | campo colecoes.0.id)}")"

SINCRONIA=$(corpo POST "/api/importacoes.php?id=$VID&acao=sincronizar")
checa "POST sincronizar                   (concluída)"   "concluida" "$(echo "$SINCRONIA" | campo importacao.status)"
checa "  → 3 cartas de yugioh vinculadas"                       3   "$(echo "$SINCRONIA" | campo importacao.cartas_vinculadas)"
checa "  → 2 fora do acervo, reportadas"                        2   "$(echo "$SINCRONIA" | campo nao_localizadas.length)"
checa "  → as cartas entraram no deck"                          3   "$(corpo GET "/api/colecoes.php?id=$DECK" | campo colecao.total_cartas)"
checa "POST ?acao=inexistente             (404)"               404 "$(status POST "/api/importacoes.php?id=$VID&acao=xpto")"
checa "PUT /api/importacoes.php           (405)"               405 "$(status PUT /api/importacoes.php)"
checa "DELETE desfaz o vínculo            (200)"               200 "$(status DELETE "/api/importacoes.php?id=$VID")"
checa "  → o deck volta a ficar vazio"                          0   "$(corpo GET "/api/colecoes.php?id=$DECK" | campo colecao.total_cartas)"
checa "DELETE limpa o deck da bateria     (200)"               200 "$(status DELETE "/api/colecoes.php?id=$DECK")"

titulo "Logout"
checa "POST /api/logout.php               (200)"               200 "$(status POST /api/logout.php)"
checa "GET /api/cartas.php depois         (401)"               401 "$(status GET /api/cartas.php)"
checa "GET /dashboard.php depois          (302)"               302 "$(status GET /dashboard.php)"

printf '\n%s\n' "$(printf '═%.0s' $(seq 1 73))"
if [ "$FALHA" -eq 0 ]; then
  printf '  %s%d verificações, todas passaram.%s\n' "$VERDE" "$OK" "$NEUTRO"
else
  printf '  %s%d passaram, %d FALHARAM.%s\n' "$VERMELHO" "$OK" "$FALHA" "$NEUTRO"
fi
printf '%s\n' "$(printf '═%.0s' $(seq 1 73))"

exit $((FALHA > 0))
