#!/usr/bin/env bash
set -Eeuo pipefail
SCRIPT_FILE="$(readlink -f -- "${BASH_SOURCE[0]}")"
APP_DIR="$(cd "$(dirname "$SCRIPT_FILE")/.." && pwd)"
cd "$APP_DIR"
[[ "$(id -un)" == graduacao ]] || { echo 'Execute como usuário graduacao.' >&2; exit 1; }
[[ -f .env ]] || { echo '.env não encontrado; deploy interrompido.' >&2; exit 1; }
[[ -d .git ]] || { echo 'Repositório Git não encontrado.' >&2; exit 1; }
if ! git diff --quiet || ! git diff --cached --quiet; then echo 'Há alterações locais no servidor; deploy interrompido.' >&2; exit 1; fi
branch="$(git branch --show-current)"
[[ "$branch" == main ]] || { echo "Branch atual '$branch', esperada 'main'." >&2; exit 1; }
echo 'Gerando backup antes do deploy...'
php bin/backup.php
echo 'Baixando atualizações...'
git pull --ff-only origin main
echo 'Aplicando migrations...'
php bin/migrate.php
chmod 0600 .env
sudo -n /usr/bin/systemctl reload php8.3-fpm
echo 'Deploy concluído.'
