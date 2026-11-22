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
* **Choose which lane to count** – say whether the lane closest to the sensor is coming in or leaving, then count either lane or both, so each car is counted once on a two‑way road
* **Follow your show** – enter your master Falcon Player's address and cars are only counted while a show is playing; if the Pi can't reach it, your show hours are used
* Show schedule with any number of entries (days + hours, past‑midnight aware); detections outside it are kept as *off‑hours*
* Test mode, one‑click **Calibrate empty road**, manual +1 / −1 / reset, warnings for a silent sensor or blocked beam
* **BaldrickSignals** crowd board: estimated people per night, reported separately from cars. If the Pi is on both Ethernet and Wi‑Fi, choose which connection reaches the board (and the Falcon Player)
* **Wi‑Fi tab** – switch Wi‑Fi on or off, scan for networks and join one from the web page (needs NetworkManager)
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
   The installer also switches on the Pi's serial port, so the lidar can use either USB or the GPIO pins – you pick in the web page.
   The Pi needs **one restart** after that; the installer offers to do it (or add `--reboot`), and the web page has a *Restart the Pi* button if it's still needed.
4. Open **http://roadcounter.local/** (or the address the installer prints), log in as **admin**, and follow the
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
(the installer switches it on for you; the Pi needs one restart afterwards, and the web page tells you if it's still waiting).

## Set up, in this order
1. **Sensor** – *Sensor & Schedule* tab: choose how it is connected. The Dashboard pill turns green: *Sensor: OK*.
2. **Show hours** – same tab. One entry per set of days with the same hours.
3. **Follow your show (optional)** – *Count cars* → *Only while FPP is playing*, then your master Falcon Player's address.
4. **Count one lane (optional)** – see below.
5. **Website** – *Website Sync* tab: paste your site address and secret from the WordPress plugin, press *Test connection*.
6. **Crowd board (optional)** – *Crowd (phones)* tab. If the board is on your Ethernet network and the counter can't find it, set *Network connection to use* to *Ethernet*.
7. **Wi‑Fi (optional)** – *Wi‑Fi* tab, see below.

## Wi‑Fi from the web page
The *Wi‑Fi* tab shows what the Pi is connected to, has an on/off switch, lists nearby networks with their signal strength, and
joins one when you pick it and type the password. Hidden networks can be typed in by hand. Saved networks can be forgotten.

* It works through **NetworkManager** (`nmcli`), which is the default on current Raspberry Pi OS and most Debian/Armbian images. If your
  system manages Wi‑Fi another way the tab says so and nothing is changed.
* **You need a way to reach the page the first time.** Plug in Ethernet (or set Wi‑Fi up with `sudo nmtui`) to open the page, then use the tab.
* Joining a network can drop the page's own connection if you are using Wi‑Fi to view it. The Pi finishes the job on its own, the page
  keeps trying, and if the new network fails the Pi goes back to the one it was on. If you lose the page, join the same network on your
  phone or computer and open the Pi's new address (your router's device list shows it, or run `hostname -I` on the Pi).
* The switch warns you before turning Wi‑Fi off when that would cut you off.
* How it is kept safe: the web page never gets a shell. It may run one root‑owned script (`scripts/wifi_helper.py`, set up by `install.sh`
  with a single sudo rule), which checks every value and talks to `nmcli` without a shell. Passwords go to the script on stdin, but `nmcli`
  itself receives them as a command‑line argument for a moment, so another user logged in to the Pi could glimpse it with `ps`. On a
  single‑purpose counter that is normally just you.
* After updating from an older version, run `sudo ./install.sh` again so the new permission is added.

## Counting each car once on a two‑way road
At the entrance of a dead‑end street every car passes twice – coming in and leaving. Mount the sensor on one side of the road,
looking straight across. In *Sensor & Schedule* use **Which lane do you want to count?**:

1. Say what the lane **closest to the sensor** carries: cars *coming in* or cars *leaving*. The picture updates, and the far lane gets the other direction.
2. Choose what to count: **cars coming in**, **cars leaving**, or **both lanes**.
3. If you chose one lane, show the sensor where the lanes are: put a car (or yourself) in the near lane and press *Record near lane*,
   then the far lane and press *Record far lane*. Press *Save lane settings*.

Later you can switch between counting coming‑in and leaving cars without recording the lanes again. Drive a car through each way to check it counts
once. Cars that U‑turn or drive down the middle can still be missed or miscounted. (Older versions ignored a lane by raising *Nearest distance*;
saving here replaces that.)

## Good to know
* **The Pi's clock matters** – show hours use the Pi's own time zone. Set it with `sudo timedatectl set-timezone America/Chicago`.
  A Pi has no clock battery, so it needs Wi‑Fi to set the time after power‑ups.
* **Only one device should upload car counts to your website.** If another counter also uploads, turn its Website Sync off,
  otherwise its zero counts can overwrite the real ones.
* **Security** – the page needs a password (user `admin`). Keep it on your home network; don't forward the port to the internet.
* **Board on Ethernet, Pi on both?** Under *Crowd (phones)* → *Network connection to use*, choose the connection the board is on. The counter then sends its
  requests out of that connection only. The same choice exists for the Falcon Player address.
* **Only upload show nights.** *Website Sync* → *Only upload nights with a show* skips any night when your Falcon Player was reachable but never played.
  It needs the Falcon Player's address. Nights the counter has no record for (it was off, or the Falcon Player could not be reached) are still uploaded,
  so nothing is lost by mistake, and nights already on your website are not removed. Turning it on later does not hide old nights.
* Logs: *Log* tab, or `journalctl -u car-counter -u car-counter-web`. Services: `systemctl status car-counter car-counter-web`.
* Settings and counts live in `/var/lib/car-counter` – back it up if you care about the history.

## Development
`python3 -m unittest tests.test_car_counter tests.test_people tests.test_standalone tests.test_wifi` (needs `php-cli`; the Wi‑Fi tests use a fake `nmcli` in `tests/fake_nmcli.py`). The daemon is `counter/car_counter.py`; the web page is in `web/`.
