-- ============================================================
-- Portal de Cartas - schema + seeds
-- Executado automaticamente pelo container MySQL na primeira
-- inicializacao, via /docker-entrypoint-initdb.d/
-- ============================================================

CREATE DATABASE IF NOT EXISTS portal_cartas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portal_cartas;

-- O cliente mysql do container conecta em latin1 quando o locale nao esta
-- definido, e sem isto cada acento deste arquivo entra duplo-codificado
-- ("Mítica" vira "MÃ­tica"). Declarar a conexao resolve na origem.
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Usuarios do portal administrativo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(100)  NOT NULL,
  email      VARCHAR(150)  NOT NULL UNIQUE,
  senha      VARCHAR(255)  NOT NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Cartas cadastradas
-- imagem_url guarda tanto uma URL externa quanto o caminho
-- relativo de um upload local (ex.: uploads/carta-123.png)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cartas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nome_en     VARCHAR(150) NOT NULL,
  nome_pt     VARCHAR(150) DEFAULT NULL,
  card_game   ENUM('magic','pokemon','yugioh','onepiece','fab') NOT NULL,
  edicao_id   VARCHAR(50)  NOT NULL,
  edicao_nome VARCHAR(100) NOT NULL,
  raridade    VARCHAR(50)  NOT NULL,
  imagem_url  TEXT         DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY cartas_card_game (card_game),
  KEY cartas_edicao (card_game, edicao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed do administrador
-- Senha em texto puro: admin123password
-- Hash gerado com bcrypt custo 10, compativel com password_verify()
-- ------------------------------------------------------------
INSERT INTO usuarios (nome, email, senha) VALUES
  ('Administrador', 'admin@cards.com', '$2y$10$hZTe26.Od1ti7TbYbKS9VumJUs3uLuCuUe9hGZYf0HPY9svPHbnEC')
ON DUPLICATE KEY UPDATE senha = VALUES(senha);

-- ------------------------------------------------------------
-- Seed de cartas: edicao_id/edicao_nome batem com src/config/edicoes.php
-- ------------------------------------------------------------
-- As imagens sao servidas pelo proprio Apache, a partir de src/assets/img/cards,
-- todas em WebP a 600px de largura (o maior uso em tela e o hero, a ~312px).
-- Isso evita depender de CDN de terceiro: varios sites de TCG mandam o header
-- Cross-Origin-Resource-Policy: same-site, que faz o navegador recusar a imagem
-- quando ela e carregada de outra origem (o curl nao aplica CORP, o browser sim).
INSERT INTO cartas (nome_en, nome_pt, card_game, edicao_id, edicao_nome, raridade, imagem_url) VALUES
  ('Teferi, Hero of Dominaria', 'Teferi, Herói de Dominária',  'magic',    'dom',   'Dominaria',                        'Mítica',     'assets/img/cards/teferi-hero-of-dominaria.webp'),
  ('Llanowar Elves',            'Elfos de Llanowar',           'magic',    'dom',   'Dominaria',                        'Comum',      'assets/img/cards/llanowar-elves.webp'),
  ('Narset, Parter of Veils',   NULL,                          'magic',    'war',   'War of the Spark',                 'Incomum',    'assets/img/cards/narset-parter-of-veils.webp'),
  ('Charizard',                 NULL,                          'pokemon',  'base1', 'Base Set',                         'Rara',       'assets/img/cards/charizard.webp'),
  ('Pikachu',                   NULL,                          'pokemon',  'base1', 'Base Set',                         'Comum',      'assets/img/cards/pikachu.webp'),
  ('Miraidon ex',               NULL,                          'pokemon',  'sv1',   'Scarlet & Violet',                 'Ultra Rara', 'assets/img/cards/miraidon-ex.webp'),
  ('Blue-Eyes White Dragon',    'Dragão Branco de Olhos Azuis','yugioh',   'lob',   'Legend of Blue Eyes White Dragon', 'Ultra Rara', 'assets/img/cards/blue-eyes-white-dragon.webp'),
  ('Raigeki',                   NULL,                          'yugioh',   'lob',   'Legend of Blue Eyes White Dragon', 'Rara',       'assets/img/cards/raigeki.webp'),
  ('Gate Guardian',             NULL,                          'yugioh',   'mrd',   'Metal Raiders',                    'Ultra Rara', 'assets/img/cards/gate-guardian.webp'),
  ('Roronoa Zoro',              NULL,                          'onepiece', 'op01',  'Romance Dawn',                     'Rara',       'assets/img/cards/roronoa-zoro.webp'),
  ('Monkey.D.Luffy',            NULL,                          'onepiece', 'op01',  'Romance Dawn',                     'Ultra Rara', 'assets/img/cards/monkey-d-luffy.webp'),
  ('Rhinar, Reckless Rampage',  NULL,                          'fab',      'wtr',   'Welcome to Rathe',                 'Mítica',     'assets/img/cards/rhinar-reckless-rampage.webp');

-- ------------------------------------------------------------
-- Colecoes: agrupamentos de cartas presos a um unico card game.
-- tipo separa deck (joga), binder (guarda) e wishlist (quer).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS colecoes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT          NOT NULL,
  nome       VARCHAR(120) NOT NULL,
  descricao  VARCHAR(255) DEFAULT NULL,
  card_game  ENUM('magic','pokemon','yugioh','onepiece','fab') NOT NULL,
  tipo       ENUM('deck','binder','wishlist') NOT NULL DEFAULT 'binder',
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY colecoes_dono (usuario_id, card_game),
  KEY colecoes_tipo (usuario_id, tipo),
  CONSTRAINT colecoes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Cartas dentro de cada colecao.
-- A chave primaria composta garante uma linha por (colecao, carta):
-- repetir a mesma carta soma na quantidade, nao cria linha nova.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS colecao_cartas (
  colecao_id INT      NOT NULL,
  carta_id   INT      NOT NULL,
  quantidade SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  origem     ENUM('manual','importacao') NOT NULL DEFAULT 'manual',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (colecao_id, carta_id),
  KEY colecao_cartas_carta (carta_id),
  CONSTRAINT colecao_cartas_colecao
    FOREIGN KEY (colecao_id) REFERENCES colecoes (id) ON DELETE CASCADE,
  CONSTRAINT colecao_cartas_carta_fk
    FOREIGN KEY (carta_id) REFERENCES cartas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Importacoes: vinculo entre um deck offline (colecao do tipo deck)
-- e o mesmo deck hospedado em uma plataforma online.
--
-- plataforma guarda a chave do catalogo em config/plataformas.php.
-- A sincronizacao em si e simulada pelo backend, mas o resultado
-- (cartas vinculadas, contadores, carimbo de tempo) e persistido.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS importacoes (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id         INT          NOT NULL,
  colecao_id         INT          DEFAULT NULL,
  card_game          ENUM('magic','pokemon','yugioh','onepiece','fab') NOT NULL,
  plataforma         VARCHAR(50)  NOT NULL,
  plataforma_nome    VARCHAR(100) NOT NULL,
  identificador      VARCHAR(255) NOT NULL,
  status             ENUM('pendente','sincronizando','concluida','erro') NOT NULL DEFAULT 'pendente',
  cartas_encontradas INT          NOT NULL DEFAULT 0,
  cartas_vinculadas  INT          NOT NULL DEFAULT 0,
  mensagem           VARCHAR(255) DEFAULT NULL,
  sincronizado_em    TIMESTAMP    NULL DEFAULT NULL,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY importacoes_dono (usuario_id, card_game),
  KEY importacoes_status (usuario_id, status),
  CONSTRAINT importacoes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  -- A colecao pode ser apagada sem levar o vinculo junto: o registro fica
  -- orfao e o painel mostra "coleção removida", em vez de sumir sem aviso.
  CONSTRAINT importacoes_colecao
    FOREIGN KEY (colecao_id) REFERENCES colecoes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed de colecoes do administrador.
-- Os ids sao resolvidos por consulta, nunca chumbados: assim o seed
-- continua valido se a ordem das cartas acima mudar.
-- ------------------------------------------------------------
INSERT INTO colecoes (usuario_id, nome, descricao, card_game, tipo)
SELECT u.id, x.nome, x.descricao, x.card_game, x.tipo
  FROM usuarios u
  JOIN (
    SELECT 'Teferi Superfriends' AS nome, 'Planeswalkers de Dominaria e War of the Spark.'      AS descricao, 'magic'    AS card_game, 'deck'     AS tipo
    UNION ALL SELECT 'Binder Dominaria',   'O que guardei da coleção base.',                          'magic',    'binder'
    UNION ALL SELECT 'Charizard Control',  'Lista de torneio local, ainda em ajuste.',                'pokemon',  'deck'
    UNION ALL SELECT 'Blue-Eyes Legado',   'Montagem nostálgica com as cartas do Legend of Blue Eyes.','yugioh',   'deck'
    UNION ALL SELECT 'Zoro Agressivo',     'Deck de Romance Dawn focado em pressão inicial.',         'onepiece', 'deck'
    UNION ALL SELECT 'Faltam pro Rathe',   'Cartas de Flesh and Blood que ainda quero comprar.',      'fab',      'wishlist'
  ) AS x
 WHERE u.email = 'admin@cards.com'
   AND NOT EXISTS (SELECT 1 FROM colecoes c WHERE c.usuario_id = u.id AND c.nome = x.nome);

-- O binder e a pasta de guarda: recebe tudo o que existe do card game dele.
INSERT IGNORE INTO colecao_cartas (colecao_id, carta_id, quantidade, origem)
SELECT c.id, ct.id, 1, 'manual'
  FROM colecoes c
  JOIN cartas   ct ON ct.card_game = c.card_game
  JOIN usuarios u  ON u.id = c.usuario_id AND u.email = 'admin@cards.com'
 WHERE c.tipo = 'binder';

-- Os decks comecam com uma carta so, incluida na mao. O resto chega pela
-- sincronizacao — e assim a tela mostra lado a lado o que o usuario montou
-- e o que veio da plataforma (as importadas ganham selo).
INSERT IGNORE INTO colecao_cartas (colecao_id, carta_id, quantidade, origem)
SELECT c.id,
       (SELECT MIN(ct.id) FROM cartas ct WHERE ct.card_game = c.card_game),
       2,
       'manual'
  FROM colecoes c
  JOIN usuarios u ON u.id = c.usuario_id AND u.email = 'admin@cards.com'
 WHERE c.tipo = 'deck'
   AND EXISTS (SELECT 1 FROM cartas ct WHERE ct.card_game = c.card_game);

-- ------------------------------------------------------------
-- Seed de vinculos com plataformas online.
-- Cobre os tres estados visiveis no painel: concluida, pendente e erro.
-- ------------------------------------------------------------
INSERT INTO importacoes
  (usuario_id, colecao_id, card_game, plataforma, plataforma_nome, identificador,
   status, cartas_encontradas, cartas_vinculadas, mensagem, sincronizado_em)
SELECT c.usuario_id, c.id, c.card_game, x.plataforma, x.plataforma_nome, x.identificador,
       x.status, x.encontradas, x.vinculadas, x.mensagem,
       CASE WHEN x.status = 'concluida' THEN CURRENT_TIMESTAMP - INTERVAL 2 HOUR ELSE NULL END
  FROM colecoes c
  JOIN (
    SELECT 'Teferi Superfriends' AS colecao, 'moxfield'   AS plataforma, 'Moxfield'      AS plataforma_nome,
           -- Os numeros batem com o que deck_online() devolve para magic:
           -- 3 cartas do acervo + 2 que so existem na lista online.
           'AbCd1234EfGh'        AS identificador, 'concluida' AS status, 5 AS encontradas, 3 AS vinculadas,
           '3 de 5 cartas casaram com o acervo. Fora: Cyclonic Rift, Wrenn and Six.' AS mensagem
    UNION ALL SELECT 'Charizard Control', 'ptcglive',   'Pokémon TCG Live',
           'xKp29d-Fj1LmQ', 'pendente', 0, 0, NULL
    UNION ALL SELECT 'Blue-Eyes Legado',  'masterduel', 'Master Duel',
           'A1B2C3D4', 'erro', 0, 0, 'A plataforma não respondeu. Tente sincronizar novamente.'
  ) AS x ON x.colecao = c.nome
  JOIN usuarios u ON u.id = c.usuario_id AND u.email = 'admin@cards.com'
 WHERE NOT EXISTS (
    SELECT 1 FROM importacoes i
     WHERE i.usuario_id = c.usuario_id AND i.plataforma = x.plataforma AND i.identificador = x.identificador
 );

-- Cartas que a sincronizacao ja concluida trouxe. O INSERT IGNORE preserva a
-- linha manual do deck: e exatamente o que sincronizar() faz em producao.
INSERT IGNORE INTO colecao_cartas (colecao_id, carta_id, quantidade, origem)
SELECT i.colecao_id, ct.id, 1 + (ct.id % 3), 'importacao'
  FROM importacoes i
  JOIN cartas ct ON ct.card_game = i.card_game
 WHERE i.status = 'concluida' AND i.colecao_id IS NOT NULL;
