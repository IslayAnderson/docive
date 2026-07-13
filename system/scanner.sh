#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODE="Lineart"
RESOLUTION="600"
OUTPUT=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --mode) MODE="$2"; shift 2 ;;
        --resolution) RESOLUTION="$2"; shift 2 ;;
        --output) OUTPUT="$2"; shift 2 ;;
        *) echo "Unknown argument: $1" >&2; exit 1 ;;
    esac
done

if [ -z "$OUTPUT" ]; then
    mkdir -p "$SCRIPT_DIR/../scans"
    SCAN_DIR="$(cd "$SCRIPT_DIR/../scans" && pwd)"
    OUTPUT="$SCAN_DIR/scan-$(date +%Y%m%d-%H%M%S).pdf"
fi

DEVICE_LINE="$(scanimage -L | grep '^device ' | head -1)"
DEVICE="${DEVICE_LINE#*\`}"
DEVICE="${DEVICE%%\'*}"

if [ -z "$DEVICE" ]; then
    echo "No scanner detected (scanimage -L found nothing)" >&2
    exit 1
fi

scanimage -d "$DEVICE" --resolution "$RESOLUTION" --mode "$MODE" -x 210 -y 297 | pnmtops -imagewidth 11.3 -imageheight 11.7 -nocenter | ps2pdf - "$OUTPUT"

echo "$OUTPUT"
