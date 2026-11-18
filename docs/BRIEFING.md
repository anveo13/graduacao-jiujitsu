# Briefing — Graduação Jiu-Jitsu

**Situação:** versão 0.1.0 publicada em `https://listagraduacao.duckdns.org/`. A versão 0.2.0 está em preparação local.
**Escrito em:** 17/11/2026, a partir da conversa feita dentro do projeto Carmelito's.
**Revisado em:** 18/11/2026 — 0.1.0 publicada; VM A1 Flex e subdomínio configurados.
**Primeiro evento real:** graduação de **04/12/2026**.

> **Para começar numa conversa nova** (Claude Code, Codex ou outro agente): abra esta pasta
> (`C:\Users\nan13\www\graduacao-jiujitsu`) e diga:
> *"Leia o docs/BRIEFING.md e continue da próxima etapa da seção 9."*
> Este arquivo é a **única fonte** do que fazer e de como trabalhar (seção 12).

---

## 1. O que é

Um sistema pequeno para o professor controlar as **graduações de jiu-jitsu**:

- cadastro dos alunos, com faixa e graus;
- lista de quem vai graduar num evento, com um "check" na hora;
- histórico de todas as graduações;
- aviso de quando cada aluno **pode ser graduado de novo** (tempo mínimo por faixa);
- alguns usuários, cada um com um perfil que define o que pode fazer.

Começou como uma lista de tarefas e virou um cadastro com histórico. Continua sendo um
sistema **simples**: poucas telas, feito para usar no **celular**, no tatame.

É um projeto **separado do Carmelito's e do MyMon**: outro repositório, outro banco, outro
servidor, outro endereço. Do MyMon vem só a **forma de trabalhar**: versões numeradas,
migrations, deploy por `git push` e convites por link.

---

## 2. Decisões já fechadas

| Assunto | Decisão |
|---|---|
| Linguagem | **Tudo em PHP**, sem framework (como o Carmelito's e o MyMon) |
| Banco | **MariaDB**, via PDO |
| Tela | HTML, CSS e JavaScript puros, **mobile-first** |
| Hospedagem | **Oracle Cloud Always Free + DuckDNS + Caddy**, numa **VM nova**, só deste projeto |
| Acesso | **Login próprio** (usuário e senha). Nada a ver com o login do Carmelito's ou do MyMon |
| Perfis | **Master**, **Administrador** e **Visualizador** (seção 3.6) |
| Usuários novos | Entram por **convite** (link de uso único, enviado pelo WhatsApp), criado pelo Master |
| Usuário inicial | `gbocidental`, perfil **Master**, criado por comando no servidor |
| Quem fez o quê | Os registros mostram "alterado por" e "graduado por" |
| Versões | Numeradas (0.1.0, 0.2.0…), com lista de novidades, como no MyMon |
| QA | **Sem etapa de QA** e sem documentos de QA: o projeto é simples. Ficam só os testes automáticos da regra de elegibilidade |
| Faixas | Branca, Laranja, Verde, Azul, Roxa, Marrom, Preta (sem cinza e amarela) |
| Faixa preta | Vai de **0 a 6 graus** |
| Tempo mínimo | **Só avisa, não bloqueia.** A decisão final é do professor |
| Hoje | **Todos estão elegíveis.** O tempo mínimo só conta a partir de graduações registradas no sistema |
| Graduação | Pode ser em **evento** (ex.: 04/12) ou **avulsa**, em qualquer dia |
| Riscados | Ficam no evento **para sempre**. Não existe botão "limpar graduados" |

---

## 3. Regras

### 3.1 Faixas e graus

- Ordem das faixas: **Branca → Laranja → Verde → Azul → Roxa → Marrom → Preta**.
- Faixas coloridas: de **0 a 4 graus**.
- Faixa preta: de **0 a 6 graus**.

### 3.2 Próxima graduação

O professor **sempre escolhe** o que o aluno vai receber. O sistema só sugere.

Opções ao definir a próxima graduação de um aluno:

- **+1, +2 ou +3 graus** — só aparecem as opções que cabem até o limite da faixa.
  Ex.: Branca 2 graus → pode escolher +1 (vai a 3) ou +2 (vai a 4). Não aparece +3.
- **Trocar de faixa** — o professor escolhe a faixa nova. O aluno entra nela com **0 graus**.

Sugestão automática (pode ser trocada):

- com **menos de 4 graus** → sugere **+1 grau**;
- com **4 graus** → sugere a **faixa seguinte**:
  Branca→Azul, Laranja→Verde, Verde→Azul, Azul→Roxa, Roxa→Marrom, Marrom→Preta.

Exemplos:

| Hoje | Escolha | Fica |
|---|---|---|
| Branca, 2 graus | +1 grau | Branca, 3 graus |
| Branca, 2 graus | +2 graus | Branca, 4 graus |
| Azul, 4 graus | trocar de faixa → Roxa | Roxa, 0 graus |
| Preta, 0 graus | +1 grau | Preta, 1 grau |

### 3.3 Tempo mínimo para graduar de novo (elegibilidade)

Depois de uma graduação, o aluno fica um tempo **sem poder graduar de novo**.
O tempo depende da **faixa em que ele ficou depois da graduação**:

| Faixa do aluno | Tempo mínimo até a próxima graduação |
|---|---|
| Branca | 2 meses |
| Laranja, Verde | 3 meses |
| Azul, Roxa, Marrom | 5 meses |
| Preta com 0, 1 ou 2 graus | 3 anos |
| Preta com 3 graus ou mais | 5 anos |

Como calcular:

```
elegível a partir de = data da última graduação + tempo mínimo da faixa atual
```

- Conta de **qualquer graduação**, seja grau ou troca de faixa.
- Quem recebeu +2 graus de uma vez fez **uma** graduação: o tempo é o mesmo.
- Meses e anos são de calendário: 04/12/2026 + 2 meses = 04/02/2027.
  Se o dia não existir no mês de destino (ex.: 31/12 + 2 meses), usa o último dia do mês.
- **Aluno sem nenhuma graduação registrada no sistema está elegível.**
  Não existe campo "data da última graduação" para preencher no cadastro.
- Preta no último grau (6): não há próxima graduação.

Exemplos:

| Graduação registrada | Ficou | Elegível a partir de |
|---|---|---|
| 04/12/2026 | Branca, 3 graus | 04/02/2027 |
| 04/12/2026 | Verde, 1 grau | 04/03/2027 |
| 04/12/2026 | Roxa, 0 graus (veio da Azul) | 04/05/2027 |
| 04/12/2026 | Preta, 0 graus (veio da Marrom) | 04/12/2029 |
| 04/12/2026 | Preta, 3 graus | 04/12/2031 |

**Só avisa.** Se alguém for graduar um aluno antes do prazo, o sistema mostra
"ainda não elegível, faltam X dias" e pede uma confirmação, mas deixa.

Esta regra deve ficar numa **função pura** (sem banco, sem tela), com testes automáticos
usando os exemplos da tabela acima. É a única regra "de conta" do sistema.

### 3.4 O "check" de graduado

Ao marcar um aluno como graduado:

1. grava a graduação no histórico (de qual faixa/grau, para qual, em que data);
2. grava **quem deu o check** ("graduado por");
3. **atualiza a faixa e os graus do aluno**;
4. a data vira a "última graduação" dele e o tempo mínimo começa a contar;
5. na lista do evento, a linha fica **riscada e vai para o fim**.

Os três perfis podem dar o check.

Deve existir um **desfazer** (clicou na pessoa errada): volta a faixa/grau anterior e
remove a graduação. Só vale para a graduação mais recente daquele aluno.

- **Master e Administrador** desfazem qualquer check.
- **Visualizador** só desfaz o check que **ele mesmo** deu.

### 3.5 Esconder (faltou no dia)

Na lista de um evento, "esconder" tira o aluno da lista **sem apagar** nada.
A graduação dele continua pendente. Um link "mostrar escondidos" traz de volta.

Se o evento passar, a graduação planejada fica parada no evento antigo. O botão
"levar para outro evento" fica para depois (seção 10).

### 3.6 Perfis

| Ação | Master | Administrador | Visualizador |
|---|---|---|---|
| Ver tudo (alunos, eventos, histórico, início) | sim | sim | sim |
| Dar o check de graduado | sim | sim | sim |
| Desfazer um check | qualquer um | qualquer um | só o próprio |
| Cadastrar, editar e desativar alunos | sim | sim | não |
| Criar e editar eventos | sim | sim | não |
| Adicionar aluno ao evento e escolher a próxima graduação | sim | sim | não |
| Esconder / mostrar escondidos | sim | sim | não |
| Graduação avulsa ("graduar agora") | sim | sim | não |
| Usuários e convites | sim | não | não |

- Existe **um único Master**: o `gbocidental`. Ele não pode ser rebaixado nem desativado,
  e não existe convite para Master.
- A permissão é conferida **no servidor**, em toda ação. Esconder o botão na tela não basta.
- Tudo que um usuário grava fica com o nome dele: os registros mostram embaixo
  "alterado por Fulano em dd/mm/aaaa" e, nas graduações, "graduado por Fulano".

### 3.7 Convites

Não existe cadastro aberto. Usuário novo só entra por convite, no mesmo esquema do MyMon:

1. O Master abre **Usuários › Novo convite**, escreve o nome da pessoa e escolhe o perfil
   (**Administrador** ou **Visualizador**).
2. O sistema gera um **link de uso único**, válido por **7 dias**, e mostra dois botões:
   **copiar o link** e **enviar pelo WhatsApp** (abre o WhatsApp com a mensagem pronta).
3. A pessoa abre o link e escolhe o **nome**, o **usuário** e a **senha**. Não pede e-mail.
4. A conta já nasce com o perfil definido no convite. O link deixa de valer.

Regras:

- O link traz um código aleatório longo. No banco fica só o **hash** do código.
- Convite vencido, usado ou cancelado mostra "convite indisponível", sem dar detalhes.
- O Master pode **cancelar** um convite que ainda não foi usado.
- A tela de aceitar convite tem limite de tentativas, como o login.
- Depois, o Master pode **trocar o perfil** ou **desativar** qualquer usuário (menos ele mesmo).

---

## 4. Telas

### 4.1 Login
Usuário e senha. Sem cadastro aberto: o Master é criado por comando no servidor e os
demais entram por convite.

### 4.2 Início
Três blocos:

1. **Já podem ser graduados** — alunos que foram graduados pelo sistema e cujo tempo
   mínimo acabou. Ex.: "Fulano já pode ser graduado (Branca 3 graus, desde 04/02)".
   O aviso fica até o aluno ser graduado ou entrar na lista de um evento.
   Aluno que nunca foi graduado pelo sistema **não** aparece aqui (senão, no começo,
   apareceria todo mundo).
2. **Ficam elegíveis nos próximos 30 dias** — para planejar.
3. **Próximo evento** — nome, data e progresso ("12 de 30 graduados").

### 4.3 Alunos
- Lista de todos, **separada por faixa**, com busca por nome.
- Cada aluno mostra: faixa, graus, última graduação e a situação
  (*elegível* ou *elegível a partir de dd/mm/aaaa*).
- **Cadastrar / editar**: nome, faixa atual, graus atuais.
- **Desativar** quem parou de treinar (some das listas, mantém o histórico).
- **Graduar agora** (graduação avulsa): escolhe o que o aluno recebe e registra na hora,
  sem precisar de evento.
- Abrindo um aluno: **histórico** de todas as graduações dele, com "graduado por",
  e embaixo do cadastro "alterado por".
- O Visualizador vê tudo, sem os botões de cadastrar, editar, desativar e graduar agora.

### 4.4 Graduações (eventos)
- Lista de eventos (nome e data). Criar evento novo.
- Dentro de um evento:
  - adicionar alunos e definir a próxima graduação de cada um;
  - lista **separada pela faixa atual**, com contador por faixa ("3 de 7");
  - linha: *João · Branca 2 graus → Branca 4 graus (+2)*;
  - **check** (ver 3.4), **esconder** (ver 3.5) e **desfazer**;
  - quem ainda não está elegível aparece com um aviso na linha;
  - linha riscada mostra "graduado por Fulano".
- Os riscados ficam no evento para sempre: o evento é o registro daquele dia.
- O Visualizador só vê a lista e dá o check (e desfaz o próprio).

### 4.5 Usuários (só o Master)
- Lista dos usuários: nome, usuário, perfil, ativo ou não.
- **Trocar o perfil** e **desativar / reativar**.
- **Novo convite** e lista dos convites pendentes, com **cancelar** (ver 3.7).

### 4.6 Aceitar convite (pública)
Aberta pelo link. Pede nome, usuário e senha (duas vezes). Ao concluir, já entra no sistema.

### 4.7 Meu perfil
Trocar o próprio nome e a própria senha.

### 4.8 Novidades
A versão atual aparece no rodapé. Tocando nela abre a lista de novidades de cada versão.

---

## 5. Banco de dados

Banco próprio (`graduacao`). Nomes de tabelas e colunas em inglês, textos da tela em
português (mesma convenção do MyMon).

### `users` — quem faz login
| Coluna | Tipo | Observação |
|---|---|---|
| id | INT UNSIGNED PK | |
| name | VARCHAR(100) | nome que aparece em "alterado por" |
| username | VARCHAR(60) UNIQUE | |
| password_hash | VARCHAR(255) | `password_hash()` |
| role | ENUM('master','admin','viewer') | |
| active | TINYINT(1) DEFAULT 1 | |
| created_at | TIMESTAMP | |

### `invitations` — convites
| Coluna | Tipo | Observação |
|---|---|---|
| id | INT UNSIGNED PK | |
| token_hash | CHAR(64) UNIQUE | SHA-256 do código do link; o código em si não é guardado |
| name | VARCHAR(100) | nome da pessoa convidada (para o Master reconhecer) |
| role | ENUM('admin','viewer') | perfil que a conta vai ter |
| created_by | INT UNSIGNED FK | o Master |
| expires_at | DATETIME | criação + 7 dias |
| accepted_at | DATETIME NULL | preenchido no aceite |
| accepted_user_id | INT UNSIGNED FK NULL | conta criada |
| cancelled_at | DATETIME NULL | |
| created_at | TIMESTAMP | |

### `login_attempts` — limite de tentativas
| Coluna | Tipo | Observação |
|---|---|---|
| id | INT UNSIGNED PK | |
| ip | VARCHAR(45) | |
| username | VARCHAR(60) | vazio nas tentativas de convite |
| kind | ENUM('login','invite') | |
| success | TINYINT(1) | |
| created_at | TIMESTAMP | |

### `students` — alunos
| Coluna | Tipo | Observação |
|---|---|---|
| id | INT UNSIGNED PK | |
| name | VARCHAR(150) | |
| belt | ENUM('white','orange','green','blue','purple','brown','black') | faixa atual |
| degrees | TINYINT UNSIGNED DEFAULT 0 | graus atuais |
| last_graduation_at | DATE NULL | NULL = nunca graduado pelo sistema = elegível |
| active | TINYINT(1) DEFAULT 1 | 0 = parou de treinar |
| created_by, updated_by | INT UNSIGNED FK NULL | quem cadastrou e quem alterou o cadastro por último |
| created_at, updated_at | TIMESTAMP | |

### `events` — eventos de graduação
| Coluna | Tipo | Observação |
|---|---|---|
| id | INT UNSIGNED PK | |
| name | VARCHAR(150) | ex.: "Graduação de fim de ano" |
| event_date | DATE | ex.: 2026-12-04 |
| created_by, updated_by | INT UNSIGNED FK NULL | |
| created_at, updated_at | TIMESTAMP | |

### `graduations` — planejadas e realizadas
Uma linha serve para as duas coisas: nasce **planejada** quando o aluno entra na lista de
um evento e vira **realizada** no check. A graduação avulsa já nasce realizada.

| Coluna | Tipo | Observação |
|---|---|---|
| id | INT UNSIGNED PK | |
| student_id | INT UNSIGNED FK | |
| event_id | INT UNSIGNED FK NULL | NULL = graduação avulsa |
| from_belt, from_degrees | | situação antes |
| to_belt, to_degrees | | o que recebe |
| status | ENUM('planned','done') | |
| hidden | TINYINT(1) DEFAULT 0 | faltou no dia |
| graduated_at | DATE NULL | preenchido no check |
| graduated_by | INT UNSIGNED FK NULL | quem deu o check |
| created_by, updated_by | INT UNSIGNED FK NULL | quem planejou e quem alterou por último |
| created_at, updated_at | TIMESTAMP | |

Regras do banco:
- um aluno só pode ter **uma** graduação planejada por evento;
- o check roda numa **transação**: muda `graduations` e `students` juntos;
- nada é apagado de verdade em graduação realizada, exceto pelo "desfazer";
- usuário nunca é apagado, só desativado (para o "alterado por" continuar valendo).

---

## 6. Tecnologia e organização do código

- **PHP 8.3** (o servidor usa 8.3; no PC há 8.5), sem framework e, a princípio, **sem Composer**.
- **MariaDB** via PDO, sempre com prepared statements.
- **Sem phpMyAdmin**: no servidor da Oracle as mudanças de banco são feitas por
  **migrations** (arquivos SQL numerados, aplicados por um comando). Isso substitui o
  "rodar o SQL no phpMyAdmin antes do deploy" que era necessário no Carmelito's.
- Senha do banco e outros segredos ficam num arquivo **`.env`**, fora do git.
- Proteção **CSRF** em todo formulário e em toda ação que grava.

Estrutura proposta (versão enxuta da do MyMon):

```
public/              única pasta visível na internet
  index.php          entrada do sistema (todas as páginas passam por aqui)
  assets/            css, js, imagens
src/
  bootstrap.php      carrega o .env, sessão, conexão com o banco
  auth.php           login, logout, "exige login", "exige perfil"
  csrf.php           token CSRF
  belts.php          faixas, graus e sugestão da próxima graduação
  eligibility.php    regra do tempo mínimo (função pura)
  students.php       consultas e gravações de alunos
  events.php         eventos e lista de graduação
  graduations.php    check, desfazer, graduação avulsa
  users.php          usuários: listar, trocar perfil, desativar
  invitations.php    convites: criar, cancelar, aceitar
config/
  releases.php       versões e novidades (mais nova primeiro)
views/               telas (login, início, alunos, eventos, usuários, convite, perfil)
migrations/          0001_inicial.sql, 0002_..., aplicadas em ordem
bin/
  migrate.php        aplica as migrations pendentes
  create-user.php    cria ou altera um usuário (pergunta a senha)
  backup.php         gera o backup do banco
tests/               testes da regra de elegibilidade
deploy/
  provision.sh       prepara o servidor (uma vez)
  deploy.sh          atualiza o sistema no servidor
docs/                este briefing e os guias
.env.example         modelo do .env, sem segredos
```

Por que `public/` separado: o Caddy só enxerga essa pasta, então `.env`, `src/`,
`migrations/` e `bin/` **nunca** ficam acessíveis pela internet.

Rodar no PC:

```
php bin/migrate.php
php bin/create-user.php
php -S localhost:8000 -t public public/index.php
```

### 6.1 Versões

Mesma ideia do MyMon, sem a parte de QA:

- cada entrega tem um número (0.1.0, 0.2.0…) e uma entrada em `config/releases.php`
  com data, título e a lista do que mudou, em texto simples;
- a versão atual aparece no rodapé e abre a tela Novidades (4.8);
- fechar uma versão = testes passando, `releases.php` atualizado, commit, tag `vX.Y.Z`
  e deploy;
- correção pequena sobe o último número (0.3.1).

---

## 7. Hospedagem: Oracle Cloud + DuckDNS (igual ao MyMon, em VM nova)

Como o MyMon está hoje (referência, lida de `C:\Users\nan13\www\MyMon`):

- VM Oracle **`VM.Standard.E2.1.Micro`** (Ubuntu 24.04, 1 GB de RAM + 2 GB de swap);
- **Caddy** (HTTPS automático), **PHP 8.3-FPM**, **MariaDB** só local;
- endereço `mymon-anveo.duckdns.org`, atualizado por um timer a cada 5 minutos;
- app em `/var/www/mymon`, rodando como o usuário `mymon`;
- atalhos `mymon-deploy` e `mymon-cli`; `ssh mymon` no PC;
- backup diário às 03:00;
- guia completo em `MyMon/docs/GUIA-DEPLOY.md` e script em `MyMon/deploy/provision.sh`.

Peças do projeto novo:

```
celular/PC --HTTPS--> NOME.duckdns.org --> IP da VM (Oracle)
                                          ├─ Caddy (certificado automático)
                                          │    └─ só mostra /var/www/graduacao/public
                                          ├─ PHP 8.3-FPM (usuário "graduacao")
                                          ├─ MariaDB (banco "graduacao", só local)
                                          └─ timers: backup diário · DuckDNS a cada 5 min

PC --git push--> GitHub --> servidor: graduacao-deploy (backup → git pull → migrate → reload)
```

### 7.1 Decisão: VM nova, só para este projeto

- A VM publicada usa `VM.Standard.A1.Flex` com **1 OCPU e 6 GB de memória**, em Ubuntu 24.04.
  A VCN é `vcn-graduacao`, com a subnet pública `public subnet-vcn-graduacao`.
- Reaproveita quase inteiro o `provision.sh` do MyMon, trocando nomes
  (`mymon` → `graduacao`) e tirando o que este projeto não usa.
- **Nenhum risco para o MyMon**, que guarda dados financeiros. Nada da VM do MyMon é tocado.
- Custo: mais um servidor para acompanhar (e-mails da Oracle, regra de VM ociosa).

### 7.2 Passo a passo

Segue o `MyMon/docs/GUIA-DEPLOY.md`, com os nomes trocados. Resumo:

1. **DuckDNS**: entrar com a conta do GitHub e criar um subdomínio novo
   (cada conta tem direito a 5). O token é o mesmo da conta.
2. **Oracle**: VM Ubuntu 24.04 `VM.Standard.A1.Flex` (1 OCPU / 6 GB), IP público e chave SSH baixada.
3. **Rede da Oracle**: liberar as portas 80 e 443 na Security List.
4. **DuckDNS**: apontar o subdomínio para o IP da VM.
5. **SSH**: guardar a chave em `C:\Users\nan13\.ssh\` e criar o atalho `ssh graduacao`.
6. **`provision.sh`**: instala e configura tudo no servidor (PHP, MariaDB, Caddy,
   firewall, SSH só por chave, fail2ban, backup, DuckDNS) e mostra a senha do banco
   **uma vez**.
7. **GitHub**: cadastrar a *deploy key* do servidor no repositório (somente leitura).
8. **Primeira instalação**: criar o `.env`, rodar `migrate` e `create-user`
   (cria o `gbocidental` como Master; a senha é digitada na hora).
9. **Atualizações**: `git push` no PC e `graduacao-deploy` no servidor
   (ou deploy automático pelo GitHub Actions, como no MyMon).

Os passos 1 a 5 e o 7 são feitos pelo dono, no navegador. O agente guia e confere.

### 7.3 O que muda em relação ao Carmelito's

| | Carmelito's | Este projeto |
|---|---|---|
| Publicar | cPanel › Deploy HEAD Commit | `git push` + `graduacao-deploy` |
| Mudança no banco | colar SQL no phpMyAdmin | migration aplicada pelo deploy |
| Senha do banco | `config/database.php` | `.env` |
| Backup | manual | automático, todo dia |
| HTTPS | da hospedagem | Caddy, automático |

---

## 8. Segurança (o mínimo que não pode faltar)

- Login obrigatório para tudo, menos a tela de login e a de aceitar convite.
- Perfil conferido **no servidor** em toda ação (seção 3.6).
- Senhas com `password_hash()` / `password_verify()`. Mínimo de 8 caracteres.
- **Nenhuma senha escrita** em arquivo, commit, migration ou documento. A do Master é
  digitada no `create-user.php` e deve ser trocada no primeiro acesso.
- Limite de tentativas de login e de convite (ex.: 5 erros em 15 minutos).
- Convite: código aleatório longo, só o hash no banco, uso único, vence em 7 dias.
- Sessão com cookie `HttpOnly`, `Secure` e `SameSite=Lax`. Usuário desativado perde a sessão.
- CSRF em toda ação que grava (inclusive o check e o esconder).
- Só `public/` exposto; `.env` com permissão 600 no servidor.
- Banco acessível só de dentro da VM (porta 3306 fechada).

---

## 9. Ordem de construção

Primeiro o básico, depois a publicação. A partir daí o dono acompanha tudo pelo endereço
na internet, e cada etapa seguinte sobe como uma versão nova.

| Etapa | Entrega | Como saber que terminou |
|---|---|---|
| **0.1.0 Básico** | Pastas, git, `.env`, conexão com o banco, `migrate.php`, migration inicial (todas as tabelas da seção 5), login e logout, `create-user.php`, os 3 perfis conferidos no servidor, layout base para celular, rodapé com a versão | no PC: `php bin/migrate.php` cria as tabelas e o `gbocidental` entra e sai |
| **Publicação** | Repositório no GitHub, VM, DuckDNS, `provision.sh`, `deploy.sh`, backup | o sistema abre pelo endereço `.duckdns.org` com cadeado e o login funciona |
| **0.2.0** | Regra de elegibilidade + testes. Tela Alunos: listar por faixa, buscar, cadastrar, editar, desativar | os exemplos da seção 3.3 passam nos testes; cadastra a turma inteira pelo celular |
| **0.3.0** | Eventos e lista de graduação: adicionar alunos, escolher a próxima graduação, check, esconder, desfazer | simula o evento de 04/12 do começo ao fim |
| **0.4.0** | Usuários e convites: tela Usuários, convite por link/WhatsApp, aceitar convite, Meu perfil | um Visualizador convidado entra, dá um check e não consegue editar nada |
| **0.5.0** | Graduação avulsa, histórico do aluno e tela Início com os avisos | gradua alguém fora de evento, vê no histórico e o aviso aparece quando o prazo vence |

**Prazo:** para usar em 04/12, precisam estar no ar o Básico, a Publicação, a 0.2.0 e a
0.3.0. No dia do evento só o Master usa; os convites (0.4.0) entram depois.
A 0.5.0 pode esperar: o tempo mínimo mais curto é de 2 meses, então os primeiros avisos
só aparecem em fevereiro.

---

## 10. Fora do escopo por enquanto

- Botão "levar para outro evento" para quem faltou.
- Botão "limpar graduados" (os riscados ficam no evento).
- Aviso por WhatsApp ou e-mail (o aviso é só na tela inicial; o WhatsApp serve só para
  enviar o link do convite).
- E-mail dos usuários, "esqueci a senha" (o Master gera um convite novo ou a senha é
  trocada pelo `create-user.php`), passkey / Face ID.
- Foto do aluno, idade, turma, presença, mensalidade.
- Relatórios e impressão.
- Etapa e documentos de QA.

---

## 11. Dúvidas

### Resolvidas em 17/11/2026

| Dúvida | Resposta |
|---|---|
| VM nova ou a mesma do MyMon? | **VM nova** |
| Nome do repositório | `anveo13/graduacao-jiujitsu` |
| Faixa preta vai até qual grau? | **6** |
| Sugestão de faixa seguinte | Branca→Azul e Verde→Azul estão certos |
| Quem vai usar? | Vários usuários, em 3 perfis, por convite (seções 3.6 e 3.7) |
| Riscados ficam no evento para sempre? | **Sim** |
| Quem faltou e o evento passou | Fica parado no evento antigo; botão de levar para outro evento fica para depois |
| Primeiro usuário | `gbocidental`, Master |
| Visualizador pode desfazer check? | Só o que ele mesmo deu |
| Convite pede e-mail? | Não. Nome, usuário e senha; vale 7 dias |
| Pode haver outro Master? | Não. Só o `gbocidental` |
| Convites antes de 04/12? | Não. No dia só o Master usa |
| Um agente ou equipe? QA? | **Um agente só, sem QA** |
| Subdomínio no DuckDNS | `listagraduacao.duckdns.org` (VM `VM.Standard.A1.Flex`, 1 OCPU / 6 GB, rede `vcn-graduacao`) |

### Em aberto

Nenhuma.

---

## 12. Como trabalhar neste projeto (para qualquer agente: Claude Code, Codex…)

Este briefing basta para executar. Não há outro documento de instruções.

**Antes de começar**

- Leia este arquivo inteiro. Veja em que etapa da seção 9 o projeto está
  (`git log`, `config/releases.php` e o que já existe nas pastas).
- Faça **uma etapa por vez**, na ordem da seção 9.

**Siga sozinho, sem interromper**, enquanto o trabalho estiver coberto por este briefing:
criar arquivos, escrever código, migrations e testes, rodar no PC, corrigir o que quebrar,
fazer commit ao fim de cada parte que funciona.

**Pare e pergunte ao dono só quando:**

- uma regra de negócio não estiver escrita aqui ou der para entender de dois jeitos
  (**não invente**: registre a dúvida na seção 11 e pergunte);
- a ação apagar dados, for difícil de desfazer ou mexer em algo fora deste projeto
  (o MyMon, o Carmelito's, a VM do MyMon);
- precisar de senha, token, chave ou de algo que só o dono faz no navegador
  (Oracle, DuckDNS, GitHub);
- for publicar em produção pela primeira vez ou rodar uma migration que altera dados
  já existentes no servidor;
- quiser trocar algo das decisões fechadas (seção 2) ou acrescentar algo da seção 10.

**Convenções**

- Código, tabelas e colunas em inglês; textos da tela, documentos e commits em português.
- `declare(strict_types=1);` em todo arquivo PHP de `src/`.
- PDO com prepared statements, sempre. CSRF em toda ação que grava.
- Permissão de perfil conferida no servidor (seção 3.6).
- Toda saída na tela passa por `htmlspecialchars`.
- Datas em `America/Sao_Paulo`; no PHP, `DateTimeImmutable`.
- Segredos só no `.env`. Nunca escrever senha em arquivo, commit ou log.
- Mudança de banco só por migration nova; nunca editar uma migration já aplicada.
- Sem framework, sem Composer, sem etapa de build. Simples primeiro.

**Ao fechar uma etapa**

1. Rodar os testes (`php tests/run.php`) e conferir a tela na largura de celular.
2. Atualizar `config/releases.php` e, se algo mudou de regra, este briefing.
3. Commit, tag da versão e deploy (depois da etapa de Publicação).
4. Dizer ao dono, em poucas linhas, o que entrou e o que ele pode testar no endereço.
