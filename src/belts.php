<?php
declare(strict_types=1);

function belt_names(): array
{
    return [
        'white' => 'Branca',
        'orange' => 'Laranja',
        'green' => 'Verde',
        'blue' => 'Azul',
        'purple' => 'Roxa',
        'brown' => 'Marrom',
        'black' => 'Preta',
    ];
}

function belt_max_degrees(string $belt): int
{
    if (!array_key_exists($belt, belt_names())) {
        throw new InvalidArgumentException('Faixa inválida.');
    }

    return $belt === 'black' ? 6 : 4;
}

function belt_label(string $belt): string
{
    return belt_names()[$belt] ?? 'Faixa inválida';
}

/** Faixa e graus para a tela: "Azul", "Azul 1 grau", "Azul 2 graus" (0 grau mostra só a faixa). */
function belt_rank(string $belt, int $degrees): string
{
    $label = belt_label($belt);
    if ($degrees <= 0) return $label;
    return $label.' '.$degrees.' '.($degrees === 1 ? 'grau' : 'graus');
}
