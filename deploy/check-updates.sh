#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

[[ "$(id -un)" == graduacao ]] || { echo 'Execute como usuário graduacao.' >&2; exit 1; }
[[ -f .env && -d .git ]] || { echo 'Aplicação ainda não foi configurada.' >&2; exit 1; }
[[ "$(git branch --show-current)" == main ]] || { echo 'A branch local não é main.' >&2; exit 1; }

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo 'Há alterações locais; atualização automática ignorada.' >&2
  exit 1
fi

git fetch --quiet origin +refs/heads/main:refs/remotes/origin/main
current="$(git rev-parse HEAD)"
remote="$(git rev-parse refs/remotes/origin/main)"
if [[ "$current" == "$remote" ]]; then
  echo 'Sem atualizações.'
  exit 0
fi

if ! git merge-base --is-ancestor "$current" "$remote"; then
  echo 'A branch remota divergiu; deploy automático interrompido.' >&2
  exit 1
fi

echo 'Nova versão encontrada; iniciando deploy.'
exec /usr/local/bin/graduacao-deploy
