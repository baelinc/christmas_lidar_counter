<?php
/* Shared helpers for the Christmas Lidar Counter web pages. */

/* Locations (the daemon uses the same defaults; the CC_* environment overrides exist for testing). */
define('CC_DATA_DIR', getenv('CC_DATA_DIR') ?: '/var/lib/car-counter');
define('CC_RUN_DIR',  getenv('CC_RUN_DIR')  ?: '/run/car-counter');
define('CC_LOG_FILE', getenv('CC_LOG_FILE') ?: '/var/log/car-counter/car-counter.log');
define('CC_ERR_FILE', getenv('CC_ERR_FILE') ?: '/var/log/car-counter/car-counter.err');

/* key => [type, default, extra]   (must mirror SCHEMA in counter/car_counter.py) */
function cc_schema() {
    return array(
        'show_name'         => array('str', 'My Light Show', 80),
        'connection'        => array('choice', 'usb', array('usb', 'gpio', 'custom')),
        'usb_device'        => array('str', '', 120),
        'serial_port'       => array('str', '', 120),
        'baud'              => array('choice', 115200, array(9600, 19200, 38400, 57600, 115200, 230400)),
        'unit'              => array('choice', 'cm', array('cm', 'mm')),
        'min_cm'            => array('int', 30, array(1, 5000)),
        'max_cm'            => array('int', 1200, array(2, 20000)),
        'min_strength'      => array('int', 0, array(0, 65534)),
        'confirm_ms'        => array('int', 250, array(20, 5000)),
        'clear_ms'          => array('int', 800, array(100, 30000)),
        'max_dwell_s'       => array('int', 120, array(5, 3600)),
        'schedule_mode'     => array('choice', 'window', array('always', 'window', 'playing')),
        'lane_split_cm'     => array('int', 0, array(0, 20000)),
        'lane_near_is'      => array('choice', 'exiting', array('entering', 'exiting')),
        'lane_count'        => array('choice', 'both', array('both', 'entering', 'exiting')),
        'fpp_host'          => array('str', '', 120),
        'fpp_interface'     => array('str', '', 15),
        'window_start'      => array('time', '17:00', null),
        'window_end'        => array('time', '22:00', null),
        'grace_min'         => array('int', 10, array(0, 240)),
        'rollover_hour'     => array('int', 6, array(0, 12)),
        'sync_enabled'      => array('bool', false, null),
        'wp_url'            => array('str', '', 300),
        'secret'            => array('str', '', 200),
        'sync_time'         => array('time', '23:00', null),
        'sync_days'         => array('int', 7, array(1, 60)),
        'sync_only_if_played' => array('bool', false, null),
        'live_interval_min' => array('int', 0, array(0, 240)),
        'verify_tls'        => array('bool', true, null),
        'people_enabled'      => array('bool', false, null),
        'people_host'         => array('str', '', 120),
        'people_interface'    => array('str', '', 15),
        'people_interval_min' => array('int', 5, array(1, 120)),
        'people_clock'        => array('choice', 'local', array('local', 'utc')),
        'people_time_col'     => array('int', 0, array(0, 40)),
        'people_count_col'    => array('int', 0, array(0, 40)),
    );
}

function cc_coerce($key, $value) {
    $s = cc_schema()[$key];
    list($type, $def, $extra) = $s;
    if ($key === 'people_host' || $key === 'fpp_host') {   // accept "http://192.168.1.50/csv" and keep just the host
        $v = preg_replace('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', '', trim((string)$value));
        $v = preg_split('#[/?]#', $v)[0];
        return preg_match('/^[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?(:\d{1,5})?$/', $v) ? substr($v, 0, 120) : '';
    }
    if ($key === 'people_interface' || $key === 'fpp_interface') {   // a plain network name like eth0 or wlan0, or '' for automatic
        $v = trim((string)$value);
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,14}$/', $v) ? $v : '';
    }
    switch ($type) {
        case 'str':
            return substr(trim((string)$value), 0, $extra);
        case 'bool':
            if (is_string($value)) return in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
            return (bool)$value;
        case 'int':
            if (!is_numeric($value)) return $def;
            return max($extra[0], min($extra[1], (int)round((float)$value)));
        case 'choice':
            if (is_int($def)) $value = (int)$value;
            return in_array($value, $extra, true) ? $value : $def;
        case 'time':
            return (is_string($value) && preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', trim($value))) ? trim($value) : $def;
    }
    return $def;
}

/* Schedule entries: array of array('days' => [0..6, 0 = Sunday], 'start' => 'HH:MM', 'end' => 'HH:MM'). */
function cc_coerce_schedules($value) {
    $out = array();
    if (!is_array($value)) return $out;
    foreach (array_slice(array_values($value), 0, 28) as $e) {
        if (!is_array($e) || !isset($e['days']) || !is_array($e['days'])) continue;
        $days = array();
        foreach ($e['days'] as $d) {
            if (is_numeric($d) && (int)$d >= 0 && (int)$d <= 6) $days[(int)$d] = (int)$d;
        }
        ksort($days);
        $start = isset($e['start']) ? trim((string)$e['start']) : '';
        $end   = isset($e['end']) ? trim((string)$e['end']) : '';
        $ok = '/^([01]?\d|2[0-3]):[0-5]\d$/';
        if (!$days || !preg_match($ok, $start) || !preg_match($ok, $end)) continue;
        $out[] = array('days' => array_values($days), 'start' => $start, 'end' => $end);
    }
    return $out;
}

function cc_read_json($path) {
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function cc_config() {
    $raw = cc_read_json(CC_DATA_DIR . '/config.json');
    $out = array();
    foreach (cc_schema() as $k => $s) {
        $out[$k] = ($raw !== null && array_key_exists($k, $raw)) ? cc_coerce($k, $raw[$k]) : $s[1];
    }
    if ($out['max_cm'] <= $out['min_cm']) $out['max_cm'] = $out['min_cm'] + 10;
    $out['wp_url'] = rtrim($out['wp_url'], '/');
    if ($out['schedule_mode'] === 'playing' && $out['fpp_host'] === '') $out['schedule_mode'] = 'window';
    if ($raw === null || !array_key_exists('connection', $raw)) {   // config saved before the option existed
        $sp = ($raw !== null && isset($raw['serial_port'])) ? trim((string)$raw['serial_port']) : '';
        if ($sp === '') { $out['connection'] = 'usb'; }
        elseif ($sp === '/dev/serial0') { $out['connection'] = 'gpio'; }
        elseif (preg_match('#^/dev/(ttyUSB|ttyACM|serial/by-id)#', $sp)) { $out['connection'] = 'usb'; if (!isset($raw['usb_device'])) $out['usb_device'] = $sp; }
        else { $out['connection'] = 'custom'; }
    }
    if ($raw !== null && isset($raw['schedules']) && is_array($raw['schedules'])) {
        $out['schedules'] = cc_coerce_schedules($raw['schedules']);
    } else {   // older config (single window) or fresh install: one entry for every day
        $out['schedules'] = array(array('days' => range(0, 6), 'start' => $out['window_start'], 'end' => $out['window_end']));
    }
    return $out;
}

/* Merge the submitted values over the saved config. A blank secret keeps the old one. */
function cc_save_config($changes) {
    $cfg = cc_config();
    $schema = cc_schema();
    foreach ($changes as $k => $v) {
        if ($k === 'schedules') { $cfg['schedules'] = cc_coerce_schedules($v); continue; }
        if (!isset($schema[$k])) continue;
        if ($k === 'secret' && trim((string)$v) === '') continue;
        $cfg[$k] = cc_coerce($k, $v);
    }
    if ($cfg['max_cm'] <= $cfg['min_cm']) $cfg['max_cm'] = $cfg['min_cm'] + 10;
    $cfg['wp_url'] = rtrim($cfg['wp_url'], '/');
    if (!is_dir(CC_DATA_DIR)) @mkdir(CC_DATA_DIR, 0775, true);
    $tmp = CC_DATA_DIR . '/config.json.tmp.' . getmypid();
    if (file_put_contents($tmp, json_encode($cfg)) === false) return false;
    @chmod($tmp, 0600);
    return rename($tmp, CC_DATA_DIR . '/config.json');
}

/* USB serial adapters, best first (CP210x / Silicon Labs ahead of anything else). Mirrors list_usb_ports() in the daemon. */
function cc_usb_ports() {
    $ports = array();
    foreach (glob('/dev/serial/by-id/*') ?: array() as $p) $ports[] = array('path' => $p, 'label' => basename($p));
    if (!$ports) {
        foreach (array_merge(glob('/dev/ttyUSB*') ?: array(), glob('/dev/ttyACM*') ?: array()) as $p) $ports[] = array('path' => $p, 'label' => $p);
    }
    usort($ports, function ($a, $b) {
        $ra = (stripos($a['path'], 'cp210') !== false || stripos($a['path'], 'silicon_labs') !== false) ? 0 : 1;
        $rb = (stripos($b['path'], 'cp210') !== false || stripos($b['path'], 'silicon_labs') !== false) ? 0 : 1;
        return $ra - $rb;
    });
    return $ports;
}

/* State of the Pi's GPIO serial port:
 *  ready         - switched on and the login console is off (the lidar can use the GPIO pins)
 *  needs_restart - switched on in the boot files, but the Pi has not restarted since
 *  not_enabled   - not switched on (install.sh does this; re-run it)
 *  unknown       - not a Raspberry Pi */
function cc_uart_state() {
    $cfgf = $cmdf = '';
    $dirs = getenv('CC_BOOT_DIR') ? array(getenv('CC_BOOT_DIR')) : array('/boot/firmware', '/boot');
    foreach ($dirs as $d) {
        if (is_file($d . '/config.txt')) { $cfgf = $d . '/config.txt'; $cmdf = $d . '/cmdline.txt'; break; }
    }
    if ($cfgf === '') return 'unknown';
    $console = '/console=(serial0|ttyAMA0|ttyS0),/';
    $on = preg_match('/^enable_uart=1\s*$/m', (string)@file_get_contents($cfgf)) && !preg_match($console, (string)@file_get_contents($cmdf));
    if (!$on) return 'not_enabled';
    $running = (string)@file_get_contents(getenv('CC_PROC_CMDLINE') ?: '/proc/cmdline');
    return preg_match($console, $running) ? 'needs_restart' : 'ready';
}

function cc_status() {
    $st = cc_read_json(CC_RUN_DIR . '/status.json');
    $running = ($st !== null && isset($st['ts']) && (microtime(true) - $st['ts']) < 6);
    return array('running' => $running, 'data' => $running ? $st : null);
}

function cc_send_command($cmd) {
    if (!is_dir(CC_RUN_DIR . '/cmd')) return false;
    $id = bin2hex(random_bytes(6));
    $cmd['id'] = $id;
    $name = sprintf('%s/cmd/%.6f-%s', CC_RUN_DIR, microtime(true), $id);
    if (file_put_contents($name . '.tmp', json_encode($cmd)) === false) return false;
    @chmod($name . '.tmp', 0666);
    return rename($name . '.tmp', $name . '.json') ? $id : false;
}

function cc_service($op) {
    if (!in_array($op, array('start', 'stop', 'restart', 'reboot'), true)) return 'bad op';
    // systemd runs the counter; the web user may only start/stop/restart that one unit or restart the Pi (sudoers rule made by install.sh)
    $out = array();
    $sc = 'sudo -n ' . escapeshellarg(getenv('CC_SYSTEMCTL') ?: 'systemctl') . ' ' . escapeshellarg($op);
    exec($op === 'reboot' ? $sc . ' 2>&1' : $sc . ' car-counter 2>&1', $out);
    return implode("\n", $out) ?: 'Done';
}

/* Wi-Fi: everything goes through the root-owned helper (scripts/wifi_helper.py) via one narrow sudo rule made by
 * install.sh.  Arguments are passed as an array (no shell), and the password goes in on stdin, never on a command line. */
function cc_wifi_call($args, $stdin = null) {
    $helper = getenv('CC_WIFI_HELPER') ?: dirname(__DIR__) . '/scripts/wifi_helper.py';
    $cmd = (getenv('CC_WIFI_NOSUDO') === '1') ? array($helper) : array('sudo', '-n', $helper);   // NOSUDO: tests only
    foreach ($args as $a) $cmd[] = (string)$a;
    $p = @proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($p)) return array('ok' => false, 'helper_missing' => true, 'error' => 'Could not start the Wi-Fi helper.');
    if ($stdin !== null) fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($p);
    $j = json_decode((string)$out, true);
    if (is_array($j)) return $j;
    $why = (stripos($err, 'sudo') !== false || stripos($err, 'password') !== false || stripos($err, 'not allowed') !== false)
        ? 'The web page is not allowed to change Wi-Fi yet. Run  sudo ./install.sh  again from the downloaded folder, then reload.'
        : 'The Wi-Fi helper did not answer.';
    return array('ok' => false, 'helper_missing' => true, 'error' => $why);
}

/* The Pi's network connections (Ethernet, Wi-Fi...) so the page can offer "use this one to reach the board". */
function cc_ifaces() {
    $ips = array();
    foreach (array('/usr/sbin/ip', '/sbin/ip', '/usr/bin/ip', '/bin/ip') as $ipbin) {
        if (!is_executable($ipbin)) continue;
        $lines = array();
        @exec(escapeshellarg($ipbin) . ' -4 -o addr show 2>/dev/null', $lines);
        foreach ($lines as $l) {
            if (preg_match('/^\d+:\s+(\S+)\s+inet\s+(\d+\.\d+\.\d+\.\d+)\//', $l, $m) && !isset($ips[$m[1]])) $ips[$m[1]] = $m[2];
        }
        break;
    }
    $out = array();
    foreach (glob('/sys/class/net/*') ?: array() as $d) {
        $n = basename($d);
        if ($n === 'lo' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,14}$/', $n)) continue;
        if (preg_match('/^(docker|veth|br-|virbr|ifb|dummy|sit\d|ip6tnl|tunl|gre)/', $n)) continue;
        $wifi = is_dir($d . '/wireless') || is_dir($d . '/phy80211');
        $out[] = array('name' => $n, 'kind' => $wifi ? 'wifi' : 'ethernet', 'ip' => $ips[$n] ?? '',
                       'up' => trim((string)@file_get_contents($d . '/operstate')) === 'up');
    }
    usort($out, function ($a, $b) { return strcmp($a['kind'] . $a['name'], $b['kind'] . $b['name']); });   // ethernet before wifi
    return $out;
}

/* Is this address inside a 192.168.1.50/24 style range? */
function cc_in_subnet($addr, $cidr) {
    if (strpos($cidr, '/') === false) return false;
    list($net, $bits) = explode('/', $cidr, 2);
    $a = ip2long($addr); $n = ip2long($net); $bits = (int)$bits;
    if ($a === false || $n === false || $bits < 1 || $bits > 32) return false;
    $mask = $bits === 32 ? -1 : ((-1 << (32 - $bits)) & 0xFFFFFFFF);
    return (($a & $mask) === ($n & $mask));
}

function cc_tail($path, $lines = 150) {
    $fh = @fopen($path, 'r');
    if (!$fh) return '';
    $size = filesize($path);
    fseek($fh, max(0, $size - 65536));
    $data = stream_get_contents($fh);
    fclose($fh);
    $arr = explode("\n", rtrim($data));
    return implode("\n", array_slice($arr, -$lines));
}
