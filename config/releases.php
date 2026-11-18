<?php
declare(strict_types=1);

return [
    'current' => '0.4.1',
    'releases' => [
        [
            'version' => '0.4.1',
            'date' => '2026-11-18',
            'title' => 'Identidade visual vermelha',
            'changes' => [
                'Paleta atualizada com vermelho, preto e branco inspirados nas cores da equipe.',
                'Favicon GJ em SVG criado para as telas e a aba do navegador.',
            ],
        ],
        [
            'version' => '0.4.0',
            'date' => '2026-11-18',
            'title' => 'Usuários e convites',
            'changes' => [
                'Convites de uso único para Administradores e Visualizadores, válidos por sete dias.',
                'Tela de usuários para alterar perfis, desativar e reativar contas.',
                'Aceite de convite com criação de conta e entrada automática; perfil para trocar nome e senha.',
            ],
        ],
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
