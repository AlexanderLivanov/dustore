#!/usr/bin/env bash
# Тянет БД с прода в ЛОКАЛЬНУЮ и обезличивает. Запуск из Git Bash: bash tools/pull-db.sh
# Настройки — в tools/pull-db.env (в git не попадает), пример:
#   SSH_TARGET=user@dustore.ru
#   REMOTE_DB=dustore
#   LOCAL_DB=dustore_local
#   MYSQL_BIN=/c/xampp/mysql/bin
#   SKIP_TABLES="dustore.sessions dustore.logs"   # тяжёлые/ненужные, через пробел
# Пароль БД на сервере — в ~/.my.cnf ([client] user/password), не здесь.
set -euo pipefail
cd "$(dirname "$0")/.."
source tools/pull-db.env
MYSQL_BIN="${MYSQL_BIN:-/c/xampp/mysql/bin}"
IGN=""; for t in ${SKIP_TABLES:-}; do IGN="$IGN --ignore-table=$t"; done

echo ">> дамп $SSH_TARGET:$REMOTE_DB -> $LOCAL_DB"
"$MYSQL_BIN/mysql" -uroot -e "CREATE DATABASE IF NOT EXISTS \`$LOCAL_DB\` CHARACTER SET utf8mb4"
ssh "$SSH_TARGET" "mysqldump --single-transaction --quick --no-tablespaces --routines $IGN $REMOTE_DB | gzip" \
  | gunzip | "$MYSQL_BIN/mysql" -uroot "$LOCAL_DB"

echo ">> обезличивание"
DB_NAME="$LOCAL_DB" php tools/anonymize.php
echo ">> миграции"
DB_NAME="$LOCAL_DB" php tools/migrate.php
echo "Готово."
