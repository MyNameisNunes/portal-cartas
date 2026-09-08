Guia de Instalação Passo a Passo — Portal de Cartas

Este guia descreve a instalação em uma máquina Ubuntu/Debian. Se usa outra distro (CentOS/RHEL/Fedora) peça e eu adapto os comandos.

1) Atualizar sistema

```bash
sudo apt update
sudo apt upgrade -y
```

2) Instalar Apache, PHP e extensões necessárias

```bash
sudo apt install -y apache2 php libapache2-mod-php php-mysql php-gd php-mbstring php-xml php-zip unzip curl wget
```

Verifique as versões:

```bash
apache2ctl -v
php -v
php -m | egrep "pdo|pdo_mysql|mysqli|gd|mbstring|xml" || true
```

3) Instalar MySQL (server) e ajustar segurança

```bash
sudo apt install -y mysql-server
sudo systemctl enable --now mysql
sudo mysql_secure_installation
```

Siga os prompts do `mysql_secure_installation` para configurar senha root e opções de segurança.

4) Importar schema do projeto

No diretório raiz do projeto (onde está `backend/sql/schema.sql`) rode:

```bash
mysql -u root -p < backend/sql/schema.sql
```

Ou, caso queira executar dentro do cliente mysql:

```bash
mysql -u root -p
mysql> SOURCE /caminho/para/portal-cartas/backend/sql/schema.sql;
```

Se o banco já foi criado com uma versão anterior do projeto, aplique também a migração:

```bash
mysql -u root -p < backend/sql/migrate_cards.sql
```

5) Gerar hash da senha do administrador (opcional)

O seed no arquivo `schema.sql` pode conter um placeholder. Para definir uma senha conhecida (ex.: `password123`) execute:

```bash
# Gera hash bcrypt com PHP
php -r 'echo password_hash("password123", PASSWORD_BCRYPT) . "\n";' 
```

Pegue o hash gerado e atualize o usuário admin no banco:

```bash
mysql -u root -p -e "USE portal_cartas; UPDATE users SET password='SEU_HASH_AQUI' WHERE email='admin@example.com';"
```

6) Criar diretório de uploads e ajustar permissões

```bash
mkdir -p backend/public/uploads
sudo chown -R www-data:www-data backend/public/uploads
sudo chmod -R 755 backend/public/uploads
```

7) Configurar Apache (opcional — para servir o backend em produção)

Crie um virtual host em `/etc/apache2/sites-available/portal-cartas.conf` com este conteúdo (substitua `/home/usuario/Documentos/RepositoriosMint/portal-cartas` pelo path correto):

```
<VirtualHost *:80>
    ServerName portal-cartas.local
    DocumentRoot /home/usuario/Documentos/RepositoriosMint/portal-cartas/backend/public

    <Directory /home/usuario/Documentos/RepositoriosMint/portal-cartas/backend/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/portal-cartas-error.log
    CustomLog ${APACHE_LOG_DIR}/portal-cartas-access.log combined
</VirtualHost>
```

Ative o site e módulos necessários:

```bash
sudo a2enmod rewrite
sudo a2ensite portal-cartas
sudo systemctl reload apache2
```

Adicione uma entrada no `/etc/hosts` (apenas para desenvolvimento local):

```bash
# como root ou sudo edite /etc/hosts e adicione
127.0.0.1   portal-cartas.local
```

8) Rodar em modo desenvolvimento (alternativa ao Apache)

Se quiser testes rápidos sem configurar Apache, use o servidor embutido do PHP:

```bash
# a partir da raiz do projeto
php -S localhost:8000 -t backend/public
```

E sirva o frontend estaticamente (opcional):

```bash
python3 -m http.server 8080 --directory frontend
```

9) Testes rápidos

- Testar backend:

```bash
curl -i http://localhost:8000/
```

- Verificar que o banco `portal_cartas` existe e que o usuário admin está presente:

```bash
mysql -u root -p -e "SELECT id,email,created_at FROM portal_cartas.users LIMIT 10;"
```

10) Dicas e resolução de problemas

- "Access denied for user 'root'@'localhost'": verifique se usou `-p` corretamente e se a senha root do MySQL foi definida. Use `sudo mysql` para entrar como root sem senha em instalações que usam auth_socket.
- Se o PHP não carregar extensões (`pdo_mysql`): instale `php-mysql` e reinicie o Apache (`sudo systemctl restart apache2`).
- Permissões de upload: se conseguir salvar arquivos, remova permissão de escrita ampla e mantenha `www-data` como dono.
- Firewall: se acessar de outra máquina, abra porta 80/443 (`sudo ufw allow 'Apache Full'`).

11) Próximos passos sugeridos

- Implementar rotas básicas de API (`/login`, `/cards`, `/editions`) em `backend/src`.
- Implementar o frontend (login + CRUD) consumindo a API.

Se quiser, eu adapto este guia para CentOS/RHEL (dnf) ou crio o arquivo de `VirtualHost` pronto com seu caminho exato — me diga o path do projeto se quiser que eu gere o arquivo já preenchido.
