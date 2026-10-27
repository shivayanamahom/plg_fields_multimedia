#!/usr/bin/env bash
# Собирает установочный архив Joomla: dist/plg_fields_multimedia-<версия>.zip
# Запуск из любого места:  tools/build.sh
set -euo pipefail

cd "$(dirname "$0")/.."

VERSION=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' multimedia.xml | head -1)
OUT="dist/plg_fields_multimedia-${VERSION}.zip"

mkdir -p dist
rm -f "$OUT"

# Манифест лежит в корне архива; служебные файлы репозитория не включаются.
zip -X -q -r "$OUT" multimedia.xml services src params tmpl layouts media language LICENSE \
  -x '*.DS_Store' -x '__MACOSX/*'

echo "Готово: $OUT ($(du -h "$OUT" | cut -f1))"
