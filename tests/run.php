<?php
declare(strict_types=1);

require dirname(__DIR__).'/src/belts.php';
require dirname(__DIR__).'/src/eligibility.php';
require dirname(__DIR__).'/src/events.php';

$timezone = new DateTimeZone('America/Sao_Paulo');
$tests = 0;
$failures = [];

$assertSame = static function (mixed $expected, mixed $actual, string $label) use (&$tests, &$failures): void {
    $tests++;
    if ($expected !== $actual) {
        $failures[] = $label . ': esperado ' . var_export($expected, true) . ', recebido ' . var_export($actual, true);
    }
};

$assertThrows = static function (callable $callback, string $label) use (&$tests, &$failures): void {
    $tests++;
    try {
        $callback();
        $failures[] = $label . ': esperava InvalidArgumentException.';
    } catch (InvalidArgumentException) {
        // Resultado esperado.
    }
};

$case = static function (string $belt, int $degrees, ?string $lastDate, string $today) use ($timezone): array {
    return student_eligibility($belt, $degrees, $lastDate, new DateTimeImmutable($today, $timezone));
};

$assertSame(['eligible' => true, 'eligible_at' => null, 'status' => 'eligible'], $case('white', 0, null, '2026-11-18'), 'Aluno sem graduação anterior');
$assertSame(['eligible' => false, 'eligible_at' => '2027-02-04', 'status' => 'waiting'], $case('white', 3, '2026-12-04', '2027-02-03'), 'Branca antes de dois meses');
$assertSame(['eligible' => true, 'eligible_at' => '2027-02-04', 'status' => 'eligible'], $case('white', 3, '2026-12-04', '2027-02-04'), 'Branca na data de elegibilidade');
$assertSame('2027-03-04', $case('orange', 2, '2026-12-04', '2027-03-04')['eligible_at'], 'Laranja com três meses');
$assertSame('2027-03-04', $case('green', 1, '2026-12-04', '2027-03-04')['eligible_at'], 'Verde com três meses');
$assertSame('2027-05-04', $case('blue', 0, '2026-12-04', '2027-05-04')['eligible_at'], 'Azul com cinco meses');
$assertSame('2027-05-04', $case('purple', 0, '2026-12-04', '2027-05-04')['eligible_at'], 'Roxa com cinco meses');
$assertSame('2027-05-04', $case('brown', 0, '2026-12-04', '2027-05-04')['eligible_at'], 'Marrom com cinco meses');
$assertSame('2029-12-04', $case('black', 0, '2026-12-04', '2029-12-04')['eligible_at'], 'Preta até dois graus com três anos');
$assertSame('2031-12-04', $case('black', 3, '2026-12-04', '2031-12-04')['eligible_at'], 'Preta com três graus ou mais com cinco anos');
$assertSame('2027-02-28', $case('white', 0, '2026-12-31', '2027-02-28')['eligible_at'], 'Ajuste de dia no mês de destino');
$assertSame('2027-02-28', $case('black', 0, '2024-02-29', '2027-02-28')['eligible_at'], 'Clampeia 29 de fevereiro em ano comum');
$assertSame(['eligible' => false, 'eligible_at' => null, 'status' => 'max'], $case('black', 6, '2024-01-01', '2030-01-01'), 'Preta no grau máximo');
$assertThrows(static fn() => $case('white', 5, null, '2026-11-18'), 'Graus acima do limite da faixa');
$assertThrows(static fn() => $case('gray', 0, null, '2026-11-18'), 'Faixa inexistente');
$assertThrows(static fn() => $case('blue', 0, '2026-02-30', '2026-11-18'), 'Data impossível');

$assertSame('belt:blue', suggested_graduation_target('white', 4), 'Sugestão para faixa branca com quatro graus');
$assertSame('degree:1', suggested_graduation_target('white', 2), 'Sugestão de mais um grau');
$assertSame(null, suggested_graduation_target('black', 6), 'Faixa preta no grau máximo sem sugestão');
$assertSame(null, suggested_graduation_target('black', 4), 'Sem sugestão de nova faixa para a faixa preta');
$assertSame('Roxa', belt_rank('purple', 0), 'Faixa com 0 grau mostra só a faixa');
$assertSame('Branca 1 grau', belt_rank('white', 1), 'Faixa com 1 grau');
$assertSame('Preta 3 graus', belt_rank('black', 3), 'Faixa com vários graus');
$assertSame('<span class="belt-text belt-text-blue">Azul 2 graus</span>', belt_rank_html('blue', 2), 'Nome da faixa na cor da faixa');
$assertSame(3, substr_count(belt_bar_html('purple', 3), '<i></i>'), 'Mini-faixa com uma listra por grau');
$assertSame(0, substr_count(belt_bar_html('white', 0), '<i></i>'), 'Mini-faixa sem grau não tem listras');
$assertSame(['belt'=>'white','degrees'=>4], resolve_graduation_target('white',2,'degree:2'), 'Escolha de dois graus');
$assertSame(['belt'=>'purple','degrees'=>0], resolve_graduation_target('blue',4,'belt:purple'), 'Troca de faixa zera os graus');
$assertThrows(static fn() => resolve_graduation_target('white',4,'degree:1'), 'Não ultrapassa o limite da faixa');
$assertThrows(static fn() => validate_event_date('2026-02-30'), 'Data impossível de evento');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "{$tests} testes passaram.\n");
