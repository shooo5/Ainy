#!/usr/bin/env bash
# Ainy UI 禁止パターン簡易チェック（Phase 5 ガード）
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FAIL=0

check() {
  local label="$1"
  local pattern="$2"
  local path="$3"
  local hits
  hits=$(rg -n "$pattern" "$path" --glob '!docs/**' --glob '!tools/**' 2>/dev/null | head -8 || true)
  if [ -n "$hits" ]; then
    echo "FAIL: $label"
    echo "$hits"
    FAIL=1
  fi
}

echo "Checking Ainy UI patterns..."

if rg -n '\.page-[^ ]+ \.btn-(primary|secondary)\s*\{' "$ROOT/assets/css/pages" --glob '!design-reference.css' 2>/dev/null | head -1 | grep -q .; then
  echo "WARN: pages CSS may still override .btn variants (review manually)"
fi

if [ "$FAIL" -eq 0 ]; then
  echo "OK: quick scan complete"
fi
exit "$FAIL"
