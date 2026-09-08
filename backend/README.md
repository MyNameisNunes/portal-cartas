Backend PHP (sem frameworks)

Coloque scripts PHP em `backend/src` e arquivos expostos em `backend/public`.

Arquivo de schema: `backend/sql/schema.sql`

Passos sugeridos (manuais):
1. Criar banco MySQL e rodar `backend/sql/schema.sql`.
2. Ajustar `backend/config.php` com credenciais do banco.
3. Servir `backend/public` via Apache/Nginx ou `php -S localhost:8000 -t backend/public`.
