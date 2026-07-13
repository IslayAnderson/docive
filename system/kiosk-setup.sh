#!/bin/bash
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this script with sudo." >&2
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(dirname "$SCRIPT_DIR")"
WEB_ROOT="$REPO_DIR/web"
APP_URL="http://localhost:8000"
KIOSK_USER="kiosk"
KIOSK_HOME="/home/$KIOSK_USER"

apt-get update
apt-get install -y xserver-xorg xinit x11-xserver-utils php-cli curl gnupg

if ! command -v google-chrome-stable &>/dev/null; then
    curl -fsSL https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor -o /usr/share/keyrings/google-chrome.gpg
    echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome.gpg] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list
    apt-get update
    apt-get install -y google-chrome-stable
fi

if ! id "$KIOSK_USER" &>/dev/null; then
    useradd -m -s /bin/bash "$KIOSK_USER"
fi
for group in scanner plugdev dialout; do
    getent group "$group" &>/dev/null && usermod -aG "$group" "$KIOSK_USER"
done

# Grant traverse access into the repo's parent directory if it's owned by a
# restrictive group (e.g. the repo lives inside a regular user's home dir).
REPO_PARENT_GROUP="$(stat -c '%G' "$(dirname "$REPO_DIR")" 2>/dev/null || true)"
if [ -n "$REPO_PARENT_GROUP" ] && getent group "$REPO_PARENT_GROUP" &>/dev/null; then
    usermod -aG "$REPO_PARENT_GROUP" "$KIOSK_USER"
fi

# docive.service runs as $KIOSK_USER, which only has group access to a repo
# owned by whoever ran git clone - grant it write access to the specific
# directories build_assets.php generates into at runtime.
for dir in "$WEB_ROOT/src/css" "$WEB_ROOT/src/js" "$WEB_ROOT/src/scss"; do
    mkdir -p "$dir"
    [ -n "$REPO_PARENT_GROUP" ] && chgrp "$REPO_PARENT_GROUP" "$dir"
    chmod g+ws "$dir"
done

mkdir -p /etc/systemd/system/getty@tty1.service.d
cat > /etc/systemd/system/getty@tty1.service.d/autologin.conf <<EOF
[Service]
ExecStart=
ExecStart=-/sbin/agetty --autologin $KIOSK_USER --noclear %I \$TERM
EOF
systemctl enable getty@tty1.service

cat > "$KIOSK_HOME/.bash_profile" <<'EOF'
if [ -z "${DISPLAY:-}" ] && [ "$(tty)" = "/dev/tty1" ]; then
    exec startx -- -nocursor
fi
EOF

cat > "$KIOSK_HOME/.xinitrc" <<EOF
#!/bin/sh
xset -dpms
xset s off
xset s noblank

OUTPUT=\$(xrandr | awk '/ connected/{print \$1; exit}')
[ -n "\$OUTPUT" ] && xrandr --output "\$OUTPUT" --auto

RESOLUTION=\$(xrandr | awk '/\*/{print \$1; exit}')
WIDTH="\${RESOLUTION%x*}"
HEIGHT="\${RESOLUTION#*x}"

for i in \$(seq 1 30); do
    curl -s -o /dev/null "$APP_URL" && break
    sleep 1
done

exec google-chrome-stable --kiosk --start-fullscreen --window-position=0,0 --window-size="\${WIDTH:-1920},\${HEIGHT:-1080}" --force-device-scale-factor=1 --ozone-platform=x11 --noerrdialogs --disable-infobars --no-first-run --disable-session-crashed-bubble --disable-translate "$APP_URL"
EOF
chmod +x "$KIOSK_HOME/.xinitrc"
chown "$KIOSK_USER:$KIOSK_USER" "$KIOSK_HOME/.bash_profile" "$KIOSK_HOME/.xinitrc"

cat > /etc/systemd/system/docive.service <<EOF
[Unit]
Description=Docive kiosk web app
After=network.target

[Service]
ExecStart=/usr/bin/php -S localhost:8000 -t $WEB_ROOT
Restart=always
User=$KIOSK_USER

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now docive.service

if [ -f /etc/default/grub ]; then
    if grep -q '^GRUB_TIMEOUT=' /etc/default/grub; then
        sed -i 's/^GRUB_TIMEOUT=.*/GRUB_TIMEOUT=0/' /etc/default/grub
    else
        echo 'GRUB_TIMEOUT=0' >> /etc/default/grub
    fi
    update-grub
fi

systemctl disable cups.service 2>/dev/null || true
systemctl disable bluetooth.service 2>/dev/null || true
systemctl disable ModemManager.service 2>/dev/null || true

echo "Done. The docive service is running at $APP_URL."
echo "Reboot to test autologin + kiosk launch: sudo reboot"
