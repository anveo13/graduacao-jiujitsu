<?php
declare(strict_types=1);

return [
    'current' => '0.3.0',
    'releases' => [
        [
            'version' => '0.3.0',
            'date' => '2026-11-18',
            'title' => 'Eventos de graduação',
            'changes' => [
                'Criação de eventos e planejamento da graduação de cada aluno.',
                'Check com aviso de elegibilidade, esconder faltantes e desfazer o check mais recente.',
                'Lista organizada por faixa, com progresso e identificação de quem graduou cada aluno.',
            ],
        ],
        [
            'version' => '0.2.0',
            'date' => '2026-11-18',
            'title' => 'Cadastro da equipe',
            'changes' => [
                'Lista de alunos ativos por faixa, com busca por nome.',
                'Cadastro, edição e desativação de alunos para Master e Administrador.',
                'Aviso de elegibilidade calculado conforme faixa, graus e data da última graduação.',
            ],
        ],
        [
            'version' => '0.1.0',
            'date' => '2026-11-17',
            'title' => 'Base do sistema',
            'changes' => [
                'Login e perfis com permissões no servidor.',
                'Banco inicial, migrations e criação segura do usuário Master.',
                'Layout inicial adaptado para celular.',
            ],
        ],
    ],
];
