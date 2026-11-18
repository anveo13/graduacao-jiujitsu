<?php
declare(strict_types=1);

function list_users(): array
{
    return db()->query("SELECT id,name,username,role,active,created_at FROM users
        ORDER BY FIELD(role,'master','admin','viewer'),active DESC,name,id")->fetchAll();
}

function list_invitations(): array
{
    return db()->query("SELECT i.id,i.name,i.role,i.expires_at,i.created_at,u.name AS created_by_name,
        CASE WHEN i.expires_at<=NOW() THEN 'expired' ELSE 'pending' END AS status
        FROM invitations i JOIN users u ON u.id=i.created_by
        WHERE i.accepted_at IS NULL AND i.cancelled_at IS NULL
        ORDER BY i.created_at DESC,i.id DESC")->fetchAll();
}

function create_invitation(string $name, string $role, int $masterId): string
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name,'UTF-8') > 100) throw new InvalidArgumentException('Informe o nome da pessoa (até 100 caracteres).');
    if (!in_array($role,['admin','viewer'],true)) throw new InvalidArgumentException('Escolha Administrador ou Visualizador.');

    $token = bin2hex(random_bytes(32));
    $stmt = db()->prepare("INSERT INTO invitations(token_hash,name,role,created_by,expires_at) VALUES(?,?,?, ?,DATE_ADD(NOW(),INTERVAL 7 DAY))");
    $stmt->execute([hash('sha256',$token),$name,$role,$masterId]);
    return app_base_url().'/?page=accept-invite&token='.$token;
}

function cancel_invitation(int $invitationId): void
{
    $stmt = db()->prepare('UPDATE invitations SET cancelled_at=NOW() WHERE id=? AND accepted_at IS NULL AND cancelled_at IS NULL');
    $stmt->execute([$invitationId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Convite pendente não encontrado.');
}

function update_user_role(int $userId, string $role): void
{
    if (!in_array($role,['admin','viewer'],true)) throw new InvalidArgumentException('Perfil inválido.');
    $stmt = db()->prepare("UPDATE users SET role=? WHERE id=? AND role<>'master'");
    $stmt->execute([$role,$userId]);
    if ($stmt->rowCount() !== 1) {
        $check=db()->prepare('SELECT id,role FROM users WHERE id=?');$check->execute([$userId]);$row=$check->fetch();
        if (!$row || $row['role']==='master') throw new RuntimeException('Usuário não encontrado ou protegido.');
    }
}

function set_user_active(int $userId, bool $active): void
{
    $stmt = db()->prepare("UPDATE users SET active=? WHERE id=? AND role<>'master'");
    $stmt->execute([$active?1:0,$userId]);
    if ($stmt->rowCount() !== 1) {
        $check=db()->prepare('SELECT id,role,active FROM users WHERE id=?');$check->execute([$userId]);$row=$check->fetch();
        if (!$row || $row['role']==='master') throw new RuntimeException('Usuário não encontrado ou protegido.');
        if ((int)$row['active']===($active?1:0)) return;
    }
}

function update_own_profile(array $user, string $name, string $currentPassword, string $newPassword, string $confirmPassword): void
{
    $name=trim($name);
    if ($name==='' || mb_strlen($name,'UTF-8')>100) throw new InvalidArgumentException('Informe seu nome (até 100 caracteres).');

    $newPasswordHash=null;
    if ($newPassword!=='' || $confirmPassword!=='') {
        if (strlen($newPassword)<8) throw new InvalidArgumentException('A nova senha deve ter pelo menos 8 caracteres.');
        if (!hash_equals($newPassword,$confirmPassword)) throw new InvalidArgumentException('As senhas novas não coincidem.');
        $stmt=db()->prepare('SELECT password_hash FROM users WHERE id=? AND active=1');$stmt->execute([(int)$user['id']]);$hash=$stmt->fetchColumn();
        if (!is_string($hash)||!password_verify($currentPassword,$hash)) throw new InvalidArgumentException('A senha atual está incorreta.');
        $newPasswordHash=password_hash($newPassword,PASSWORD_DEFAULT);
    }

    if ($newPasswordHash===null) {
        $stmt=db()->prepare('UPDATE users SET name=? WHERE id=? AND active=1');$stmt->execute([$name,(int)$user['id']]);
    } else {
        $stmt=db()->prepare('UPDATE users SET name=?,password_hash=? WHERE id=? AND active=1');$stmt->execute([$name,$newPasswordHash,(int)$user['id']]);
    }
    if ($stmt->rowCount()===0) {
        $exists=db()->prepare('SELECT id FROM users WHERE id=? AND active=1');$exists->execute([(int)$user['id']]);
        if (!$exists->fetchColumn()) throw new RuntimeException('Sua conta não está ativa.');
    }
    $_SESSION['user']['name']=$name;
}

function app_base_url(): string
{
    $environment=(string)($_ENV['APP_ENV']??'production');
    $default=$environment==='production'?'https://listagraduacao.duckdns.org':'http://localhost:8000';
    $base=trim((string)($_ENV['APP_URL']??$default));
    $parts=parse_url($base);
    if (!filter_var($base,FILTER_VALIDATE_URL)||!is_array($parts)||!in_array($parts['scheme']??'', ['http','https'],true)||empty($parts['host'])) {
        throw new RuntimeException('APP_URL deve ser um endereço HTTP ou HTTPS válido.');
    }
    return rtrim($base,'/');
}

function invitation_attempts_blocked(string $ip): bool
{
    $stmt=db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND kind='invite' AND success=0 AND created_at>=NOW()-INTERVAL 15 MINUTE");
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn()>=5;
}

function record_invitation_attempt(string $ip, string $username, bool $success): void
{
    $stmt=db()->prepare("INSERT INTO login_attempts(ip,username,kind,success) VALUES(?,?,'invite',?)");
    $stmt->execute([substr($ip,0,45),substr($username,0,60),$success?1:0]);
}

function find_valid_invitation(string $token): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/',$token)!==1) return null;
    $stmt=db()->prepare('SELECT id,name,role,expires_at FROM invitations WHERE token_hash=? AND accepted_at IS NULL AND cancelled_at IS NULL AND expires_at>NOW()');
    $stmt->execute([hash('sha256',$token)]);
    $invite=$stmt->fetch();
    return $invite ?: null;
}

/** Creates the invited user once, logs the attempt and returns the new session user. */
function accept_invitation(string $token, string $name, string $username, string $password, string $confirmation, string $ip): array
{
    $username=trim($username);
    if (invitation_attempts_blocked($ip)) throw new RuntimeException('Limite de tentativas atingido. Tente novamente em 15 minutos.');
    $invite=find_valid_invitation($token);
    if ($invite===null) {
        record_invitation_attempt($ip,$username,false);
        throw new InvalidArgumentException('Este convite está indisponível. Peça um novo link ao Master.');
    }

    try {
        $name=trim($name);
        if ($name==='' || mb_strlen($name,'UTF-8')>100) throw new InvalidArgumentException('Informe seu nome (até 100 caracteres).');
        if ($username==='' || mb_strlen($username,'UTF-8')>60) throw new InvalidArgumentException('Informe o nome de usuário (até 60 caracteres).');
        if (strlen($password)<8) throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        if (!hash_equals($password,$confirmation)) throw new InvalidArgumentException('As senhas não coincidem.');

        $pdo=db();$pdo->beginTransaction();
        $lock=$pdo->prepare('SELECT id,role FROM invitations WHERE id=? AND token_hash=? AND accepted_at IS NULL AND cancelled_at IS NULL AND expires_at>NOW() FOR UPDATE');
        $lock->execute([(int)$invite['id'],hash('sha256',$token)]);$locked=$lock->fetch();
        if (!$locked) throw new InvalidArgumentException('Este convite está indisponível. Peça um novo link ao Master.');
        $insert=$pdo->prepare('INSERT INTO users(name,username,password_hash,role,active) VALUES(?,?,?,?,1)');
        $insert->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT),$locked['role']]);
        $userId=(int)$pdo->lastInsertId();
        $used=$pdo->prepare('UPDATE invitations SET accepted_at=NOW(),accepted_user_id=? WHERE id=? AND accepted_at IS NULL');
        $used->execute([$userId,(int)$locked['id']]);
        if ($used->rowCount()!==1) throw new RuntimeException('Este convite já foi usado.');
        $pdo->commit();
        record_invitation_attempt($ip,$username,true);
        return ['id'=>$userId,'name'=>$name,'username'=>$username,'role'=>$locked['role'],'active'=>1];
    } catch (Throwable $error) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        record_invitation_attempt($ip,$username,false);
        if ($error instanceof PDOException && (string)$error->getCode()==='23000') {
            throw new InvalidArgumentException('Esse nome de usuário já está em uso. Escolha outro.');
        }
        throw $error;
    }
}
