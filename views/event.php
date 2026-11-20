<?php
declare(strict_types=1);
$eventDate = new DateTimeImmutable($event['event_date'], new DateTimeZone('America/Sao_Paulo'));
$pendingCount = $progress['planned'];
$doneCount = $progress['done'];
$eventId = (int)$event['id'];
$hiddenFields = static fn(array $row): string => csrf_field().'<input type="hidden" name="event_id" value="'.(int)$row['event_id'].'"><input type="hidden" name="graduation_id" value="'.(int)$row['id'].'">';
// Valor do <select> que corresponde ao que está planejado ("degree:2" ou "belt:blue").
$plannedTarget = static fn(array $row): string => $row['to_belt'] === $row['from_belt'] ? 'degree:'.((int)$row['to_degrees'] - (int)$row['from_degrees']) : 'belt:'.$row['to_belt'];
$shareText = $doneCount > 0 ? event_share_text($event, $graduations) : '';
?>
<a class="back-link" href="/?page=events">‹ Eventos</a>
<section class="page-heading">
    <div><p class="eyebrow">EVENTO · <?=h($eventDate->format('d/m/Y'))?></p><h1><?=h($event['name'])?></h1><p class="muted"><?=$doneCount?> de <?=$doneCount+$pendingCount?> graduados<?= $showHidden ? ' · mostrando escondidos' : '' ?></p></div>
</section>
<?php if ($error !== ''): ?><p class="alert" role="alert"><?=h($error)?></p><?php endif; ?>

<div class="event-actions">
    <a class="secondary-link" href="/?page=report&amp;kind=event&amp;id=<?=$eventId?>">Gerar relatório</a>
    <?php if ($canManageEvents && $availableStudents !== []): ?><button class="secondary" type="button" data-open="event-add-panel">+ Adicionar alunos</button><?php endif; ?>
    <?php if ($shareText !== ''): ?><a class="secondary-link" href="https://wa.me/?text=<?=rawurlencode($shareText)?>" target="_blank" rel="noopener">Compartilhar no WhatsApp</a><button class="secondary" type="button" id="copy-share" data-text="<?=h($shareText)?>">Copiar resultado</button><?php endif; ?>
    <?php if ($canManageEvents): ?><button class="text-button" type="button" data-open="event-edit-panel">Editar evento</button><?php endif; ?>
</div>

<?php if ($canManageEvents): ?>
<section class="event-panel" id="event-edit-panel" hidden>
    <form class="event-edit-form" method="post" action="/?page=event-update">
        <?=csrf_field()?><input type="hidden" name="event_id" value="<?=$eventId?>">
        <label>Nome do evento<input name="name" value="<?=h($event['name'])?>" maxlength="150" required></label>
        <label>Data<input type="date" name="event_date" value="<?=h($event['event_date'])?>" required<?=$doneCount>0?' readonly':''?>></label>
        <button class="primary" type="submit">Salvar</button>
    </form>
    <?php if ($doneCount > 0): ?><p class="form-hint">A data não muda porque o evento já tem graduações registradas.</p>
    <?php else: ?><form method="post" action="/?page=event-delete" data-confirm="Apagar o evento &quot;<?=h($event['name'])?>&quot; e a lista planejada? Não dá para desfazer."><?=csrf_field()?><input type="hidden" name="event_id" value="<?=$eventId?>"><button class="text-button danger" type="submit">Apagar evento</button></form><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($canManageEvents && $availableStudents !== []): ?>
<section class="event-panel" id="event-add-panel"<?=isset($_GET['added'])||$error!==''?'':' hidden'?>>
    <form method="post" action="/?page=event-add-students" class="bulk-add-form">
        <?=csrf_field()?><input type="hidden" name="event_id" value="<?=$eventId?>">
        <div class="bulk-add-head"><h2>Adicionar alunos</h2><button class="text-button" type="button" id="bulk-toggle-all">Marcar todos</button></div>
        <p class="form-hint">Marque quem vai graduar. A graduação já vem sugerida; troque se quiser.</p>
        <?php foreach (belt_names() as $beltCode => $beltName): $beltStudents=array_values(array_filter($availableStudents,static fn(array $s):bool=>$s['belt']===$beltCode)); if ($beltStudents===[]) continue; ?>
            <h3 class="bulk-belt"><?=belt_bar_html($beltCode)?><?=h($beltName)?></h3>
            <?php foreach ($beltStudents as $student): $options=graduation_target_options($student['belt'],(int)$student['degrees']); if ($options===[]) continue; $suggested=suggested_graduation_target($student['belt'],(int)$student['degrees']); ?>
                <div class="bulk-row">
                    <label class="bulk-check"><input type="checkbox" name="students[]" value="<?=(int)$student['id']?>"><span><strong><?=h($student['name'])?></strong><small><?=belt_rank_html($student['belt'],(int)$student['degrees'])?></small></span></label>
                    <select name="target[<?=(int)$student['id']?>]" aria-label="Graduação de <?=h($student['name'])?>"><?php if ($suggested===null): ?><option value="">Escolha</option><?php endif; ?><?php foreach ($options as $option): ?><option value="<?=h($option['value'])?>"<?=$option['value']===$suggested?' selected':''?>><?=h($option['label'])?></option><?php endforeach; ?></select>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <div class="form-actions"><button class="primary" type="submit" id="bulk-submit" disabled>Adicionar marcados</button></div>
    </form>
</section>
<?php elseif ($canManageEvents): ?>
<p class="form-hint event-all-added">Todos os alunos ativos já estão na lista deste evento.</p>
<?php endif; ?>

<div class="event-toolbar">
    <?php if ($showHidden): ?><a class="text-link" href="/?page=event&id=<?=$eventId?>">Ocultar faltantes</a>
    <?php else: ?><a class="text-link" href="/?page=event&id=<?=$eventId?>&hidden=1">Mostrar escondidos</a><?php endif; ?>
</div>

<?php if ($graduations !== []): ?>
<div class="event-search">
    <label for="event-search-input">Buscar aluno neste evento</label>
    <input id="event-search-input" type="search" placeholder="Digite o nome do aluno" autocomplete="off" aria-describedby="event-search-status">
    <span id="event-search-status" role="status" aria-live="polite"><?=count($graduations)?> alunos na lista</span>
</div>
<p class="empty-state compact event-search-empty" id="event-search-empty" hidden>Nenhum aluno encontrado com esse nome.</p>
<?php endif; ?>

<?php if ($graduations === []): ?>
<section class="empty-state compact"><div class="belt-icon" aria-hidden="true">🥋</div><h2>Lista vazia</h2><p>Adicione alunos para planejar as graduações deste evento.</p></section>
<?php else: ?>
<?php foreach (belt_names() as $beltCode=>$beltName): ?>
    <?php $rows=array_values(array_filter($graduations,static fn(array $row):bool=>$row['from_belt']===$beltCode && $row['status']==='planned')); if ($rows===[]) continue; ?>
    <section class="belt-group" data-event-group><h2><?=belt_bar_html($beltCode)?><?=h($beltName)?> <span><?=count($rows)?></span></h2><div class="graduation-list">
        <?php foreach ($rows as $row): $row['event_id']=$eventId; ?>
            <?php
            $eligibility=student_eligibility($row['current_belt'],(int)$row['current_degrees'],$row['last_graduation_at'],$eventDate);
            $stale=$row['from_belt']!==$row['current_belt']||(int)$row['from_degrees']!==(int)$row['current_degrees'];
            ?>
            <article class="graduation-card <?=$row['hidden']?'is-hidden':''?>" data-event-card data-student-name="<?=h($row['student_name'])?>">
                <div class="graduation-person"><h3><a class="student-name-link" href="/?page=student&id=<?=(int)$row['student_id']?>&evento=<?=$eventId?>"><?=h($row['student_name'])?></a></h3><p><?=belt_rank_html($row['from_belt'],(int)$row['from_degrees'])?> <span aria-hidden="true">→</span> <strong><?=belt_rank_html($row['to_belt'],(int)$row['to_degrees'])?></strong></p>
                    <?php if ($stale): ?><small class="eligibility waiting">O aluno mudou para <?=h(belt_rank($row['current_belt'],(int)$row['current_degrees']))?> depois deste plano. Use "Alterar" para revisar.</small>
                    <?php elseif (!$eligibility['eligible'] && $eligibility['eligible_at']!==null): ?><small class="eligibility waiting">Ainda não elegível; a partir de <?=h((new DateTimeImmutable($eligibility['eligible_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?></small><?php endif; ?>
                    <?php if ($canManageEvents): $options=graduation_target_options($row['current_belt'],(int)$row['current_degrees']); $selected=$stale?suggested_graduation_target($row['current_belt'],(int)$row['current_degrees']):$plannedTarget($row); ?>
                    <details class="row-edit"<?=$stale?' open':''?>><summary>Alterar</summary>
                        <?php if ($options!==[]): ?><form method="post" action="/?page=event-target" class="row-edit-form"><?=$hiddenFields($row)?><label class="sr-only" for="target-<?=(int)$row['id']?>">Nova graduação de <?=h($row['student_name'])?></label><select id="target-<?=(int)$row['id']?>" name="target"><?php foreach ($options as $option): ?><option value="<?=h($option['value'])?>"<?=$option['value']===$selected?' selected':''?>><?=h($option['label'])?></option><?php endforeach; ?></select><button class="secondary" type="submit">Salvar</button></form><?php endif; ?>
                        <form method="post" action="/?page=event-remove" data-confirm="Tirar <?=h($row['student_name'])?> da lista deste evento?"><?=$hiddenFields($row)?><button class="text-button danger" type="submit">Tirar da lista</button></form>
                    </details>
                    <?php endif; ?>
                </div>
                <div class="graduation-actions">
                    <form method="post" action="/?page=event-check" class="check-form">
                        <?=$hiddenFields($row)?>
                        <?php if ($confirmationNeeded===(int)$row['id']): ?><label class="confirm-ineligible"><input type="checkbox" name="confirm_ineligible" value="1" required> Confirmo graduar antes do prazo</label><?php endif; ?>
                        <button class="check-button" type="submit"><?=$confirmationNeeded===(int)$row['id']?'Confirmar check':'Dar check'?></button>
                    </form>
                    <?php if ($canManageEvents): ?><form method="post" action="/?page=<?=$row['hidden']?'event-show':'event-hide'?>"><?=$hiddenFields($row)?><button class="text-button" type="submit"><?=$row['hidden']?'Mostrar':'Esconder'?></button></form><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div></section>
<?php endforeach; ?>
<?php $doneRows=array_values(array_filter($graduations,static fn(array $row):bool=>$row['status']==='done')); if ($doneRows!==[]): ?>
<section class="belt-group done-group" data-event-group><h2>Já graduados <span><?=count($doneRows)?></span></h2><div class="graduation-list">
    <?php foreach ($doneRows as $row): $row['event_id']=$eventId; $undoAllowed=$user['role']!=='viewer'||(int)$row['graduated_by_id']===(int)$user['id']; ?>
        <article class="graduation-card is-done" data-event-card data-student-name="<?=h($row['student_name'])?>"><div class="graduation-person"><h3><a class="student-name-link" href="/?page=student&id=<?=(int)$row['student_id']?>&evento=<?=$eventId?>"><?=h($row['student_name'])?></a></h3><p><?=belt_rank_html($row['from_belt'],(int)$row['from_degrees'])?> <span aria-hidden="true">→</span> <strong><?=belt_rank_html($row['to_belt'],(int)$row['to_degrees'])?></strong></p><small class="graduation-by">Graduado por <?=h($row['graduated_by_name']??'usuário')?></small></div>
            <?php if ($undoAllowed): ?><div class="graduation-actions"><form method="post" action="/?page=event-undo" data-confirm="Desfazer o check de <?=h($row['student_name'])?>? O aluno volta para a lista pendente."><?=$hiddenFields($row)?><button class="text-button danger" type="submit">Desfazer</button></form></div><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div></section>
<?php endif; ?>
<?php endif; ?>
<script>
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();if(window.confirm(form.dataset.confirm||'Confirmar ação?'))form.submit();}));
// Botões que abrem/fecham os painéis (adicionar alunos, editar evento).
document.querySelectorAll('[data-open]').forEach(button=>button.addEventListener('click',()=>{const panel=document.getElementById(button.dataset.open);panel.hidden=!panel.hidden;if(!panel.hidden)panel.scrollIntoView({behavior:'smooth',block:'nearest'});}));
const shareButton=document.getElementById('copy-share');
if(shareButton)shareButton.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(shareButton.dataset.text);shareButton.textContent='Resultado copiado';}catch{window.prompt('Copie o texto:',shareButton.dataset.text);}});
const eventSearch=document.getElementById('event-search-input');
if(eventSearch){
    const cards=[...document.querySelectorAll('[data-event-card]')];const groups=[...document.querySelectorAll('[data-event-group]')];const empty=document.getElementById('event-search-empty');const status=document.getElementById('event-search-status');
    const normalize=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('pt-BR').trim();
    eventSearch.addEventListener('input',()=>{const query=normalize(eventSearch.value);let matches=0;cards.forEach(card=>{const match=normalize(card.dataset.studentName||'').includes(query);card.hidden=!match;if(match)matches++;});groups.forEach(group=>{group.hidden=!group.querySelector('[data-event-card]:not([hidden])');});empty.hidden=matches!==0;status.textContent=query?`${matches} ${matches===1?'aluno encontrado':'alunos encontrados'}`:`${cards.length} alunos na lista`;});
}
const bulkForm=document.querySelector('.bulk-add-form');
if(bulkForm){
    const boxes=[...bulkForm.querySelectorAll('input[name="students[]"]')];const submit=document.getElementById('bulk-submit');const toggle=document.getElementById('bulk-toggle-all');
    const refresh=()=>{const count=boxes.filter(box=>box.checked).length;submit.disabled=count===0;submit.textContent=count===0?'Adicionar marcados':(count===1?'Adicionar 1 aluno':'Adicionar '+count+' alunos');toggle.textContent=count===boxes.length?'Desmarcar todos':'Marcar todos';};
    boxes.forEach(box=>box.addEventListener('change',refresh));
    // Mexeu na graduação de alguém: marca a pessoa.
    bulkForm.querySelectorAll('.bulk-row select').forEach(select=>select.addEventListener('change',()=>{select.closest('.bulk-row').querySelector('input[type="checkbox"]').checked=true;refresh();}));
    toggle.addEventListener('click',()=>{const all=boxes.every(box=>box.checked);boxes.forEach(box=>box.checked=!all);refresh();});
    refresh();
}
</script>
