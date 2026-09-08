<?php
declare(strict_types=1);

/**
 * Helpers usados pelas paginas HTML (index, dashboard, colecoes, importacoes)
 * e pelos parciais compartilhados. Nada aqui toca no banco.
 */

/** Escape curto para interpolar valores no HTML. */
function h(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Iniciais para o avatar do topo: "Ana Souza" -> "AS". */
function iniciais(string $nome, string $padrao = 'AD'): string
{
    $partes = preg_split('/\s+/u', trim($nome)) ?: [];
    $letras = '';

    foreach (array_slice(array_filter($partes), 0, 2) as $parte) {
        $letras .= mb_substr($parte, 0, 1);
    }

    return mb_strtoupper($letras !== '' ? $letras : $padrao);
}
