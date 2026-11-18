#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=/var/www/graduacao
APP_USER=graduacao
[[ $EUID -eq 0 ]] || { echo 'Execute com sudo.' >&2; exit 1; }
[[ -d "$APP_DIR/.git" && -f "$APP_DIR/deploy/check-updates.sh" ]] || {
  echo "Repositório ou scripts ausentes em $APP_DIR." >&2
  exit 1
}

runuser -u "$APP_USER" -- git -C "$APP_DIR" config core.filemode false
chown "$APP_USER:$APP_USER" "$APP_DIR/deploy/deploy.sh" "$APP_DIR/deploy/check-updates.sh"
chmod 0755 "$APP_DIR/deploy/deploy.sh" "$APP_DIR/deploy/check-updates.sh"
ln -sfn "$APP_DIR/deploy/deploy.sh" /usr/local/bin/graduacao-deploy
ln -sfn "$APP_DIR/deploy/check-updates.sh" /usr/local/bin/graduacao-deploy-check

cat > /etc/systemd/system/graduacao-deploy-check.service <<'EOF'
[Unit]
Description=Verifica atualizações do sistema Graduação Jiu-Jitsu
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=graduacao
Group=graduacao
WorkingDirectory=/var/www/graduacao
ExecStart=/usr/local/bin/graduacao-deploy-check
EOF

cat > /etc/systemd/system/graduacao-deploy-check.timer <<'EOF'
[Unit]
Description=Verifica atualizações de Graduação Jiu-Jitsu a cada cinco minutos

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min
Persistent=true

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now graduacao-deploy-check.timer
systemctl start graduacao-deploy-check.service
echo 'Deploy automático ativado; verifica a branch main a cada cinco minutos.'
