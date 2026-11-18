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
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $event = $pdo->prepare('SELECT id FROM events WHERE id=? FOR UPDATE');
        $event->execute([$eventId]);
        if (!$event->fetch()) throw new RuntimeException('Evento não encontrado.');
        $student = $pdo->prepare('SELECT id,belt,degrees FROM students WHERE id=? AND active=1 FOR UPDATE');
        $student->execute([$studentId]);
        $row = $student->fetch();
        if (!$row) throw new RuntimeException('Aluno não encontrado ou desativado.');
        $destination = resolve_graduation_target($row['belt'], (int) $row['degrees'], $target);
        $insert = $pdo->prepare("INSERT INTO graduations(student_id,event_id,from_belt,from_degrees,to_belt,to_degrees,status,created_by,updated_by)
            VALUES(?,?,?,?,?,?,'planned',?,?)");
        $insert->execute([$studentId,$eventId,$row['belt'],$row['degrees'],$destination['belt'],$destination['degrees'],$userId,$userId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && (string) $error->getCode() === '23000') {
            throw new RuntimeException('Este aluno já está na lista deste evento.');
        }
        throw $error;
    }
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
            throw new RuntimeException('O aluno recebeu outra graduação depois deste planejamento. O plano do evento foi mantido, mas precisa ser revisto antes do check.');
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
