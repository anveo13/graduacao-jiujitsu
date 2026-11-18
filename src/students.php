<?php
declare(strict_types=1);

function list_active_students(string $search = ''): array
{
    $sql = 'SELECT s.id,s.name,s.belt,s.degrees,s.last_graduation_at,s.created_at,s.updated_at,cu.name AS created_by_name,uu.name AS updated_by_name
            FROM students s
            LEFT JOIN users cu ON cu.id=s.created_by
            LEFT JOIN users uu ON uu.id=s.updated_by
            WHERE s.active=1';
    $params = [];

    if ($search !== '') {
        $escapedSearch = str_replace(["\\", '%', '_'], ["\\\\", "\\%", "\\_"], $search);
        $sql .= " AND s.name LIKE ? ESCAPE '\\\\'";
        $params[] = '%' . $escapedSearch . '%';
    }

    $sql .= " ORDER BY FIELD(s.belt,'white','orange','green','blue','purple','brown','black'),s.name,s.id";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function find_active_student(int $id): ?array
{
    $stmt = db()->prepare('SELECT id,name,belt,degrees,last_graduation_at,created_by,updated_by,created_at,updated_at FROM students WHERE id=? AND active=1');
    $stmt->execute([$id]);
    $student = $stmt->fetch();

    return $student ?: null;
}

function find_student(int $id): ?array
{
    $stmt=db()->prepare('SELECT s.id,s.name,s.belt,s.degrees,s.last_graduation_at,s.active,s.created_by,s.updated_by,s.created_at,s.updated_at,u.name AS updated_by_name FROM students s LEFT JOIN users u ON u.id=s.updated_by WHERE s.id=?');
    $stmt->execute([$id]);
    $student=$stmt->fetch();
    return $student?:null;
}

function list_student_graduations(int $studentId): array
{
    $stmt=db()->prepare("SELECT g.id,g.event_id,g.from_belt,g.from_degrees,g.to_belt,g.to_degrees,g.graduated_at,g.graduated_by,
        u.name AS graduated_by_name,e.name AS event_name,e.event_date
        FROM graduations g LEFT JOIN users u ON u.id=g.graduated_by LEFT JOIN events e ON e.id=g.event_id
        WHERE g.student_id=? AND g.status='done' ORDER BY g.graduated_at DESC,g.id DESC");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

function list_dashboard_students(): array
{
    $stmt=db()->query("SELECT s.id,s.name,s.belt,s.degrees,s.last_graduation_at FROM students s
        WHERE s.active=1 AND s.last_graduation_at IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM graduations g WHERE g.student_id=s.id AND g.event_id IS NOT NULL AND g.status='planned')
        ORDER BY s.last_graduation_at,s.name,s.id");
    return $stmt->fetchAll();
}

function save_student(?int $id, string $name, string $belt, int $degrees, int $userId): void
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name, 'UTF-8') > 150) {
        throw new InvalidArgumentException('Informe o nome do aluno (até 150 caracteres).');
    }
    if ($degrees < 0 || $degrees > belt_max_degrees($belt)) {
        throw new InvalidArgumentException('A quantidade de graus não é válida para a faixa selecionada.');
    }

    if ($id === null) {
        $stmt = db()->prepare('INSERT INTO students(name,belt,degrees,created_by,updated_by) VALUES(?,?,?,?,?)');
        $stmt->execute([$name, $belt, $degrees, $userId, $userId]);
        return;
    }

    $stmt = db()->prepare('UPDATE students SET name=?,belt=?,degrees=?,updated_by=? WHERE id=? AND active=1');
    $stmt->execute([$name, $belt, $degrees, $userId, $id]);
    if ($stmt->rowCount() === 0 && find_active_student($id) === null) {
        throw new RuntimeException('Aluno não encontrado ou já desativado.');
    }
}

function deactivate_student(int $id, int $userId): void
{
    $stmt = db()->prepare('UPDATE students SET active=0,updated_by=? WHERE id=? AND active=1');
    $stmt->execute([$userId, $id]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Aluno não encontrado ou já desativado.');
    }
}
