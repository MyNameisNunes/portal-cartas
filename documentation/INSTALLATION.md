# Instalação manual (sem Docker)

> O caminho recomendado é o Docker — veja [`SETUP.md`](SETUP.md). Este guia existe
> para quem precisa rodar direto no sistema, e assume Ubuntu/Debian.

O projeto não tem dependências além do próprio PHP: não há `composer install`
nem build de frontend.

## 1. Instalar PHP, Apache e MySQL

```bash
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php php-mysql php-mbstring mysql-server
```

Confirme que a extensão `pdo_mysql` está carregada:

```bash
php -m | grep -E 'pdo_mysql|mbstring'
```

## 2. Criar o banco

O mesmo arquivo usado pelo Docker serve aqui — ele cria o database, as tabelas e
os seeds:

```bash
sudo mysql < docker/mysql/init.sql
```

Crie um usuário para a aplicação (evite usar o root):

```bash
sudo mysql -e "
  CREATE USER IF NOT EXISTS 'portal'@'localhost' IDENTIFIED BY 'portal';
  GRANT ALL PRIVILEGES ON portal_cartas.* TO 'portal'@'localhost';
  FLUSH PRIVILEGES;"
```

Confira o seed do administrador:

```bash
mysql -uportal -pportal portal_cartas -e "SELECT id, nome, email FROM usuarios;"
```

## 3. Apontar a conexão

[`src/config/database.php`](../src/config/database.php) lê variáveis de ambiente e
cai em defaults voltados para o Docker (`DB_HOST=db`). Rodando local, exporte:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=portal_cartas
export DB_USER=portal
export DB_PASS=portal
```

Sob o Apache, as mesmas variáveis vão no VirtualHost com `SetEnv`.

## 4a. Rodar com o servidor embutido do PHP (desenvolvimento)

```bash
php -S localhost:8080 -t src
```

Acesse <http://localhost:8080>. As variáveis exportadas no passo 3 são herdadas
pelo processo.

## 4b. Rodar sob o Apache

```apache
<VirtualHost *:80>
    ServerName portal-cartas.local
    DocumentRoot /caminho/para/portal-cartas/src

    SetEnv DB_HOST 127.0.0.1
    SetEnv DB_NAME portal_cartas
    SetEnv DB_USER portal
    SetEnv DB_PASS portal

    <Directory /caminho/para/portal-cartas/src>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    # Nunca servir a configuração
    <Directory /caminho/para/portal-cartas/src/config>
        Require all denied
    </Directory>

    # Uploads são conteúdo estático, nunca executáveis
    <Directory /caminho/para/portal-cartas/src/uploads>
        Options -Indexes -ExecCGI
        php_flag engine off
    </Directory>
</VirtualHost>
```

```bash
sudo a2ensite portal-cartas
sudo systemctl reload apache2
echo "127.0.0.1 portal-cartas.local" | sudo tee -a /etc/hosts
```

O bloco de `uploads` **não é opcional**: sem ele, um arquivo enviado que passe
pela validação de imagem poderia ser executado como PHP.

## 5. Permissão de escrita nos uploads

```bash
mkdir -p src/uploads
sudo chown -R www-data:www-data src/uploads
sudo chmod 755 src/uploads
```

Com o servidor embutido do PHP, o dono deve ser o seu próprio usuário — nesse
caso pule o `chown`.

## 6. Verificar

```bash
curl -s 'http://localhost:8080/api/edicoes.php?game=magic'
```

Deve devolver as cinco edições de Magic. Em seguida abra o portal no navegador e
entre com `admin@cards.com` / `admin123password`.

## Problemas comuns

**Página em branco.** `display_errors` fica desligado; olhe o log do Apache
(`/var/log/apache2/error.log`) ou a saída do servidor embutido.

**"Não foi possível consultar as cartas".** Quase sempre é conexão: confira as
variáveis do passo 3 e se o MySQL está no ar.

**Login recusa a senha correta.** Confirme que o `init.sql` rodou por completo —
a coluna `senha` precisa conter um hash começando com `$2y$`.
