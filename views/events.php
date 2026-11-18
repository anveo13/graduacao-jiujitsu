<?php
declare(strict_types=1);
$todayForForm = (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
?>
<section class="page-heading">
    <div><p class="eyebrow">PLANEJAMENTO</p><h1>Graduações</h1><p class="muted">Organize os alunos por evento e acompanhe os checks no dia.</p></div>
</section>
<?php if ($error !== ''): ?><p class="alert" role="alert"><?=h($error)?></p><?php endif; ?>
<?php if ($canManageEvents): ?>
<form class="event-create-form" method="post" action="/?page=event-create">
    <?=csrf_field()?>
    <label>Nome do evento<input name="name" maxlength="150" placeholder="Ex.: Graduação de fim de ano" required></label>
    <label>Data<input type="date" name="event_date" value="<?=h($todayForForm)?>" required></label>
    <button class="primary" type="submit">Criar evento</button>
</form>
<?php endif; ?>
<?php if ($events === []): ?>
<section class="empty-state compact"><div class="belt-icon" aria-hidden="true">🥋</div><h2>Nenhum evento ainda</h2><p>Crie um evento para começar a montar a lista de graduação.</p></section>
<?php else: ?>
<section class="event-list" aria-label="Eventos">
    <?php foreach ($events as $item): ?>
        <a class="event-card" href="/?page=event&id=<?=(int)$item['id']?>">
            <span class="event-date"><?=h((new DateTimeImmutable($item['event_date'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?></span>
            <span class="event-copy"><strong><?=h($item['name'])?></strong><small><?=(int)$item['done_count']?> de <?=(int)$item['planned_count']+(int)$item['done_count']?> graduados</small></span>
            <span class="event-arrow" aria-hidden="true">›</span>
        </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>
