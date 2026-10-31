#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version="${1:-}"

if ! [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "usage: $0 X.Y.Z" >&2
	exit 2
fi

cd "$root"

node - "$version" <<'NODE'
const fs = require('node:fs')
const version = process.argv[2]

for (const file of ['package.json', 'package-lock.json']) {
	const data = JSON.parse(fs.readFileSync(file, 'utf8'))
	data.version = version
	if (data.packages && data.packages['']) {
		data.packages[''].version = version
	}
	fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`)
}

const infoFile = 'appinfo/info.xml'
const info = fs.readFileSync(infoFile, 'utf8')
if (!/<version>[^<]+<\/version>/.test(info)) {
	throw new Error('could not find <version> in appinfo/info.xml')
}
fs.writeFileSync(infoFile, info.replace(/<version>[^<]+<\/version>/, `<version>${version}</version>`))
NODE

echo "Set version to ${version} in appinfo/info.xml, package.json, and package-lock.json"
