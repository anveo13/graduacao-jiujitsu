<?php
declare(strict_types=1);
$isEditing = $student !== null;
$formName = (string)($formValues['name'] ?? $student['name'] ?? '');
$formBelt = (string)($formValues['belt'] ?? $student['belt'] ?? 'white');
$formDegrees = (string)($formValues['degrees'] ?? $student['degrees'] ?? '0');
?>
<?php if ($isEditing): ?><a class="back-link" href="/?page=student&id=<?=(int)$student['id']?>">‹ <?=h($student['name'])?></a><?php else: ?><a class="back-link" href="/?page=students">‹ Alunos</a><?php endif; ?>
<section class="page-heading">
    <div><p class="eyebrow">EQUIPE</p><h1><?= $isEditing ? 'Editar aluno' : 'Cadastrar aluno' ?></h1><p class="muted">Informe a faixa e os graus atuais.</p></div>
</section>
<?php if ($formError !== ''): ?><p class="alert" role="alert"><?=h($formError)?></p><?php endif; ?>
<form class="student-form" method="post" action="/?page=student-save">
    <?=csrf_field()?>
    <input type="hidden" name="id" value="<?= $isEditing ? (int)$student['id'] : '' ?>">
    <label>Nome do aluno<input name="name" value="<?=h($formName)?>" maxlength="150" autocomplete="name" required autofocus></label>
    <label>Faixa<select name="belt" id="student-belt" required>
        <?php foreach (belt_names() as $code => $label): ?><option value="<?=h($code)?>" <?=$formBelt===$code?'selected':''?>><?=h($label)?></option><?php endforeach; ?>
    </select></label>
    <label>Graus<select name="degrees" id="student-degrees" required>
        <?php for ($degree=0; $degree<=($formBelt==='black'?6:4); $degree++): ?><option value="<?=$degree?>" <?=(string)$degree===$formDegrees?'selected':''?>><?=$degree?></option><?php endfor; ?>
    </select></label>
    <?php if ($isEditing && $student['last_graduation_at'] !== null): ?><p class="form-hint">Última graduação registrada: <?=h((new DateTimeImmutable($student['last_graduation_at'], $timezone))->format('d/m/Y'))?>. Essa data é atualizada quando uma graduação for registrada.</p><?php endif; ?>
    <div class="form-actions"><button class="primary" type="submit">Salvar aluno</button><a class="secondary-link" href="<?= $isEditing ? '/?page=student&id='.(int)$student['id'] : '/?page=students' ?>">Cancelar</a></div>
</form>
<script>
const beltSelect=document.getElementById('student-belt');
const degreeSelect=document.getElementById('student-degrees');
const currentDegree=<?=json_encode($formDegrees, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
function updateDegreeOptions(){const max=beltSelect.value==='black'?6:4;const selected=Math.min(Number(degreeSelect.value||currentDegree||0),max);degreeSelect.replaceChildren();for(let degree=0;degree<=max;degree++){const option=document.createElement('option');option.value=String(degree);option.textContent=String(degree);option.selected=degree===selected;degreeSelect.append(option);}}
beltSelect.addEventListener('change',updateDegreeOptions);
</script>
