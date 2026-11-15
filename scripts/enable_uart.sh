#!/bin/bash
# Frees the Raspberry Pi's GPIO serial port (pins 8 / 10) for the lidar.
# install.sh runs this for you; it is safe to run again (it changes nothing if the port is already set up).
# The Pi needs one restart after the first time.
set -e
if [ "$(id -u)" != "0" ]; then echo "Run with sudo." >&2; exit 1; fi

# CC_BOOT_DIR lets the tests point at a fake boot folder.
BOOT="${CC_BOOT_DIR:-}"
if [ -z "$BOOT" ]; then
    if [ -f /boot/firmware/config.txt ]; then BOOT=/boot/firmware; else BOOT=/boot; fi
fi
CONFIG="$BOOT/config.txt"; CMDLINE="$BOOT/cmdline.txt"
STAMP=$(date +%Y%m%d%H%M%S)
CHANGED=0

if [ ! -f "$CONFIG" ]; then
    echo "This does not look like a Raspberry Pi (no config.txt) - skipping the serial port setup."
    exit 0
fi

if ! grep -q '^enable_uart=1[[:space:]]*$' "$CONFIG"; then
    cp "$CONFIG" "$CONFIG.bak.$STAMP"
    if grep -q '^enable_uart=' "$CONFIG"; then
        sed -i 's/^enable_uart=.*/enable_uart=1/' "$CONFIG"
    else
        printf '\n# Added by Christmas Lidar Counter\nenable_uart=1\n' >> "$CONFIG"
    fi
    echo "Switched the serial port on in $CONFIG (backup: $CONFIG.bak.$STAMP)"
    CHANGED=1
fi

if [ -f "$CMDLINE" ] && grep -Eq 'console=(serial0|ttyAMA0|ttyS0),[0-9]+' "$CMDLINE"; then
    cp "$CMDLINE" "$CMDLINE.bak.$STAMP"
    sed -i -E -e 's/ ?console=(serial0|ttyAMA0|ttyS0),[0-9]+//g' -e 's/^ +//' "$CMDLINE"
    echo "Took the login console off the serial port in $CMDLINE (backup: $CMDLINE.bak.$STAMP)"
    CHANGED=1
fi

systemctl disable --now serial-getty@ttyS0.service serial-getty@ttyAMA0.service >/dev/null 2>&1 || true
usermod -a -G dialout carcounter 2>/dev/null || true

if [ "$CHANGED" = 1 ]; then
    echo "Serial port set up. Restart the Pi once to finish (the web page has a button for it)."
else
    echo "Serial port was already set up."
fi
