<?php
declare(strict_types=1);
$reportEventDate = new DateTimeImmutable($reportEvent['event_date'], new DateTimeZone('America/Sao_Paulo'));
$reportDone = count(array_filter($reportRows, static fn(array $row): bool => $row['status'] === 'done'));
$reportPending = count($reportRows) - $reportDone;
$reportEventFormatted = $reportEventDate->format('d/m/Y');
$reportRowsByBelt = [];
foreach (belt_names() as $beltCode => $beltName) {
    $sameBeltRows = array_values(array_filter($reportRows, static fn(array $row): bool => $row['from_belt'] === $beltCode && $row['to_belt'] === $beltCode));
    $beltChangeRows = array_values(array_filter($reportRows, static fn(array $row): bool => $row['from_belt'] === $beltCode && $row['to_belt'] !== $beltCode));
    $beltRows = [...$sameBeltRows, ...$beltChangeRows];
    if ($beltRows !== []) $reportRowsByBelt[$beltCode] = ['name' => $beltName, 'rows' => $beltRows];
}
?>
<a class="back-link no-print" href="/?page=reports">‹ Relatórios</a>
<div class="report-toolbar no-print">
    <a class="secondary-link" href="/?page=event&id=<?=(int)$reportEvent['id']?>">Voltar ao evento</a>
    <button class="primary" type="button" onclick="window.print()">Imprimir / salvar PDF</button>
</div>
<article class="report-paper">
    <header class="report-header">
        <div class="report-brand"><span class="brand-badge">GJ</span><span>GRADUAÇÃO<br><small>JIU-JITSU</small></span></div>
        <div class="report-heading-copy">
            <p class="eyebrow"><?=$reportIsPast ? 'RESULTADO DO EVENTO' : 'PLANEJAMENTO DO EVENTO'?></p>
            <h1><?=h($reportEvent['name'])?></h1>
            <p class="muted">Graduação em <?=$reportEventFormatted?></p>
        </div>
        <div class="report-stamp"><?=$reportIsPast ? 'RESULTADO' : 'PRÉVIA'?></div>
    </header>

    <section class="report-summary" aria-label="Resumo do evento">
        <div><strong><?=count($reportRows)?></strong><span>alunos na lista</span></div>
        <div><strong><?=$reportDone?></strong><span>graduados</span></div>
        <div><strong><?=$reportPending?></strong><span><?=$reportIsPast ? 'não graduados' : 'pendentes'?></span></div>
    </section>

    <?php if ($reportRows === []): ?>
        <p class="report-empty">Nenhum aluno foi adicionado a este evento.</p>
    <?php else: ?>
        <?php foreach ($reportRowsByBelt as $beltCode => $beltGroup): ?>
            <section class="report-belt-group event-report-group">
                <h2><?=belt_bar_html($beltCode)?><?=h($beltGroup['name'])?><span><?=count($beltGroup['rows'])?></span></h2>
                <table class="report-table">
                    <?php if (!$reportIsPast): ?><thead><tr><th>Aluno</th><th>Graduação atual</th><th>Graduação prevista</th><th>Situação</th></tr></thead>
                    <?php else: ?><thead><tr><th>Aluno</th><th>Graduação antes do evento</th><th>Graduação após o evento</th><th>Resultado</th></tr></thead><?php endif; ?>
                    <tbody>
                    <?php foreach ($beltGroup['rows'] as $row): ?>
                        <?php if (!$reportIsPast): ?>
                            <tr>
                                <td class="report-student-name"><?=h($row['student_name'])?></td>
                                <td><?=belt_rank_html($row['from_belt'],(int)$row['from_degrees'])?></td>
                                <td><?=belt_bar_html($row['to_belt'],(int)$row['to_degrees'])?><?=belt_rank_html($row['to_belt'],(int)$row['to_degrees'])?></td>
                                <td><span class="report-status <?=$row['status']==='done'?'is-done':'is-pending'?>"><?=$row['status']==='done'?'Check registrado':'Planejado'?></span><?=$row['hidden']?' <small class="report-note">Oculto na lista</small>':''?></td>
                            </tr>
                        <?php else: $graduated=$row['status']==='done'; $afterBelt=$graduated?$row['to_belt']:$row['from_belt']; $afterDegrees=$graduated?(int)$row['to_degrees']:(int)$row['from_degrees']; ?>
                            <tr>
                                <td class="report-student-name"><?=h($row['student_name'])?></td>
                                <td><?=belt_rank_html($row['from_belt'],(int)$row['from_degrees'])?></td>
                                <td><?=belt_bar_html($afterBelt,$afterDegrees)?><?=belt_rank_html($afterBelt,$afterDegrees)?></td>
                                <td><span class="report-status <?=$graduated?'is-done':'is-pending'?>"><?=$graduated?'Graduado':'Não graduado'?></span><?=$row['hidden']?' <small class="report-note">Oculto na lista</small>':''?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <footer class="report-footer"><span>Sistema de Graduação Jiu-Jitsu</span><span>Gerado em <?=h((new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i'))?></span></footer>
</article>
