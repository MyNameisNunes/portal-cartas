Guia de Setup — Portal de Cartas

Pré-requisitos:
- PHP 8+ (CLI)
- MySQL (ou MariaDB)
- Navegador moderno

1) Criar banco e rodar schema

No terminal, dentro da raiz do projeto execute:

```bash
# importe o schema (o arquivo cria o database se necessário)
mysql -u root -p < backend/sql/schema.sql
```

2) Ajustar variáveis de ambiente (opcional)

Você pode exportar as variáveis para apontar a conexão do PDO:

```bash
export DB_HOST=127.0.0.1
export DB_NAME=portal_cartas
export DB_USER=root
export DB_PASS=senha_do_root
```

3) Iniciar backend (servidor de desenvolvimento PHP)

```bash
php -S localhost:8000 -t backend/public
```

4) Iniciar frontend (servidor estático simples)

Você pode abrir `frontend/index.html` diretamente ou servir com Python:

```bash
# a partir da raiz do projeto
python3 -m http.server 8080 --directory frontend
```

Acesse:
- Backend: http://localhost:8000
- Frontend: http://localhost:8080

Notas:
- Para ambiente de produção, configure um servidor Apache/Nginx apontando `document root` para `backend/public` e proteja arquivos sensíveis.
- Garanta permissão de escrita para `backend/public/uploads` se implementar upload de imagens.
