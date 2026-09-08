-- Schema inicial MySQL para Portal de Cartas

CREATE DATABASE IF NOT EXISTS portal_cartas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portal_cartas;

-- Usuários (simples)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cartas
CREATE TABLE IF NOT EXISTS cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name_en VARCHAR(255) NOT NULL,
  name_pt VARCHAR(255) DEFAULT NULL,
  game ENUM('magic','pokemon','yugioh','onepiece','fab') NOT NULL,
  edition_id VARCHAR(50) DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  image_url VARCHAR(1000) DEFAULT NULL,
  image_reference VARCHAR(1000) DEFAULT NULL,
  rarity VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY cards_game_edition_name (game, edition_id, name_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cartas de referência do guia JSON. image_url aponta para a fonte pública da imagem;
-- image_path continua reservado para uploads locais.
INSERT IGNORE INTO cards (name_en, game, edition_id, rarity, image_url, image_reference) VALUES
('Teferi, Hero of Dominaria', 'magic', 'dom', 'Mythic Rare', NULL, 'https://api.scryfall.com/cards/named?exact=Teferi%2C%20Hero%20of%20Dominaria'),
('Llanowar Elves', 'magic', 'dom', 'Common', NULL, 'https://api.scryfall.com/cards/named?exact=Llanowar%20Elves&set=dom'),
('Narset, Parter of Veils', 'magic', 'war', 'Uncommon', NULL, 'https://api.scryfall.com/cards/named?exact=Narset%2C%20Parter%20of%20Veils'),
('Charizard', 'pokemon', 'base1', 'Rare Holo', 'https://images.pokemontcg.io/base1/4.png', NULL),
('Pikachu', 'pokemon', 'base1', 'Common', 'https://images.pokemontcg.io/base1/58.png', NULL),
('Blue-Eyes White Dragon', 'yugioh', 'lob', 'Ultra Rare', 'https://images.ygoprodeck.com/images/cards/89631139.jpg', NULL),
('Raigeki', 'yugioh', 'lob', 'Super Rare', 'https://images.ygoprodeck.com/images/cards/12580477.jpg', NULL),
('Roronoa Zoro', 'onepiece', 'op01', 'Leader', 'https://en.onepiece-cardgame.com/images/cardlist/card/OP01-001.png', NULL),
('Monkey.D.Luffy', 'onepiece', 'op01', 'Super Rare', 'https://en.onepiece-cardgame.com/images/cardlist/card/OP01-016.png', NULL),
('Rhinar, Reckless Alpha', 'fab', 'wtr', 'Majestic', NULL);

-- Seed mínimo
INSERT IGNORE INTO users (email, password, name) VALUES
('admin@example.com', '$2y$10$saltsaltsaltsaltsaltsalt1234567890abcdef', 'Admin');
