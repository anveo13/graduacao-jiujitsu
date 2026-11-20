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

<div class="student-list-controls">
    <form class="search-form" method="get" action="/">
        <input type="hidden" name="page" value="students">
        <label class="sr-only" for="student-search">Buscar aluno por nome</label>
        <input id="student-search" name="q" value="<?=h($search)?>" placeholder="Buscar por nome" maxlength="150">
        <button class="secondary" type="submit">Buscar</button>
        <?php if ($search !== ''): ?><a class="text-link" href="/?page=students">Limpar</a><?php endif; ?>
    </form>
    <?php if ($students !== []): ?>
        <label class="student-sort" for="student-sort-select">Ordenar alunos por
            <select id="student-sort-select"><option value="graduation">Ordem de graduação</option><option value="alphabetical">Ordem alfabética</option></select>
        </label>
    <?php endif; ?>
</div>

<?php if ($students === []): ?>
    <section class="empty-state compact"><div class="belt-icon" aria-hidden="true">🥋</div><h2><?= $search === '' ? 'Nenhum aluno cadastrado' : 'Nenhum aluno encontrado' ?></h2><p><?= $search === '' ? 'Cadastre o primeiro aluno da equipe.' : 'Tente outro nome.' ?></p></section>
<?php else: ?>
    <?php foreach (belt_names() as $beltCode => $beltName): ?>
        <?php $beltStudents = array_values(array_filter($students, static fn(array $student): bool => $student['belt'] === $beltCode)); ?>
        <?php if ($beltStudents !== []): ?>
            <details class="dashboard-section student-belt-section" id="students-belt-<?=h($beltCode)?>" open>
                <summary class="dashboard-heading"><div><h2><?=belt_bar_html($beltCode)?><?=h($beltName)?></h2></div><span class="dashboard-heading-side"><span class="count-pill"><?=count($beltStudents)?></span><svg class="dashboard-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></span></summary>
                <div class="student-list">
                    <?php foreach ($beltStudents as $student): ?>
                        <?php $eligibility = student_eligibility($student['belt'], (int)$student['degrees'], $student['last_graduation_at'], $today); ?>
                        <article class="student-card" data-student-card data-student-name="<?=h($student['name'])?>">
                            <div class="student-main">
                                <h3><a class="student-name-link" href="/?page=student&id=<?=(int)$student['id']?>"><?=h($student['name'])?></a></h3>
                                <p><?=belt_rank_html($student['belt'], (int)$student['degrees'])?></p>
                                <p class="last-graduation"><?= $student['last_graduation_at'] === null ? 'Sem data da última graduação' : 'Última graduação: '.h((new DateTimeImmutable($student['last_graduation_at'], $timezone))->format('d/m/Y')) ?></p>
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
            </details>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
<script>
document.querySelectorAll('.confirm-action').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();if(window.confirm(form.dataset.confirm||'Confirmar ação?'))form.submit();}));
const studentSections=[...document.querySelectorAll('details.student-belt-section')];
const studentSearchActive=<?=json_encode($search!=='')?>;
studentSections.forEach(section=>{const key='graduacao.'+section.id;try{if(!studentSearchActive&&localStorage.getItem(key)==='0')section.open=false;}catch{}section.addEventListener('toggle',()=>{try{localStorage.setItem(key,section.open?'1':'0');}catch{}});});
const studentSort=document.getElementById('student-sort-select');
if(studentSort){
    const lists=[...document.querySelectorAll('.student-belt-section .student-list')];
    lists.forEach(list=>[...list.children].forEach((card,index)=>{card.dataset.graduationOrder=String(index);}));
    const collator=new Intl.Collator('pt-BR',{sensitivity:'base',numeric:true});
    const applyStudentSort=()=>{lists.forEach(list=>{const cards=[...list.children];cards.sort((a,b)=>studentSort.value==='alphabetical'?collator.compare(a.dataset.studentName||'',b.dataset.studentName||''):Number(a.dataset.graduationOrder)-Number(b.dataset.graduationOrder));cards.forEach(card=>list.appendChild(card));});};
    try{if(localStorage.getItem('graduacao.students.sort')==='alphabetical')studentSort.value='alphabetical';}catch{}
    applyStudentSort();
    studentSort.addEventListener('change',()=>{applyStudentSort();try{localStorage.setItem('graduacao.students.sort',studentSort.value);}catch{}});
}
</script>
