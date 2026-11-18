<?php
declare(strict_types=1);

function suggested_graduation_target(string $belt, int $degrees): ?string
{
    $max = belt_max_degrees($belt);
    if ($degrees < 0 || $degrees > $max) throw new InvalidArgumentException('Quantidade de graus inválida para a faixa.');
    if ($belt === 'black' && $degrees >= 4) return null;
    if ($belt !== 'black' && $degrees === 4) {
        return match ($belt) {
            'white' => 'belt:blue',
            'orange' => 'belt:green',
            'green' => 'belt:blue',
            'blue' => 'belt:purple',
            'purple' => 'belt:brown',
            'brown' => 'belt:black',
        };
    }

    return $degrees < $max ? 'degree:1' : null;
}

/** @return list<array{value:string,label:string}> */
function graduation_target_options(string $belt, int $degrees): array
{
    $max = belt_max_degrees($belt);
    if ($degrees < 0 || $degrees > $max) {
        throw new InvalidArgumentException('Faixa ou graus atuais inválidos.');
    }

    $options = [];
    for ($amount = 1; $degrees + $amount <= $max; $amount++) {
        $options[] = ['value' => 'degree:'.$amount, 'label' => '+'.$amount.' '.($amount === 1 ? 'grau' : 'graus')];
    }
    foreach (belt_names() as $code => $label) {
        if ($code !== $belt) {
            $options[] = ['value' => 'belt:'.$code, 'label' => 'Trocar para faixa '.$label];
        }
    }

    return $options;
}

/** @return array{belt:string,degrees:int} */
function resolve_graduation_target(string $currentBelt, int $currentDegrees, string $target): array
{
    foreach (graduation_target_options($currentBelt, $currentDegrees) as $option) {
        if ($option['value'] !== $target) {
            continue;
        }
        [$kind, $value] = explode(':', $target, 2);
        return $kind === 'degree'
            ? ['belt' => $currentBelt, 'degrees' => $currentDegrees + (int) $value]
            : ['belt' => $value, 'degrees' => 0];
    }

    throw new InvalidArgumentException('Escolha de graduação inválida.');
}

function validate_event_date(string $date): string
{
    $timezone = new DateTimeZone('America/Sao_Paulo');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Informe uma data válida para o evento.');
    }
    return $date;
}

function create_event(string $name, string $date, int $userId): int
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
        throw new InvalidArgumentException('Informe o nome do evento (até 150 caracteres).');
    }
    validate_event_date($date);
    $stmt = db()->prepare('INSERT INTO events(name,event_date,created_by,updated_by) VALUES(?,?,?,?)');
    $stmt->execute([$name, $date, $userId, $userId]);
    return (int) db()->lastInsertId();
}

function list_events(): array
{
    return db()->query("SELECT e.id,e.name,e.event_date,
        SUM(g.id IS NOT NULL AND g.status='planned') AS planned_count,
        SUM(g.id IS NOT NULL AND g.status='done') AS done_count
        FROM events e LEFT JOIN graduations g ON g.event_id=e.id
        GROUP BY e.id,e.name,e.event_date ORDER BY e.event_date DESC,e.id DESC")->fetchAll();
}

function find_event(int $id): ?array
{
    $stmt = db()->prepare('SELECT id,name,event_date FROM events WHERE id=?');
    $stmt->execute([$id]);
    $event = $stmt->fetch();
    return $event ?: null;
}

function list_event_graduations(int $eventId, bool $showHidden = false): array
{
    $sql = "SELECT g.id,g.student_id,g.from_belt,g.from_degrees,g.to_belt,g.to_degrees,g.status,g.hidden,g.graduated_at,
        gb.id AS graduated_by_id,gb.name AS graduated_by_name,s.name AS student_name,s.belt AS current_belt,s.degrees AS current_degrees,s.last_graduation_at,
        CASE WHEN g.status='done' THEN 1 ELSE 0 END AS is_done
        FROM graduations g JOIN students s ON s.id=g.student_id
        LEFT JOIN users gb ON gb.id=g.graduated_by WHERE g.event_id=?";
    if (!$showHidden) $sql .= ' AND g.hidden=0';
    $sql .= " ORDER BY is_done ASC,FIELD(g.from_belt,'white','orange','green','blue','purple','brown','black'),g.to_belt<>g.from_belt,g.from_degrees,g.to_degrees,s.name,g.id";
    $stmt = db()->prepare($sql);
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/** @return array{planned:int,done:int} */
function event_progress(int $eventId): array
{
    $stmt = db()->prepare("SELECT SUM(status='planned') AS planned,SUM(status='done') AS done FROM graduations WHERE event_id=?");
    $stmt->execute([$eventId]);
    $row = $stmt->fetch() ?: [];
    return ['planned'=>(int)($row['planned']??0),'done'=>(int)($row['done']??0)];
}

function find_next_event(string $fromDate): ?array
{
    $stmt=db()->prepare("SELECT e.id,e.name,e.event_date FROM events e WHERE e.event_date>=?
        ORDER BY e.event_date,e.id LIMIT 1");
    $stmt->execute([$fromDate]);
    $event=$stmt->fetch();
    return $event?:null;
}

function list_students_available_for_event(int $eventId): array
{
    $stmt = db()->prepare("SELECT s.id,s.name,s.belt,s.degrees FROM students s
        WHERE s.active=1 AND NOT EXISTS (SELECT 1 FROM graduations g WHERE g.event_id=? AND g.student_id=s.id)
        ORDER BY FIELD(s.belt,'white','orange','green','blue','purple','brown','black'),s.degrees,s.name");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function add_student_to_event(int $eventId, int $studentId, string $target, int $userId): void
{
    add_students_to_event($eventId, [$studentId => $target], $userId);
}

/**
 * Adiciona vários alunos de uma vez (tudo ou nada).
 * @param array<int,string> $targets id do aluno => escolha ("degree:1", "belt:blue"...)
 */
function add_students_to_event(int $eventId, array $targets, int $userId): int
{
    if ($targets === []) throw new InvalidArgumentException('Marque pelo menos um aluno.');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $event = $pdo->prepare('SELECT id FROM events WHERE id=? FOR UPDATE');
        $event->execute([$eventId]);
        if (!$event->fetch()) throw new RuntimeException('Evento não encontrado.');
        $student = $pdo->prepare('SELECT id,name,belt,degrees FROM students WHERE id=? AND active=1 FOR UPDATE');
        $insert = $pdo->prepare("INSERT INTO graduations(student_id,event_id,from_belt,from_degrees,to_belt,to_degrees,status,created_by,updated_by)
            VALUES(?,?,?,?,?,?,'planned',?,?)");
        foreach ($targets as $studentId => $target) {
            $student->execute([(int) $studentId]);
            $row = $student->fetch();
            if (!$row) throw new RuntimeException('Aluno não encontrado ou desativado.');
            try {
                $destination = resolve_graduation_target($row['belt'], (int) $row['degrees'], (string) $target);
            } catch (InvalidArgumentException) {
                throw new InvalidArgumentException('Escolha a graduação de '.$row['name'].'.');
            }
            $insert->execute([(int) $studentId,$eventId,$row['belt'],$row['degrees'],$destination['belt'],$destination['degrees'],$userId,$userId]);
        }
        $pdo->commit();
        return count($targets);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && (string) $error->getCode() === '23000') {
            throw new RuntimeException('Um dos alunos já está na lista deste evento. Atualize a página e tente de novo.');
        }
        throw $error;
    }
}

/** Troca o que o aluno vai receber. Recalcula a partir da faixa e graus ATUAIS (corrige plano desatualizado). */
function update_event_graduation_target(int $eventId, int $graduationId, string $target, int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT g.id,s.belt,s.degrees FROM graduations g JOIN students s ON s.id=g.student_id
            WHERE g.event_id=? AND g.id=? AND g.status='planned' FOR UPDATE");
        $stmt->execute([$eventId,$graduationId]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('Graduação planejada não encontrada.');
        $destination = resolve_graduation_target($row['belt'], (int) $row['degrees'], $target);
        $update = $pdo->prepare("UPDATE graduations SET from_belt=?,from_degrees=?,to_belt=?,to_degrees=?,updated_by=? WHERE id=? AND status='planned'");
        $update->execute([$row['belt'],$row['degrees'],$destination['belt'],$destination['degrees'],$userId,$graduationId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Tira o aluno da lista. Só vale para graduação ainda não realizada. */
function remove_event_graduation(int $eventId, int $graduationId): void
{
    $stmt = db()->prepare("DELETE FROM graduations WHERE event_id=? AND id=? AND status='planned'");
    $stmt->execute([$eventId,$graduationId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Graduação planejada não encontrada. Se já teve check, desfaça o check antes.');
}

function update_event(int $eventId, string $name, string $date, int $userId): void
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
        throw new InvalidArgumentException('Informe o nome do evento (até 150 caracteres).');
    }
    validate_event_date($date);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $event = $pdo->prepare('SELECT id,event_date FROM events WHERE id=? FOR UPDATE');
        $event->execute([$eventId]);
        $row = $event->fetch();
        if (!$row) throw new RuntimeException('Evento não encontrado.');
        // A data do evento é a data das graduações já feitas nele: com check, a data não muda.
        if ($row['event_date'] !== $date && event_progress($eventId)['done'] > 0) {
            throw new RuntimeException('Este evento já tem graduações registradas; a data não pode mudar. Desfaça os checks antes, se precisar.');
        }
        $stmt = $pdo->prepare('UPDATE events SET name=?,event_date=?,updated_by=? WHERE id=?');
        $stmt->execute([$name,$date,$userId,$eventId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Apaga o evento e a lista planejada. Recusa se houver graduação realizada (o evento é o registro daquele dia). */
function delete_event(int $eventId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $event = $pdo->prepare('SELECT id FROM events WHERE id=? FOR UPDATE');
        $event->execute([$eventId]);
        if (!$event->fetch()) throw new RuntimeException('Evento não encontrado.');
        $done = $pdo->prepare("SELECT COUNT(*) FROM graduations WHERE event_id=? AND status='done'");
        $done->execute([$eventId]);
        if ((int) $done->fetchColumn() > 0) throw new RuntimeException('Este evento já tem graduações registradas e não pode ser apagado.');
        $pdo->prepare("DELETE FROM graduations WHERE event_id=? AND status='planned'")->execute([$eventId]);
        $pdo->prepare('DELETE FROM events WHERE id=?')->execute([$eventId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Texto para mandar no WhatsApp com quem já recebeu o check. */
function event_share_text(array $event, array $graduations): string
{
    $date = (new DateTimeImmutable($event['event_date'], new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y');
    $lines = ['🥋 '.$event['name'].' · '.$date, ''];
    foreach ($graduations as $row) {
        if ($row['status'] !== 'done') continue;
        $lines[] = '• '.$row['student_name'].': '.belt_rank($row['from_belt'], (int) $row['from_degrees']).' → '.belt_rank($row['to_belt'], (int) $row['to_degrees']);
    }
    $lines[] = '';
    $lines[] = 'Parabéns a todos! 👏';
    return implode("\n", $lines);
}

/** Returns false when the date requires an explicit ineligible override. */
function check_event_graduation(int $eventId, int $graduationId, int $userId, bool $confirmIneligible = false): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT g.id,g.student_id,g.from_belt,g.from_degrees,g.to_belt,g.to_degrees,g.status,e.event_date,
            s.belt,s.degrees,s.last_graduation_at FROM graduations g JOIN events e ON e.id=g.event_id
            JOIN students s ON s.id=g.student_id WHERE e.id=? AND g.id=? FOR UPDATE");
        $stmt->execute([$eventId,$graduationId]);
        $row = $stmt->fetch();
        if (!$row || $row['status'] !== 'planned') throw new RuntimeException('Graduação planejada não encontrada.');
        if ($row['from_belt'] !== $row['belt'] || (int) $row['from_degrees'] !== (int) $row['degrees']) {
            throw new RuntimeException('O aluno recebeu outra graduação depois deste planejamento. Use "Alterar" no cartão dele para revisar antes do check.');
        }
        $eligibility = student_eligibility($row['belt'], (int) $row['degrees'], $row['last_graduation_at'], new DateTimeImmutable($row['event_date'], new DateTimeZone('America/Sao_Paulo')));
        if (!$eligibility['eligible'] && !$confirmIneligible) {
            $pdo->rollBack();
            return false;
        }
        $updateStudent = $pdo->prepare('UPDATE students SET belt=?,degrees=?,last_graduation_at=?,updated_by=? WHERE id=?');
        $updateStudent->execute([$row['to_belt'] ?? '', $row['to_degrees'] ?? 0, $row['event_date'], $userId, $row['student_id']]);
        $updateGraduation = $pdo->prepare("UPDATE graduations SET status='done',hidden=0,graduated_at=?,graduated_by=?,updated_by=? WHERE id=? AND status='planned'");
        $updateGraduation->execute([$row['event_date'],$userId,$userId,$graduationId]);
        if ($updateGraduation->rowCount() !== 1) throw new RuntimeException('A graduação já foi atualizada.');
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function set_event_graduation_hidden(int $eventId, int $graduationId, bool $hidden, int $userId): void
{
    $stmt = db()->prepare("UPDATE graduations SET hidden=?,updated_by=? WHERE event_id=? AND id=? AND status='planned'");
    $stmt->execute([$hidden ? 1 : 0,$userId,$eventId,$graduationId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Graduação planejada não encontrada.');
}

function undo_event_graduation(int $eventId, int $graduationId, array $user): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT g.id,g.student_id,g.event_id,g.status,g.graduated_by,s.belt,s.degrees
            FROM graduations g JOIN students s ON s.id=g.student_id WHERE g.event_id=? AND g.id=? FOR UPDATE");
        $stmt->execute([$eventId,$graduationId]);
        $row = $stmt->fetch();
        if (!$row || $row['event_id'] === null || $row['status'] !== 'done') throw new RuntimeException('Graduação realizada em evento não encontrada.');
        if ($user['role'] === 'viewer' && (int) $row['graduated_by'] !== (int) $user['id']) {
            throw new RuntimeException('Visualizadores só podem desfazer o próprio check.');
        }
        $latest = $pdo->prepare("SELECT id FROM graduations WHERE student_id=? AND status='done' ORDER BY graduated_at DESC,id DESC LIMIT 1 FOR UPDATE");
        $latest->execute([$row['student_id']]);
        if ((int) $latest->fetchColumn() !== $graduationId) throw new RuntimeException('Só é possível desfazer a graduação mais recente deste aluno.');
        $previous = $pdo->prepare("SELECT graduated_at FROM graduations WHERE student_id=? AND status='done' AND id<>? ORDER BY graduated_at DESC,id DESC LIMIT 1");
        $previous->execute([$row['student_id'],$graduationId]);
        $previousDate = $previous->fetchColumn();
        $restore = $pdo->prepare('UPDATE students SET belt=(SELECT from_belt FROM graduations WHERE id=?),degrees=(SELECT from_degrees FROM graduations WHERE id=?),last_graduation_at=?,updated_by=? WHERE id=?');
        $restore->execute([$graduationId,$graduationId,$previousDate ?: null,(int)$user['id'],$row['student_id']]);
        $undo = $pdo->prepare("UPDATE graduations SET status='planned',graduated_at=NULL,graduated_by=NULL,hidden=0,updated_by=? WHERE id=?");
        $undo->execute([(int)$user['id'],$graduationId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
