#!/usr/bin/env bash
# Instala/atualiza a partir de um ZIP do projeto. Prefira o fluxo via git (DEPLOY_VPS.md).
set -euo pipefail
ZIP="${1:-}"
[[ -n "$ZIP" && -f "$ZIP" ]] || { echo "Uso: sudo bash install_from_zip.sh /caminho/pservice.zip"; exit 1; }
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
unzip -q "$ZIP" -d "$TMP"
SRC="$(find "$TMP" -maxdepth 2 -type f -name composer.json -printf '%h\n' | head -1)"
[[ -n "$SRC" ]] || { echo "composer.json não encontrado no ZIP."; exit 1; }
SOURCE_DIR="$SRC" bash "$SRC/deploy/install_ubuntu.sh"
