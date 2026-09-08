Guia de implementação — passos sugeridos

Backend (PHP sem frameworks)
1. Criar um roteador simples em `backend/public/index.php` que encaminhe para arquivos em `backend/src` (ex: `auth.php`, `cards.php`).
2. Implementar `backend/src/auth.php`:
   - `POST /login` valida credenciais (verificar `users`), retorna sessão ou token.
3. Implementar `backend/src/cards.php` com funções:
   - `list_cards()`, `get_card($id)`, `create_card()`, `update_card($id)`, `delete_card($id)`.
   - Use `get_pdo()` de `database.php` para operações.
4. Criar endpoint para `GET /editions` que leia um arquivo JSON (`backend/public/editions.json`) e filtre por query `game`.
5. Tratar upload de imagens para `backend/public/uploads` e salvar `image_path` na tabela.

Frontend (vanilla JS)
1. Criar página de login (`frontend/login.html`) que envie `POST /login` e salve token/sessão.
2. Criar SPA simples em `frontend/index.html` com áreas para listar cartas e formulário de edição/criação.
3. No formulário de carta, ao selecionar `game`:
   - Desabilitar o select de `edition` e exibir loading.
   - Fazer `fetch('/editions?game=magic')` para popular o select.
   - Resetar seleção quando `game` mudar.
4. Usar `fetch` para consumir endpoints do backend e atualizar a UI dinamicamente.
5. Validar entradas no frontend para evitar requisições inválidas.

Teste e validação
- Teste o fluxo completo: login -> listar -> incluir -> editar -> excluir.
- Verifique upload de imagem e exibição correta.
