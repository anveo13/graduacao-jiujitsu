<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/csrf.php';
require dirname(__DIR__).'/src/auth.php';
require dirname(__DIR__).'/src/belts.php';
require dirname(__DIR__).'/src/eligibility.php';
require dirname(__DIR__).'/src/students.php';
require dirname(__DIR__).'/src/events.php';
require dirname(__DIR__).'/src/users.php';
require dirname(__DIR__).'/src/graduations.php';

$releases = require dirname(__DIR__).'/config/releases.php';
$cssVersion = (string)(filemtime(dirname(__DIR__).'/public/assets/app.css') ?: 0);
$faviconVersion = (string)(filemtime(dirname(__DIR__).'/public/favicon.svg') ?: 0);
$page = (string) ($_GET['page'] ?? 'home');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$error = '';
$user = null;

if ($page === 'login' && $method === 'POST') {
    verify_csrf();
    if (login_user(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''))) {
        redirect('/');
    }
    $error = 'Usuário ou senha inválidos, ou limite de tentativas atingido.';
}

if (!in_array($page, ['login','accept-invite'], true)) {
    $user = require_login();
}

if ($page === 'logout') {
    if ($method !== 'POST') {
        http_response_code(405);
        exit('Método não permitido.');
    }
    verify_csrf();
    $_SESSION = [];
    session_destroy();
    redirect('/?page=login');
}

$roleNames = ['master' => 'Master', 'admin' => 'Administrador', 'viewer' => 'Visualizador'];
$canManageStudents = $user !== null && in_array($user['role'], ['master', 'admin'], true);
$today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
$timezone = new DateTimeZone('America/Sao_Paulo');
$notice = '';
$confirmationNeeded = false;
$pendingTarget = null;
$canManageEvents = $user !== null && in_array($user['role'], ['master', 'admin'], true);

if ($page === 'graduate-now') {
    if ($method!=='POST') { http_response_code(405);exit('Método não permitido.'); }
    $user=require_roles('master','admin');verify_csrf();
    $studentId=filter_var($_POST['student_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($studentId===false) { http_response_code(400);exit('Aluno inválido.'); }
    $pendingTarget=(string)($_POST['target']??'');
    try {
        $done=graduate_student_now((int)$studentId,$pendingTarget,(int)$user['id'],isset($_POST['confirm_ineligible']));
        if ($done) redirect('/?page=student&id='.$studentId.'&graduated=1');
        $confirmationNeeded=true;$notice='Este aluno ainda não está elegível. Confirme abaixo para registrar a graduação mesmo assim.';
        http_response_code(422);$page='student';$_GET['id']=(string)$studentId;
    } catch (InvalidArgumentException|RuntimeException $exception) { http_response_code(422);$error=$exception->getMessage();$page='student';$_GET['id']=(string)$studentId; }
}

if ($page === 'graduation-undo-avulsa') {
    if ($method!=='POST') { http_response_code(405);exit('Método não permitido.'); }
    $user=require_login();verify_csrf();
    $studentId=filter_var($_POST['student_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $graduationId=filter_var($_POST['graduation_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($studentId===false||$graduationId===false) { http_response_code(400);exit('Aluno ou graduação inválido.'); }
    try { undo_avulsa_graduation((int)$studentId,(int)$graduationId,$user);redirect('/?page=student&id='.$studentId.'&undone=1'); }
    catch (RuntimeException $exception) { http_response_code(422);$error=$exception->getMessage();$page='student';$_GET['id']=(string)$studentId; }
}

if ($page === 'accept-invite') {
    $inviteToken=(string)($_POST['token']??$_GET['token']??'');
    $acceptInvite=find_valid_invitation($inviteToken);
    if ($method==='POST') {
        verify_csrf();
        try {
            $newUser=accept_invitation($inviteToken,(string)($_POST['name']??''),(string)($_POST['username']??''),(string)($_POST['password']??''),(string)($_POST['password_confirmation']??''),substr((string)($_SERVER['REMOTE_ADDR']??'local'),0,45));
            session_regenerate_id(true);$_SESSION['user']=$newUser;redirect('/');
        } catch (InvalidArgumentException|RuntimeException $exception) { http_response_code(422);$error=$exception->getMessage();$acceptInvite=find_valid_invitation($inviteToken); }
    } elseif ($method!=='GET') { http_response_code(405);exit('Método não permitido.'); }
    require dirname(__DIR__).'/views/accept-invite.php';
    exit;
}

if ($page === 'invite-create') {
    if ($method!=='POST') { http_response_code(405);exit('Método não permitido.'); }
    $user=require_roles('master');verify_csrf();
    try { $_SESSION['new_invitation_link']=create_invitation((string)($_POST['name']??''),(string)($_POST['role']??''),(int)$user['id']);redirect('/?page=users&invite-created=1'); }
    catch (InvalidArgumentException $exception) { http_response_code(422);$error=$exception->getMessage();$page='users'; }
}

if ($page === 'invite-cancel') {
    if ($method!=='POST') { http_response_code(405);exit('Método não permitido.'); }
    $user=require_roles('master');verify_csrf();
    $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($id===false) { http_response_code(400);exit('Convite inválido.'); }
    try { cancel_invitation((int)$id);redirect('/?page=users&invite-cancelled=1'); }
    catch (RuntimeException $exception) { http_response_code(422);$error=$exception->getMessage();$page='users'; }
}

if ($page === 'user-role' || $page === 'user-active') {
    if ($method!=='POST') { http_response_code(405);exit('Método não permitido.'); }
    $user=require_roles('master');verify_csrf();
    $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($id===false) { http_response_code(400);exit('Usuário inválido.'); }
    try {
        if ($page==='user-role') update_user_role((int)$id,(string)($_POST['role']??''));
        else set_user_active((int)$id,($_POST['active']??'')==='1');
        redirect('/?page=users&user-updated=1');
    } catch (InvalidArgumentException|RuntimeException $exception) { http_response_code(422);$error=$exception->getMessage();$page='users'; }
}

if ($page === 'profile-save') {
    if ($method!=='POST') { http_response_code(405);exit('Método não permitido.'); }
    $user=require_login();verify_csrf();
    try {
        update_own_profile($user,(string)($_POST['name']??''),(string)($_POST['current_password']??''),(string)($_POST['new_password']??''),(string)($_POST['password_confirmation']??''));
        redirect('/?page=profile&saved=1');
    } catch (InvalidArgumentException|RuntimeException $exception) { http_response_code(422);$error=$exception->getMessage();$page='profile';$profileName=trim((string)($_POST['name']??'')); }
}

if ($page === 'event-create') {
    if ($method !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
    $user = require_roles('master', 'admin'); verify_csrf();
    try {
        $eventId = create_event((string)($_POST['name'] ?? ''), (string)($_POST['event_date'] ?? ''), (int)$user['id']);
        redirect('/?page=event&id='.$eventId.'&created=1');
    } catch (InvalidArgumentException $exception) { http_response_code(422); $error = $exception->getMessage(); $page = 'events'; }
}

if ($page === 'event-add-student') {
    if ($method !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
    $user = require_roles('master', 'admin'); verify_csrf();
    $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($eventId === false || $studentId === false) { http_response_code(400); exit('Evento ou aluno inválido.'); }
    try { add_student_to_event((int)$eventId,(int)$studentId,(string)($_POST['target']??''),(int)$user['id']); redirect('/?page=event&id='.$eventId.'&added=1'); }
    catch (InvalidArgumentException|RuntimeException $exception) { http_response_code(422); $error=$exception->getMessage(); $page='event'; $_GET['id']=(string)$eventId; }
}

if ($page === 'event-hide' || $page === 'event-show') {
    if ($method !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
    $user = require_roles('master', 'admin'); verify_csrf();
    $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $graduationId = filter_var($_POST['graduation_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($eventId === false || $graduationId === false) { http_response_code(400); exit('Evento ou graduação inválido.'); }
    try { set_event_graduation_hidden((int)$eventId,(int)$graduationId,$page==='event-hide',(int)$user['id']); redirect('/?page=event&id='.$eventId.'&visibility=1'); }
    catch (RuntimeException $exception) { http_response_code(422); $error=$exception->getMessage(); $page='event'; $_GET['id']=(string)$eventId; }
}

if ($page === 'event-check') {
    if ($method !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
    verify_csrf(); $user = require_login();
    $eventId=filter_var($_POST['event_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $graduationId=filter_var($_POST['graduation_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($eventId===false||$graduationId===false) { http_response_code(400); exit('Evento ou graduação inválido.'); }
    try {
        $done=check_event_graduation((int)$eventId,(int)$graduationId,(int)$user['id'],isset($_POST['confirm_ineligible']));
        if ($done) redirect('/?page=event&id='.$eventId.'&checked=1');
        $confirmationNeeded=(int)$graduationId;
        $notice='Este aluno ainda não está elegível na data do evento. Confirme no cartão para registrar mesmo assim.';
        http_response_code(422); $page='event'; $_GET['id']=(string)$eventId;
    } catch (RuntimeException $exception) { http_response_code(422); $error=$exception->getMessage(); $page='event'; $_GET['id']=(string)$eventId; }
}

if ($page === 'event-undo') {
    if ($method !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
    verify_csrf(); $user=require_login();
    $eventId=filter_var($_POST['event_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $graduationId=filter_var($_POST['graduation_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($eventId===false||$graduationId===false) { http_response_code(400); exit('Evento ou graduação inválido.'); }
    try { undo_event_graduation((int)$eventId,(int)$graduationId,$user); redirect('/?page=event&id='.$eventId.'&undone=1'); }
    catch (RuntimeException $exception) { http_response_code(422); $error=$exception->getMessage(); $page='event'; $_GET['id']=(string)$eventId; }
}

if ($page === 'student-save') {
    if ($method !== 'POST') {
        http_response_code(405);
        exit('Método não permitido.');
    }
    $user = require_roles('master', 'admin');
    verify_csrf();

    $rawId = trim((string) ($_POST['id'] ?? ''));
    $id = $rawId === '' ? null : filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $name = (string) ($_POST['name'] ?? '');
    $belt = (string) ($_POST['belt'] ?? '');
    $rawDegrees = (string) ($_POST['degrees'] ?? '');
    $degrees = preg_match('/^\d+$/', $rawDegrees) === 1 ? (int) $rawDegrees : -1;

    try {
        if ($rawId !== '' && $id === false) {
            throw new InvalidArgumentException('Aluno inválido. Atualize a página e tente novamente.');
        }
        save_student($id, $name, $belt, $degrees, (int) $user['id']);
        redirect('/?page=students&saved=1');
    } catch (InvalidArgumentException|RuntimeException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
        $student = $id !== null && $id !== false ? find_active_student((int) $id) : null;
        if ($id !== null && $student === null) {
            http_response_code(404);
            exit('Aluno não encontrado.');
        }
        $formValues = ['name' => $name, 'belt' => $belt, 'degrees' => $rawDegrees];
        $view = 'student-form';
    }
}

if ($page === 'student-deactivate') {
    if ($method !== 'POST') {
        http_response_code(405);
        exit('Método não permitido.');
    }
    $user = require_roles('master', 'admin');
    verify_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        http_response_code(400);
        exit('Aluno inválido.');
    }
    try {
        deactivate_student((int) $id, (int) $user['id']);
        redirect('/?page=students&deactivated=1');
    } catch (RuntimeException $exception) {
        http_response_code(404);
        exit('Aluno não encontrado ou já desativado.');
    }
}

if ($page === 'login') {
    ?><!doctype html>
    <html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#c8102e"><link rel="icon" type="image/svg+xml" href="/favicon.svg?v=<?=rawurlencode($faviconVersion)?>"><title>Entrar · Graduação Jiu-Jitsu</title><link rel="stylesheet" href="/assets/app.css?v=<?=rawurlencode($cssVersion)?>"></head><body>
    <main class="login-card"><div class="brand-mark">GJ</div><p class="eyebrow">CONTROLE DE GRADUAÇÕES</p><h1>Entrar</h1><p class="muted">Acesse sua conta para continuar.</p><?php if ($error !== ''): ?><p class="alert" role="alert"><?=h($error)?></p><?php endif; ?><form method="post" action="/?page=login"><?=csrf_field()?><label>Usuário<input name="username" autocomplete="username" maxlength="60" required autofocus></label><label>Senha<input type="password" name="password" autocomplete="current-password" required></label><button class="primary" type="submit">Entrar</button></form></main>
    </body></html><?php
    exit;
}

if (!isset($view)) {
    if ($page === 'students') {
        if ($method !== 'GET') {
            http_response_code(405);
            exit('Método não permitido.');
        }
        $search = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($search, 'UTF-8') > 150) {
            $search = mb_substr($search, 0, 150, 'UTF-8');
        }
        $students = list_active_students($search);
        $notice = isset($_GET['saved']) ? 'Aluno salvo.' : (isset($_GET['deactivated']) ? 'Aluno desativado. O histórico foi mantido.' : '');
        $view = 'students';
    } elseif ($page === 'events') {
        $events = list_events();
        $view = 'events';
    } elseif ($page === 'event') {
        $eventId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($eventId === false) { http_response_code(400); exit('Evento inválido.'); }
        $event = find_event((int)$eventId);
        if ($event === null) { http_response_code(404); exit('Evento não encontrado.'); }
        $notice = $notice ?: (isset($_GET['created']) ? 'Evento criado.' : (isset($_GET['added']) ? 'Aluno adicionado à lista.' : (isset($_GET['checked']) ? 'Graduação registrada.' : (isset($_GET['undone']) ? 'Check desfeito; aluno voltou para a lista pendente.' : (isset($_GET['visibility']) ? 'Lista atualizada.' : '')))));
        $showHidden = isset($_GET['hidden']);
        $graduations = list_event_graduations((int)$eventId, $showHidden);
        $progress = event_progress((int)$eventId);
        $availableStudents = $canManageEvents ? list_students_available_for_event((int)$eventId) : [];
        $confirmationNeeded = $confirmationNeeded ?? null;
        $view = 'event';
    } elseif ($page === 'student') {
        $studentId=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($studentId===false) { http_response_code(400);exit('Aluno inválido.'); }
        $student=find_student((int)$studentId);
        if ($student===null) { http_response_code(404);exit('Aluno não encontrado.'); }
        $history=list_student_graduations((int)$studentId);
        $canGraduate=$canManageStudents&&(int)$student['active']===1;
        $notice=$notice?: (isset($_GET['graduated'])?'Graduação avulsa registrada.':(isset($_GET['undone'])?'Graduação avulsa desfeita. Faixa e graus anteriores restaurados.':''));
        $view='student';
    } elseif ($page === 'users') {
        require_roles('master');
        $users=list_users();$invitations=list_invitations();
        $newInvitationLink=(string)($_SESSION['new_invitation_link']??'');unset($_SESSION['new_invitation_link']);
        $notice=isset($_GET['invite-created'])?'Convite criado; copie o link e envie para a pessoa.':(isset($_GET['invite-cancelled'])?'Convite cancelado.':(isset($_GET['user-updated'])?'Usuário atualizado.':''));
        $view='users';
    } elseif ($page === 'profile') {
        $profileName=$profileName??$user['name'];
        $notice=isset($_GET['saved'])?'Perfil atualizado.':'';
        $view='profile';
    } elseif ($page === 'student-new') {
        require_roles('master', 'admin');
        $student = null;
        $formValues = [];
        $formError = '';
        $view = 'student-form';
    } elseif ($page === 'student-edit') {
        require_roles('master', 'admin');
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            http_response_code(400);
            exit('Aluno inválido.');
        }
        $student = find_active_student((int) $id);
        if ($student === null) {
            http_response_code(404);
            exit('Aluno não encontrado.');
        }
        $formValues = [];
        $formError = '';
        $view = 'student-form';
    } elseif ($page === 'home') {
        $dashboardStudents=list_dashboard_students();
        $nextEvent=find_next_event($today->format('Y-m-d'));
        $view = 'home';
    } else {
        http_response_code(404);
        exit('Página não encontrada.');
    }
}

$activeNav = match (true) {
    in_array($view, ['students', 'student-form', 'student'], true) => 'students',
    in_array($view, ['events', 'event'], true) => 'events',
    $view === 'users' => 'users',
    $view === 'profile' => 'profile',
    default => 'home',
};
// Ícones em SVG (traço), herdam a cor do link.
$navItems = [
    'home' => ['/', 'Início', '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>'],
    'students' => ['/?page=students', 'Alunos', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-6 6.5-6s5.9 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .7 3.2 3 3.5 6"/>'],
    'events' => ['/?page=events', 'Graduações', '<circle cx="12" cy="9" r="5"/><path d="m8.5 13-2 8 5.5-3 5.5 3-2-8"/>'],
];
if ($user['role'] === 'master') {
    $navItems['users'] = ['/?page=users', 'Usuários', '<path d="M12 3 4 6v6c0 4.5 3.4 8.2 8 9 4.6-.8 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>'];
}
$navItems['profile'] = ['/?page=profile', 'Perfil', '<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4 4-6.5 8-6.5s7.2 2.5 8 6.5"/>'];
$pageTitles = ['home' => 'Início', 'students' => 'Alunos', 'events' => 'Graduações', 'users' => 'Usuários', 'profile' => 'Meu perfil'];
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#171717"><link rel="icon" type="image/svg+xml" href="/favicon.svg?v=<?=rawurlencode($faviconVersion)?>"><title><?=h($pageTitles[$activeNav])?> · Graduação Jiu-Jitsu</title><link rel="stylesheet" href="/assets/app.css?v=<?=rawurlencode($cssVersion)?>"></head><body>
<header class="topbar">
    <a class="brand" href="/"><span class="brand-badge" aria-hidden="true">GJ</span><span class="brand-text">Graduação <span>Jiu-Jitsu</span></span></a>
    <nav class="main-nav" aria-label="Navegação principal">
        <?php foreach ($navItems as $key => [$href, $label, $icon]): ?><a class="<?=$key==='profile'?'nav-profile':''?><?=$key===$activeNav?' is-active':''?>" href="<?=h($href)?>"<?=$key===$activeNav?' aria-current="page"':''?>><svg viewBox="0 0 24 24" aria-hidden="true"><?=$icon?></svg><span><?=h($label)?></span></a><?php endforeach; ?>
    </nav>
    <div class="account"><a class="account-link" href="/?page=profile" title="Meu perfil"><strong><?=h($user['name'])?></strong><small><?=h($roleNames[$user['role']]??$user['role'])?></small></a><form method="post" action="/?page=logout"><?=csrf_field()?><button class="logout" type="submit">Sair</button></form></div>
</header>
<main class="content">
    <?php if ($notice !== ''): ?><p class="success-message" role="status"><?=h($notice)?></p><?php endif; ?>
    <?php require dirname(__DIR__).'/views/'.$view.'.php'; ?>
</main><footer>Versão <?=h($releases['current'])?> · Sistema de Graduação Jiu-Jitsu</footer>
</body></html>
