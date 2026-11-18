<?php
declare(strict_types=1);
$roleLabel=($acceptInvite['role']??'')==='admin'?'Administrador':'Visualizador';
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#17352c"><title>Aceitar convite · Graduação Jiu-Jitsu</title><link rel="stylesheet" href="/assets/app.css"></head><body>
<main class="login-card invite-card"><a class="brand-mark" href="/" aria-label="Graduação Jiu-Jitsu">GJ</a><p class="eyebrow">CONVITE PARA A EQUIPE</p>
<?php if ($acceptInvite===null): ?><h1>Convite indisponível</h1><p class="muted">Peça um novo link ao Master do sistema.</p>
<?php else: ?><h1>Crie sua conta</h1><p class="muted">Convite para <?=h($roleLabel)?> destinado a <?=h($acceptInvite['name'])?>. Preencha seus dados para entrar.</p>
    <?php if ($error!==''): ?><p class="alert" role="alert"><?=h($error)?></p><?php endif; ?>
    <form method="post" action="/?page=accept-invite" class="accept-form">
        <?=csrf_field()?> <input type="hidden" name="token" value="<?=h($inviteToken)?>">
        <label>Seu nome<input name="name" value="<?=h((string)($_POST['name']??$acceptInvite['name']))?>" maxlength="100" autocomplete="name" required autofocus></label>
        <label>Nome de usuário<input name="username" value="<?=h((string)($_POST['username']??''))?>" maxlength="60" autocomplete="username" required></label>
        <label>Senha<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
        <label>Repita a senha<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
        <button class="primary" type="submit">Aceitar convite</button>
    </form>
<?php endif; ?></main></body></html>
