<?php
declare(strict_types=1);
$roleLabels=['master'=>'Master','admin'=>'Administrador','viewer'=>'Visualizador'];
$inviteLink=$newInvitationLink;
$whatsappUrl=$inviteLink!==''?'https://wa.me/?text='.rawurlencode("Você foi convidado para o sistema Graduação Jiu-Jitsu. Crie sua conta por este link (válido por 7 dias):\n".$inviteLink):'';
?>
<section class="page-heading"><div><p class="eyebrow">ACESSO</p><h1>Usuários</h1><p class="muted">Contas da equipe, perfis e convites.</p></div></section>
<?php if ($error!==''): ?><p class="alert" role="alert"><?=h($error)?></p><?php endif; ?>
<?php if ($notice!==''): ?><p class="success-message" role="status"><?=h($notice)?></p><?php endif; ?>
<?php if ($inviteLink!==''): ?><section class="invite-result"><p class="eyebrow">LINK DE CONVITE · VÁLIDO POR 7 DIAS</p><label>Copie e envie para a pessoa<input id="invite-link" readonly value="<?=h($inviteLink)?>"></label><div class="invite-actions"><button class="secondary" id="copy-invite" type="button">Copiar link</button><a class="primary-link" href="<?=h($whatsappUrl)?>" target="_blank" rel="noopener">Enviar pelo WhatsApp</a></div></section><?php endif; ?>

<section class="user-section"><h2>Criar convite</h2><form class="invite-form" method="post" action="/?page=invite-create">
    <?=csrf_field()?>
    <label>Nome da pessoa<input name="name" maxlength="100" autocomplete="name" required></label>
    <label>Perfil<select name="role" required><option value="viewer">Visualizador</option><option value="admin">Administrador</option></select></label>
    <button class="primary" type="submit">Gerar convite</button>
</form></section>

<section class="user-section"><h2>Contas <span class="count-pill"><?=count($users)?></span></h2><div class="account-list">
    <?php foreach ($users as $account): ?>
        <article class="account-card <?=$account['active']?'':'account-disabled'?>">
            <div class="account-copy"><strong><?=h($account['name'])?></strong><span><?=h($account['username'])?> · <?=h($roleLabels[$account['role']]??$account['role'])?></span><small><?=$account['active']?'Ativo':'Desativado'?></small></div>
            <?php if ($account['role']!=='master'): ?><div class="account-actions">
                <form method="post" action="/?page=user-role" class="role-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$account['id']?>"><label class="sr-only" for="role-<?=(int)$account['id']?>">Perfil de <?=h($account['name'])?></label><select id="role-<?=(int)$account['id']?>" name="role"><option value="admin" <?=$account['role']==='admin'?'selected':''?>>Administrador</option><option value="viewer" <?=$account['role']==='viewer'?'selected':''?>>Visualizador</option></select><button class="text-button" type="submit">Salvar perfil</button></form>
                <form method="post" action="/?page=user-active"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$account['id']?>"><input type="hidden" name="active" value="<?=$account['active']?'0':'1'?>"><button class="text-button <?=$account['active']?'danger':''?>" type="submit"><?=$account['active']?'Desativar':'Reativar'?></button></form>
            </div><?php else: ?><span class="master-lock">Conta protegida</span><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div></section>

<section class="user-section"><h2>Convites pendentes <span class="count-pill"><?=count($invitations)?></span></h2>
    <?php if ($invitations===[]): ?><p class="form-hint">Nenhum convite pendente.</p><?php else: ?><div class="account-list">
        <?php foreach ($invitations as $invite): ?><article class="account-card"><div class="account-copy"><strong><?=h($invite['name'])?></strong><span><?=h($roleLabels[$invite['role']]??$invite['role'])?> · criado por <?=h($invite['created_by_name'])?></span><small class="<?=$invite['status']==='expired'?'invite-expired':''?>"><?=$invite['status']==='expired'?'Expirado':'Expira em '.h((new DateTimeImmutable($invite['expires_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i'))?></small></div><form method="post" action="/?page=invite-cancel"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$invite['id']?>"><button class="text-button danger" type="submit">Cancelar convite</button></form></article><?php endforeach; ?>
    </div><?php endif; ?>
</section>
<?php if ($inviteLink!==''): ?><script>document.getElementById('copy-invite').addEventListener('click',async()=>{const input=document.getElementById('invite-link');try{await navigator.clipboard.writeText(input.value);document.getElementById('copy-invite').textContent='Link copiado';}catch{input.select();document.execCommand('copy');document.getElementById('copy-invite').textContent='Link copiado';}});</script><?php endif; ?>
