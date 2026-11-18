<?php
declare(strict_types=1);
$eventDate = new DateTimeImmutable($event['event_date'], new DateTimeZone('America/Sao_Paulo'));
$pendingCount = $progress['planned'];
$doneCount = $progress['done'];
?>
<a class="back-link" href="/?page=events">‹ Eventos</a>
<section class="page-heading">
    <div><p class="eyebrow">EVENTO · <?=h($eventDate->format('d/m/Y'))?></p><h1><?=h($event['name'])?></h1><p class="muted"><?=$doneCount?> de <?=$doneCount+$pendingCount?> graduados<?= $showHidden ? ' · mostrando escondidos' : '' ?></p></div>
</section>
<?php if ($error !== ''): ?><p class="alert" role="alert"><?=h($error)?></p><?php endif; ?>

<?php if ($canManageEvents && $availableStudents !== []): ?>
<?php // Fechado por padrão para a lista vir primeiro; fica aberto logo depois de adicionar, para adicionar o próximo. ?>
<details class="event-add"<?=isset($_GET['added'])||$error!==''?' open':''?>>
<summary class="secondary">+ Adicionar aluno</summary>
<form class="event-add-form" method="post" action="/?page=event-add-student">
    <?=csrf_field()?> <input type="hidden" name="event_id" value="<?=(int)$event['id']?>">
    <label>Aluno<select name="student_id" id="event-student" required><option value="">Selecione um aluno</option>
        <?php foreach ($availableStudents as $student): ?><option value="<?=(int)$student['id']?>" data-belt="<?=h($student['belt'])?>" data-degrees="<?=(int)$student['degrees']?>"><?=h($student['name'])?> · <?=h(belt_rank($student['belt'],(int)$student['degrees']))?></option><?php endforeach; ?>
    </select></label>
    <label>Próxima graduação<select name="target" id="event-target" required disabled><option value="">Primeiro selecione o aluno</option></select></label>
    <button class="primary" type="submit">Adicionar à lista</button>
</form>
</details>
<?php elseif ($canManageEvents): ?>
<p class="form-hint event-all-added">Todos os alunos ativos já estão na lista deste evento.</p>
<?php endif; ?>

<div class="event-toolbar">
    <?php if ($showHidden): ?><a class="text-link" href="/?page=event&id=<?=(int)$event['id']?>">Ocultar faltantes</a>
    <?php else: ?><a class="text-link" href="/?page=event&id=<?=(int)$event['id']?>&hidden=1">Mostrar escondidos</a><?php endif; ?>
</div>

<?php if ($graduations === []): ?>
<section class="empty-state compact"><div class="belt-icon" aria-hidden="true">🥋</div><h2>Lista vazia</h2><p>Adicione alunos para planejar as graduações deste evento.</p></section>
<?php else: ?>
<?php foreach (belt_names() as $beltCode=>$beltName): ?>
    <?php $rows=array_values(array_filter($graduations,static fn(array $row):bool=>$row['from_belt']===$beltCode && $row['status']==='planned')); if ($rows===[]) continue; ?>
    <section class="belt-group"><h2><?=h($beltName)?> <span><?=count($rows)?></span></h2><div class="graduation-list">
        <?php foreach ($rows as $row): ?>
            <?php $eligibility=student_eligibility($row['current_belt'],(int)$row['current_degrees'],$row['last_graduation_at'],$eventDate); $undoAllowed=$user['role']!=='viewer'||(int)$row['graduated_by_id']===(int)$user['id']; ?>
            <article class="graduation-card <?=$row['status']==='done'?'is-done':''?> <?=$row['hidden']?'is-hidden':''?>">
                <div class="graduation-person"><h3><a class="student-name-link" href="/?page=student&id=<?=(int)$row['student_id']?>&evento=<?=(int)$event['id']?>"><?=h($row['student_name'])?></a></h3><p><?=h(belt_rank($row['from_belt'],(int)$row['from_degrees']))?> <span aria-hidden="true">→</span> <strong><?=h(belt_rank($row['to_belt'],(int)$row['to_degrees']))?></strong></p>
                    <?php if ($row['status']==='done'): ?><small class="graduation-by">Graduado por <?=h($row['graduated_by_name']??'usuário')?></small>
                    <?php elseif (!$eligibility['eligible'] && $eligibility['eligible_at']!==null): ?><small class="eligibility waiting">Ainda não elegível; a partir de <?=h((new DateTimeImmutable($eligibility['eligible_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?></small><?php endif; ?>
                </div>
                <div class="graduation-actions">
                    <?php if ($row['status']==='planned'): ?>
                        <form method="post" action="/?page=event-check" class="check-form">
                            <?=csrf_field()?> <input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><input type="hidden" name="graduation_id" value="<?=(int)$row['id']?>">
                            <?php if ($confirmationNeeded===(int)$row['id']): ?><label class="confirm-ineligible"><input type="checkbox" name="confirm_ineligible" value="1" required> Confirmo graduar antes do prazo</label><?php endif; ?>
                            <button class="check-button" type="submit"><?=$confirmationNeeded===(int)$row['id']?'Confirmar check':'Dar check'?></button>
                        </form>
                        <?php if ($canManageEvents): ?><form method="post" action="/?page=<?=$row['hidden']?'event-show':'event-hide'?>">
                            <?=csrf_field()?> <input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><input type="hidden" name="graduation_id" value="<?=(int)$row['id']?>">
                            <button class="text-button" type="submit"><?=$row['hidden']?'Mostrar':'Esconder'?></button>
                        </form><?php endif; ?>
                    <?php elseif ($undoAllowed): ?>
                        <form method="post" action="/?page=event-undo" data-confirm="Desfazer o check de <?=h($row['student_name'])?>? O aluno volta para a lista pendente.">
                            <?=csrf_field()?> <input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><input type="hidden" name="graduation_id" value="<?=(int)$row['id']?>">
                            <button class="text-button danger" type="submit">Desfazer</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div></section>
<?php endforeach; ?>
<?php $doneRows=array_values(array_filter($graduations,static fn(array $row):bool=>$row['status']==='done')); if ($doneRows!==[]): ?>
<section class="belt-group done-group"><h2>Já graduados <span><?=count($doneRows)?></span></h2><div class="graduation-list">
    <?php foreach ($doneRows as $row): $undoAllowed=$user['role']!=='viewer'||(int)$row['graduated_by_id']===(int)$user['id']; ?>
        <article class="graduation-card is-done"><div class="graduation-person"><h3><a class="student-name-link" href="/?page=student&id=<?=(int)$row['student_id']?>&evento=<?=(int)$event['id']?>"><?=h($row['student_name'])?></a></h3><p><?=h(belt_rank($row['from_belt'],(int)$row['from_degrees']))?> <span aria-hidden="true">→</span> <strong><?=h(belt_rank($row['to_belt'],(int)$row['to_degrees']))?></strong></p><small class="graduation-by">Graduado por <?=h($row['graduated_by_name']??'usuário')?></small></div>
            <?php if ($undoAllowed): ?><div class="graduation-actions"><form method="post" action="/?page=event-undo" data-confirm="Desfazer o check de <?=h($row['student_name'])?>? O aluno volta para a lista pendente.">
                <?=csrf_field()?> <input type="hidden" name="event_id" value="<?=(int)$event['id']?>"><input type="hidden" name="graduation_id" value="<?=(int)$row['id']?>"><button class="text-button danger" type="submit">Desfazer</button>
            </form></div><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div></section>
<?php endif; ?>
<script>document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();if(window.confirm(form.dataset.confirm||'Confirmar ação?'))form.submit();}));</script>
<?php endif; ?>

<?php if ($canManageEvents && $availableStudents!==[]): ?>
<script>
const eventStudents=document.getElementById('event-student');const eventTargets=document.getElementById('event-target');
const beltLabels=<?=json_encode(belt_names(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const nextBelt={white:'blue',orange:'green',green:'blue',blue:'purple',purple:'brown',brown:'black'};
function fillTargets(){const selected=eventStudents.selectedOptions[0];eventTargets.replaceChildren();if(!selected||!selected.value){eventTargets.add(new Option('Primeiro selecione o aluno',''));eventTargets.disabled=true;return;}const belt=selected.dataset.belt;const degrees=Number(selected.dataset.degrees);const max=belt==='black'?6:4;for(let amount=1;degrees+amount<=max;amount++)eventTargets.add(new Option('+'+amount+' '+(amount===1?'grau':'graus'),'degree:'+amount));for(const [code,label] of Object.entries(beltLabels)){if(code!==belt)eventTargets.add(new Option('Trocar para faixa '+label,'belt:'+code));}const suggested=degrees===4?(nextBelt[belt]?'belt:'+nextBelt[belt]:''):(degrees<Math.min(max,4)?'degree:1':'');if(suggested)eventTargets.value=suggested;eventTargets.disabled=false;}
eventStudents.addEventListener('change',fillTargets);
</script>
<?php endif; ?>
