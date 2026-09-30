#!/usr/bin/env bash
# Regenerates database/sql/spims_install.sql (schema + base seed, no users) from the migrations.
# Needs a local MySQL/MariaDB where the .env DB user can create the database "spims_export".
set -euo pipefail
cd "$(dirname "$0")/.."
DB=spims_export
mysql -u"${MYSQL_ROOT_USER:-root}" -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
DB_DATABASE=$DB php artisan migrate:fresh --seed --force
{
  sed -n '1,/^SET NAMES/p' database/sql/spims_install.sql   # keep the instructions header
  mysqldump -u"${MYSQL_ROOT_USER:-root}" --single-transaction --skip-comments --no-tablespaces --skip-add-locks \
    --skip-lock-tables --default-character-set=utf8mb4 $DB \
  | sed -e '1{/sandbox mode/d}' -e 's/longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`\([a-z_]*\)`))/json DEFAULT NULL/'
} > /tmp/spims_install.sql && mv /tmp/spims_install.sql database/sql/spims_install.sql
mysql -u"${MYSQL_ROOT_USER:-root}" -e "DROP DATABASE $DB;"
echo "Updated database/sql/spims_install.sql"
