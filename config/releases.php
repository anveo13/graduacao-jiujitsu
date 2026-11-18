<?php
declare(strict_types=1);

return [
    'current' => '0.5.3',
    'releases' => [
        [
            'version' => '0.5.3',
            'date' => '2026-11-18',
            'title' => 'App no celular',
            'changes' => [
                'Dá para instalar o sistema na tela inicial do celular: abre em tela cheia, com o ícone do escudo GJ.',
                'Dentro de cada faixa, a ordem vai do menor para o maior grau.',
                'Na lista do evento, quem vai trocar de faixa fica sempre por último na faixa.',
            ],
        ],
        [
            'version' => '0.5.2',
            'date' => '2026-11-18',
            'title' => 'Navegação mais clara',
            'changes' => [
                'A aba "Graduações" agora se chama "Eventos".',
                'No evento, a lista vem primeiro; "+ Adicionar aluno" abre o formulário só quando precisa.',
                'Todas as telas têm um "‹ Voltar" no mesmo lugar, acima do título.',
                'No evento, tocar no nome do aluno abre a ficha dele, e o Voltar da ficha devolve para o evento.',
                'No celular, o Perfil saiu da barra de baixo e virou o ícone de pessoa no topo.',
                'Quem troca de faixa aparece só com a faixa (ex.: "Roxa"), sem o "0°".',
                'Início: a Agenda vem primeiro, e todos os blocos abrem e fecham (o aparelho lembra a escolha).',
            ],
        ],
        [
            'version' => '0.5.1',
            'date' => '2026-11-18',
            'title' => 'Menu novo',
            'changes' => [
                'Menu refeito: no computador fica no topo, com a página atual marcada; no celular vira uma barra fixa embaixo, com ícones.',
                'Novo link Início no menu. Tocar no seu nome (ou em Perfil, no celular) abre o Meu perfil.',
                'Pode haver mais de um usuário Master.',
                'Início: "Elegível desde" agora mostra a data em que o prazo venceu, e não a da última graduação.',
                'Layout do Início e da ficha do aluno no celular corrigido.',
            ],
        ],
        [
            'version' => '0.5.0',
            'date' => '2026-11-18',
            'title' => 'Histórico e painel',
            'changes' => [
                'Graduação avulsa com confirmação quando o aluno ainda não está elegível.',
                'Ficha do aluno com histórico, eventos e identificação de quem graduou.',
                'Início com alertas de elegibilidade e progresso do próximo evento.',
            ],
        ],
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
