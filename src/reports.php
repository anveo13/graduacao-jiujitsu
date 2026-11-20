<?php
declare(strict_types=1);

/** Todas as graduações planejadas ou realizadas do evento, inclusive as ocultas. */
function list_event_report_rows(int $eventId): array
{
    $stmt = db()->prepare("SELECT g.id,g.student_id,g.from_belt,g.from_degrees,g.to_belt,g.to_degrees,g.status,g.hidden,g.graduated_at,
        s.name AS student_name
        FROM graduations g JOIN students s ON s.id=g.student_id
        WHERE g.event_id=?
        ORDER BY FIELD(g.from_belt,'white','orange','green','blue','purple','brown','black'),
            (g.from_belt=g.to_belt),g.from_degrees,g.to_degrees,s.name,g.id");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/** Alunos ativos para o relatório geral, organizados por faixa e graus. */
function list_student_report_rows(): array
{
    return db()->query("SELECT id,name,belt,degrees,last_graduation_at
        FROM students WHERE active=1
        ORDER BY FIELD(belt,'white','orange','green','blue','purple','brown','black'),degrees,name,id")->fetchAll();
}
