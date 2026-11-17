# Publicação na Oracle Cloud

Este guia prepara a VM exclusiva do Graduação Jiu-Jitsu. A publicação inicial deve ser
acompanhada pelo dono, pois exige criar recursos na Oracle e configurar o DuckDNS.

## 1. DuckDNS

1. Entre em [DuckDNS](https://www.duckdns.org/) com a conta do GitHub.
2. Crie um subdomínio livre, por exemplo `graduacao-gbocidental.duckdns.org`.
3. Deixe a página aberta para obter o token da conta.

## 2. VM e rede Oracle

1. Crie uma VM **Always Free-eligible**, Ubuntu 24.04, `VM.Standard.E2.1.Micro`, com IP público.
2. Baixe e guarde a chave SSH privada no PC.
3. Na Security List da subnet, permita TCP 22 (idealmente só do seu IP), 80 e 443.
4. Na página do DuckDNS, aponte o subdomínio para o IP público da VM.

## 3. Copiar o provisionador e preparar a VM

No PowerShell, copie o script para a VM (ajuste o IP e o caminho da chave):

```powershell
scp -i C:\Users\SEU_USUARIO\.ssh\graduacao.key deploy/provision.sh ubuntu@IP_DA_VM:/tmp/provision.sh
```

Conecte por SSH e rode o script. Ele pedirá o token DuckDNS sem exibi-lo:

```bash
sudo bash /tmp/provision.sh --domain graduacao-gbocidental.duckdns.org --repo git@github.com:anveo13/graduacao-jiujitsu.git
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
do projeto. Abra `https://graduacao-gbocidental.duckdns.org` e confirme o login.

## 6. Atualizações e backup

Depois de enviar uma alteração para `main`, conecte-se à VM e rode `graduacao-deploy`.
O comando gera um backup antes de atualizar o código, aplica migrations e recarrega PHP-FPM.
Backups compactados ficam em `/var/backups/graduacao`, com retenção de sete dias; o timer
roda diariamente às 03:00 no fuso de São Paulo. O timer DuckDNS atualiza o IP a cada cinco
minutos.

Oracle também exige a regra de rede para 80/443; o firewall da VM não substitui essa regra.
