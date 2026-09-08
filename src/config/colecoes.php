<?php
declare(strict_types=1);

/**
 * Vocabulario das colecoes.
 *
 * Uma colecao e um agrupamento de cartas do acervo, sempre preso a um unico
 * card game. O "tipo" separa o que o usuario esta montando: um deck que vai
 * para a mesa, um binder de guarda ou uma lista de desejos.
 *
 * As chaves aqui espelham o ENUM colecoes.tipo do banco: mudar uma exige
 * migracao. Os rotulos sao livres.
 */
function tipos_de_colecao(): array
{
    return [
        'deck'     => 'Deck',
        'binder'   => 'Binder',
        'wishlist' => 'Lista de desejos',
    ];
}

/** Frase curta exibida no formulario para explicar cada tipo. */
function descricoes_dos_tipos(): array
{
    return [
        'deck'     => 'Lista fechada, pronta para jogar. É o tipo que aceita vínculo com um deck online.',
        'binder'   => 'Pasta de guarda: o que você tem em mãos, sem compromisso com um formato.',
        'wishlist' => 'O que você ainda quer comprar ou trocar.',
    ];
}

/** Chaves aceitas pela coluna ENUM colecoes.tipo. */
function tipos_suportados(): array
{
    return array_keys(tipos_de_colecao());
}
