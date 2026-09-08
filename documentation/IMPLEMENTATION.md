# Como o projeto está implementado

Notas de arquitetura — o que está onde, e por que foi feito assim.

## Restrições respeitadas

| Restrição | Como |
| --- | --- |
| PHP puro, sem framework | Nenhum `composer.json`, nenhuma dependência. Só funções e PDO da stdlib |
| MySQL | `mysql:8.0`, schema em `docker/mysql/init.sql` |
| HTML/CSS/JS vanilla | Nenhum script externo além das webfonts. Sem React/Vue/jQuery, sem Bootstrap/Tailwind |
| Um comando para subir | `docker-compose up -d --build` |

## Camadas

```
src/config/     estado e recursos compartilhados (banco, sessão, catálogo)
src/api/        endpoints HTTP, um arquivo por recurso
src/*.php       páginas, protegidas por sessão no servidor
src/assets/js/  comportamento do cliente
```

**`src/config/` não conhece HTTP.** `database.php`, `sessao.php` e `edicoes.php`
não emitem header nem encerram request. É isso que permite que tanto as páginas
HTML quanto os endpoints JSON os carreguem — se a sessão vivesse dentro do
bootstrap da API, `dashboard.php` não poderia usá-la sem receber junto o
`Content-Type: application/json`.

**`src/api/bootstrap.php`** é a base só dos endpoints: headers JSON,
`responder()`, `erro()`, leitura de corpo (JSON ou form) e negociação de método.
**`src/api/auth.php`** acrescenta a guarda `exigir_autenticacao()`.

## Proteção de acesso, em duas camadas

1. **Servidor.** `dashboard.php` checa a sessão e faz `header('Location: …')`
   antes de qualquer saída. Sem sessão, o HTML do painel nunca é gerado.
2. **API.** Todo `/api/cartas.php` chama `exigir_autenticacao()` e devolve `401`.
   O frontend trata esse status redirecionando para `index.php?expirada=1`, o que
   cobre o caso da sessão morrer com a aba já aberta.

A primeira camada sozinha não bastaria (a API ficaria exposta); a segunda
sozinha entregaria o HTML do painel a quem não está logado.

## Fonte única para as edições

`src/config/edicoes.php` alimenta o endpoint `GET /api/edicoes.php` **e** valida
o par `(card_game, edicao_id)` na gravação, resolvendo `edicao_nome` no servidor.

Isso significa que o cliente nunca envia o nome da edição. Não existe o caso de
uma carta gravada com `edicao_id=lob` e `edicao_nome="Base Set"`, nem de uma
edição de Pokémon associada a uma carta de Magic — independente do que for
enviado na requisição.

## Upload de imagem

`imagem_url` guarda tanto uma URL externa quanto um caminho `uploads/…` local, e
os dois vão direto para o `src` de um `<img>`. O que o backend garante:

- o tipo vem do **conteúdo** do arquivo (`getimagesize`), não da extensão nem do
  `Content-Type` enviado pelo cliente
- o nome final é gerado com `random_bytes()`, então o nome original do arquivo
  nunca chega ao disco
- o Apache está configurado para **não executar PHP** dentro de `uploads/`
- ao trocar ou excluir a imagem, o upload anterior é apagado do disco

A edição usa `POST` com `_method=PUT` porque o PHP não faz o parse do corpo de um
`PUT` real em `multipart/form-data` — sem isso, não daria para trocar a imagem
numa edição. O `PUT` real continua funcionando para corpo JSON.

## Filtros

`GET /api/cartas.php` filtra no banco (`game`, `search`) com prepared statements,
e o `search` escapa os curingas `%` e `_` para que sejam tratados como texto
literal. O frontend faz debounce de 350 ms na busca e mantém a lista visível
durante o refiltro — o esqueleto de carregamento aparece só na primeira carga,
para a tela não piscar a cada tecla.

O `resumo` da resposta é calculado sobre a tabela inteira, ignorando os filtros,
porque os indicadores do topo descrevem o acervo e não a busca atual.

## Onde procurar cada coisa

| Comportamento | Arquivo |
| --- | --- |
| Estados do select de edições | [`src/assets/js/cards.js`](../src/assets/js/cards.js), `carregarEdicoes()` |
| Validação de carta | [`src/api/cartas.php`](../src/api/cartas.php), `campos_validados()` |
| Regras de imagem | [`src/api/cartas.php`](../src/api/cartas.php), `resolver_imagem()` / `salvar_upload()` |
| Login e hash de senha | [`src/config/sessao.php`](../src/config/sessao.php), `autenticar()` |
| Catálogo de edições | [`src/config/edicoes.php`](../src/config/edicoes.php) |
| Schema e seeds | [`docker/mysql/init.sql`](../docker/mysql/init.sql) |
