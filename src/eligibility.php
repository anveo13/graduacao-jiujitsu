<?php
declare(strict_types=1);

/**
 * Calculates when a student can next be graduated.
 * The date-only result is deterministic for the supplied student state and today.
 *
 * @return array{eligible: bool, eligible_at: ?string, status: 'eligible'|'waiting'|'max'}
 */
function student_eligibility(string $belt, int $degrees, ?string $lastGraduationAt, DateTimeImmutable $today): array
{
    $maxDegrees = belt_max_degrees($belt);
    if ($degrees < 0 || $degrees > $maxDegrees) {
        throw new InvalidArgumentException('Quantidade de graus inválida para a faixa.');
    }

    if ($belt === 'black' && $degrees === 6) {
        return ['eligible' => false, 'eligible_at' => null, 'status' => 'max'];
    }

    if ($lastGraduationAt === null) {
        return ['eligible' => true, 'eligible_at' => null, 'status' => 'eligible'];
    }

    $timezone = new DateTimeZone('America/Sao_Paulo');
    $lastDate = DateTimeImmutable::createFromFormat('!Y-m-d', $lastGraduationAt, $timezone);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$lastDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $lastDate->format('Y-m-d') !== $lastGraduationAt) {
        throw new InvalidArgumentException('Data da última graduação inválida.');
    }

    [$months, $years] = match ($belt) {
        'white' => [2, 0],
        'orange', 'green' => [3, 0],
        'blue', 'purple', 'brown' => [5, 0],
        'black' => [0, $degrees <= 2 ? 3 : 5],
    };

    $eligibleAt = add_calendar_period($lastDate, $months, $years);
    $todayDate = $today->setTimezone($timezone)->setTime(0, 0);
    $eligible = $todayDate >= $eligibleAt;

    return [
        'eligible' => $eligible,
        'eligible_at' => $eligibleAt->format('Y-m-d'),
        'status' => $eligible ? 'eligible' : 'waiting',
    ];
}

function add_calendar_period(DateTimeImmutable $date, int $months, int $years): DateTimeImmutable
{
    $targetMonthIndex = ($date->format('Y') * 12) + ((int) $date->format('n') - 1) + $months + ($years * 12);
    $targetYear = intdiv($targetMonthIndex, 12);
    $targetMonth = ($targetMonthIndex % 12) + 1;
    $targetMonthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $targetYear, $targetMonth), $date->getTimezone());
    $targetDay = min((int) $date->format('j'), (int) $targetMonthStart->format('t'));

    return $date->setDate($targetYear, $targetMonth, $targetDay)->setTime(0, 0);
}
