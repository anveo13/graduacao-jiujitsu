<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/csrf.php';
require dirname(__DIR__).'/src/auth.php';
require dirname(__DIR__).'/src/belts.php';
require dirname(__DIR__).'/src/eligibility.php';
require dirname(__DIR__).'/src/students.php';

$releases = require dirname(__DIR__).'/config/releases.php';
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

if ($page !== 'login') {
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
    <html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#17352c"><title>Entrar · Graduação Jiu-Jitsu</title><link rel="stylesheet" href="/assets/app.css"></head><body>
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
        $view = 'home';
    } else {
        http_response_code(404);
        exit('Página não encontrada.');
    }
}

?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#17352c"><title><?= $view === 'students' || $view === 'student-form' ? 'Alunos' : 'Início' ?> · Graduação Jiu-Jitsu</title><link rel="stylesheet" href="/assets/app.css"></head><body>
<header class="topbar"><a class="brand" href="/">Graduação <span>Jiu-Jitsu</span></a><nav class="main-nav" aria-label="Navegação principal"><a href="/?page=students">Alunos</a></nav><div class="account"><span><?=h($user['name'])?><small><?=h($roleNames[$user['role']]??$user['role'])?></small></span><form method="post" action="/?page=logout"><?=csrf_field()?><button class="logout" type="submit">Sair</button></form></div></header>
<main class="content">
    <?php if ($view === 'home'): ?><section class="welcome"><p class="eyebrow">PAINEL</p><h1>Olá, <?=h($user['name'])?>.</h1><p>Seu espaço para acompanhar as graduações da equipe.</p></section><section class="empty-state"><div class="belt-icon" aria-hidden="true">🥋</div><h2>Cadastro da equipe</h2><p>Consulte os alunos, confira as faixas e mantenha os cadastros em dia.</p><a class="primary-link" href="/?page=students">Ver alunos</a><p class="home-role"><span class="badge"><?= $user['role']==='viewer'?'Acesso de visualização':'Perfil com edição' ?></span></p></section>
    <?php else: ?>
        <?php if ($notice !== ''): ?><p class="success-message" role="status"><?=h($notice)?></p><?php endif; ?>
        <?php require dirname(__DIR__).'/views/'.$view.'.php'; ?>
    <?php endif; ?>
</main><footer>Versão <?=h($releases['current'])?> · Sistema de Graduação Jiu-Jitsu</footer>
</body></html>
