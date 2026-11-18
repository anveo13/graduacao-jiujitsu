<?php
declare(strict_types=1);
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">EQUIPE</p>
        <h1>Alunos</h1>
        <p class="muted">Alunos ativos organizados por faixa.</p>
    </div>
    <?php if ($canManageStudents): ?><a class="primary-link" href="/?page=student-new">Cadastrar aluno</a><?php endif; ?>
</section>

<form class="search-form" method="get" action="/">
    <input type="hidden" name="page" value="students">
    <label class="sr-only" for="student-search">Buscar aluno por nome</label>
    <input id="student-search" name="q" value="<?=h($search)?>" placeholder="Buscar por nome" maxlength="150">
    <button class="secondary" type="submit">Buscar</button>
    <?php if ($search !== ''): ?><a class="text-link" href="/?page=students">Limpar</a><?php endif; ?>
</form>

<?php if ($students === []): ?>
    <section class="empty-state compact"><div class="belt-icon" aria-hidden="true">🥋</div><h2><?= $search === '' ? 'Nenhum aluno cadastrado' : 'Nenhum aluno encontrado' ?></h2><p><?= $search === '' ? 'Cadastre o primeiro aluno da equipe.' : 'Tente outro nome.' ?></p></section>
<?php else: ?>
    <?php foreach (belt_names() as $beltCode => $beltName): ?>
        <?php $beltStudents = array_values(array_filter($students, static fn(array $student): bool => $student['belt'] === $beltCode)); ?>
        <?php if ($beltStudents !== []): ?>
            <section class="belt-group" aria-labelledby="belt-<?=h($beltCode)?>">
                <h2 id="belt-<?=h($beltCode)?>"><?=h($beltName)?> <span><?=count($beltStudents)?></span></h2>
                <div class="student-list">
                    <?php foreach ($beltStudents as $student): ?>
                        <?php $eligibility = student_eligibility($student['belt'], (int)$student['degrees'], $student['last_graduation_at'], $today); ?>
                        <article class="student-card">
                            <div class="student-main">
                                <h3><a class="student-name-link" href="/?page=student&id=<?=(int)$student['id']?>"><?=h($student['name'])?></a></h3>
                                <p><?=h($beltName)?> · <?= (int)$student['degrees'] ?> <?= (int)$student['degrees'] === 1 ? 'grau' : 'graus' ?></p>
                                <p class="last-graduation"><?= $student['last_graduation_at'] === null ? 'Sem graduação registrada' : 'Última graduação: '.h((new DateTimeImmutable($student['last_graduation_at'], $timezone))->format('d/m/Y')) ?></p>
                                <span class="eligibility <?=h($eligibility['status'])?>">
                                    <?php if ($eligibility['status'] === 'max'): ?>Último grau
                                    <?php elseif ($eligibility['eligible']): ?>Elegível
                                    <?php else: ?>Elegível a partir de <?=h((new DateTimeImmutable($eligibility['eligible_at'], $timezone))->format('d/m/Y'))?><?php endif; ?>
                                </span>
                            </div>
                            <?php if ($canManageStudents): ?>
                                <div class="student-actions">
                                    <a class="text-link" href="/?page=student-edit&id=<?=(int)$student['id']?>">Editar</a>
                                    <form class="confirm-action" data-confirm="<?=h('Desativar '.$student['name'].'? O histórico será mantido.')?>" method="post" action="/?page=student-deactivate">
                                        <?=csrf_field()?>
                                        <input type="hidden" name="id" value="<?=(int)$student['id']?>">
                                        <button class="text-button danger" type="submit">Desativar</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
<script>
document.querySelectorAll('.confirm-action').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();if(window.confirm(form.dataset.confirm||'Confirmar ação?'))form.submit();}));
</script>
