# Guia de Implementação e Estrutura de Dados para TCGs

Este documento contém a estrutura de dados expandida em formato JSON para os principais **Trading Card Games (TCGs)**, acompanhado por um guia de implementação completo em TypeScript e JavaScript, incluindo boas práticas de arquitetura e validação de esquemas.

---

## 1. Estrutura de Dados JSON Expandida

O schema abaixo inclui coleções e cartas detalhadas para cinco jogos:
* **Magic: The Gathering**
* **Pokémon TCG**
* **Yu-Gi-Oh!**
* **One Piece Card Game**
* **Flesh and Blood**

```json
{
  "magic": [
    {
      "id": "dom",
      "name": "Dominaria",
      "releaseDate": "2018-04-27",
      "cards": [
        {
          "id": "dom-100",
          "name": "Teferi, Hero of Dominaria",
          "type": "Planeswalker",
          "rarity": "Mythic Rare",
          "manaCost": "{3}{W}{U}",
          "attributes": {
            "loyalty": 4
          }
        },
        {
          "id": "dom-130",
          "name": "Llanowar Elves",
          "type": "Creature — Elf Druid",
          "rarity": "Common",
          "manaCost": "{G}",
          "attributes": {
            "power": "1",
            "toughness": "1"
          }
        }
      ]
    },
    {
      "id": "war",
      "name": "War of the Spark",
      "releaseDate": "2019-05-03",
      "cards": [
        {
          "id": "war-061",
          "name": "Narset, Parter of Veils",
          "type": "Planeswalker",
          "rarity": "Uncommon",
          "manaCost": "{1}{U}",
          "attributes": {
            "loyalty": 5
          }
        }
      ]
    }
  ],
  "pokemon": [
    {
      "id": "base1",
      "name": "Base Set",
      "releaseDate": "1999-01-09",
      "cards": [
        {
          "id": "base1-4",
          "name": "Charizard",
          "type": "Pokémon",
          "rarity": "Rare Holo",
          "attributes": {
            "hp": 120,
            "element": "Fire",
            "stage": "Stage 2"
          }
        },
        {
          "id": "base1-58",
          "name": "Pikachu",
          "type": "Pokémon",
          "rarity": "Common",
          "attributes": {
            "hp": 40,
            "element": "Lightning",
            "stage": "Basic"
          }
        }
      ]
    }
  ],
  "yugioh": [
    {
      "id": "lob",
      "name": "Legend of Blue Eyes White Dragon",
      "releaseDate": "2002-03-08",
      "cards": [
        {
          "id": "LOB-001",
          "name": "Blue-Eyes White Dragon",
          "type": "Normal Monster",
          "rarity": "Ultra Rare",
          "attributes": {
            "attribute": "LIGHT",
            "level": 8,
            "atk": 3000,
            "def": 2500
          }
        },
        {
          "id": "LOB-053",
          "name": "Raigeki",
          "type": "Spell Card",
          "rarity": "Super Rare",
          "attributes": {
            "spellType": "Normal"
          }
        }
      ]
    }
  ],
  "onepiece": [
    {
      "id": "op01",
      "name": "Romance Dawn",
      "releaseDate": "2022-07-22",
      "cards": [
        {
          "id": "OP01-001",
          "name": "Roronoa Zoro",
          "type": "Leader",
          "rarity": "Leader",
          "attributes": {
            "color": "Red",
            "power": 5000,
            "life": 4
          }
        },
        {
          "id": "OP01-016",
          "name": "Monkey.D.Luffy",
          "type": "Character",
          "rarity": "Super Rare",
          "attributes": {
            "color": "Red",
            "cost": 8,
            "power": 9000
          }
        }
      ]
    }
  ],
  "fab": [
    {
      "id": "wtr",
      "name": "Welcome to Rathe",
      "releaseDate": "2019-10-11",
      "cards": [
        {
          "id": "WTR001",
          "name": "Rhinar, Reckless Alpha",
          "type": "Hero",
          "rarity": "Majestic",
          "attributes": {
            "class": "Brute",
            "intellect": 4,
            "health": 20
          }
        }
      ]
    }
  ]
}
```

---

## 2. Guia de Implementação Passo a Passo

### Passo 1: Definição de Tipos e Interfaces (TypeScript)

Para assegurar consistência no projeto, definimos os tipos das entidades do sistema:

```typescript
// Tipos para os atributos flexíveis das cartas
export interface CardAttributes {
  hp?: number;
  element?: string;
  stage?: string;
  power?: string | number;
  toughness?: string;
  loyalty?: number;
  level?: number;
  atk?: number;
  def?: number;
  cost?: number;
  color?: string;
  class?: string;
  health?: number;
  intellect?: number;
  [key: string]: unknown; // Permite extensão de atributos específicos
}

// Representação individual de uma carta
export interface Card {
  id: string;
  name: string;
  type: string;
  rarity: string;
  manaCost?: string;
  attributes?: CardAttributes;
}

// Representação de uma coleção/set
export interface TCGSet {
  id: string;
  name: string;
  releaseDate?: string;
  cards: Card[];
}

// Estrutura principal do Banco de Dados JSON
export type TCGDatabase = Record<string, TCGSet[]>;
```

---

### Passo 2: Funções Utilitárias de Busca (JavaScript / TypeScript)

Abaixo estão as funções utilitárias para pesquisar jogos, coleções e cartas no arquivo de dados.

```javascript
/**
 * Retorna todas as coleções de um jogo específico.
 * @param {Object} db - Banco de dados JSON
 * @param {string} gameKey - Chave do jogo (ex: 'magic', 'pokemon')
 * @returns {Array} Lista de coleções
 */
function getSetsByGame(db, gameKey) {
  return db[gameKey] || [];
}

/**
 * Busca uma coleção específica pelo ID dentro de um jogo.
 * @param {Object} db - Banco de dados JSON
 * @param {string} gameKey - Chave do jogo
 * @param {string} setId - ID da coleção
 * @returns {Object|null}
 */
function getSetById(db, gameKey, setId) {
  const sets = getSetsByGame(db, gameKey);
  return sets.find(set => set.id.toLowerCase() === setId.toLowerCase()) || null;
}

/**
 * Busca uma carta específica em determinada coleção e jogo.
 * @param {Object} db - Banco de dados JSON
 * @param {string} gameKey - Chave do jogo
 * @param {string} setId - ID da coleção
 * @param {string} cardId - ID da carta
 * @returns {Object|null}
 */
function getCardById(db, gameKey, setId, cardId) {
  const set = getSetById(db, gameKey, setId);
  if (!set) return null;
  return set.cards.find(card => card.id.toLowerCase() === cardId.toLowerCase()) || null;
}

/**
 * Pesquisa global de cartas por nome em todos os jogos.
 * @param {Object} db - Banco de dados JSON
 * @param {string} query - Termo de busca
 * @returns {Array} Lista de cartas encontradas com indicação do jogo e coleção
 */
function searchCardsByName(db, query) {
  const results = [];
  const searchTerm = query.toLowerCase();

  for (const [game, sets] of Object.entries(db)) {
    sets.forEach(set => {
      set.cards.forEach(card => {
        if (card.name.toLowerCase().includes(searchTerm)) {
          results.push({
            game,
            setId: set.id,
            setName: set.name,
            card
          });
        }
      });
    });
  }

  return results;
}
```

---

### Passo 3: Validação de Schemas com Zod

Para garantir que novos arquivos JSON carregados na aplicação estejam estruturados corretamente, recomenda-se o uso da biblioteca **Zod**.

```typescript
import { z } from "zod";

const CardSchema = z.object({
  id: z.string(),
  name: z.string(),
  type: z.string(),
  rarity: z.string(),
  manaCost: z.string().optional(),
  attributes: z.record(z.unknown()).optional()
});

const TCGSetSchema = z.object({
  id: z.string(),
  name: z.string(),
  releaseDate: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
  cards: z.array(CardSchema)
});

export const TCGDatabaseSchema = z.record(z.array(TCGSetSchema));

// Função auxiliar para validação de dados recebidos de APIs ou arquivos externos
export function validateTCGData(jsonContent: unknown) {
  const result = TCGDatabaseSchema.safeParse(jsonContent);
  if (!result.success) {
    console.error("Erro de validação no JSON:", result.error.format());
    return null;
  }
  return result.data;
}
```

---

## 3. Boas Práticas e Recomendações de Performance

1. **Otimização de Indexação ($O(1)$)**:
   * Se a base de dados crescer para mais de 10.000 cartas, evite realizar iterações com `.find()` ou `.filter()`. Recomenda-se converter o array de cartas em um Mapa/Dicionário indexado pela chave `id` durante o carregamento inicial.

2. **Padrão de Nomeação de IDs**:
   * Adote prefixos padronizados nos IDs das cartas (ex: `OP01-001`, `LOB-001`, `dom-100`). Isso previne colisão de chaves entre edições diferentes e simplifica rotas em aplicações REST/GraphQL.

3. **Separação de Arquivos em Produção**:
   * Para grande volume de dados, armazene cada jogo em um arquivo JSON isolado (ex: `magic.json`, `pokemon.json`) para viabilizar o carregamento sob demanda (*lazy loading*).