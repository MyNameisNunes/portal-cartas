Especificação de API — Portal de Cartas

Base (supondo `http://localhost:8000`)

Autenticação
- POST /login
  - Request JSON: { "email": "user@example.com", "password": "plainpassword" }
  - Response 200: { "token": "..." } (ou sessão via cookie)

Cartas
- GET /cards
  - Query: ?page=1&limit=20
  - Query opcional: `game` e `search`
  - Response: [ { "id":1, "name_en":"...", "game":"magic", "image":"https://...", "image_url":"https://...", "image_reference":"https://..." }, ... ]
- GET /cards/{id}
  - Response: { "id":1, "name_en":"...", ... }
- POST /cards
  - FormData: fields `name_en`, `name_pt`, `game`, `edition_id`, `rarity`, `image` (file)
  - Response 201: created object
- PUT /cards/{id}
  - Update fields (same como POST)
- DELETE /cards/{id}
  - Response 204 No Content

Edições
- GET /editions?game=magic
  - Response: array de edições para o jogo solicitado (veja `editions.json` no enunciado)

Observações
- Use código HTTP apropriado (401 para não autorizado, 422 para dados inválidos).
- Proteja rotas de criação/edição/exclusão com autenticação.

Imagens
- `image` é o valor pronto para usar no `<img>`: primeiro upload local (`image_path`), depois `image_url`.
- `image_url` deve ser uma imagem direta, como as fontes do Pokémon TCG API ou YGOPRODeck usadas no seed.
- `image_reference` é a fonte de consulta quando não existe URL direta. Para Magic, consulte o endpoint Scryfall indicado e use `image_uris.normal` da resposta.
- Os jogos aceitos pelo backend são `magic`, `pokemon`, `yugioh`, `onepiece` e `fab`.
