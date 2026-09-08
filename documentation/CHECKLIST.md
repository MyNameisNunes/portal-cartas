# Checklist de entrega

## Requisitos do desafio

- [x] Backend em **PHP puro**, sem nenhum framework
- [x] Banco **MySQL** com schema e seeds versionados
- [x] Frontend em **HTML5 + CSS3 + JavaScript vanilla**, sem framework ou biblioteca
- [x] Sobe com **um único comando** (`docker-compose up -d --build`)
- [x] `docker-compose.yml` com os serviços `db` e `web`
- [x] Schema inicializado via `/docker-entrypoint-initdb.d/`

## Banco

- [x] Tabela `usuarios` com senha em `password_hash()`
- [x] Tabela `cartas` com `created_at` e `updated_at`
- [x] Seed do administrador (`admin@cards.com`)
- [x] Seed de cartas de exemplo cobrindo todos os card games

## Backend

- [x] `POST /api/login.php` — valida credenciais e abre sessão
- [x] `POST /api/logout.php` — encerra a sessão
- [x] `GET /api/edicoes.php?game=…` — edições por card game
- [x] `GET /api/cartas.php` — listagem, com filtros e paginação
- [x] `POST /api/cartas.php` — inclusão, com upload ou URL de imagem
- [x] `PUT /api/cartas.php?id=…` — edição
- [x] `DELETE /api/cartas.php?id=…` — exclusão
- [x] Sessão nativa do PHP (`session_start()`)
- [x] PDO com prepared statements em todas as consultas
- [x] Respostas JSON com headers e códigos de status adequados

## Frontend

- [x] Tela de login com validação de campos obrigatórios
- [x] Mensagens de erro amigáveis no login
- [x] Painel protegido (sem sessão, redireciona para o login)
- [x] Header com botão de logout
- [x] Listagem em grid com imagem, nomes, card game, edição e raridade
- [x] Ações de editar e excluir por carta
- [x] Formulário com nome EN obrigatório e nome PT opcional
- [x] `<select>` de card game com os jogos suportados
- [x] `<select>` de edição iniciando desabilitado
- [x] Busca das edições via `fetch` ao escolher o card game
- [x] Estado visual de carregamento no select de edições
- [x] Reset da edição escolhida ao trocar o card game
- [x] Campo de raridade
- [x] Imagem por upload de arquivo **ou** URL
- [x] Confirmação visual antes de excluir
- [x] Layout responsivo
- [x] Variáveis CSS, Flexbox e CSS Grid

## Documentação

- [x] `README.md` com passo a passo de inicialização
- [x] URL de acesso local documentada
- [x] Credenciais de teste no `README.md`
- [x] Mínimo de 2 decisões de UX/Produto explicadas
- [x] Menção aos avaliadores `liga-LeonardoWada` e `cauaneroberta`
- [x] Documentação de API, setup e arquitetura

## Antes de enviar

- [x] `docker-compose down -v && docker-compose up -d --build` a partir do zero (subiu em 16s)
- [x] Fluxo completo testado via API: login → listar → incluir → editar → excluir
- [ ] Fluxo completo conferido no navegador (clique a clique)
- [x] Upload verificado via API: arquivo gravado, servido como `image/png` e apagado junto com a carta
- [ ] Imagem conferida visualmente na listagem
- [ ] Repositório publicado no GitHub
- [ ] Acesso liberado para `liga-LeonardoWada` e `cauaneroberta`
