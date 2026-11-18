<?php
declare(strict_types=1);

function graduate_student_now(int $studentId, string $target, int $userId, bool $confirmIneligible = false): bool
{
    $pdo=db();
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT id,belt,degrees,last_graduation_at FROM students WHERE id=? AND active=1 FOR UPDATE');
        $stmt->execute([$studentId]);
        $student=$stmt->fetch();
        if (!$student) throw new RuntimeException('Aluno não encontrado ou desativado.');

        $today=new DateTimeImmutable('today',new DateTimeZone('America/Sao_Paulo'));
        $eligibility=student_eligibility($student['belt'],(int)$student['degrees'],$student['last_graduation_at'],$today);
        if (!$eligibility['eligible']&&!$confirmIneligible) { $pdo->rollBack(); return false; }

        $destination=resolve_graduation_target($student['belt'],(int)$student['degrees'],$target);
        $date=$today->format('Y-m-d');
        $insert=$pdo->prepare("INSERT INTO graduations(student_id,event_id,from_belt,from_degrees,to_belt,to_degrees,status,graduated_at,graduated_by,created_by,updated_by)
            VALUES(?,NULL,?,?,?,?,'done',?,?,?,?)");
        $insert->execute([$studentId,$student['belt'],$student['degrees'],$destination['belt'],$destination['degrees'],$date,$userId,$userId,$userId]);
        $update=$pdo->prepare('UPDATE students SET belt=?,degrees=?,last_graduation_at=?,updated_by=? WHERE id=? AND active=1');
        $update->execute([$destination['belt'],$destination['degrees'],$date,$userId,$studentId]);
        if ($update->rowCount()!==1) throw new RuntimeException('Não foi possível atualizar o cadastro do aluno.');
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function undo_avulsa_graduation(int $studentId, int $graduationId, array $user): void
{
    $pdo=db();$pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare("SELECT g.id,g.student_id,g.from_belt,g.from_degrees,g.status,g.graduated_by
            FROM graduations g JOIN students s ON s.id=g.student_id WHERE g.id=? AND g.student_id=? AND g.event_id IS NULL FOR UPDATE");
        $stmt->execute([$graduationId,$studentId]);$row=$stmt->fetch();
        if (!$row||$row['status']!=='done') throw new RuntimeException('Graduação avulsa não encontrada.');
        if ($user['role']==='viewer'&&(int)$row['graduated_by']!==(int)$user['id']) throw new RuntimeException('Visualizadores só podem desfazer o próprio check.');
        $latest=$pdo->prepare("SELECT id FROM graduations WHERE student_id=? AND status='done' ORDER BY graduated_at DESC,id DESC LIMIT 1 FOR UPDATE");
        $latest->execute([$studentId]);
        if ((int)$latest->fetchColumn()!==$graduationId) throw new RuntimeException('Só é possível desfazer a graduação mais recente deste aluno.');
        $previous=$pdo->prepare("SELECT graduated_at FROM graduations WHERE student_id=? AND status='done' AND id<>? ORDER BY graduated_at DESC,id DESC LIMIT 1");
        $previous->execute([$studentId,$graduationId]);$previousDate=$previous->fetchColumn();
        $restore=$pdo->prepare('UPDATE students SET belt=?,degrees=?,last_graduation_at=?,updated_by=? WHERE id=?');
        $restore->execute([$row['from_belt'],$row['from_degrees'],$previousDate?:null,(int)$user['id'],$studentId]);
        $delete=$pdo->prepare("DELETE FROM graduations WHERE id=? AND event_id IS NULL AND status='done'");
        $delete->execute([$graduationId]);
        if ($delete->rowCount()!==1) throw new RuntimeException('Não foi possível desfazer a graduação avulsa.');
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function student_has_pending_event_graduation(int $studentId): bool
{
    $stmt=db()->prepare("SELECT 1 FROM graduations WHERE student_id=? AND event_id IS NOT NULL AND status='planned' LIMIT 1");
    $stmt->execute([$studentId]);
    return (bool)$stmt->fetchColumn();
}
