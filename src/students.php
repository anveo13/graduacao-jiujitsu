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
