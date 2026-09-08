-- Migração para bancos criados com uma versão anterior do schema.
USE portal_cartas;

ALTER TABLE cards
  MODIFY game ENUM('magic','pokemon','yugioh','onepiece','fab') NOT NULL,
  ADD COLUMN image_url VARCHAR(1000) DEFAULT NULL AFTER image_path,
  ADD COLUMN image_reference VARCHAR(1000) DEFAULT NULL AFTER image_url;

ALTER TABLE cards
  ADD UNIQUE KEY cards_game_edition_name (game, edition_id, name_en);

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
('Rhinar, Reckless Alpha', 'fab', 'wtr', 'Majestic', NULL, NULL);
