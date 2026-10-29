#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$root/appinfo/info.xml" | head -n1)"
out="$root/build/booktailor-$version.tar.gz"

cd "$root"
npm run build

mkdir -p "$root/build"
rm -f "$out"
tar -czf "$out" \
	appinfo \
	lib \
	l10n \
	img \
	templates \
	js \
	css \
	README.md \
	LICENSE \
	CHANGELOG.md \
	composer.json \
	openapi.json

echo "Created $out"
