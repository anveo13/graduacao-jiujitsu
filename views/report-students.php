<?php
declare(strict_types=1);
$studentCount = count($reportRows);
$lastGraduationText = static function (?string $date): string {
    return $date === null ? '—' : (new DateTimeImmutable($date, new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y');
};
?>
<a class="back-link no-print" href="/?page=reports">‹ Relatórios</a>
<div class="report-toolbar no-print">
    <a class="secondary-link" href="/?page=students">Voltar aos alunos</a>
    <button class="primary" type="button" onclick="window.print()">Imprimir / salvar PDF</button>
</div>
<article class="report-paper">
    <header class="report-header">
        <div class="report-brand"><span class="brand-badge">GJ</span><span>GRADUAÇÃO<br><small>JIU-JITSU</small></span></div>
        <div class="report-heading-copy">
            <p class="eyebrow">CADASTRO DA EQUIPE</p>
            <h1>Relatório de alunos</h1>
            <p class="muted">Posição atual dos alunos ativos</p>
        </div>
        <div class="report-stamp">EQUIPE</div>
    </header>

    <section class="report-summary" aria-label="Resumo dos alunos">
        <div><strong><?=$studentCount?></strong><span>alunos ativos</span></div>
        <?php foreach (belt_names() as $beltCode=>$beltName): $count=count(array_filter($reportRows,static fn(array $student):bool=>$student['belt']===$beltCode)); if ($count===0) continue; ?>
            <div><strong><?=$count?></strong><span><?=h($beltName)?></span></div>
        <?php endforeach; ?>
    </section>

    <?php if ($reportRows === []): ?>
        <p class="report-empty">Nenhum aluno ativo cadastrado.</p>
    <?php else: ?>
        <?php foreach (belt_names() as $beltCode=>$beltName): $beltStudents=array_values(array_filter($reportRows,static fn(array $student):bool=>$student['belt']===$beltCode)); if ($beltStudents===[]) continue; ?>
            <section class="report-belt-group">
                <h2><?=belt_bar_html($beltCode)?><?=h($beltName)?><span><?=count($beltStudents)?></span></h2>
                <table class="report-table">
                    <thead><tr><th>Aluno</th><th>Graduação atual</th><th>Última graduação</th></tr></thead>
                    <tbody><?php foreach ($beltStudents as $student): ?>
                        <tr><td class="report-student-name"><?=h($student['name'])?></td><td><?=belt_rank_html($student['belt'],(int)$student['degrees'])?></td><td><?=h($lastGraduationText($student['last_graduation_at']))?></td></tr>
                    <?php endforeach; ?></tbody>
                </table>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <footer class="report-footer"><span>Sistema de Graduação Jiu-Jitsu</span><span>Gerado em <?=h((new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i'))?></span></footer>
</article>
