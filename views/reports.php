<?php
declare(strict_types=1);
?>
<section class="page-heading">
    <div><p class="eyebrow">IMPRESSÃO E PDF</p><h1>Relatórios</h1><p class="muted">Escolha um evento ou gere a relação atual de alunos.</p></div>
</section>

<div class="report-options">
    <section class="report-option">
        <div class="report-option-icon" aria-hidden="true">01</div>
        <p class="eyebrow">GRADUAÇÃO</p>
        <h2>Relatório de evento</h2>
        <p>Antes do evento, mostra a faixa atual e a graduação planejada. Depois, mostra quem foi graduado e o resultado registrado.</p>
        <?php if ($events === []): ?>
            <p class="form-hint">Cadastre um evento para gerar este relatório.</p>
        <?php else: ?>
            <form class="report-select-form" method="get" action="/">
                <input type="hidden" name="page" value="report"><input type="hidden" name="kind" value="event">
                <label for="report-event-select">Evento</label>
                <select id="report-event-select" name="id" required>
                    <?php foreach ($events as $item): ?>
                        <option value="<?=(int)$item['id']?>"><?=h($item['name'])?> · <?=h((new DateTimeImmutable($item['event_date'],new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y'))?></option>
                    <?php endforeach; ?>
                </select>
                <button class="primary" type="submit">Gerar relatório do evento</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="report-option">
        <div class="report-option-icon" aria-hidden="true">02</div>
        <p class="eyebrow">EQUIPE</p>
        <h2>Relatório de alunos</h2>
        <p>Relação dos alunos ativos, separada por faixa, com os graus e a data da última graduação.</p>
        <form class="report-select-form" method="get" action="/">
            <input type="hidden" name="page" value="report"><input type="hidden" name="kind" value="students">
            <button class="primary" type="submit">Gerar relatório de alunos</button>
        </form>
    </section>
</div>
