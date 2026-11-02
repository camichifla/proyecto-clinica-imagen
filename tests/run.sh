#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
PHP=${PHP:-/opt/lampp/bin/php}
command -v "$PHP" >/dev/null || PHP=php

fd -e php -E vendor -E storage . -X "$PHP" -l | { rg -v '^No syntax errors' || true; }
fd -e js -E vendor . -X node --check
node --test tests/
"$PHP" -d zend.assertions=1 -d assert.exception=1 tests/php-checks.php
