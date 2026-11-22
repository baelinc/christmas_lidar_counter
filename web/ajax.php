<?php
/* JSON endpoint used by ui.php.  Reached through plugin.php (see router.php). */
require_once __DIR__ . '/lib.php';

$action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

function cc_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

$mutating = array('save_config', 'command', 'service', 'wifi_power', 'wifi_connect', 'wifi_forget', 'update_apply');
if (in_array($action, $mutating, true)) {
    // POST only, and only from our own page (a custom header cannot be set by a cross-site form).
    if (!$isPost || !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        cc_out(array('ok' => false, 'error' => 'Forbidden'), 403);
    }
}
$body = array();
if ($isPost) {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = array();
}

switch ($action) {
    case 'status':
        $s = cc_status();
        cc_out(array('running' => $s['running'], 'status' => $s['data']));

    case 'stats':
        $st = cc_read_json(CC_RUN_DIR . '/stats.json');
        cc_out($st === null ? array('days' => array(), 'season_total' => 0, 'best' => null) : $st);

    case 'config':
        $cfg = cc_config();
        $cfg['secret_set'] = ($cfg['secret'] !== '');
        unset($cfg['secret']);
        cc_out($cfg);

    case 'save_config':
        if (!cc_save_config($body)) cc_out(array('ok' => false, 'error' => 'Could not write config file'), 500);
        cc_out(array('ok' => true));

    case 'command':
        $allowed = array('adjust', 'reset_today', 'test_mode', 'calibrate', 'sync_now', 'sync_test', 'people_test', 'people_poll', 'update_check');
        $cmd = isset($body['cmd']) ? (string)$body['cmd'] : '';
        if (!in_array($cmd, $allowed, true)) cc_out(array('ok' => false, 'error' => 'Unknown command'), 400);
        $payload = array('cmd' => $cmd);
        if ($cmd === 'adjust') $payload['delta'] = (isset($body['delta']) && (int)$body['delta'] < 0) ? -1 : 1;
        if ($cmd === 'test_mode') $payload['on'] = !empty($body['on']);
        if (!cc_status()['running']) cc_out(array('ok' => false, 'error' => 'The counter service is not running.'), 503);
        $id = cc_send_command($payload);
        if ($id === false) cc_out(array('ok' => false, 'error' => 'Could not queue command'), 500);
        cc_out(array('ok' => true, 'id' => $id));

    case 'service':
        $op = isset($body['op']) ? (string)$body['op'] : '';
        $res = cc_service($op);
        cc_out(array('ok' => true, 'output' => $res));

    case 'ifaces':
        cc_out(array('ifaces' => cc_ifaces()));

    case 'wifi_status':
        $r = cc_wifi_call(array('status'));
        // Is this page being viewed over the Pi's Wi-Fi?  (then changing Wi-Fi can cut the page off).  Judged by the viewer's address
        // sitting on the Wi-Fi adapter's network, or by the page having been opened at the Wi-Fi address itself.
        $via = false;
        if (!empty($r['ip'])) {
            $via = (($_SERVER['SERVER_NAME'] ?? '') === $r['ip']) || (($_SERVER['SERVER_ADDR'] ?? '') === $r['ip'])
                || (!empty($r['cidr']) && cc_in_subnet($_SERVER['REMOTE_ADDR'] ?? '', $r['cidr']));
        }
        $r['via_wifi'] = $via;
        cc_out($r);

    case 'wifi_scan':
        cc_out(cc_wifi_call(array('scan', (($_REQUEST['rescan'] ?? '') === '1') ? 'yes' : 'no')));

    case 'wifi_power':
        cc_out(cc_wifi_call(array('power', !empty($body['on']) ? 'on' : 'off')));

    case 'wifi_connect':
        $ssid = $body['ssid'] ?? '';
        if (!is_string($ssid) || $ssid === '') cc_out(array('ok' => false, 'error' => 'Choose a network first.'), 400);
        $payload = array(
            'ssid' => $ssid,
            'password' => is_string($body['password'] ?? null) ? $body['password'] : '',
            'security' => is_string($body['security'] ?? null) ? $body['security'] : 'wpa-psk',
            'hidden' => !empty($body['hidden']),
            'use_saved' => !empty($body['use_saved']),
        );
        cc_out(cc_wifi_call(array('connect'), json_encode($payload)));

    case 'wifi_forget':
        $ssid = $body['ssid'] ?? '';
        if (!is_string($ssid) || $ssid === '') cc_out(array('ok' => false, 'error' => 'Choose a network first.'), 400);
        cc_out(cc_wifi_call(array('forget'), json_encode(array('ssid' => $ssid))));

    case 'update_status':
        $info = cc_read_json(CC_RUN_DIR . '/update.json');
        $res = cc_read_json(CC_RUN_DIR . '/update_result.json');
        // A result that stopped reporting long ago (power cut mid-update) must not leave the page "busy" forever.
        if (is_array($res) && !in_array($res['state'] ?? '', array('ok', 'failed', 'uptodate'), true)
            && time() - (int)($res['ts'] ?? 0) > 900) {
            $res = array('state' => 'failed', 'message' => 'The last update stopped part-way. Check that the counter is running, then try again.');
        }
        cc_out(array('info' => $info, 'result' => $res, 'version' => is_file(dirname(__DIR__) . '/VERSION') ? trim((string)file_get_contents(dirname(__DIR__) . '/VERSION')) : ''));

    case 'update_apply':
        $cmd = (getenv('CC_UPDATER_NOSUDO') === '1') ? array(getenv('CC_UPDATER') ?: dirname(__DIR__) . '/scripts/updater.py', 'apply')
             : array('sudo', '-n', dirname(__DIR__) . '/scripts/updater.py', 'apply');
        $p = @proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($p)) cc_out(array('ok' => false, 'error' => 'Could not start the updater.'));
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
        $j = json_decode((string)$out, true);
        if (is_array($j)) cc_out($j);
        cc_out(array('ok' => false, 'error' => 'The page is not allowed to install updates yet. Run  sudo ./install.sh  once from the downloaded folder, then try again.'));

    case 'log':
        cc_out(array('log' => cc_tail(CC_LOG_FILE, 200), 'errors' => cc_tail(CC_ERR_FILE, 30)));

    case 'ports':
        cc_out(array(
            'usb'  => cc_usb_ports(),
            'gpio' => file_exists('/dev/serial0'),
            'uart' => cc_uart_state(),
            'all'  => array_values(array_unique(array_merge(
                glob('/dev/serial0') ?: array(), glob('/dev/ttyAMA*') ?: array(), glob('/dev/ttyS0') ?: array(),
                glob('/dev/ttyUSB*') ?: array(), glob('/dev/ttyACM*') ?: array()))),
        ));

    case 'csv':
        $st = cc_read_json(CC_RUN_DIR . '/stats.json');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="car-counts-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        $head = array('date', 'cars', 'offhours_detections', 'people_est', 'people_peak');
        for ($h = 0; $h < 24; $h++) $head[] = sprintf('h%02d', $h);
        fputcsv($out, $head, ',', '"', '\\');
        foreach (($st['days'] ?? array()) as $d) {
            fputcsv($out, array_merge(array($d['date'], $d['total'], $d['offhours'], $d['people'] ?? '', $d['people_peak'] ?? ''), $d['hours']), ',', '"', '\\');
        }
        exit;

    default:
        cc_out(array('ok' => false, 'error' => 'Unknown action'), 400);
}
