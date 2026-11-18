<?php
declare(strict_types=1);
$eligibleNow=[];$eligibleSoon=[];
$limitDate=$today->modify('+30 days');
foreach ($dashboardStudents as $item) {
    $eligibility=student_eligibility($item['belt'],(int)$item['degrees'],$item['last_graduation_at'],$today);
    if ($eligibility['eligible']) $eligibleNow[]=['student'=>$item,'eligibility'=>$eligibility];
    elseif ($eligibility['eligible_at']!==null&&$eligibility['eligible_at']<=$limitDate->format('Y-m-d')) $eligibleSoon[]=['student'=>$item,'eligibility'=>$eligibility];
}
?>
<section class="welcome"><p class="eyebrow">PAINEL</p><h1>Olá, <?=h($user['name'])?>.</h1><p>Acompanhe as graduações e o planejamento da equipe.</p></section>

<section class="dashboard-section">
    <div class="dashboard-heading"><div><p class="eyebrow">ELEGIBILIDADE</p><h2>Já podem ser graduados</h2></div><span class="count-pill"><?=count($eligibleNow)?></span></div>
    <?php if ($eligibleNow===[]): ?><p class="dashboard-empty">Nenhum aviso de elegibilidade no momento.</p><?php else: ?><div class="dashboard-list">
        <?php foreach ($eligibleNow as $item): $student=$item['student']; ?>
            <a class="dashboard-student" href="/?page=student&id=<?=(int)$student['id']?>"><span><strong><?=h($student['name'])?></strong><small><?=h(belt_label($student['belt']))?> · <?=(int)$student['degrees']?>°</small></span><small>Elegível desde <?=h((new DateTimeImmutable($student['last_graduation_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?></small></a>
        <?php endforeach; ?>
    </div><?php endif; ?>
</section>

<section class="dashboard-section">
    <div class="dashboard-heading"><div><p class="eyebrow">PLANEJAMENTO</p><h2>Elegíveis nos próximos 30 dias</h2></div><span class="count-pill"><?=count($eligibleSoon)?></span></div>
    <?php if ($eligibleSoon===[]): ?><p class="dashboard-empty">Nenhum aluno entra no período nos próximos 30 dias.</p><?php else: ?><div class="dashboard-list">
        <?php foreach ($eligibleSoon as $item): $student=$item['student']; ?>
            <a class="dashboard-student" href="/?page=student&id=<?=(int)$student['id']?>"><span><strong><?=h($student['name'])?></strong><small><?=h(belt_label($student['belt']))?> · <?=(int)$student['degrees']?>°</small></span><small>A partir de <?=h((new DateTimeImmutable($item['eligibility']['eligible_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?></small></a>
        <?php endforeach; ?>
    </div><?php endif; ?>
</section>

<section class="dashboard-section next-event-section">
    <div class="dashboard-heading"><div><p class="eyebrow">AGENDA</p><h2>Próximo evento</h2></div></div>
    <?php if ($nextEvent===null): ?><p class="dashboard-empty">Nenhum próximo evento cadastrado.</p><?php if ($canManageEvents): ?><a class="primary-link" href="/?page=events">Criar evento</a><?php endif; ?>
    <?php else: $progress=event_progress((int)$nextEvent['id']); ?>
        <a class="next-event-card" href="/?page=event&id=<?=(int)$nextEvent['id']?>"><span class="event-date"><?=h((new DateTimeImmutable($nextEvent['event_date'],new DateTimeZone('America/Sao_Paulo')))->format('d/m'))?></span><span class="event-copy"><strong><?=h($nextEvent['name'])?></strong><small><?=h((new DateTimeImmutable($nextEvent['event_date'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?> · <?=$progress['done']?> de <?=($progress['done']+$progress['planned'])?> graduados</small></span><span class="event-arrow" aria-hidden="true">›</span></a>
    <?php endif; ?>
</section>
