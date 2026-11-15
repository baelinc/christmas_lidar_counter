# Christmas Lidar Counter

Counts the cars that visit your Christmas light display with a **TFmini / TFmini‑S / TFmini Plus / TF‑Luna** lidar on a
Raspberry Pi, keeps nightly and hourly totals, and uploads them to your WordPress site
(companion plugin: [*Light Show Car Counter*](https://github.com/baelinc/lidar_counter_wp)). It has its own friendly,
phone‑ready web page for setup and a live dashboard.

It runs on its own small Raspberry Pi – a **Pi Zero 2 W** is plenty – so you can put the sensor wherever cars pass, for
example the entrance of your street, without needing it to be next to your show controller.
(Running the counter on the Falcon Player Pi itself? Use the FPP plugin instead: [lidar_counter_fpp](https://github.com/baelinc/lidar_counter_fpp).)

## Features
* **Easy web page** – works on a phone; setup checklist, big live count, charts, CSV export, password protected
* **Lidar on USB or GPIO** – pick *USB‑to‑serial adapter* or *Pi GPIO header* in the settings; the page shows the matching wiring table
* **Count only one lane** helper – record the near and far lane and cars leaving (near lane) are ignored, so each car is counted once on a two‑way road
* **Follow your show** – enter your master Falcon Player's address and cars are only counted while a show is playing; if the Pi can't reach it, your show hours are used
* Show schedule with any number of entries (days + hours, past‑midnight aware); detections outside it are kept as *off‑hours*
* Test mode, one‑click **Calibrate empty road**, manual +1 / −1 / reset, warnings for a silent sensor or blocked beam
* **BaldrickSignals** crowd board: estimated people per night, reported separately from cars
* **Website sync**: nightly upload (catches up after a power cut), optional live updates, HMAC‑signed
* Python standard library only; SQLite storage; restarts itself after a crash or reboot

## Install (about 10 minutes)
1. **Put Raspberry Pi OS Lite on the SD card** with *Raspberry Pi Imager*. In its settings (gear icon) set a hostname
   (e.g. `roadcounter`), a username and password, your **Wi‑Fi**, your **time zone** and **enable SSH**.
2. Start the Pi, then from your computer: `ssh <user>@roadcounter.local`
3. Get the code and install it:
   ```
   sudo apt update && sudo apt install -y git
   git clone https://github.com/baelinc/christmas_lidar_counter
   cd christmas_lidar_counter
   sudo ./install.sh
   ```
   It asks you to choose a password for the settings page (or makes one up and shows it).
   **Lidar wired to the GPIO pins instead of a USB adapter?** Run `sudo ./install.sh --uart` and reboot afterwards.
4. Open **http://roadcounter.local:8080/** (or the address the installer prints), log in as **admin**, and follow the
   *Let's get you set up* list on the Dashboard.

**Update later:** `cd christmas_lidar_counter && git pull && sudo ./install.sh` (settings and counts are kept).
**Change the password:** `sudo car-counter-passwd`.
**Remove:** `sudo ./uninstall.sh` (add `--purge` to delete counts and settings too).

## Wiring
In **Sensor & Schedule**, set *How is the lidar connected?*; the page shows the table for your choice.

| Lidar wire | USB adapter (CP2102) | Pi GPIO header |
|---|---|---|
| Red (5 V) | 5V | Pin 2 (5V) |
| Black (GND) | GND | Pin 6 (GND) |
| Green (lidar TX) | RXD | Pin 10 (GPIO15 / RXD) |
| White (lidar RX) | TXD | Pin 8 (GPIO14 / TXD) |

The data wires cross over (lidar TX → receive pin). Wire colours differ between sensor models – check your datasheet.
The lidar needs 5 V power; its data signals are 3.3 V. A USB adapter needs no serial‑port setup; the GPIO header does
(`sudo ./install.sh --uart`, then reboot, then choose `/dev/serial0`).

## Set up, in this order
1. **Sensor** – *Sensor & Schedule* tab: choose how it is connected. The Dashboard pill turns green: *Sensor: OK*.
2. **Show hours** – same tab. One entry per set of days with the same hours.
3. **Follow your show (optional)** – *Count cars* → *Only while FPP is playing*, then your master Falcon Player's address.
4. **Count one lane (optional)** – see below.
5. **Website** – *Website Sync* tab: paste your site address and secret from the WordPress plugin, press *Test connection*.
6. **Crowd board (optional)** – *Crowd (phones)* tab (the Pi must be on the same network as the board).

## Counting each car once on a two‑way road
At the entrance of a dead‑end street every car passes twice – arriving and leaving. Mount the sensor on one side of the road,
looking straight across. Arriving cars use the far lane; leaving cars use the near lane. In *Sensor & Schedule* use
**Count only one lane**: put a car (or yourself) in the near lane and press *Record near lane*, then the far lane and press
*Record far lane*, then *Use these settings*. Drive a car through each way to check. Cars that U‑turn or drive down the
middle can still be missed or miscounted.

## Good to know
* **The Pi's clock matters** – show hours use the Pi's own time zone. Set it with `sudo timedatectl set-timezone America/Chicago`.
  A Pi has no clock battery, so it needs Wi‑Fi to set the time after power‑ups.
* **Only one device should upload car counts to your website.** If another counter also uploads, turn its Website Sync off,
  otherwise its zero counts can overwrite the real ones.
* **Security** – the page needs a password (user `admin`). Keep it on your home network; don't forward the port to the internet.
* Logs: *Log* tab, or `journalctl -u car-counter -u car-counter-web`. Services: `systemctl status car-counter car-counter-web`.
* Settings and counts live in `/var/lib/car-counter` – back it up if you care about the history.

## Development
`python3 -m unittest discover -s tests` (needs `php-cli`). The daemon is `counter/car_counter.py`; the web page is in `web/`.
