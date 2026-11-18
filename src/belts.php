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

/** HTML: faixa e graus com o nome na cor da faixa (classe belt-text-{faixa}). */
function belt_rank_html(string $belt, int $degrees): string
{
    $code = array_key_exists($belt, belt_names()) ? $belt : 'white';
    return '<span class="belt-text belt-text-'.$code.'">'.htmlspecialchars(belt_rank($belt, $degrees), ENT_QUOTES, 'UTF-8').'</span>';
}

/** HTML: mini-faixa desenhada (cor da faixa + ponteira com uma listra por grau). Decorativa. */
function belt_bar_html(string $belt, int $degrees = 0): string
{
    $code = array_key_exists($belt, belt_names()) ? $belt : 'white';
    $stripes = str_repeat('<i></i>', max(0, min($degrees, belt_max_degrees($code))));
    return '<span class="belt-bar belt-bar-'.$code.'" aria-hidden="true"><span class="belt-tip">'.$stripes.'</span></span>';
}
