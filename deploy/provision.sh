#!/usr/bin/env bash
set -Eeuo pipefail
APP=graduacao; APP_USER=graduacao; APP_DIR=/var/www/graduacao; ETC_DIR=/etc/graduacao; DOMAIN=; REPO=
usage(){ echo "Uso: sudo bash provision.sh --domain nome.duckdns.org --repo git@github.com:anveo13/graduacao-jiujitsu.git"; }
fail(){ echo "ERRO: $*" >&2; exit 1; }
while (($#)); do case "$1" in --domain) DOMAIN="${2:-}"; shift 2;; --repo) REPO="${2:-}"; shift 2;; -h|--help) usage; exit 0;; *) fail "opção desconhecida $1";; esac; done
[[ $EUID -eq 0 ]] || fail "execute com sudo"
[[ "$DOMAIN" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.duckdns\.org$ ]] || fail "informe domínio DuckDNS válido"
[[ "$REPO" =~ ^git@github\.com:[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\.git$ ]] || fail "informe repositório SSH do GitHub"
. /etc/os-release; [[ "${ID:-}" == ubuntu && "${VERSION_ID:-}" == 24.04 ]] || fail "requer Ubuntu 24.04"
if [[ ! -f /etc/graduacao/duckdns.curl ]]; then
 if [[ -z "${DUCKDNS_TOKEN:-}" ]]; then read -r -s -p 'Token do DuckDNS: ' DUCKDNS_TOKEN; printf '\n'; fi
 [[ -n "$DUCKDNS_TOKEN" ]] || fail 'token DuckDNS vazio'
fi
export DEBIAN_FRONTEND=noninteractive
echo "iptables-persistent iptables-persistent/autosave_v4 boolean false" | debconf-set-selections
echo "iptables-persistent iptables-persistent/autosave_v6 boolean false" | debconf-set-selections
apt-get update; apt-get -y upgrade
apt-get install -y ca-certificates curl git openssh-client gnupg openssl iptables-persistent netfilter-persistent fail2ban python3-systemd mariadb-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml
timedatectl set-timezone America/Sao_Paulo
getent group graduacao-web >/dev/null || groupadd --system graduacao-web
id "$APP_USER" >/dev/null 2>&1 || useradd --system --create-home --home-dir "/home/$APP_USER" --shell /bin/bash "$APP_USER"
install -d -o "$APP_USER" -g "$APP_USER" -m 0750 "$APP_DIR"
install -d -m 0750 "$ETC_DIR"; install -d -o "$APP_USER" -g "$APP_USER" -m 0700 /var/backups/graduacao
if [[ ! -f "$ETC_DIR/duckdns.curl" ]]; then
 cat > "$ETC_DIR/duckdns.curl" <<EOF
url = "https://www.duckdns.org/update?domains=${DOMAIN%.duckdns.org}&token=$DUCKDNS_TOKEN&ip="
silent
show-error
EOF
 chmod 0600 "$ETC_DIR/duckdns.curl"
fi
cat > /etc/mysql/mariadb.conf.d/60-graduacao.cnf <<'EOF'
[mysqld]
bind-address=127.0.0.1
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
innodb_buffer_pool_size=64M
max_connections=30
performance_schema=OFF
EOF
systemctl enable --now mariadb; systemctl restart mariadb
if [[ ! -s "$ETC_DIR/db-password" ]]; then openssl rand -hex 32 > "$ETC_DIR/db-password"; chmod 0600 "$ETC_DIR/db-password"; fi
DB_PASSWORD="$(cat "$ETC_DIR/db-password")"
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS graduacao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'graduacao'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER 'graduacao'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON graduacao.* TO 'graduacao'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
cat > /etc/php/8.3/fpm/pool.d/graduacao.conf <<'EOF'
[graduacao]
user = graduacao
group = graduacao
listen = /run/php/php8.3-fpm-graduacao.sock
listen.owner = graduacao
listen.group = graduacao-web
listen.mode = 0660
pm = ondemand
pm.max_children = 5
pm.process_idle_timeout = 30s
pm.max_requests = 500
php_admin_value[date.timezone] = America/Sao_Paulo
php_admin_flag[display_errors] = off
php_admin_flag[log_errors] = on
EOF
systemctl enable --now php8.3-fpm; systemctl restart php8.3-fpm
if ! command -v caddy >/dev/null 2>&1; then
 apt-get install -y debian-keyring debian-archive-keyring apt-transport-https
 curl -1sLf https://dl.cloudsmith.io/public/caddy/stable/gpg.key | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
 curl -1sLf https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt -o /etc/apt/sources.list.d/caddy-stable.list
 apt-get update; apt-get install -y caddy
fi
cat > /etc/caddy/Caddyfile <<EOF
$DOMAIN {
 root * $APP_DIR/public
 encode zstd gzip
 php_fastcgi unix//run/php/php8.3-fpm-graduacao.sock
 file_server
 header {
  X-Content-Type-Options nosniff
  X-Frame-Options DENY
  Referrer-Policy strict-origin-when-cross-origin
  -Server
 }
}
EOF
systemctl enable --now caddy; caddy validate --config /etc/caddy/Caddyfile; systemctl reload caddy
usermod -aG graduacao-web caddy
systemctl restart caddy
install -d -o "$APP_USER" -g "$APP_USER" -m 0700 "/home/$APP_USER/.ssh"
if [[ ! -f "/home/$APP_USER/.ssh/id_ed25519" ]]; then runuser -u "$APP_USER" -- ssh-keygen -q -t ed25519 -N '' -C graduacao-deploy -f "/home/$APP_USER/.ssh/id_ed25519"; fi
cat > "/home/$APP_USER/.ssh/config" <<'EOF'
Host github.com
 HostName github.com
 User git
 IdentityFile ~/.ssh/id_ed25519
 IdentitiesOnly yes
 StrictHostKeyChecking accept-new
EOF
chown "$APP_USER:$APP_USER" "/home/$APP_USER/.ssh/config"; chmod 0600 "/home/$APP_USER/.ssh/config"
if [[ ! -d "$APP_DIR/.git" ]]; then runuser -u "$APP_USER" -- git clone "$REPO" "$APP_DIR" || echo "Clone pendente: cadastre a chave pública no GitHub e rode novamente."; fi
if [[ -d "$APP_DIR/.git" && ! -f "$APP_DIR/.env" ]]; then
 cat > "$APP_DIR/.env" <<EOF
APP_ENV=production
APP_TIMEZONE=America/Sao_Paulo
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=graduacao
DB_USER=graduacao
DB_PASSWORD=$DB_PASSWORD
EOF
 chown "$APP_USER:$APP_USER" "$APP_DIR/.env"; chmod 0600 "$APP_DIR/.env"
fi
cat > /etc/sudoers.d/graduacao-fpm <<'EOF'
graduacao ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
EOF
chmod 0440 /etc/sudoers.d/graduacao-fpm; visudo -cf /etc/sudoers.d/graduacao-fpm
cat > /etc/systemd/system/graduacao-backup.service <<EOF
[Unit]
Description=Backup do banco Graduação Jiu-Jitsu
After=mariadb.service
[Service]
Type=oneshot
User=graduacao
Group=graduacao
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php $APP_DIR/bin/backup.php
EOF
cat > /etc/systemd/system/graduacao-backup.timer <<'EOF'
[Unit]
Description=Backup diário de Graduação Jiu-Jitsu
[Timer]
OnCalendar=*-*-* 03:00:00
Persistent=true
[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload; systemctl enable --now graduacao-backup.timer
# Firewall: a imagem da Oracle usa iptables com um REJECT no fim do INPUT (não usar ufw).
# Abre 80/443 antes do REJECT e salva, mantendo as regras existentes.
abrir_porta(){
 if iptables -C INPUT -p "$1" -m state --state NEW -m "$1" --dport "$2" -j ACCEPT 2>/dev/null; then return; fi
 local pos; pos="$(iptables -L INPUT --line-numbers -n | awk '$2 == "REJECT" {print $1; exit}')"
 if [[ -n "$pos" ]]; then iptables -I INPUT "$pos" -p "$1" -m state --state NEW -m "$1" --dport "$2" -j ACCEPT
 else iptables -A INPUT -p "$1" -m state --state NEW -m "$1" --dport "$2" -j ACCEPT; fi
}
systemctl stop fail2ban 2>/dev/null || true
abrir_porta tcp 80; abrir_porta tcp 443; abrir_porta udp 443
netfilter-persistent save >/dev/null
# SSH só por chave (só se o admin já tiver chave, para não trancar ninguém do lado de fora)
ADMIN_KEYS="$(getent passwd "${SUDO_USER:-ubuntu}" | cut -d: -f6)/.ssh/authorized_keys"
if [[ -s "$ADMIN_KEYS" ]]; then
 cat > /etc/ssh/sshd_config.d/01-graduacao.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
PubkeyAuthentication yes
MaxAuthTries 4
EOF
 if sshd -t; then systemctl is-active --quiet ssh && systemctl reload ssh || true
 else rm -f /etc/ssh/sshd_config.d/01-graduacao.conf; echo "AVISO: configuração SSH inválida; mantida a original."; fi
fi
cat > /etc/fail2ban/jail.d/graduacao.local <<'EOF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
[sshd]
enabled = true
backend = systemd
EOF
systemctl enable fail2ban >/dev/null 2>&1; systemctl restart fail2ban
cat > /etc/systemd/system/graduacao-duckdns.service <<'EOF'
[Unit]
Description=Atualiza o IP DuckDNS para Graduação Jiu-Jitsu
[Service]
Type=oneshot
ExecStart=/usr/bin/curl --config /etc/graduacao/duckdns.curl
EOF
cat > /etc/systemd/system/graduacao-duckdns.timer <<'EOF'
[Unit]
Description=Atualiza o DuckDNS a cada cinco minutos
[Timer]
OnBootSec=2min
OnUnitActiveSec=5min
Persistent=true
[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload; systemctl enable --now graduacao-duckdns.timer
if [[ -d "$APP_DIR/.git" ]]; then bash "$APP_DIR/deploy/install-auto-deploy.sh"; fi
echo "Provisionamento concluído para $DOMAIN"
echo "Cadastre a chave pública abaixo como Deploy Key (somente leitura) no GitHub:"
cat "/home/$APP_USER/.ssh/id_ed25519.pub"
