#!/usr/bin/env bash
#
# VENTIQ's morning security check. Plain bash, so it runs even when the app
# doesn't. It checks:
#   1. PHP (command line and FPM) against the oldest patched release of
#      each PHP branch;
#   2. the PHP packages in composer.lock (composer audit);
#   3. the JavaScript packages in package-lock.json (npm audit);
#   4. which commit the server is running, and whether it's behind GitHub.
#
# Writes storage/logs/security-audit-YYYY-MM-DD.log, emails it when
# AUDIT_MAIL_TO is set and `mail` is installed, and exits 1 when something
# needs fixing (so cron or systemd show it as failed).
#
#   scripts/security-audit.sh                     # from the app's folder
#   AUDIT_MAIL_TO=you@example.com scripts/security-audit.sh
#
# Cron, every morning at 06:00:
#   0 6 * * * cd /var/www/ventiq && AUDIT_MAIL_TO=you@example.com scripts/security-audit.sh >/dev/null 2>&1

set -uo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "$APP_DIR" || exit 2

# The oldest release of each PHP branch with the October 2026 fixes
# (CVE-2026-109618, CVE-2026-109619). Raise these when PHP announces new ones.
PHP_MINIMUMS="${PHP_MINIMUMS:-8.2.35 8.3.36 8.4.27 8.5.12}"
NPM_LEVEL="${NPM_LEVEL:-high}"   # npm findings at or above this count as a problem

LOG_DIR="$APP_DIR/storage/logs"
mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/security-audit-$(date +%F).log"
problems=0

say() { echo "$*" | tee -a "$LOG"; }
section() { say ""; say "== $* =="; }

: > "$LOG"
say "VENTIQ security check on $(hostname), $(date '+%Y-%m-%d %H:%M %Z')"

# ── 1. PHP ──────────────────────────────────────────────────────────────
php_ok() {   # php_ok <version>: is it at or above its branch's patched release?
    local v="$1" branch min
    branch="$(echo "$v" | cut -d. -f1,2)"
    min="$(for m in $PHP_MINIMUMS; do [[ "$m" == "$branch".* ]] && echo "$m"; done)"
    if [[ -z "$min" ]]; then
        say "PHP $v: no patched release known for $branch (unsupported branch?)"; return 1
    fi
    if [[ "$(printf '%s\n%s\n' "$min" "$v" | sort -V | head -1)" == "$min" ]]; then
        say "PHP $v: ok (patched from $min)"; return 0
    fi
    say "PHP $v: UPDATE NEEDED, to $min or later"; return 1
}

section "PHP"
cli="$(php -r 'echo PHP_VERSION;' 2>/dev/null)"
if [[ -n "$cli" ]]; then
    say "Command line:"; php_ok "$cli" || problems=$((problems + 1))
else
    say "php not found"; problems=$((problems + 1))
fi
# The website runs on PHP-FPM, which can be a different build from the command line.
for fpm in $(compgen -c | grep -E '^php-fpm[0-9.]*$' | sort -u); do
    v="$("$fpm" -v 2>/dev/null | head -1 | awk '{print $2}')"
    [[ -n "$v" ]] && { say "$fpm:"; php_ok "$v" || problems=$((problems + 1)); }
done

# ── 2. PHP packages ─────────────────────────────────────────────────────
section "PHP packages (composer audit)"
if command -v composer >/dev/null 2>&1; then
    # --locked: what composer.lock says, whether or not vendor/ matches it.
    out="$(COMPOSER_ALLOW_SUPERUSER=1 composer audit --locked --no-interaction --format=plain 2>&1)"
    code=$?
    echo "$out" | grep -vE 'plugins have been disabled|COMPOSER_ALLOW_SUPERUSER|Do not run Composer as root' | tee -a "$LOG"
    [[ $code -ne 0 ]] && problems=$((problems + 1))
    # vendor/ that doesn't match the lock file means `composer install` wasn't run after a deploy.
    # Production installs without dev packages: compare the same way it was installed.
    nodev=""; grep -q '"dev": false' vendor/composer/installed.json 2>/dev/null && nodev="--no-dev"
    if [[ -f vendor/composer/installed.json ]] && ! COMPOSER_ALLOW_SUPERUSER=1 composer install --dry-run $nodev --no-interaction 2>&1 | grep -q 'Nothing to install, update or remove'; then
        say "vendor/ doesn't match composer.lock: run \`composer install --no-dev --optimize-autoloader\`."
        problems=$((problems + 1))
    fi
else
    say "composer not found: skipped"
fi

# ── 3. JavaScript packages ──────────────────────────────────────────────
section "JavaScript packages (npm audit, $NPM_LEVEL and above)"
if command -v npm >/dev/null 2>&1 && [[ -f package-lock.json ]]; then
    npm audit --omit=dev --audit-level="$NPM_LEVEL" 2>&1 | tail -25 | tee -a "$LOG"
    [[ ${PIPESTATUS[0]} -ne 0 ]] && problems=$((problems + 1))
else
    say "npm or package-lock.json not found: skipped"
fi

# ── 4. The code that's running ──────────────────────────────────────────
section "Code"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    branch="$(git rev-parse --abbrev-ref HEAD)"
    say "Running $branch at $(git log -1 --format='%h, %cd' --date=short)"
    if git fetch -q origin "$branch" 2>/dev/null; then
        behind="$(git rev-list --count HEAD..origin/"$branch" 2>/dev/null || echo 0)"
        if [[ "$behind" -gt 0 ]]; then
            say "$behind commit(s) behind origin/$branch: deploy them (fixes may be among them)."
            problems=$((problems + 1))
        else
            say "Up to date with origin/$branch."
        fi
    else
        say "Couldn't reach GitHub to compare."
    fi
fi

# ── The verdict ─────────────────────────────────────────────────────────
section "Result"
if [[ $problems -eq 0 ]]; then
    say "All clear."
else
    say "$problems thing(s) to fix. Full log: $LOG"
fi

if [[ -n "${AUDIT_MAIL_TO:-}" ]] && command -v mail >/dev/null 2>&1; then
    subject="VENTIQ security check: $([[ $problems -eq 0 ]] && echo 'all clear' || echo "$problems to fix") ($(hostname))"
    mail -s "$subject" "$AUDIT_MAIL_TO" < "$LOG"
fi

# Keep a month of logs.
find "$LOG_DIR" -name 'security-audit-*.log' -mtime +31 -delete 2>/dev/null

[[ $problems -eq 0 ]]
