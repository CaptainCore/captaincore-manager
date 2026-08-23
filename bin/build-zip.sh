#!/usr/bin/env bash
#
# Builds the release zip: captaincore-manager.zip, next to the plugin directory.
#
# This exists so the exclusion list lives in git rather than in a release
# runbook. The deploy path used to zip the whole clone with only .git and
# .DS_Store excluded, which shipped planning docs (roadmap.md, to-do.md,
# STATUS.md) to production.
#
# What is excluded and why:
#   .git, .gitignore, .DS_Store          repository plumbing
#   .claude/                             agent config, dev only
#   bin/                                 the release toolchain, dev only
#   roadmap.md, to-do.md                 planning docs, dev only
#   user-account-security-plan.md        planning doc, dev only
#   templates/core/STATUS.md          living build log, dev only
#   templates/core/V1-PLAN.md         release checklist, dev only
#
# vendor/ SHIPS (the optimized composer classmap is required at runtime).
# templates/ SHIPS (core.php + core/ UI). manifest.json SHIPS (updater
# fallback). changelog.md and api-docs.md SHIP (public docs).
#
# Usage: bin/build-zip.sh   → ../captaincore-manager.zip (from wp-content/plugins/)
set -euo pipefail

HERE="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
ROOT="$( cd "$HERE/.." && pwd )"
NAME="$( basename "$ROOT" )"
PARENT="$( dirname "$ROOT" )"
OUT="$PARENT/captaincore-manager.zip"

command -v zip >/dev/null || { echo "zip is required" >&2; exit 1; }

rm -f "$OUT"
cd "$PARENT"
zip -r -q -X "$OUT" "$NAME" \
	-x "$NAME/.git/*" \
	-x "$NAME/.gitignore" \
	-x "$NAME/.claude/*" \
	-x "$NAME/bin/*" \
	-x "$NAME/roadmap.md" \
	-x "$NAME/to-do.md" \
	-x "$NAME/user-account-security-plan.md" \
	-x "$NAME/templates/core/STATUS.md" \
	-x "$NAME/templates/core/V1-PLAN.md" \
	-x "*.DS_Store"

# Assert rather than trust: a silently fattened zip is invisible until someone
# downloads it, and a silently EMPTIED one bricks the plugin.
#
# Listed ONCE into a variable rather than piped per check. Under `set -o
# pipefail`, `unzip -l … | grep -q …` reports FAILURE on a match: grep exits at
# the first hit, unzip takes SIGPIPE, and the pipeline inherits its status.
listing="$( unzip -l "$OUT" )"

leaked="$( grep -cE "$NAME/(\.git|\.claude|bin)/|$NAME/(roadmap|to-do|user-account-security-plan)\.md|core/(STATUS|V1-PLAN)\.md" <<< "$listing" || true )"
[ "$leaked" = "0" ] || {
	echo "FAIL: $leaked dev file(s) leaked into the zip" >&2
	grep -E "$NAME/(\.git|\.claude|bin)/|$NAME/(roadmap|to-do|user-account-security-plan)\.md|core/(STATUS|V1-PLAN)\.md" <<< "$listing" | head >&2
	exit 1
}

for required in "$NAME/captaincore.php" "$NAME/manifest.json" "$NAME/app/DB.php" "$NAME/vendor/composer/autoload_classmap.php" "$NAME/includes/class-captaincore-manager-updater.php" "$NAME/templates/core.php" "$NAME/templates/core/app.html"; do
	grep -q " $required\$" <<< "$listing" || { echo "FAIL: $required missing from the zip" >&2; exit 1; }
done

printf '%s\n' "$OUT"
printf '  %s bytes, %s files\n' "$( wc -c < "$OUT" | tr -d ' ' )" "$( unzip -l "$OUT" | tail -1 | awk '{print $2}' )"
printf '  sha256 %s\n' "$( shasum -a 256 "$OUT" | cut -d' ' -f1 )"
