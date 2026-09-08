# Especificação da API — Portal de Cartas

Base local: `http://localhost:8080`

Todas as respostas são JSON com `Content-Type: application/json; charset=utf-8`.

**Formato de erro** (qualquer status ≥ 400):

```json
{ "success": false, "error": "Mensagem amigável para exibir ao usuário." }
```

Quando o erro é de validação de formulário (422), vem também um mapa por campo:

```json
{
  "success": false,
  "error": "Revise os campos destacados no formulário.",
  "campos": {
    "nome_en": "O nome em inglês é obrigatório.",
    "edicao_id": "Essa edição não pertence ao card game selecionado."
  }
}
```

O frontend usa `campos` para marcar cada input com `aria-invalid` e escrever a
mensagem embaixo dele.

---

## Autenticação

A sessão é a sessão nativa do PHP, transportada por cookie `HttpOnly` +
`SameSite=Lax`. Como páginas e API compartilham a mesma origem, não há CORS nem
token no corpo da resposta.

### `POST /api/login.php`

**Request**

```json
{ "email": "admin@cards.com", "senha": "admin123password" }
```

**200**

```json
{
  "success": true,
  "usuario": { "id": 1, "nome": "Administrador", "email": "admin@cards.com" }
}
```

| Status | Quando |
| --- | --- |
| `401` | E-mail ou senha inválidos (mensagem genérica de propósito) |
| `422` | E-mail ou senha ausentes |
| `405` | Método diferente de POST |

### `POST /api/logout.php`

**200**

```json
{ "success": true, "message": "Sessão encerrada com sucesso." }
```

---

## Edições

### `GET /api/edicoes.php?game={jogo}`

Endpoint **público**: é catálogo de referência estático, sem dado de negócio.

`game` aceita `magic`, `pokemon`, `yugioh`, `onepiece` ou `fab`.

**200**

```json
{
  "success": true,
  "game": "magic",
  "edicoes": [
    { "id": "dom", "name": "Dominaria" },
    { "id": "war", "name": "War of the Spark" },
    { "id": "eld", "name": "Throne of Eldraine" },
    { "id": "hob", "name": "The Hobbit" },
    { "id": "msh", "name": "Marvel Super Heroes" }
  ]
}
```

| Status | Quando |
| --- | --- |
| `422` | `game` ausente |
| `404` | `game` não suportado — a resposta traz `jogos_suportados` |

A fonte desses dados é [`src/config/edicoes.php`](../src/config/edicoes.php), que
também é usada para **validar** o par `(card_game, edicao_id)` na gravação. Por
isso `edicao_nome` nunca precisa ser enviado pelo cliente.

---

## Cartas

Todas as operações abaixo exigem sessão. Sem ela, a resposta é `401` com
`"Sessão expirada. Faça login novamente para continuar."` — o frontend trata isso
redirecionando para o login.

### `GET /api/cartas.php`

Query params, todos opcionais:

| Param | Padrão | Descrição |
| --- | --- | --- |
| `game` | — | Filtra por card game |
| `search` | — | Busca em `nome_en`, `nome_pt` e `edicao_nome` |
| `page` | `1` | Página |
| `limit` | `50` | Itens por página (máx. `100`) |

**200**

```json
{
  "success": true,
  "total": 12,
  "pagina": 1,
  "limite": 50,
  "resumo": { "total": 12, "jogos": 5, "edicoes": 7 },
  "cartas": [
    {
      "id": 4,
      "nome_en": "Charizard",
      "nome_pt": null,
      "card_game": "pokemon",
      "card_game_nome": "Pokémon",
      "edicao_id": "base1",
      "edicao_nome": "Base Set",
      "raridade": "Rara",
      "imagem_url": "https://images.pokemontcg.io/base1/4.png",
      "created_at": "2026-09-08 12:00:00",
      "updated_at": "2026-09-08 12:00:00"
    }
  ]
}
```

`resumo` é calculado sobre a tabela inteira, **ignorando os filtros** — ele
alimenta os indicadores do topo do painel, que não devem mudar quando o usuário
filtra a lista.

`card_game_nome` é o rótulo pronto para exibição, para o frontend não precisar
manter um de-para duplicado.

### `GET /api/cartas.php?id={id}`

**200** → `{ "success": true, "carta": { … } }` · **404** se não existir.

### `POST /api/cartas.php`

Cria uma carta. Aceita `application/json` **ou** `multipart/form-data` (esta
última é a única forma de enviar arquivo).

| Campo | Obrigatório | Regras |
| --- | --- | --- |
| `nome_en` | sim | até 150 caracteres |
| `nome_pt` | não | até 150 caracteres |
| `card_game` | sim | um dos cinco jogos suportados |
| `edicao_id` | sim | precisa existir dentro do `card_game` escolhido |
| `raridade` | sim | até 50 caracteres |
| `imagem` | não | arquivo: JPG, PNG, WEBP ou GIF, até 5 MB |
| `imagem_url` | não | URL `http`/`https` |

**201** → `{ "success": true, "message": "…", "carta": { … } }`

### Atualização

Duas formas equivalentes:

```
PUT  /api/cartas.php?id={id}                  # corpo JSON
POST /api/cartas.php?id={id}  + _method=PUT   # multipart, permite upload
```

O override existe porque o PHP não faz o parse do corpo de um `PUT` real em
`multipart/form-data`, o que impediria trocar a imagem na edição.

**Resolução da imagem**, nesta ordem de precedência:

1. arquivo enviado no campo `imagem` → substitui (e apaga o upload anterior)
2. `imagem_url` preenchido → passa a valer essa URL
3. `remover_imagem=1` → limpa a imagem
4. nada disso → **mantém** a imagem atual

**200** → `{ "success": true, "message": "…", "carta": { … } }`

### `DELETE /api/cartas.php?id={id}`

Remove a carta e, se a imagem era um upload local, apaga o arquivo também.

**200** → `{ "success": true, "message": "Carta excluída com sucesso." }`

---

## Plataformas

### `GET /api/plataformas.php?game={jogo}`

Endpoint **público**, mesma natureza de `/api/edicoes.php`: catálogo de referência
estático, sem dado de negócio. Lista as plataformas online que aceitam vínculo de
deck para aquele card game.

**200**

```json
{
  "success": true,
  "game": "magic",
  "plataformas": [
    {
      "id": "moxfield",
      "name": "Moxfield",
      "identificador": "ID do deck no Moxfield",
      "exemplo": "AbCd1234EfGh"
    }
  ]
}
```

`identificador` e `exemplo` existem para o formulário: o rótulo e o placeholder do
campo mudam com a plataforma escolhida, porque o que o Moxfield chama de "ID do
deck" o TCG Live chama de "código de compartilhamento".

| Status | Quando |
| --- | --- |
| `404` | `game` não é um dos cinco jogos suportados |
| `422` | `game` ausente |

---

## Coleções

Uma coleção é um agrupamento de cartas preso a um **único card game** — é essa
regra que mantém o filtro por TCG verdadeiro. `tipo` separa o que o usuário está
montando: `deck` (joga), `binder` (guarda) ou `wishlist` (quer).

Todas as rotas exigem sessão e são filtradas pelo `usuario_id` dela. Uma coleção
de outro usuário responde `404`, nunca `403`: não confirma que o id existe.

### `GET /api/colecoes.php`

Filtros opcionais: `game`, `tipo`, `search` (nome ou descrição).

**200**

```json
{
  "success": true,
  "total": 2,
  "resumo": { "total": 6, "jogos": 5, "decks": 4, "unidades": 19 },
  "colecoes": [
    {
      "id": 1,
      "nome": "Teferi Superfriends",
      "descricao": "Planeswalkers de Dominaria e War of the Spark.",
      "card_game": "magic",
      "card_game_nome": "Magic: The Gathering",
      "tipo": "deck",
      "tipo_nome": "Deck",
      "total_cartas": 3,
      "total_unidades": 6,
      "capas": [
        { "nome_en": "Teferi, Hero of Dominaria", "imagem_url": "assets/img/cards/teferi-hero-of-dominaria.webp" }
      ],
      "created_at": "2026-09-08 13:21:18",
      "updated_at": "2026-09-08 13:21:18"
    }
  ]
}
```

`resumo` é sempre do acervo inteiro, independente dos filtros ativos: alimenta os
indicadores do topo da página, que não devem mudar ao filtrar a lista.

`capas` traz até quatro cartas da coleção para o mosaico do card. Vem de uma
consulta só para toda a página, não de uma por coleção.

### `GET /api/colecoes.php?id={id}`

Mesmo objeto, mais `cartas`: a lista completa, ordenada por nome, com
`quantidade` e `origem` (`manual` ou `importacao`) de cada uma.

### `POST /api/colecoes.php`

**Request** — `{ "nome", "descricao", "card_game", "tipo" }`

**201** — `{ "success": true, "message": "…", "colecao": { … } }`

### `PUT /api/colecoes.php?id={id}`

Mesmos campos. Trocar o `card_game` de uma coleção que já tem cartas responde
`409`: a regra é explícita, em vez de esvaziar a coleção em silêncio.

### `DELETE /api/colecoes.php?id={id}`

Remove a coleção e os vínculos dela com as cartas. **As cartas seguem no acervo** —
só o agrupamento some.

### `POST /api/colecoes.php?id={id}&acao=cartas`

**Request** — `{ "carta_id": 4, "quantidade": 2 }`

Inclui a carta ou ajusta a quantidade da que já está lá. Recusa com `422` uma
carta de outro card game — é o que garante que o filtro por TCG continue
verdadeiro depois de qualquer inclusão.

**200** — devolve a lista `cartas` atualizada, para o frontend não precisar de um
segundo `GET`.

### `DELETE /api/colecoes.php?id={id}&acao=cartas&carta_id={id}`

Tira a carta da coleção. **200** com a lista `cartas` atualizada; `404` se a carta
não estava ali.

---

## Importações

O vínculo entre um deck offline do portal e o mesmo deck hospedado numa
plataforma online. Só coleções do tipo `deck` recebem vínculo.

> **A integração é simulada.** Nenhuma requisição sai para as plataformas:
> `deck_online()` gera a lista a partir do próprio acervo, mais duas cartas reais
> que de propósito não estão cadastradas. Todo o resto é real — o casamento por
> nome, a gravação em `colecao_cartas`, os contadores, os estados e a transação.
> Trocar por uma integração de verdade é trocar só aquela função: o contrato de
> saída (`nome_en` + `quantidade`) é o que o resto do arquivo consome.

### `GET /api/importacoes.php`

Filtros opcionais: `game`, `status`.

**200**

```json
{
  "success": true,
  "resumo": { "total": 3, "sincronizados": 1, "pendentes": 1, "falhas": 1, "cartas": 3 },
  "importacoes": [
    {
      "id": 1,
      "colecao_id": 1,
      "colecao_nome": "Teferi Superfriends",
      "colecao_removida": false,
      "card_game": "magic",
      "card_game_nome": "Magic: The Gathering",
      "plataforma": "moxfield",
      "plataforma_nome": "Moxfield",
      "identificador": "AbCd1234EfGh",
      "status": "concluida",
      "status_nome": "Sincronizado",
      "cartas_encontradas": 5,
      "cartas_vinculadas": 3,
      "mensagem": "3 de 5 cartas casaram com o acervo. Fora: Cyclonic Rift, Wrenn and Six.",
      "sincronizado_em": "2026-09-08 11:23:41"
    }
  ]
}
```

`colecao_removida` marca o vínculo cujo deck de destino foi excluído. A FK é
`ON DELETE SET NULL` de propósito: o registro fica órfão e a tela mostra "coleção
removida", em vez de a linha sumir sem o usuário entender o motivo.

### `POST /api/importacoes.php`

**Request** — `{ "card_game", "plataforma", "identificador", "colecao_id" }`

`plataforma_nome` nunca vem do cliente: é resolvido a partir do catálogo.

| Status | Quando |
| --- | --- |
| `201` | Vínculo criado, em `pendente` |
| `409` | O mesmo `(plataforma, identificador)` já está vinculado |
| `422` | Campo inválido, coleção de outro card game, ou coleção que não é `deck` |

### `POST /api/importacoes.php?id={id}&acao=sincronizar`

Roda um ciclo completo dentro de uma transação:

1. pede a lista ao "deck online";
2. casa cada nome com o acervo do mesmo card game;
3. apaga as cartas que a importação anterior tinha trazido (`origem = 'importacao'`)
   e que saíram da lista;
4. insere as casadas. **Uma carta já presente não é tocada** — ela foi incluída na
   mão, e a quantidade é do usuário, não da plataforma;
5. grava contadores, mensagem e `sincronizado_em`.

**200**

```json
{
  "success": true,
  "message": "3 cartas vinculadas em “Charizard Control”.",
  "nao_localizadas": ["Professor's Research", "Iono"],
  "importacao": { "…": "estado atualizado" }
}
```

Uma falha de negócio — coleção de destino excluída, nenhuma carta do jogo no
acervo — também responde `200`, com o registro marcado como `erro` e a razão em
`mensagem`. O status do registro é a fonte da verdade, não o código HTTP: o erro
é do domínio, não do transporte.

### `DELETE /api/importacoes.php?id={id}`

Desfaz o vínculo. As cartas com `origem = 'importacao'` saem do deck; as que o
usuário incluiu na mão permanecem.

---

## Imagens

`imagem_url` guarda uma de três coisas:

- uma **URL externa** (`https://exemplo.com/carta.png`),
- um **caminho relativo de upload** (`uploads/carta-9f2c8a1b7d4e5f60.png`), ou
- uma **imagem do seed** (`assets/img/cards/charizard.webp`)

Nos dois casos o valor pode ir direto para o `src` de um `<img>`, porque o Apache
serve `src/uploads/` como conteúdo estático. O frontend não precisa distinguir os
dois casos na exibição — só no formulário de edição, onde um caminho de upload
não é devolvido para o campo de URL.

Na gravação, só são aceitos `http`, `https`, um caminho `uploads/…` gerado pelo
próprio backend, ou `assets/img/cards/…`. Isso bloqueia `javascript:` e `data:`,
que virariam XSS ao cair em `<img src>`, e os dois caminhos relativos usam regex
estrita de nome de arquivo, o que impede path traversal.

As imagens do seed são servidas localmente de propósito: hosts de TCG costumam
enviar `Cross-Origin-Resource-Policy: same-site`, e nesse caso o navegador recusa
a imagem mesmo com o servidor devolvendo `200` — o `curl` não aplica CORP, então
o problema só aparece na tela.
