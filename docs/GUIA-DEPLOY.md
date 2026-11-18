# Publicação na Oracle Cloud

Este guia prepara a VM exclusiva do Graduação Jiu-Jitsu. A publicação inicial deve ser
acompanhada pelo dono, pois exige criar recursos na Oracle e configurar o DuckDNS.

## 1. DuckDNS

1. Entre em [DuckDNS](https://www.duckdns.org/) com a conta do GitHub.
2. Crie um subdomínio livre, por exemplo `listagraduacao.duckdns.org`.
3. Deixe a página aberta para obter o token da conta.

## 2. VM e rede Oracle

A rede é criada **antes** da VM, igual foi feito no MyMon. Nada da rede ou da VM do MyMon é
tocado: este projeto tem rede própria.

### 2.1 Criar a rede (VCN e subnets)

No console da Oracle: menu ☰ › **Networking** › **Virtual cloud networks**.

1. Confira o **Compartment** (o mesmo do MyMon, normalmente o da raiz da conta).
2. **Actions** › **Start VCN Wizard** › **Create VCN with Internet Connectivity** › **Start VCN Wizard**.
3. Preencha:
   - **VCN name:** `vcn-graduacao`
   - **VCN IPv4 CIDR block:** deixe o padrão (`10.0.0.0/16`)
   - **Public Subnet CIDR block:** deixe o padrão (`10.0.0.0/24`)
   - **Private Subnet CIDR block:** deixe o padrão (`10.0.1.0/24`)
4. **Next** › **Create**. Ele cria a VCN, as duas subnets, o Internet Gateway e as rotas.
   **Deu certo se** todos os itens ficarem verdes › **View VCN**.

> Se aparecer erro de limite de VCN: a conta grátis permite poucas VCNs. Nesse caso, pule
> este passo e, na criação da VM, escolha a VCN e a **subnet pública do MyMon**. Funciona
> igual e não muda nada no MyMon (as portas 22, 80 e 443 já estão abertas lá).

### 2.2 Liberar as portas 80 e 443

1. Dentro da `vcn-graduacao` › **Subnets** › clique em **public subnet-vcn-graduacao**.
2. Aba **Security** (ou **Security Lists**) › **Default Security List for vcn-graduacao**.
3. **Security rules** (ou **Ingress Rules**) › **Add Ingress Rules**:
   - **Stateless:** desmarcado
   - **Source Type:** CIDR · **Source CIDR:** `0.0.0.0/0`
   - **IP Protocol:** TCP · **Destination Port Range:** `80`
   - **+ Another Ingress Rule** › igual, com porta `443`
   - **Add Ingress Rules**.

**Deu certo se** a lista mostrar regras para 22 (já existia), 80 e 443 com origem `0.0.0.0/0`.

> Não abra a porta 3306 (banco).

### 2.3 Criar a VM

Menu ☰ › **Compute** › **Instances** › **Create instance**.

1. **Name:** `graduacao`.
2. **Placement:** deixe o primeiro **Availability Domain**. Se der "Out of capacity", volte
   aqui e troque de AD.
3. **Image and shape** › **Edit**:
   - **Image** › **Change image** › **Ubuntu** › **Canonical Ubuntu 24.04** (a normal, não a
     "Minimal") › **Select image**.
   - **Shape** › **Change shape** › `VM.Standard.A1.Flex` › configure **1 OCPU** e **6 GB** ›
     confira o selo **Always Free-eligible** › **Select shape**.
4. **Networking**:
   - **Select existing virtual cloud network** › `vcn-graduacao`
   - **Select existing subnet** › **public subnet-vcn-graduacao** (a pública, não a privada)
   - marque **Automatically assign public IPv4 address**.
5. **Add SSH keys** › **Generate a key pair for me** › **Save private key** e **Save public key**.
   A chave privada é a única forma de entrar no servidor: guarde uma cópia no gerenciador de
   senhas.
6. **Boot volume:** deixe o padrão.
7. **Create**. Em 1 a 3 minutos o estado fica **Running** (verde).
8. Copie o **Public IP address** da página da instância.

**Se der "LimitExceeded" / "service limit":** a conta já usa as duas `E2.1.Micro` grátis.
Confira em **Compute › Instances** (em todos os compartments) se há alguma VM sobrando,
inclusive parada ou de teste, e avise antes de apagar qualquer coisa.

### 2.4 Salvar a chave no PC (PowerShell)

```powershell
Move-Item "$env:USERPROFILE\Downloads\ssh-key-*.key" "C:\Users\nan13\.ssh\graduacao.key"
icacls "C:\Users\nan13\.ssh\graduacao.key" /inheritance:r
icacls "C:\Users\nan13\.ssh\graduacao.key" /grant:r "${env:USERNAME}:(R)"
```

Se houver mais de um `ssh-key-*.key` em Downloads, troque o `*` pelo nome do arquivo de hoje
(a chave do MyMon já está em `.ssh`, não deve estar em Downloads).

### 2.5 DuckDNS e atalho `ssh graduacao`

1. Em <https://www.duckdns.org>, na linha `listagraduacao`, cole o IP da VM em
   **current ip** › **update ip**.
2. Crie o atalho e teste:

```powershell
Add-Content -Path "$env:USERPROFILE\.ssh\config" -Value @"

Host graduacao
    HostName listagraduacao.duckdns.org
    User ubuntu
    IdentityFile C:/Users/nan13/.ssh/graduacao.key
"@
ssh graduacao
```

Na primeira vez, responda `yes`. **Deu certo se** o prompt virar `ubuntu@graduacao:~$`.
Para sair, `exit`.

## 3. Copiar o provisionador e preparar a VM

No PowerShell, dentro da pasta do projeto, copie o script para a VM:

```powershell
scp deploy/provision.sh graduacao:/tmp/provision.sh
```

Conecte com `ssh graduacao` e rode o script. Ele pedirá o token DuckDNS sem exibi-lo:

```bash
sudo bash /tmp/provision.sh --domain listagraduacao.duckdns.org --repo git@github.com:anveo13/graduacao-jiujitsu.git
```

O script instala PHP 8.3-FPM, MariaDB, Caddy, firewall e os timers de backup e DuckDNS.
Ele também cria uma chave SSH própria do app e imprime somente a chave pública.

## 4. Deploy key do GitHub

No repositório `anveo13/graduacao-jiujitsu`, abra **Settings → Deploy keys → Add deploy key**.
Cole a chave pública que o provisionador imprimiu e deixe a permissão de escrita desmarcada.
Na VM, rode novamente o mesmo comando `sudo bash /tmp/provision.sh ...` para clonar o app.

## 5. Primeira instalação

Depois que o clone terminar, na VM:

```bash
sudo -u graduacao -H php /var/www/graduacao/bin/migrate.php
sudo -u graduacao -H php /var/www/graduacao/bin/create-user.php
```

O segundo comando cria `gbocidental` como Master e solicita a senha sem gravá-la em arquivo
do projeto. Abra `https://listagraduacao.duckdns.org` e confirme o login.

## 6. Atualizações e backup

Depois de enviar uma alteração para `main`, conecte-se à VM e rode `graduacao-deploy`.
O comando gera um backup antes de atualizar o código, aplica migrations e recarrega PHP-FPM.
Backups compactados ficam em `/var/backups/graduacao`, com retenção de sete dias; o timer
roda diariamente às 03:00 no fuso de São Paulo. O timer DuckDNS atualiza o IP a cada cinco
minutos.

Oracle também exige a regra de rede para 80/443; o firewall da VM não substitui essa regra.
