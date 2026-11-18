<?php
declare(strict_types=1);
$beltName=belt_label($student['belt']);
$studentEligibility=student_eligibility($student['belt'],(int)$student['degrees'],$student['last_graduation_at'],$today);
$targetOptions=$canGraduate?graduation_target_options($student['belt'],(int)$student['degrees']):[];
$suggestedTarget=suggested_graduation_target($student['belt'],(int)$student['degrees']);
$selectedTarget=$pendingTarget??$suggestedTarget;
$latestHistoryId=(int)($history[0]['id']??0);
$roleLabels=['master'=>'Master','admin'=>'Administrador','viewer'=>'Visualizador'];
// Veio de um evento (?evento=ID): o "Voltar" leva de volta para ele.
$backEvent=filter_var($_GET['evento']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$backEvent=$backEvent!==false?find_event((int)$backEvent):null;
?>
<?php if ($backEvent!==null): ?><a class="back-link" href="/?page=event&id=<?=(int)$backEvent['id']?>">‹ <?=h($backEvent['name'])?></a><?php else: ?><a class="back-link" href="/?page=students">‹ Alunos</a><?php endif; ?>
<section class="page-heading student-detail-heading"><div><p class="eyebrow">FICHA DO ALUNO<?=$student['active']?'':' · DESATIVADO'?></p><h1><?=h($student['name'])?></h1><p class="muted student-rank"><?=belt_bar_html($student['belt'],(int)$student['degrees'])?><?=belt_rank_html($student['belt'],(int)$student['degrees'])?><?= $student['last_graduation_at']!==null?' · Última graduação em '.h((new DateTimeImmutable($student['last_graduation_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y')):'' ?></p></div></section>
<?php if ($error!==''): ?><p class="alert" role="alert"><?=h($error)?></p><?php endif; ?>

<section class="student-detail-card"><div><span class="eligibility <?=$studentEligibility['status']?>"><?php if ($studentEligibility['status']==='max'): ?>Último grau<?php elseif ($studentEligibility['eligible']): ?>Elegível<?php else: ?>Elegível a partir de <?=h((new DateTimeImmutable($studentEligibility['eligible_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?><?php endif; ?></span>
    <p class="form-hint">Cadastrado em <?=h((new DateTimeImmutable($student['created_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?><?= $student['updated_by']!==null&&$student['updated_at']!==$student['created_at']?' · Alterado por '.h($student['updated_by_name']??'usuário').' em '.h((new DateTimeImmutable($student['updated_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y')):'' ?></p></div>
    <?php if ($canManageStudents&&$student['active']): ?><div class="student-detail-actions"><a class="text-link" href="/?page=student-edit&id=<?=(int)$student['id']?>">Editar cadastro</a><form class="confirm-action" data-confirm="Desativar <?=h($student['name'])?>? O histórico será mantido." method="post" action="/?page=student-deactivate"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$student['id']?>"><button class="text-button danger" type="submit">Desativar</button></form></div><?php endif; ?>
</section>

<?php if ($canGraduate&&$student['active']): ?>
<section class="dashboard-section graduate-now-section"><div class="dashboard-heading"><div><p class="eyebrow">GRADUAÇÃO AVULSA</p><h2>Graduar agora</h2></div></div>
    <?php if ($targetOptions===[]): ?><p class="dashboard-empty">Este aluno já está no grau máximo da faixa preta.</p><?php else: ?>
    <form class="graduate-now-form" method="post" action="/?page=graduate-now"><?=csrf_field()?>
        <input type="hidden" name="student_id" value="<?=(int)$student['id']?>">
        <label>Próxima graduação<select name="target" required><?php foreach ($targetOptions as $option): ?><option value="<?=h($option['value'])?>" <?=$selectedTarget===$option['value']?'selected':''?>><?=h($option['label'])?></option><?php endforeach; ?></select></label>
        <?php if (!$studentEligibility['eligible']&&$studentEligibility['eligible_at']!==null): ?><p class="eligibility-warning">Este aluno só estará elegível em <?=h((new DateTimeImmutable($studentEligibility['eligible_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?>. O sistema pedirá confirmação antes de registrar.</p><?php endif; ?>
        <?php if ($confirmationNeeded): ?><label class="confirm-ineligible"><input type="checkbox" name="confirm_ineligible" value="1" required> Confirmo graduar antes do prazo</label><?php endif; ?>
        <?php if (student_has_pending_event_graduation((int)$student['id'])): ?><p class="form-hint">Há um plano deste aluno em um evento. A graduação avulsa não altera essa lista; o plano continuará como está.</p><?php endif; ?>
        <button class="primary" type="submit"><?=$confirmationNeeded?'Confirmar graduação':'Registrar graduação'?></button>
    </form><?php endif; ?>
</section>
<?php endif; ?>

<section class="dashboard-section history-section"><div class="dashboard-heading"><div><p class="eyebrow">HISTÓRICO</p><h2>Graduações registradas</h2></div><span class="count-pill"><?=count($history)?></span></div>
    <?php if ($history===[]): ?><p class="dashboard-empty">Nenhuma graduação registrada no sistema.</p><?php else: ?><div class="history-list">
        <?php foreach ($history as $item): $date=new DateTimeImmutable($item['graduated_at'],new DateTimeZone('America/Sao_Paulo')); ?>
            <article class="history-card"><div class="history-date"><?=h($date->format('d/m/Y'))?></div><div class="history-content"><strong><?=belt_rank_html($item['from_belt'],(int)$item['from_degrees'])?> <span aria-hidden="true">→</span> <?=belt_rank_html($item['to_belt'],(int)$item['to_degrees'])?></strong><small><?php if ($item['event_id']===null): ?>Graduação avulsa<?php else: ?><a href="/?page=event&id=<?=(int)$item['event_id']?>"><?=h($item['event_name']??'Evento')?></a><?php endif; ?> · Graduado por <?=h($item['graduated_by_name']??'usuário')?></small></div>
                <?php if ($item['event_id']===null&&(int)$item['id']===$latestHistoryId&&$canManageStudents): ?><form method="post" action="/?page=graduation-undo-avulsa" data-confirm="Desfazer esta graduação avulsa? A faixa e os graus anteriores serão restaurados."><?=csrf_field()?><input type="hidden" name="student_id" value="<?=(int)$student['id']?>"><input type="hidden" name="graduation_id" value="<?=(int)$item['id']?>"><button class="text-button danger" type="submit">Desfazer</button></form><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div><?php endif; ?>
</section>
<script>document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();if(window.confirm(form.dataset.confirm||'Confirmar ação?'))form.submit();}));</script>
