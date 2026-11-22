#!/bin/bash
# Christmas Lidar Counter - install on a Raspberry Pi.
#   sudo ./install.sh [--port 80] [--password 'secret'] [--reboot] [--no-uart] [--no-start] [--update]
# --update is what the page's Update button uses: keeps the port and password, asks nothing, never restarts the Pi.
# Safe to run again: it updates the program and keeps your settings and counts.
set -euo pipefail

PORT=""; PASSWORD=""; UART=1; REBOOT=0; START=1; UPDATE=0
while [ $# -gt 0 ]; do
    case "$1" in
        --port) PORT="${2:?--port needs a number}"; shift 2 ;;
        --password) PASSWORD="${2:?--password needs a value}"; shift 2 ;;
        --no-uart) UART=0; shift ;;
        --reboot) REBOOT=1; shift ;;
        --no-start) START=0; shift ;;
        --update) UPDATE=1; UART=0; shift ;;
        -h|--help) sed -n '2,4p' "$0"; exit 0 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done
if [ -z "$PORT" ] && [ "$UPDATE" = 1 ]; then
    PORT="$(sed -n 's/^CC_WEB_PORT=//p' /etc/car-counter/car-counter.env 2>/dev/null | head -n1)"; PORT="${PORT:-80}"
fi
if [ -z "$PORT" ]; then
    PORT=80
    # Port 80 is the normal web port. If something else already uses it (and it is not our own page), use 8080 instead.
    if (exec 3<>/dev/tcp/127.0.0.1/80) 2>/dev/null && ! systemctl is-active --quiet car-counter-web 2>/dev/null; then
        PORT=8080; echo "Port 80 is already in use on this Pi, so the page will use port 8080."
    fi
fi
case "$PORT" in *[!0-9]*) echo "--port must be a number" >&2; exit 2 ;; esac

if [ "$(id -u)" != "0" ]; then echo "Run with sudo:  sudo $0" >&2; exit 1; fi
SRC="$(cd "$(dirname "$0")" && pwd)"
APP=/opt/car-counter
USER_NAME=carcounter
ETC=/etc/car-counter
DATA=/var/lib/car-counter
LOGDIR=/var/log/car-counter

NEED=""
command -v python3 >/dev/null 2>&1 || NEED="$NEED python3"
command -v php >/dev/null 2>&1 || NEED="$NEED php-cli"
command -v sudo >/dev/null 2>&1 || NEED="$NEED sudo"
if [ -n "$NEED" ] && command -v apt-get >/dev/null 2>&1; then
    echo "== Installing packages:$NEED (this can take several minutes on a slow Pi - please wait)"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y $NEED ca-certificates
else
    echo "== Required packages are already installed"
fi
command -v python3 >/dev/null || { echo "python3 is required" >&2; exit 1; }
command -v php >/dev/null || { echo "php-cli is required (sudo apt install php-cli)" >&2; exit 1; }
SYSTEMCTL="$(command -v systemctl || true)"
[ -n "$SYSTEMCTL" ] || { echo "systemd is required" >&2; exit 1; }

echo "== Program files -> $APP"
mkdir -p "$APP"
rm -rf "$APP/counter" "$APP/web" "$APP/systemd" "$APP/scripts"
cp -a "$SRC/counter" "$SRC/web" "$SRC/systemd" "$SRC/scripts" "$APP/"
cp -a "$SRC/set-password.sh" "$SRC/VERSION" "$APP/"
[ -f "$SRC/CHANGELOG.md" ] && cp -a "$SRC/CHANGELOG.md" "$APP/"
find "$APP" -name '__pycache__' -prune -exec rm -rf {} + 2>/dev/null || true
chmod +x "$APP"/*.sh "$APP"/scripts/*.sh "$APP"/counter/*.py 2>/dev/null || true
chown -R root:root "$APP"
chmod 755 "$APP/scripts/wifi_helper.py" "$APP/scripts/updater.py" "$APP/scripts/web_watchdog.py" "$APP/scripts/scheduled_reboot.py"

echo "== Service account and folders"
id "$USER_NAME" >/dev/null 2>&1 || useradd --system --home-dir "$DATA" --shell /usr/sbin/nologin "$USER_NAME"
usermod -a -G dialout "$USER_NAME"
mkdir -p "$ETC" "$DATA" "$LOGDIR"
chown "$USER_NAME:$USER_NAME" "$DATA" "$LOGDIR"; chmod 750 "$DATA"
echo "d /run/car-counter 0775 $USER_NAME $USER_NAME -" > /etc/tmpfiles.d/car-counter.conf
systemd-tmpfiles --create /etc/tmpfiles.d/car-counter.conf

ENVF="$ETC/car-counter.env"
[ -f "$ENVF" ] || : > "$ENVF"
setenv() { grep -q "^$1=" "$ENVF" && sed -i "s|^$1=.*|$1=$2|" "$ENVF" || echo "$1=$2" >> "$ENVF"; }
setenv CC_DATA_DIR "$DATA"
setenv CC_RUN_DIR /run/car-counter
setenv CC_LOG_FILE "$LOGDIR/car-counter.log"
setenv CC_ERR_FILE "$LOGDIR/car-counter.err"
setenv CC_WEB_PASSWORD_FILE "$ETC/web.pass"
setenv CC_WEB_PORT "$PORT"
setenv CC_SYSTEMCTL "$SYSTEMCTL"
chmod 644 "$ENVF"

echo "== Web page password"
install -m 755 "$APP/set-password.sh" /usr/local/bin/car-counter-passwd
if [ ! -s "$ETC/web.pass" ]; then
    GENERATED=0
    if [ -z "$PASSWORD" ]; then
        if [ -t 0 ]; then
            read -r -s -p "Choose a password for the settings page (Enter = make one up): " PASSWORD; echo
        fi
        if [ -z "$PASSWORD" ]; then PASSWORD="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 12)"; GENERATED=1; fi
    fi
    printf '%s' "$PASSWORD" | /usr/local/bin/car-counter-passwd --stdin
    [ "$GENERATED" = 1 ] && echo "   Generated password: $PASSWORD   (user: admin)   - write it down; change it with: sudo car-counter-passwd"
else
    echo "   Keeping the existing password (change it with: sudo car-counter-passwd)"
fi

echo "== Permission for the web page to restart the counter, change Wi-Fi and install updates"
cat > /etc/sudoers.d/car-counter <<SUDO
$USER_NAME ALL=(root) NOPASSWD: $SYSTEMCTL start car-counter, $SYSTEMCTL stop car-counter, $SYSTEMCTL restart car-counter, $SYSTEMCTL reboot
$USER_NAME ALL=(root) NOPASSWD: $APP/scripts/wifi_helper.py *
$USER_NAME ALL=(root) NOPASSWD: $APP/scripts/updater.py apply
SUDO
chmod 440 /etc/sudoers.d/car-counter
if command -v visudo >/dev/null 2>&1 && ! visudo -cf /etc/sudoers.d/car-counter >/dev/null; then
    rm -f /etc/sudoers.d/car-counter; echo "sudoers check failed - Restart buttons will not work" >&2
fi

echo "== systemd services"
for u in car-counter car-counter-web; do
    sed "s|@APP@|$APP|g" "$APP/systemd/$u.service" > "/etc/systemd/system/$u.service"
done
sed "s|@APP@|$APP|g" "$APP/systemd/car-counter-watchdog.service" > /etc/systemd/system/car-counter-watchdog.service
cp "$APP/systemd/car-counter-watchdog.timer" /etc/systemd/system/car-counter-watchdog.timer
"$SYSTEMCTL" daemon-reload
"$SYSTEMCTL" enable car-counter car-counter-web >/dev/null 2>&1
"$SYSTEMCTL" enable car-counter-watchdog.timer >/dev/null 2>&1

NEED_REBOOT=0
if [ "$UART" = 1 ]; then
    echo "== Switching on the Pi's serial port (so the lidar can use the GPIO pins)"
    BOOTDIR=/boot/firmware; [ -f "$BOOTDIR/config.txt" ] || BOOTDIR=/boot
    BEFORE="$(cat "$BOOTDIR/config.txt" "$BOOTDIR/cmdline.txt" 2>/dev/null | md5sum)"
    "$APP/scripts/enable_uart.sh" || echo "Could not change the serial settings - use raspi-config > Interface Options > Serial Port" >&2
    AFTER="$(cat "$BOOTDIR/config.txt" "$BOOTDIR/cmdline.txt" 2>/dev/null | md5sum)"
    [ "$BEFORE" != "$AFTER" ] && NEED_REBOOT=1
    grep -Eq 'console=(serial0|ttyAMA0|ttyS0),' /proc/cmdline 2>/dev/null && [ -f "$BOOTDIR/config.txt" ] && NEED_REBOOT=1
fi

if [ "$START" = 1 ]; then
    "$SYSTEMCTL" restart car-counter car-counter-web
    "$SYSTEMCTL" start car-counter-watchdog.timer >/dev/null 2>&1 || true
    sleep 2
    for u in car-counter car-counter-web; do
        printf '   %-18s %s\n' "$u" "$("$SYSTEMCTL" is-active "$u" || true)"
    done
fi

TZ_NOW="$(timedatectl show -p Timezone --value 2>/dev/null || cat /etc/timezone 2>/dev/null || echo unknown)"
NTP_OK="$(timedatectl show -p NTPSynchronized --value 2>/dev/null || echo unknown)"
IPS="$(hostname -I 2>/dev/null | tr -s ' ' | sed 's/ $//')"
echo
echo "Done. Open the settings page:"
SUFFIX=""; [ "$PORT" = 80 ] || SUFFIX=":$PORT"
for ip in $IPS; do echo "   http://$ip$SUFFIX/"; done
echo "   http://$(hostname).local$SUFFIX/   (user: admin)"
echo
echo "Check the Pi's clock - show hours use the Pi's own time zone."
echo "   Time zone now: $TZ_NOW   (change: sudo timedatectl set-timezone America/Chicago)   Clock synced: $NTP_OK"
if [ "$NEED_REBOOT" = 1 ]; then
    echo
    echo "One last thing: the Pi must restart once to finish switching on the serial port."
    if [ "$REBOOT" = 1 ]; then
        echo "Restarting in 5 seconds..."; sleep 5; "$SYSTEMCTL" reboot
    elif [ -t 0 ]; then
        read -r -p "Restart now? [Y/n] " ANS
        case "$ANS" in n|N|no|NO) echo "OK - restart later with:  sudo reboot  (or use the button on the settings page)." ;; *) "$SYSTEMCTL" reboot ;; esac
    else
        echo "Restart it with:  sudo reboot   (or use the button on the settings page)."
    fi
fi
echo "Only one device should upload car counts to your website. If another counter (for example an FPP plugin) also uploads, turn its Website Sync off."
