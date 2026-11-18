<?php declare(strict_types=1); ?>
<section class="page-heading"><div><p class="eyebrow">CONTA</p><h1>Meu perfil</h1><p class="muted">Atualize o nome que aparece nas ações do sistema ou troque sua senha.</p></div></section>
<?php if ($error!==''): ?><p class="alert" role="alert"><?=h($error)?></p><?php endif; ?>
<?php if ($notice!==''): ?><p class="success-message" role="status"><?=h($notice)?></p><?php endif; ?>
<form class="profile-form" method="post" action="/?page=profile-save">
    <?=csrf_field()?>
    <label>Nome<input name="name" value="<?=h($profileName)?>" maxlength="100" required autocomplete="name"></label>
    <div class="form-divider"><span>Trocar senha (opcional)</span></div>
    <label>Senha atual<input type="password" name="current_password" autocomplete="current-password"></label>
    <label>Nova senha<input type="password" name="new_password" minlength="8" autocomplete="new-password"></label>
    <label>Repita a nova senha<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password"></label>
    <div class="form-actions"><button class="primary" type="submit">Salvar alterações</button></div>
</form>
