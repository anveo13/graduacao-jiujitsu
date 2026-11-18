<?php
declare(strict_types=1);
$eligibleNow=[];$eligibleSoon=[];
$limitDate=$today->modify('+30 days');
foreach ($dashboardStudents as $item) {
    $eligibility=student_eligibility($item['belt'],(int)$item['degrees'],$item['last_graduation_at'],$today);
    if ($eligibility['eligible']) $eligibleNow[]=['student'=>$item,'eligibility'=>$eligibility];
    elseif ($eligibility['eligible_at']!==null&&$eligibility['eligible_at']<=$limitDate->format('Y-m-d')) $eligibleSoon[]=['student'=>$item,'eligibility'=>$eligibility];
}
$chevron='<svg class="dashboard-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
?>
<section class="welcome"><p class="eyebrow">PAINEL</p><h1>Olá, <?=h($user['name'])?>.</h1><p>Acompanhe as graduações e o planejamento da equipe.</p></section>

<?php // Blocos em sanfona: abertos por padrão; o aberto/fechado fica guardado neste aparelho (ids blk_*). ?>
<details class="dashboard-section next-event-section" id="blk_agenda" open>
    <summary class="dashboard-heading"><div><p class="eyebrow">AGENDA</p><h2>Próximo evento</h2></div><span class="dashboard-heading-side"><?=$chevron?></span></summary>
    <?php if ($nextEvent===null): ?><p class="dashboard-empty">Nenhum próximo evento cadastrado.</p><?php if ($canManageEvents): ?><a class="primary-link" href="/?page=events">Criar evento</a><?php endif; ?>
    <?php else: $progress=event_progress((int)$nextEvent['id']); ?>
        <a class="next-event-card" href="/?page=event&id=<?=(int)$nextEvent['id']?>"><span class="event-date"><?=h((new DateTimeImmutable($nextEvent['event_date'],new DateTimeZone('America/Sao_Paulo')))->format('d/m'))?></span><span class="event-copy"><strong><?=h($nextEvent['name'])?></strong><small><?=h((new DateTimeImmutable($nextEvent['event_date'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?> · <?=$progress['done']?> de <?=($progress['done']+$progress['planned'])?> graduados</small></span><span class="event-arrow" aria-hidden="true">›</span></a>
    <?php endif; ?>
</details>

<details class="dashboard-section" id="blk_elegiveis" open>
    <summary class="dashboard-heading"><div><p class="eyebrow">ELEGIBILIDADE</p><h2>Já podem ser graduados</h2></div><span class="dashboard-heading-side"><span class="count-pill"><?=count($eligibleNow)?></span><?=$chevron?></span></summary>
    <?php if ($eligibleNow===[]): ?><p class="dashboard-empty">Nenhum aviso de elegibilidade no momento.</p><?php else: ?><div class="dashboard-list">
        <?php foreach ($eligibleNow as $item): $student=$item['student']; ?>
            <a class="dashboard-student" href="/?page=student&id=<?=(int)$student['id']?>"><span><strong><?=h($student['name'])?></strong><small><?=belt_rank_html($student['belt'],(int)$student['degrees'])?></small></span><small>Elegível desde <?=h((new DateTimeImmutable($item['eligibility']['eligible_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?></small></a>
        <?php endforeach; ?>
    </div><?php endif; ?>
</details>

<details class="dashboard-section" id="blk_proximos" open>
    <summary class="dashboard-heading"><div><p class="eyebrow">PLANEJAMENTO</p><h2>Elegíveis nos próximos 30 dias</h2></div><span class="dashboard-heading-side"><span class="count-pill"><?=count($eligibleSoon)?></span><?=$chevron?></span></summary>
    <?php if ($eligibleSoon===[]): ?><p class="dashboard-empty">Nenhum aluno entra no período nos próximos 30 dias.</p><?php else: ?><div class="dashboard-list">
        <?php foreach ($eligibleSoon as $item): $student=$item['student']; ?>
            <a class="dashboard-student" href="/?page=student&id=<?=(int)$student['id']?>"><span><strong><?=h($student['name'])?></strong><small><?=belt_rank_html($student['belt'],(int)$student['degrees'])?></small></span><small>A partir de <?=h((new DateTimeImmutable($item['eligibility']['eligible_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?></small></a>
        <?php endforeach; ?>
    </div><?php endif; ?>
</details>

<details class="dashboard-section" id="blk_ultimas" open>
    <summary class="dashboard-heading"><div><p class="eyebrow">HISTÓRICO</p><h2>Últimas graduações</h2></div><span class="dashboard-heading-side"><?=$chevron?></span></summary>
    <?php if ($recentGraduations===[]): ?><p class="dashboard-empty">Nenhuma graduação registrada ainda.</p><?php else: ?><div class="history-list">
        <?php foreach ($recentGraduations as $item): ?>
            <article class="history-card"><div class="history-date"><?=h((new DateTimeImmutable($item['graduated_at'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?></div><div class="history-content"><a class="recent-name" href="/?page=student&id=<?=(int)$item['student_id']?>"><?=h($item['student_name'])?></a><strong><?=belt_rank_html($item['from_belt'],(int)$item['from_degrees'])?> <span aria-hidden="true">→</span> <?=belt_rank_html($item['to_belt'],(int)$item['to_degrees'])?></strong><small><?php if ($item['event_id']===null): ?>Graduação avulsa<?php else: ?><a href="/?page=event&id=<?=(int)$item['event_id']?>"><?=h($item['event_name']??'Evento')?></a><?php endif; ?> · Graduado por <?=h($item['graduated_by_name']??'usuário')?></small></div></article>
        <?php endforeach; ?>
    </div>
    <?php if ($recentHasMore): ?><a class="secondary-link show-more" href="/?ultimas=<?=$recentLimit+20?>#blk_ultimas">Mostrar mais</a><?php endif; ?>
    <?php endif; ?>
</details>
<script>
document.querySelectorAll('details[id^="blk_"]').forEach(block=>{
    try{const saved=localStorage.getItem('graduacao.'+block.id);if(saved==='0')block.open=false;}catch{}
    block.addEventListener('toggle',()=>{try{localStorage.setItem('graduacao.'+block.id,block.open?'1':'0');}catch{}});
});
</script>
