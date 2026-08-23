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

# The version lives in three places and the self-updater compares it against the
# manifest, so a build whose header disagrees with its own manifest either offers
# an update forever or never offers one. Fail the build rather than ship that.
header_version="$( sed -n 's/^ \* Version:[[:space:]]*\(.*\)$/\1/p' "$ROOT/captaincore.php" | head -1 | tr -d '[:space:]' )"
const_version="$( sed -n "s/^define( 'CAPTAINCORE_VERSION', '\(.*\)' );$/\1/p" "$ROOT/captaincore.php" | head -1 )"
manifest_version="$( sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$ROOT/manifest.json" | head -1 )"

[ -n "$header_version" ] || { echo "FAIL: could not read the plugin header version" >&2; exit 1; }
if [ "$header_version" != "$const_version" ] || [ "$header_version" != "$manifest_version" ]; then
	echo "FAIL: version mismatch — header=$header_version CAPTAINCORE_VERSION=$const_version manifest=$manifest_version" >&2
	exit 1
fi
if ! grep -q "/v${header_version}/" "$ROOT/manifest.json"; then
	echo "FAIL: manifest download_url does not point at the v${header_version} release asset" >&2
	exit 1
fi
echo "Version $header_version consistent across header, constant and manifest."

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
