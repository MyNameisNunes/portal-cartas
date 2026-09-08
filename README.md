# Portal Administrativo de Gestão de Cartas

Portal web para cadastro e gestão de cartas de **Trading Card Games (TCGs)**, com
autenticação por login/senha e CRUD completo de cartas.

Construído com **PHP puro**, **MySQL** e **HTML5 + CSS3 + JavaScript vanilla** —
sem nenhum framework, biblioteca de componentes ou utilitário externo, tanto no
backend quanto no frontend. Sobe inteiro com um único comando via Docker.

---

## Como rodar

Pré-requisitos: **Docker** e **Docker Compose**.

```bash
docker-compose up -d --build
```

> Em instalações mais novas, o Compose é um plugin do Docker.
> Se `docker-compose` não existir na sua máquina, use `docker compose up -d --build`.

Acesse: **<http://localhost:8080>**

O banco leva alguns segundos para inicializar na primeira execução. O container
web só sobe depois que o MySQL responde ao healthcheck, então basta aguardar o
`docker-compose ps` mostrar os dois serviços de pé.

Para encerrar:

```bash
docker-compose down          # para os containers, preserva o banco
docker-compose down -v       # para os containers e apaga o banco (reexecuta os seeds no próximo up)
```

### Credenciais de teste

| Campo   | Valor               |
| ------- | ------------------- |
| Usuário | `admin@cards.com`   |
| Senha   | `admin123password`  |

A senha é gravada no banco com `password_hash()` (bcrypt, custo 10) e conferida
com `password_verify()`. Em nenhum momento trafega ou é armazenada em texto puro.

### Conferindo o ambiente

Com a stack de pé, uma bateria de fumaça verifica o portal inteiro pela porta
HTTP — páginas, estáticos, blindagem do Apache e os quatro endpoints da API, com
sessão de verdade:

```bash
./scripts/smoke.sh
```

São 86 verificações. Ela cria o próprio deck e a própria carta, apaga tudo o que
criou e não depende do estado do seed, então pode rodar quantas vezes for
preciso, inclusive contra um banco já em uso. Sai com código diferente de zero se
qualquer checagem falhar, o que a torna utilizável em CI. Para apontar para outro
host: `BASE=http://meu-servidor:8080 ./scripts/smoke.sh`.

---

## Decisões de UX/Produto

O portal é operado por administradores com níveis muito diferentes de
familiaridade com tecnologia. As decisões abaixo partem daí.

### 1. Feedback visual explícito em toda ação assíncrona

O ponto mais crítico é o `<select>` de **Edição**, que depende de uma requisição
disparada pela escolha do Card Game. Um select que fica vazio por meio segundo
não comunica nada: o usuário não sabe se o sistema travou, se ele errou o passo
ou se deve clicar de novo.

O campo passa por quatro estados visíveis, e nunca fica ambíguo:

| Estado | O que o usuário vê |
| --- | --- |
| **Inicial** | Desabilitado, com o texto *"Selecione o card game primeiro"* — a ordem de preenchimento fica óbvia sem precisar de instrução |
| **Carregando** | Desabilitado, texto *"Carregando edições…"* e um spinner sobreposto |
| **Pronto** | Habilitado e populado com as edições daquele jogo |
| **Erro** | Estado de erro em vermelho e um botão *"Tentar carregar as edições novamente"*, em vez de um beco sem saída |

A mesma lógica vale para os botões: enquanto um envio está no ar, o botão fica
com `aria-busy`, desabilitado e com rótulo trocado (*"Salvando…"*, *"Entrando…"*).
Isso **elimina o clique duplo** — a causa mais comum de registros duplicados em
formulários administrativos — sem depender de o usuário perceber sozinho que algo
está acontecendo.

Dois detalhes de robustez que vieram junto: trocar o Card Game rapidamente
**aborta a requisição anterior** (`AbortController`), então uma resposta atrasada
do jogo antigo nunca sobrescreve a lista do jogo escolhido por último; e as
edições já carregadas ficam em cache, então voltar a um jogo anterior é
instantâneo, sem repetir o spinner à toa.

### 2. Exclusão sempre confirmada, e layout em grid de cards

**Confirmação de exclusão.** Excluir uma carta é destrutivo e irreversível, e o
botão *Excluir* fica a poucos pixels do *Editar* dentro de cada card. Em vez do
`confirm()` nativo do navegador — que é genérico, não diz *qual* item será
apagado e é fácil de dispensar no automático — o portal abre um
`role="alertdialog"` que **nomeia a carta em questão**, avisa explicitamente que a
ação não pode ser desfeita e rotula os botões pela consequência (*"Manter carta"*
e *"Sim, excluir"*, em vez de *OK/Cancelar*). O foco vai para o diálogo, `Esc`
cancela, e o botão destrutivo é o único em vermelho na tela.

**Grid de cards responsivo.** A escolha de grid de cards em vez de tabela vem da
natureza do dado: carta de TCG é reconhecida **pela arte**, não pelo texto. Uma
tabela obriga o administrador a ler nomes em inglês para localizar um item; o
grid deixa a imagem fazer esse trabalho, com a raridade como badge colorido sobre
ela. O grid usa CSS Grid e reflui de 5 colunas para 2 e depois para 1 conforme a
largura, sem quebrar — enquanto uma tabela com 6 colunas exigiria rolagem
horizontal no celular, que é onde a conferência rápida de acervo costuma
acontecer.

### 3. Identidade vermelha, tipografia fixa e tema claro/escuro

**Uma paleta, declarada num lugar só.** Toda cor, fonte e sombra da interface sai
de um token em `:root` (`--marca`, `--tinta`, `--recuo`, `--fonte-titulo`…).
Nenhuma regra da folha carrega um hexadecimal solto. Foi o que permitiu virar a
marca de azul para vermelho e acrescentar o tema escuro sem varrer a folha atrás
de valores perdidos: o tema escuro **redeclara os mesmos tokens**, nada mais.

A marca tem dois vermelhos, porque texto e preenchimento pedem contrastes
diferentes: `--marca` (`#c8102e`) vai sobre o fundo da página, e `--marca-cheia`
(`#d81f2a`) recebe texto branco por cima. Ambos passam de 4.5:1 nos dois temas,
assim como o texto apagado e as cores de estado.

**Ação destrutiva não é distinguida só pela cor.** Com a marca em vermelho,
pintar *Excluir* de vermelho o deixaria idêntico a *Editar*. Quem separa os dois
é a forma: a ação principal vem preenchida com a marca, a destrutiva vem apenas
contornada. Isso também protege quem não distingue as duas matizes.

**Tipografia em dois papéis.** Space Grotesk para títulos, números e rótulos
curtos; DM Sans para texto corrido, campos e botões. As duas famílias são
declaradas uma única vez, em `--fonte-titulo` e `--fonte-texto`, cada uma com a
pilha de fallback do sistema para a janela em que a fonte do Google ainda não
chegou — antes, cada regra repetia `'Space Grotesk'` e boa parte delas sem
fallback nenhum.

**Tema sem piscada.** Sem escolha salva vale o `prefers-color-scheme` do sistema;
ao clicar no botão do topo, a escolha vira explícita e passa a valer em todas as
páginas, mesmo que o sistema mude depois. Quem aplica o tema é um script inline
no `<head>` (em `partials/documento.php`), antes da primeira pintura — um arquivo
externo carregaria depois do CSS e a tela apareceria clara por um quadro antes de
escurecer. Como esse script resolve o tema sozinho, a folha de estilo tem **um
gatilho só** (`html[data-tema="escuro"]`) em vez da lista de tokens repetida
dentro de uma media query.

---

## Arquitetura

```text
/
├── docker/
│   ├── mysql/
│   │   ├── init.sql              # schema + seeds (roda no 1º boot do MySQL)
│   │   └── migrations/           # o mesmo schema, para bancos que já existem
│   └── php/
│       ├── Dockerfile            # PHP 8.3 + Apache + pdo_mysql
│       └── apache-vhost.conf     # document root, proteção de config/ e uploads/
├── src/                          # tudo que o Apache serve
│   ├── api/
│   │   ├── bootstrap.php         # headers JSON, leitura de corpo, respostas
│   │   ├── auth.php              # guarda de autenticação das rotas
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── edicoes.php
│   │   ├── plataformas.php       # catálogo de plataformas online por jogo
│   │   ├── cartas.php            # CRUD + upload
│   │   ├── colecoes.php          # CRUD + cartas dentro de cada coleção
│   │   └── importacoes.php       # vínculo com deck online + sincronização
│   ├── config/
│   │   ├── database.php          # conexão PDO
│   │   ├── sessao.php            # sessão nativa, login/logout
│   │   ├── vista.php             # helpers de escape usados pelas páginas
│   │   ├── edicoes.php           # catálogo de edições por jogo
│   │   ├── colecoes.php          # tipos de coleção (deck, binder, wishlist)
│   │   └── plataformas.php       # catálogo de plataformas + estados do vínculo
│   ├── partials/                 # incluídos via require; o Apache não os serve
│   │   ├── documento.php         # <head> comum: meta, fontes, CSS e o tema
│   │   ├── cabecalho.php         # header + navbar, comum às três páginas
│   │   └── rodape.php
│   ├── assets/
│   │   ├── css/style.css         # folha única, dirigida por tokens em :root
│   │   ├── js/{common,tema,auth,cards,colecoes,importacoes}.js
│   │   └── img/
│   │       ├── hero-*.jpg        # arte do topo, montada com as cartas do seed
│   │       └── cards/            # as 12 cartas do seed, em WebP a 600px
│   ├── uploads/                  # imagens enviadas (fora do versionamento)
│   ├── index.php                 # login
│   ├── dashboard.php             # painel de cartas (protegido por sessão)
│   ├── colecoes.php              # coleções por TCG (protegido por sessão)
│   └── importacoes.php           # importações (protegido por sessão)
├── scripts/
│   └── smoke.sh                  # 86 verificações HTTP contra a stack de pé
├── documentation/                # API, instalação e decisões, em detalhe
├── docker-compose.yml
└── README.md
```

**Frontend e backend na mesma origem.** O Apache serve as páginas e a API sob
`http://localhost:8080`, então o cookie de sessão funciona sem CORS e sem
`Access-Control-Allow-*`. Uma superfície a menos para configurar errado.

### Serviços

| Serviço | Imagem | Porta | Papel |
| --- | --- | --- | --- |
| `web` | PHP 8.3 + Apache (build local) | `8080:80` | Serve `src/` e a API |
| `db`  | `mysql:8.0` | `3307:3306` | Banco, com schema e seeds automáticos |

A porta do MySQL é exposta em **3307** no host para não conflitar com uma
instalação local de MySQL na 3306. Para inspecionar o banco:

```bash
docker-compose exec db mysql -uportal -pportal portal_cartas -e "SELECT id, nome_en, card_game, edicao_nome, raridade FROM cartas;"
```

---

## Banco de dados

### `usuarios`

| Coluna | Tipo | Observação |
| --- | --- | --- |
| `id` | INT AUTO_INCREMENT | PK |
| `nome` | VARCHAR(100) | NOT NULL |
| `email` | VARCHAR(150) | NOT NULL, UNIQUE |
| `senha` | VARCHAR(255) | hash `password_hash()` |
| `created_at` | TIMESTAMP | default `CURRENT_TIMESTAMP` |

### `cartas`

| Coluna | Tipo | Observação |
| --- | --- | --- |
| `id` | INT AUTO_INCREMENT | PK |
| `nome_en` | VARCHAR(150) | NOT NULL |
| `nome_pt` | VARCHAR(150) | NULL |
| `card_game` | ENUM | `magic`, `pokemon`, `yugioh`, `onepiece`, `fab` |
| `edicao_id` | VARCHAR(50) | NOT NULL |
| `edicao_nome` | VARCHAR(100) | NOT NULL, resolvido no servidor |
| `raridade` | VARCHAR(50) | NOT NULL |
| `imagem_url` | TEXT | URL externa, caminho de upload local, ou imagem do seed |
| `created_at` | TIMESTAMP | default `CURRENT_TIMESTAMP` |
| `updated_at` | TIMESTAMP | `ON UPDATE CURRENT_TIMESTAMP` |

### `colecoes`

Um agrupamento de cartas preso a um único card game. É o que sustenta o filtro
por TCG: como a coleção não pode misturar jogos, o filtro nunca mente.

| Coluna | Tipo | Observação |
| --- | --- | --- |
| `id` | INT AUTO_INCREMENT | PK |
| `usuario_id` | INT | FK → `usuarios`, `ON DELETE CASCADE` |
| `nome` | VARCHAR(120) | NOT NULL |
| `descricao` | VARCHAR(255) | NULL |
| `card_game` | ENUM | mesmos cinco jogos de `cartas` |
| `tipo` | ENUM | `deck`, `binder`, `wishlist` |
| `created_at` / `updated_at` | TIMESTAMP | `updated_at` também é tocado ao mexer nas cartas |

### `colecao_cartas`

| Coluna | Tipo | Observação |
| --- | --- | --- |
| `colecao_id` | INT | PK composta, FK → `colecoes` |
| `carta_id` | INT | PK composta, FK → `cartas` |
| `quantidade` | SMALLINT UNSIGNED | 1 a 99 |
| `origem` | ENUM | `manual` (o usuário incluiu) ou `importacao` (veio da sincronização) |

A PK composta garante uma linha por par: repetir a carta ajusta a quantidade em
vez de duplicar. `origem` é o que permite desfazer uma importação sem levar
junto o que foi montado na mão.

### `importacoes`

Vínculo entre um deck do portal e o mesmo deck hospedado numa plataforma online.

| Coluna | Tipo | Observação |
| --- | --- | --- |
| `id` | INT AUTO_INCREMENT | PK |
| `usuario_id` | INT | FK → `usuarios`, `ON DELETE CASCADE` |
| `colecao_id` | INT | FK → `colecoes`, `ON DELETE SET NULL` |
| `card_game` | ENUM | mesmos cinco jogos |
| `plataforma` / `plataforma_nome` | VARCHAR | chave do catálogo + rótulo resolvido no servidor |
| `identificador` | VARCHAR(255) | o que a plataforma usa para achar o deck |
| `status` | ENUM | `pendente`, `sincronizando`, `concluida`, `erro` |
| `cartas_encontradas` | INT | quantas a lista online trouxe |
| `cartas_vinculadas` | INT | quantas dessas existiam no acervo |
| `mensagem` | VARCHAR(255) | resultado do último ciclo, exibido no card |
| `sincronizado_em` | TIMESTAMP | NULL enquanto nunca sincronizou |

O `ON DELETE SET NULL` é deliberado: apagar a coleção não apaga o vínculo. Ele
fica órfão e o painel mostra “coleção removida”, em vez de a linha sumir sem o
usuário entender o motivo.

> **A integração com as plataformas é simulada.** Nenhuma requisição sai para
> fora: `deck_online()` em `src/api/importacoes.php` gera a lista a partir do
> próprio acervo, mais duas cartas reais que de propósito não estão cadastradas
> — é o que exercita o caso em que nem tudo casa. Todo o resto é real: o
> casamento por nome, a gravação em `colecao_cartas`, os contadores, os estados
> e a transação. Plugar uma plataforma de verdade é trocar só aquela função; o
> contrato de saída (`nome_en` + `quantidade`) é o que o resto do arquivo
> consome.

---

## Seeds

O banco já sobe com **12 cartas de exemplo** cobrindo os cinco jogos, todas com
imagem — cartas reais dos cinco jogos, em WebP a 600px de largura (o maior uso em
tela é a arte do topo, a ~312px). As imagens ficam em `src/assets/img/cards/` e
são servidas pelo próprio Apache — o seed **não depende de CDN de terceiro**. Isso é deliberado: vários
sites de TCG enviam o header `Cross-Origin-Resource-Policy: same-site`, que faz o
navegador recusar a imagem quando ela é carregada de outra origem. Servir local
também mantém o portal funcionando offline e atrás de firewall corporativo.

Junto vêm **6 coleções** (quatro decks, um binder e uma lista de desejos) e
**3 vínculos de importação**, um em cada estado visível — sincronizado, aguardando
e falhou. Os decks começam com uma carta incluída na mão de propósito: assim, ao
sincronizar, dá para ver lado a lado o que o usuário montou e o que chegou da
plataforma (com o selo *importada*).

O `init.sql` só roda no **primeiro** boot do MySQL. Num banco que já existe,
aplique o mesmo schema com:

```bash
docker compose exec -T db mysql --default-character-set=utf8mb4 \
  -uportal -pportal portal_cartas < docker/mysql/migrations/01-colecoes.sql
```

O `--default-character-set=utf8mb4` importa: o cliente `mysql` do container
conecta em `latin1` quando o locale não está definido, e sem isso todo acento do
arquivo entra duplo-codificado (`Mítica` vira `MÃ­tica`). Os arquivos SQL já
trazem `SET NAMES utf8mb4` para cobrir o caso.

---

## API

Todas as respostas são JSON (`Content-Type: application/json; charset=utf-8`).
Erros seguem o formato `{ "success": false, "error": "mensagem amigável" }`, com
um campo extra `campos` quando o problema é de validação de formulário.

| Método | Rota | Autenticação | Descrição |
| --- | --- | --- | --- |
| `POST` | `/api/login.php` | — | Recebe `{ "email", "senha" }`, abre a sessão |
| `POST` | `/api/logout.php` | — | Encerra a sessão |
| `GET` | `/api/edicoes.php?game={jogo}` | — | Edições do card game informado |
| `GET` | `/api/cartas.php` | sessão | Lista (filtros: `game`, `search`, `page`, `limit`) |
| `GET` | `/api/cartas.php?id={id}` | sessão | Detalhe de uma carta |
| `POST` | `/api/cartas.php` | sessão | Cria (JSON ou `multipart/form-data` com upload) |
| `PUT` | `/api/cartas.php?id={id}` | sessão | Atualiza (JSON) |
| `POST` | `/api/cartas.php?id={id}` + `_method=PUT` | sessão | Atualiza com upload de arquivo |
| `DELETE` | `/api/cartas.php?id={id}` | sessão | Exclui |
| `GET` | `/api/plataformas.php?game={jogo}` | — | Plataformas online do card game informado |
| `GET` | `/api/colecoes.php` | sessão | Lista (filtros: `game`, `tipo`, `search`) |
| `GET` | `/api/colecoes.php?id={id}` | sessão | Detalhe, com as cartas dentro |
| `POST` | `/api/colecoes.php` | sessão | Cria |
| `PUT` | `/api/colecoes.php?id={id}` | sessão | Atualiza |
| `DELETE` | `/api/colecoes.php?id={id}` | sessão | Exclui (as cartas seguem no acervo) |
| `POST` | `/api/colecoes.php?id={id}&acao=cartas` | sessão | Inclui/ajusta uma carta na coleção |
| `DELETE` | `/api/colecoes.php?id={id}&acao=cartas&carta_id={id}` | sessão | Tira a carta da coleção |
| `GET` | `/api/importacoes.php` | sessão | Lista (filtros: `game`, `status`) |
| `POST` | `/api/importacoes.php` | sessão | Cria o vínculo com o deck online |
| `POST` | `/api/importacoes.php?id={id}&acao=sincronizar` | sessão | Puxa a lista online para o deck |
| `DELETE` | `/api/importacoes.php?id={id}` | sessão | Desfaz o vínculo |

`/api/edicoes.php` e `/api/plataformas.php` são públicos de propósito: são
catálogos de referência estáticos, sem nenhum dado de negócio. Todo o resto —
inclusive a leitura — exige sessão válida, e cada consulta é filtrada pelo
`usuario_id` da sessão: um recurso de outro usuário responde `404`, nunca `403`,
para não confirmar que o id existe.

O override `_method=PUT` existe porque o PHP não faz o parse do corpo de um `PUT`
real em `multipart/form-data`, o que inviabilizaria upload de imagem na edição.

### Exemplo rápido com curl

```bash
# login (guarda o cookie de sessão)
curl -s -c cookies.txt -X POST http://localhost:8080/api/login.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@cards.com","senha":"admin123password"}'

# edições de um jogo
curl -s 'http://localhost:8080/api/edicoes.php?game=magic'

# listar cartas usando a sessão
curl -s -b cookies.txt http://localhost:8080/api/cartas.php

# coleções de um TCG específico
curl -s -b cookies.txt 'http://localhost:8080/api/colecoes.php?game=magic'

# sincronizar um deck vinculado (id do vínculo em /api/importacoes.php)
curl -s -b cookies.txt -X POST \
  'http://localhost:8080/api/importacoes.php?id=1&acao=sincronizar'
```

---

## Segurança

- **SQL Injection:** toda consulta usa `PDO` com *prepared statements* reais
  (`ATTR_EMULATE_PREPARES => false`). Não há interpolação de string em SQL.
- **Senhas:** `password_hash()` / `password_verify()`. O `password_verify` roda
  mesmo quando o e-mail não existe, contra um hash descartável, para o tempo de
  resposta não revelar quais e-mails estão cadastrados.
- **Sessão:** cookie `HttpOnly` + `SameSite=Lax`, e `session_regenerate_id()` no
  login para impedir fixação de sessão.
- **XSS:** saída escapada com `htmlspecialchars()` no PHP e com um helper
  equivalente no JavaScript. URLs de imagem aceitam apenas `http`/`https`, o que
  bloqueia `javascript:` e `data:` caindo em `<img src>`.
- **Upload:** o tipo é determinado pelo conteúdo do arquivo (`getimagesize`), não
  pela extensão nem pelo `Content-Type` enviado; o nome final é gerado com
  `random_bytes()`; e o Apache está configurado para **não executar PHP** dentro
  de `uploads/`.
- **`edicao_nome`** nunca vem do cliente: é resolvido no servidor a partir do par
  `(card_game, edicao_id)`, então a lista não aceita edição inventada nem
  incoerente com o jogo.
- **Superfície HTTP mínima:** `config/` e `partials/` só existem para serem
  incluídos via `require` pelas páginas, e o vhost os bloqueia (`403`). Servidos
  pela URL eles rodariam fora do escopo que esperam e devolveriam HTML pela
  metade; não há rota legítima para eles.

---

## Para os avaliadores

Acesso ao repositório liberado para:

- **`liga-LeonardoWada`**
- **`cauaneroberta`**

Roteiro sugerido para avaliar em ~3 minutos:

1. `docker-compose up -d --build`, depois `./scripts/smoke.sh` para ver as 86
   verificações passando, e abra <http://localhost:8080>
2. Tente entrar com a senha errada — a mensagem é genérica de propósito
3. Entre com `admin@cards.com` / `admin123password`
4. Clique em **Nova carta** e observe o `<select>` de Edição: ele começa
   desabilitado, e só carrega ao escolher o Card Game
5. Troque o Card Game com a lista já carregada — a edição escolhida é resetada
6. Salve com campos em branco para ver a validação por campo
7. Cadastre uma carta com upload de imagem e outra com URL
8. Clique em **Excluir** e leia o diálogo de confirmação
9. Reduza a janela para ver o grid refluir de 5 para 2 e para 1 coluna
