#!/bin/bash
set -euo pipefail

SMB_HOST="192.168.1.165"
SMB_SHARE="Documents/ingest"
SMB_USER="islay"
MOUNT_POINT="/mnt/paperless-ingest"
CREDENTIALS_FILE="/etc/samba/credentials.paperless"

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this script with sudo." >&2
    exit 1
fi

apt-get update
apt-get install -y cifs-utils sane-utils netpbm ghostscript

mkdir -p "$MOUNT_POINT"

if [ ! -f "$CREDENTIALS_FILE" ]; then
    read -rp "SMB password for $SMB_USER: " -s SMB_PASSWORD
    echo
    mkdir -p "$(dirname "$CREDENTIALS_FILE")"
    cat > "$CREDENTIALS_FILE" <<EOF
username=$SMB_USER
password=$SMB_PASSWORD
EOF
    chmod 600 "$CREDENTIALS_FILE"
fi

REAL_USER="${SUDO_USER:-$(logname)}"
REAL_UID=$(id -u "$REAL_USER")
REAL_GID=$(id -g "$REAL_USER")

FSTAB_LINE="//$SMB_HOST/$SMB_SHARE $MOUNT_POINT cifs credentials=$CREDENTIALS_FILE,uid=$REAL_UID,gid=$REAL_GID,iocharset=utf8,vers=3.0 0 0"

if ! grep -qF "$MOUNT_POINT" /etc/fstab; then
    echo "$FSTAB_LINE" >> /etc/fstab
fi

mount -a

chmod +x "$(dirname "$0")/scanner.sh"

echo "Done. /mnt/paperless-ingest contents:"
ls -la "$MOUNT_POINT"
