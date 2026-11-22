<?php
/* Christmas Lidar Counter - main page (included by index.php). */
require_once __DIR__ . '/lib.php';
?>
<style>
#cc { max-width: 1100px; --line: rgba(128,128,128,.35); --accent: #2f7d32; --warn: #c77700; --bad: #c62828; }
#cc h2 { margin: 0 0 .6rem; }
#cc .cc-pills { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
#cc .pill { border: 1px solid var(--line); border-radius: 999px; padding: .2rem .8rem; font-size: .9rem; }
#cc .pill.ok { border-color: var(--accent); color: var(--accent); }
#cc .pill.warn { border-color: var(--warn); color: var(--warn); }
#cc .pill.bad { border-color: var(--bad); color: var(--bad); }
#cc .tabs { display: flex; gap: .25rem; border-bottom: 1px solid var(--line); margin-bottom: 1rem; }
#cc .tabs button { border: 1px solid transparent; border-bottom: 0; background: none; padding: .5rem 1rem; cursor: pointer; font-size: 1rem; color: inherit; border-radius: 6px 6px 0 0; }
#cc .tabs button.active { border-color: var(--line); font-weight: 600; background: rgba(128,128,128,.12); }
#cc .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .8rem; margin-bottom: 1rem; }
#cc .card { border: 1px solid var(--line); border-radius: 8px; padding: .8rem 1rem; }
#cc .card .big { font-size: 2.6rem; font-weight: 700; line-height: 1.1; }
#cc .card .sub { opacity: .7; font-size: .85rem; }
#cc .panel { border: 1px solid var(--line); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
#cc .panel h3 { font-size: 1.1rem; margin: 0 0 .6rem; }
#cc .row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin: .5rem 0; }
#cc button.b { border: 1px solid var(--line); background: rgba(128,128,128,.12); color: inherit; padding: .45rem .9rem; border-radius: 6px; cursor: pointer; font-size: .95rem; }
#cc button.b:hover { background: rgba(128,128,128,.25); }
#cc button.b.primary { background: #1f6feb; border-color: #1f6feb; color: #fff; }
#cc button.b.danger { border-color: var(--bad); color: var(--bad); }
#cc button.b.on { background: var(--warn); border-color: var(--warn); color: #fff; }
#cc button.b:disabled { opacity: .5; cursor: default; }
#cc .msg { min-height: 1.4em; font-size: .95rem; }
#cc .msg.ok { color: var(--accent); } #cc .msg.bad { color: var(--bad); } #cc .msg.warn { color: var(--warn); }
#cc .track { position: relative; height: 26px; border: 1px solid var(--line); border-radius: 4px; margin: .5rem 0; background: rgba(128,128,128,.08); }
#cc .zone { position: absolute; top: 0; bottom: 0; background: rgba(47,125,50,.25); border-left: 2px solid var(--accent); border-right: 2px solid var(--accent); }
#cc .marker { position: absolute; top: -3px; bottom: -3px; width: 4px; background: #1f6feb; transition: left .15s; }
#cc .marker.hit { background: var(--bad); }
#cc .chart { display: flex; align-items: flex-end; gap: 3px; height: 150px; border-bottom: 1px solid var(--line); padding-top: 14px; }
#cc .chart .col { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; min-width: 0; }
#cc .chart .bar { width: 100%; max-width: 70px; background: #1f6feb; border-radius: 3px 3px 0 0; min-height: 1px; }
#cc .chart .val { font-size: .7rem; opacity: .8; }
#cc .labels { display: flex; gap: 3px; font-size: .7rem; opacity: .7; }
#cc .labels span { flex: 1; text-align: center; overflow: hidden; }
#cc table.t { border-collapse: collapse; width: 100%; font-size: .92rem; }
#cc table.t th, #cc table.t td { border-bottom: 1px solid var(--line); padding: .3rem .5rem; text-align: left; }
#cc table.t td.n, #cc table.t th.n { text-align: right; }
#cc .field { display: grid; grid-template-columns: 220px 1fr; gap: .5rem 1rem; align-items: start; margin: .6rem 0; }
#cc .field label { font-weight: 600; padding-top: .35rem; }
#cc .field input[type=text], #cc .field input[type=number], #cc .field input[type=password], #cc .field input[type=time], #cc .field select { width: 100%; max-width: 380px; padding: .35rem .5rem; }
#cc .help { font-size: .82rem; opacity: .7; margin-top: .2rem; }
#cc pre { max-height: 380px; overflow: auto; border: 1px solid var(--line); border-radius: 6px; padding: .6rem; font-size: .8rem; }
#cc .warnbox { border: 1px solid var(--warn); color: var(--warn); border-radius: 6px; padding: .5rem .8rem; margin-bottom: 1rem; }
#cc .sched-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .6rem; border: 1px solid var(--line); border-radius: 8px; padding: .5rem .7rem; margin-bottom: .5rem; max-width: 640px; }
#cc .sched-days { display: flex; gap: 3px; }
#cc .sched-days button { width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--line); background: none; color: inherit; cursor: pointer; font-weight: 600; }
#cc .sched-days button.on { background: #1f6feb; border-color: #1f6feb; color: #fff; }
#cc .sched-row input[type=time] { padding: .3rem .4rem; width: 8.5rem; max-width: 8.5rem; }
#cc .sched-quick button { border: 0; background: none; color: #1f6feb; cursor: pointer; font-size: .8rem; padding: 0 .3rem; }
#cc .sched-del { margin-left: auto; }
#ccToast { position: fixed; left: 50%; bottom: 1.5rem; transform: translateX(-50%); z-index: 9999; padding: .75rem 1.2rem; border-radius: 12px; font-weight: 600; box-shadow: 0 6px 24px rgba(0,0,0,.28); background: #1b7f4b; color: #fff; max-width: 92vw; text-align: center; opacity: 0; pointer-events: none; transition: opacity .2s; }
#ccToast.show { opacity: 1; }
#ccToast.bad { background: #c62f2f; }
#cc .setup-item { display: flex; align-items: center; gap: .8rem; padding: .55rem 0; border-bottom: 1px solid var(--line); }
#cc .setup-item:last-child { border-bottom: 0; }
#cc .setup-ico { flex: none; width: 1.7rem; height: 1.7rem; border-radius: 50%; border: 2px solid var(--line); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .95rem; }
#cc .setup-item.done .setup-ico { background: var(--accent); border-color: var(--accent); color: #fff; }
#cc .setup-txt { flex: 1; min-width: 0; }
#cc .setup-txt b { display: block; }
#cc .setup-txt span { font-size: .85rem; opacity: .7; }
#cc .lane-now { font-size: 2.2rem; font-weight: 700; line-height: 1.1; }
#cc .lane-step { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin: .7rem 0; }
#cc .lane-val { font-weight: 700; min-width: 5rem; }
@media (max-width: 700px) { #cc .field { grid-template-columns: 1fr; } }
</style>

<div id="cc">
  <h2>Christmas Lidar Counter <small id="ccVersion" style="font-size:.5em;opacity:.6"></small></h2>
  <div class="cc-pills">
    <span class="pill" id="pillDaemon">Service: …</span>
    <span class="pill" id="pillSensor">Sensor: …</span>
    <span class="pill" id="pillCounting">Counting: …</span>
    <span class="pill" id="pillSync" style="display:none"></span>
  </div>
  <div id="warnings"></div>

  <div class="tabs" id="tabs">
    <button data-tab="dash" class="active">Dashboard</button>
    <button data-tab="settings">Sensor &amp; Schedule</button>
    <button data-tab="people">Crowd (phones)</button>
    <button data-tab="website">Website Sync</button>
    <button data-tab="wifi">Wi-Fi</button>
    <button data-tab="log">Log</button>
  </div>

  <!-- ===================== Dashboard ===================== -->
  <div id="tab-dash">
    <div class="panel" id="setupBox" style="display:none">
      <h3>Let’s get you set up</h3>
      <div id="setupList"></div>
      <div class="row"><button class="b" id="setupHide">Hide this</button></div>
    </div>
    <div class="grid">
      <div class="card"><div class="sub" id="tonightLabel">Tonight</div><div class="big" id="vTonight">–</div><div class="sub" id="vTonightSub"></div></div>
      <div class="card"><div class="sub">Last night</div><div class="big" id="vLast">–</div><div class="sub" id="vLastSub"></div></div>
      <div class="card"><div class="sub">Season total</div><div class="big" id="vSeason">–</div><div class="sub" id="vSeasonSub"></div></div>
      <div class="card" id="cardPeople" style="display:none"><div class="sub">People tonight (est.)</div><div class="big" id="vPeople">–</div><div class="sub" id="vPeopleSub"></div></div>
      <div class="card" id="cardDevices" style="display:none"><div class="sub">Devices nearby now</div><div class="big" id="vDevices">–</div><div class="sub" id="vDevicesSub"></div></div>
      <div class="card"><div class="sub">Off-hours detections tonight</div><div class="big" id="vOff">–</div><div class="sub">Not included in totals</div></div>
    </div>

    <div class="panel">
      <h3>Live sensor</h3>
      <div class="row"><strong id="vDist" style="font-size:1.6rem">–</strong><span id="vState" class="pill">idle</span><span class="sub" id="vSensorDetail"></span></div>
      <div class="track"><div class="zone" id="zone"></div><div class="marker" id="marker" style="left:0"></div></div>
      <div class="help">The green band is the detection zone. A vehicle is counted when the reading stays inside it. An empty road should read "no return" or sit to the right of the band.</div>
      <div class="row">
        <button class="b" id="btnPlus">+1 car</button>
        <button class="b" id="btnMinus">−1 car</button>
        <button class="b danger" id="btnReset">Reset tonight</button>
        <button class="b" id="btnTest">Test mode</button>
        <button class="b" id="btnCal">Calibrate empty road</button>
      </div>
      <div class="msg" id="cmdMsg"></div>
    </div>

    <div class="panel">
      <h3>Tonight by hour</h3>
      <div class="chart" id="chartHours"></div><div class="labels" id="labelsHours"></div>
    </div>
    <div class="panel">
      <h3>Recent nights</h3>
      <div class="chart" id="chartDays"></div><div class="labels" id="labelsDays"></div>
      <table class="t" style="margin-top:1rem"><thead><tr id="tblHead"><th>Night</th><th class="n">Cars</th><th class="n">Off-hours</th><th>First car</th><th>Last car</th></tr></thead><tbody id="tblDays"></tbody></table>
      <div class="row"><a class="b" style="text-decoration:none" id="csvLink" href="#">Download CSV</a></div>
    </div>
  </div>

  <!-- ===================== Settings ===================== -->
  <div id="tab-settings" style="display:none">
    <div class="panel" id="wireUsb"><h3>Wiring – USB-to-serial adapter (CP2102)</h3>
      <table class="t" style="max-width:420px"><thead><tr><th>Lidar wire</th><th>Adapter pin</th></tr></thead><tbody>
        <tr><td>Red (5 V power)</td><td>5V</td></tr>
        <tr><td>Black (ground)</td><td>GND</td></tr>
        <tr><td>Green (lidar TX)</td><td>RXD</td></tr>
        <tr><td>White (lidar RX)</td><td>TXD</td></tr>
      </tbody></table>
      <div class="help" style="opacity:.9;margin-top:.5rem">
        The data wires cross over: the lidar's TX goes to the adapter's RXD, and the lidar's RX to the adapter's TXD.
        Plug the adapter into any USB port on the Pi – no serial-port setup and no reboot needed.
        Power the lidar from the adapter's <strong>5V</strong> pin (it needs 5 V; its data signals are 3.3 V). If your adapter has a 3.3V/5V logic selector, choose 3.3V.
        Wire colours differ between sensor models – check your datasheet.
      </div>
    </div>
    <div class="panel" id="wireGpio" style="display:none"><h3>Wiring – Pi GPIO header</h3>
      <table class="t" style="max-width:420px"><thead><tr><th>Lidar wire</th><th>Pi pin</th></tr></thead><tbody>
        <tr><td>Red (5 V power)</td><td>Pin 2 (5V)</td></tr>
        <tr><td>Black (ground)</td><td>Pin 6 (GND)</td></tr>
        <tr><td>Green (lidar TX)</td><td>Pin 10 (GPIO15 / RXD)</td></tr>
        <tr><td>White (lidar RX)</td><td>Pin 8 (GPIO14 / TXD)</td></tr>
      </tbody></table>
      <div class="row"><span class="msg" id="uartMsg"></span><button class="b primary" id="btnRebootUart" style="display:none">Restart the Pi</button></div>
      <div class="help" style="opacity:.9;margin-top:.5rem">
        The Pi's serial port is switched on for you when you install. If an LED-panel HAT or bonnet uses these GPIO pins, use a USB-to-serial adapter instead.
      </div>
    </div>
    <div class="panel"><h3>Sensor</h3><div id="fSensor"></div></div>
    <div class="panel" id="laneHelper"><h3>Which lane do you want to count?</h3>
      <div class="help" style="opacity:.9">On a two-way road, cars coming in pass one lane and cars leaving pass the other. Tell the counter which is which and what to count, so each car is counted once instead of twice.</div>
      <svg id="laneSvg" viewBox="0 0 320 142" role="img" aria-label="The far lane at the top, the near lane below it, and the sensor beside the near lane">
        <rect id="laneFarBox" x="10" y="8" width="300" height="46" rx="9" class="lane-box"/>
        <rect id="laneNearBox" x="10" y="60" width="300" height="46" rx="9" class="lane-box"/>
        <text id="laneFarTxt" x="160" y="36" text-anchor="middle" class="lane-txt"></text>
        <text id="laneNearTxt" x="160" y="88" text-anchor="middle" class="lane-txt"></text>
        <path d="M160 130 L118 110 M160 130 L202 110" class="lane-beam"/>
        <circle cx="160" cy="130" r="7" class="lane-sensor"/>
        <text x="176" y="136" class="lane-small">sensor (you are here)</text>
      </svg>
      <div class="lane-q"><b>1.</b> The lane <b>closest to the sensor</b> carries cars that are…</div>
      <div class="seg" role="radiogroup" aria-label="Direction of the near lane">
        <label><input type="radio" name="laneNearIs" value="entering"><span>Coming in</span></label>
        <label><input type="radio" name="laneNearIs" value="exiting"><span>Leaving</span></label>
      </div>
      <div class="lane-q"><b>2.</b> Which cars do you want to <b>count</b>?</div>
      <div class="seg" role="radiogroup" aria-label="Which cars to count">
        <label><input type="radio" name="laneCount" value="entering"><span>Cars coming in</span></label>
        <label><input type="radio" name="laneCount" value="exiting"><span>Cars leaving</span></label>
        <label><input type="radio" name="laneCount" value="both"><span>Both lanes</span></label>
      </div>
      <div id="laneRecord">
        <div class="lane-q"><b>3.</b> Show the sensor where the lanes are.</div>
        <div class="lane-step"><span>Live reading</span><span class="lane-now" id="laneNow">–</span></div>
        <div class="lane-step"><span>Put a car (or yourself) in the lane <b>closest</b> to the sensor, hold still, then</span><button class="b" id="laneNear">Record near lane</button><span class="lane-val" id="laneNearV"></span></div>
        <div class="lane-step"><span>Do the same in the <b>far</b> lane, then</span><button class="b" id="laneFar">Record far lane</button><span class="lane-val" id="laneFarV"></span></div>
      </div>
      <div class="msg" id="laneCurrent"></div>
      <div class="msg" id="laneMsg"></div>
      <div class="row"><button class="b primary" id="laneApply" disabled>Save lane settings</button><button class="b" id="laneReset">Count both lanes again</button></div>
    </div>
    <div class="panel"><h3>Detection</h3><div id="fDetect"></div></div>
    <div class="panel"><h3>When to count</h3><div id="fSched"></div></div>
    <div class="row"><button class="b primary" id="btnSaveSettings">Save settings</button><span class="msg" id="saveMsg1"></span></div>
  </div>

  <!-- ===================== Crowd ===================== -->
  <div id="tab-people" style="display:none">
    <div class="panel"><h3>BaldrickSignals crowd board</h3>
      <div class="help" style="opacity:.9;margin-bottom:.6rem">A BaldrickSignals board estimates how many phones are near your display. This counter checks the board on a schedule, keeps the numbers that fall inside your show hours, and reports them as <strong>people (estimated)</strong> – separate from the car count, since one car can hold several people. Only the board's address is needed; the board must be on the same network as this Pi.</div>
      <div id="fPeople"></div>
      <div class="row"><button class="b primary" id="btnSavePeople">Save</button><button class="b" id="btnTestPeople">Test board</button><button class="b" id="btnPollPeople">Check now</button><span class="msg" id="saveMsgPeople"></span></div>
    </div>
    <div class="panel"><h3>Board status</h3><div id="peopleStatus" class="msg">Not enabled.</div>
      <div id="peopleTestBox" style="display:none">
        <div class="sub" style="margin-top:.6rem">What the counter read from the board (first and last lines):</div>
        <pre id="peopleLines" style="max-height:11rem;overflow:auto"></pre>
        <div class="msg" id="peopleDetect"></div>
      </div>
    </div>
    <div class="panel"><h3>How the numbers are worked out</h3>
      <div class="help" style="opacity:.9">The board reports an estimate of the devices it can see right now (it already applies the offset and multiplier you set on the board). For each show night this counter records the <strong>peak</strong> (the most at once) and an <strong>estimated visitor total</strong> – the number of arrivals seen as the count rises through the evening, after ignoring one-reading blips. Readings outside the show schedule are ignored. It is an estimate, not a head count.</div>
    </div>
  </div>

  <!-- ===================== Website ===================== -->
  <div id="tab-website" style="display:none">
    <div class="panel"><h3>WordPress connection</h3>
      <div class="help" style="opacity:.9;margin-bottom:.6rem">Install the <em>Light Show Car Counter</em> plugin on your WordPress site, then copy the website address and shared secret from <em>Settings → Light Show Car Counter</em> into the boxes below.</div>
      <div id="fWebsite"></div>
      <div class="row"><button class="b primary" id="btnSaveWeb">Save</button><button class="b" id="btnTestWeb">Test connection</button><button class="b" id="btnSyncNow">Upload now</button><span class="msg" id="saveMsg2"></span></div>
    </div>
    <div class="panel"><h3>Sync status</h3><div id="syncStatus" class="msg"></div></div>
  </div>

  <!-- ===================== Wi-Fi ===================== -->
  <div id="tab-wifi" style="display:none">
    <div class="panel" id="wifiUnavailable" style="display:none">
      <h3>Wi-Fi setup is not available here</h3>
      <div class="help" id="wifiUnavailableMsg"></div>
    </div>
    <div id="wifiMain">
      <div class="panel wifi-status">
        <div class="wifi-head">
          <div class="wifi-ico" id="wifiIco" aria-hidden="true"></div>
          <div class="wifi-state"><b id="wifiTitle">Checking…</b><span id="wifiSub"></span></div>
          <label class="switch" title="Turn Wi-Fi on or off"><input type="checkbox" id="wifiPower" aria-label="Wi-Fi on or off"><span class="slider"></span></label>
        </div>
        <div class="warnbox" id="wifiOffWarn" style="display:none">
          You are probably using Wi-Fi to see this page, or this Pi has no network cable plugged in. If you turn Wi-Fi off, this page will stop working until you plug in Ethernet, or switch Wi-Fi back on at the Pi itself. Turn it off anyway?
          <div class="row"><button class="b danger" id="wifiOffYes">Yes, turn Wi-Fi off</button><button class="b" id="wifiOffNo">Keep it on</button></div>
        </div>
        <div class="msg" id="wifiPowerMsg"></div>
      </div>
      <div class="panel" id="wifiProgress" style="display:none">
        <h3 id="wifiProgTitle"></h3>
        <div class="msg" id="wifiProgMsg" style="white-space:pre-line"></div>
      </div>
      <div class="panel">
        <div class="row between"><h3 style="margin:0">Networks</h3><button class="b" id="wifiScan">Scan again</button></div>
        <div id="wifiList" class="wifi-list"></div>
        <div class="msg" id="wifiListMsg"></div>
      </div>
      <div class="panel">
        <h3>Network not in the list?</h3>
        <div class="help">Hidden networks do not show up in a scan. Type the name exactly as it is spelled, including capital letters.</div>
        <div class="field"><label for="wifiHidSsid">Network name</label><div><input type="text" id="wifiHidSsid" maxlength="32" autocomplete="off" autocapitalize="off" spellcheck="false"></div></div>
        <div class="field"><label for="wifiHidSec">Security</label><div><select id="wifiHidSec"><option value="wpa-psk">Password (WPA2 / WPA3)</option><option value="open">No password</option></select></div></div>
        <div class="field" id="wifiHidPwRow"><label for="wifiHidPw">Password</label><div><input type="password" id="wifiHidPw" maxlength="64" autocomplete="off"></div></div>
        <div class="row"><button class="b primary" id="wifiHidGo">Connect</button><span class="msg" id="wifiHidMsg"></span></div>
      </div>
    </div>
  </div>

  <!-- ===================== Log ===================== -->
  <div id="tab-log" style="display:none">
    <div class="row">
      <button class="b" id="btnLog">Refresh</button>
      <button class="b" data-svc="restart">Restart counter service</button>
      <button class="b" data-svc="start">Start</button>
      <button class="b" data-svc="stop">Stop</button>
      <button class="b danger" id="btnRebootLog">Restart the Pi</button>
      <span class="msg" id="svcMsg"></span>
    </div>
    <pre id="logBox">Loading…</pre>
    <div class="sub" id="errLabel" style="display:none">Crash output:</div><pre id="errBox" style="display:none"></pre>
  </div>
</div>

<script>
(function () {
  var AJAX = 'plugin.php?page=ajax.php&nopage=1';
  var $ = function (id) { return document.getElementById(id); };
  var cfg = null, status = null, running = false, stats = null;

  function api(action, body) {
    var opt = { cache: 'no-store' };
    if (body !== undefined) {
      opt.method = 'POST';
      opt.headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      opt.body = JSON.stringify(body);
    }
    return fetch(AJAX + '&action=' + action, opt).then(function (r) { return r.json(); });
  }
  function setMsg(el, text, ok) { el.textContent = text || ''; el.className = 'msg' + (text ? (ok ? ' ok' : ' bad') : ''); }
  function fmtTime(ts) { return ts ? new Date(ts * 1000).toLocaleString() : '–'; }
  function fmtClock(ts) { return ts ? new Date(ts * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '–'; }
  function pill(id, text, cls) { var e = $(id); e.textContent = text; e.className = 'pill ' + (cls || ''); }

  /* ---------- tabs ---------- */
  var tabs = ['dash', 'settings', 'people', 'website', 'wifi', 'log'];
  $('tabs').addEventListener('click', function (e) {
    var t = e.target.getAttribute && e.target.getAttribute('data-tab');
    if (!t) return;
    tabs.forEach(function (n) {
      $('tab-' + n).style.display = (n === t) ? '' : 'none';
    });
    Array.prototype.forEach.call($('tabs').children, function (b) { b.classList.toggle('active', b === e.target); });
    if (t === 'log') loadLog();
    if (t === 'wifi') wifiOpen();
    if (t === 'people' || t === 'settings') loadIfaces();
  });

  /* ---------- settings forms ---------- */
  var FIELDS = {
    fSensor: [
      { k: 'show_name', l: 'Show name', t: 'text', h: 'Shown on your website.' },
      { k: 'connection', l: 'How is the lidar connected?', t: 'select', o: [['usb', 'USB-to-serial adapter (CP2102 etc.) – easiest'], ['gpio', 'Pi GPIO header pins (wired to the Pi)'], ['custom', 'Custom serial port (advanced)']], h: 'Choose how the lidar is plugged in. A USB adapter avoids conflicts with LED-panel HATs; the GPIO pins need no adapter.' },
      { k: 'usb_device', l: 'USB adapter', t: 'select', o: [['', 'Auto-detect (recommended)']], h: 'Only change this if you have more than one USB serial device plugged in.' },
      { k: 'serial_port', l: 'Serial port', t: 'text', list: 'ccPorts', h: 'Full device path, e.g. /dev/ttyAMA1.' },
      { k: 'baud', l: 'Baud rate', t: 'select', o: [9600, 19200, 38400, 57600, 115200, 230400], h: 'TFmini, TFmini-S, TFmini Plus and TF-Luna all default to 115200.' },
      { k: 'unit', l: 'Sensor distance unit', t: 'select', o: [['cm', 'Centimetres (factory default)'], ['mm', 'Millimetres']], h: 'Only change if you switched the sensor into millimetre output.' }
    ],
    fDetect: [
      { k: 'min_cm', l: 'Nearest distance (cm)', t: 'number', h: 'Readings closer than this are ignored.' },
      { k: 'max_cm', l: 'Farthest distance (cm)', t: 'number', h: 'Anything between nearest and farthest counts as a vehicle. Use “Calibrate empty road” on the Dashboard to set this automatically.' },
      { k: 'confirm_ms', l: 'Confirm time (ms)', t: 'number', h: 'How long something must stay in the beam before it counts. Raise it to ignore pedestrians and animals; lower it if fast cars are missed.' },
      { k: 'clear_ms', l: 'Clear time (ms)', t: 'number', h: 'How long the beam must be empty before the next car can count. Stops windows and slow traffic being counted twice.' },
      { k: 'min_strength', l: 'Minimum signal strength', t: 'number', h: '0 = off. Raise to ignore weak, unreliable returns (see the strength shown in the live panel).' },
      { k: 'max_dwell_s', l: 'Blocked-beam warning (s)', t: 'number', h: 'Show a warning if the beam is blocked this long (e.g. a fallen sign). A parked car is still counted once.' }
    ],
    fSched: [
      { k: 'schedule_mode', l: 'Count cars', t: 'select', o: [['window', 'During the show schedule (below)'], ['playing', 'Only while FPP is playing'], ['always', 'Always']], h: 'Detections outside the schedule are kept as “off-hours” so you can see them, but are not added to your totals.' },
      { k: 'fpp_host', l: 'Falcon Player address', t: 'text', h: 'Required for this mode: the IP address of your master Falcon Player, e.g. 192.168.1.50. If it cannot be reached the show schedule below is used instead.' },
      { k: 'fpp_interface', l: 'Network connection to use', t: 'select', o: [['', 'Automatic (recommended)']], h: 'Only change this if the counter cannot reach your Falcon Player. Pick the connection (Ethernet or Wi-Fi) that the Falcon Player is on.' },
      { k: 'schedules', l: 'Show schedule', t: 'schedules', h: 'Used in “During the show schedule” mode, and as the backup when the Falcon Player cannot be reached. Add one entry per set of days with the same hours (e.g. Sun–Thu 5:30 PM–10:00 PM, and Fri–Sat 5:30 PM–11:00 PM). An end time earlier than the start time runs past midnight and counts as part of the day it started.' },
      { k: 'grace_min', l: 'Grace period (min)', t: 'number', h: '“Only while FPP is playing” mode: keep counting this long after the show stops.' },
      { k: 'rollover_hour', l: 'New night starts at', t: 'number', h: 'Hour (0–12). Cars counted after midnight still belong to the previous show night until this hour.' }
    ],
    fPeople: [
      { k: 'people_enabled', l: 'Use BaldrickSignals', t: 'checkbox' },
      { k: 'people_host', l: 'Board IP address', t: 'text', ph: '192.168.1.50', h: 'The address shown on the board’s web page (or baldrickboard.local). Give the board a fixed IP in your router so it does not change.' },
      { k: 'people_interface', l: 'Network connection to use', t: 'select', o: [['', 'Automatic (recommended)']], h: 'Pick the connection your board is on. If the board is on your Ethernet network and the counter cannot find it, choose Ethernet here. Leave on Automatic if it already works.' },
      { k: 'people_interval_min', l: 'Check the board every (min)', t: 'number', h: 'How often to read the board. Each check only downloads the readings since the last check. Outside show hours it checks hourly.' },
      { k: 'people_clock', l: 'Board clock', t: 'select', o: [['local', 'Shows local time (same as this Pi)'], ['utc', 'Shows UTC']], h: 'Only matters if the board’s timestamps have no time zone. Use “Test board” to confirm the newest reading is about now.' },
      { k: 'people_time_col', l: 'Time column (advanced)', t: 'number', h: '0 = detect automatically. Otherwise the column number (1 = first) holding the timestamp.' },
      { k: 'people_count_col', l: 'Count column (advanced)', t: 'number', h: '0 = detect automatically. Otherwise the column number holding the crowd/device count. Use “Test board” to see what was detected.' }
    ],
    fWebsite: [
      { k: 'sync_enabled', l: 'Upload to website', t: 'checkbox' },
      { k: 'wp_url', l: 'Website address', t: 'text', ph: 'https://www.example.com', h: 'Just the site address – no /wp-json path.' },
      { k: 'secret', l: 'Shared secret', t: 'password', h: 'Copied from the WordPress plugin settings.' },
      { k: 'sync_time', l: 'Nightly upload time', t: 'time', h: 'Choose a time after your show ends. If the Pi was off or the site was down it catches up automatically.' },
      { k: 'sync_only_if_played', l: 'Only upload nights with a show', t: 'checkbox', h: 'Skips nights when your Falcon Player did not play a show, so the website only shows real show nights. Needs your Falcon Player’s address. Nights the counter has no record for (it was off, or the Falcon Player could not be reached) are still uploaded.' },
      { k: 'sync_days', l: 'Nights to include', t: 'number', h: 'Each upload re-sends the last N nights so a missed night heals itself.' },
      { k: 'live_interval_min', l: 'Live update every (min)', t: 'number', h: '0 = nightly only. Set e.g. 10 to let the website show tonight’s count while the show is running.' },
      { k: 'verify_tls', l: 'Verify HTTPS certificate', t: 'checkbox', h: 'Leave on unless your site uses a self-signed certificate.' }
    ]
  };
  var DAY_LETTERS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
  var DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  function schedRow(e) {
    var r = document.createElement('div'); r.className = 'sched-row';
    var days = document.createElement('div'); days.className = 'sched-days';
    for (var d = 0; d < 7; d++) {
      var b = document.createElement('button'); b.type = 'button'; b.textContent = DAY_LETTERS[d]; b.title = DAY_NAMES[d];
      b.setAttribute('data-day', d); b.setAttribute('aria-pressed', 'false');
      if (e.days.indexOf(d) >= 0) { b.classList.add('on'); b.setAttribute('aria-pressed', 'true'); }
      b.onclick = function () { var on = this.classList.toggle('on'); this.setAttribute('aria-pressed', on ? 'true' : 'false'); };
      days.appendChild(b);
    }
    function setDays(list) {
      Array.prototype.forEach.call(days.children, function (btn) {
        var on = list.indexOf(+btn.getAttribute('data-day')) >= 0; btn.classList.toggle('on', on); btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }
    var quick = document.createElement('span'); quick.className = 'sched-quick';
    [['Every day', [0,1,2,3,4,5,6]], ['Weekdays', [1,2,3,4,5]], ['Weekends', [0,6]]].forEach(function (q) {
      var qb = document.createElement('button'); qb.type = 'button'; qb.textContent = q[0]; qb.onclick = function () { setDays(q[1]); }; quick.appendChild(qb);
    });
    var st = document.createElement('input'); st.type = 'time'; st.value = e.start; st.className = 'sched-start'; st.setAttribute('aria-label', 'Start time');
    var to = document.createElement('span'); to.textContent = 'to';
    var en = document.createElement('input'); en.type = 'time'; en.value = e.end; en.className = 'sched-end'; en.setAttribute('aria-label', 'End time');
    var del = document.createElement('button'); del.type = 'button'; del.className = 'b danger sched-del'; del.textContent = 'Remove';
    del.onclick = function () { r.parentNode.removeChild(r); schedEmptyNote(); };
    r.appendChild(days); r.appendChild(st); r.appendChild(to); r.appendChild(en); r.appendChild(del); r.appendChild(quick);
    return r;
  }
  function schedRows() {
    var out = [];
    Array.prototype.forEach.call(document.querySelectorAll('#schedEditor .sched-row'), function (r) {
      var days = [];
      Array.prototype.forEach.call(r.querySelectorAll('.sched-days button.on'), function (b) { days.push(+b.getAttribute('data-day')); });
      out.push({ days: days, start: r.querySelector('.sched-start').value, end: r.querySelector('.sched-end').value });
    });
    return out;
  }
  function schedEmptyNote() {
    var rows = schedRows(), bad = rows.some(function (x) { return !x.days.length || !x.start || !x.end; });
    $('schedEmpty').textContent = !rows.length ? 'No schedule entries – nothing will be counted.' : (bad ? 'Every entry needs at least one day and both times, or it is ignored.' : '');
  }
  function fillSchedules() {
    var ed = $('schedEditor'); ed.textContent = '';
    (cfg.schedules || []).forEach(function (e) { ed.appendChild(schedRow(e)); });
    schedEmptyNote();
  }
  var portInfo = { usb: [], gpio: false, all: [], uart: 'unknown' };
  function populateUsb() {
    var sel = $('cf_usb_device'), cur = cfg ? cfg.usb_device : sel.value;
    sel.textContent = '';
    function opt(v, t) { var o = document.createElement('option'); o.value = v; o.textContent = t; sel.appendChild(o); }
    opt('', portInfo.usb.length ? 'Auto-detect (recommended)' : 'Auto-detect (none plugged in yet)');
    portInfo.usb.forEach(function (p) { opt(p.path, p.label); });
    if (cur && !portInfo.usb.some(function (p) { return p.path === cur; })) opt(cur, cur + ' (not detected)');
    sel.value = cur || '';
    var n = $('usbDetected');
    if (portInfo.usb.length) { n.textContent = 'Detected: ' + portInfo.usb[0].label + (portInfo.usb.length > 1 ? ' (+' + (portInfo.usb.length - 1) + ' more)' : ''); n.className = 'msg ok'; }
    else { n.textContent = 'No USB serial adapter detected. Plug it into a USB port on the Pi, then reload this page.'; n.className = 'msg bad'; }
  }
  function applyConnection() {
    var m = $('cf_connection').value;
    $('row_usb_device').style.display = (m === 'usb') ? '' : 'none';
    $('row_serial_port').style.display = (m === 'custom') ? '' : 'none';
    $('wireUsb').style.display = (m === 'usb') ? '' : 'none';
    $('wireGpio').style.display = (m === 'gpio') ? '' : 'none';
    renderUart();
  }
  function applyModeVisibility() {
    var f = $('schedField'); if (f) f.style.display = ($('cf_schedule_mode').value !== 'always') ? '' : 'none';
    var needFpp = ($('cf_schedule_mode').value === 'playing') || !!($('cf_sync_only_if_played') && $('cf_sync_only_if_played').checked);
    var pl = needFpp ? '' : 'none';
    var h = $('row_fpp_host'); if (h) h.style.display = pl;
    var hi = $('row_fpp_interface'); if (hi) hi.style.display = pl;
    var w = $('fppNeeded');
    if (w) w.style.display = ($('cf_sync_only_if_played').checked && !($('cf_fpp_host').value || '').trim()) ? '' : 'none';
  }
  function buildForm(boxId) {
    var box = $(boxId);
    FIELDS[boxId].forEach(function (f) {
      var row = document.createElement('div'); row.className = 'field'; row.id = 'row_' + f.k;
      var lab = document.createElement('label'); lab.textContent = f.l; lab.htmlFor = 'cf_' + f.k;
      var cell = document.createElement('div'), inp;
      if (f.t === 'schedules') {
        var ed = document.createElement('div'); ed.id = 'schedEditor';
        var add = document.createElement('button'); add.type = 'button'; add.className = 'b'; add.id = 'schedAdd'; add.textContent = '+ Add schedule';
        add.onclick = function () {
          var last = schedRows().slice(-1)[0];
          ed.insertBefore(schedRow(last ? { days: [], start: last.start, end: last.end } : { days: [0,1,2,3,4,5,6], start: '17:30', end: '22:00' }), add.parentNode === ed ? add : null);
          schedEmptyNote();
        };
        var note = document.createElement('div'); note.id = 'schedEmpty'; note.className = 'msg bad';
        cell.appendChild(ed); cell.appendChild(note); cell.appendChild(add);
        if (f.h) { var hh = document.createElement('div'); hh.className = 'help'; hh.textContent = f.h; cell.appendChild(hh); }
        row.id = 'schedField'; row.appendChild(lab); row.appendChild(cell); box.appendChild(row);
        return;
      }
      if (f.t === 'select') {
        inp = document.createElement('select');
        f.o.forEach(function (o) {
          var op = document.createElement('option');
          op.value = Array.isArray(o) ? o[0] : o; op.textContent = Array.isArray(o) ? o[1] : o; inp.appendChild(op);
        });
      } else {
        inp = document.createElement('input'); inp.type = f.t;
        if (f.list) inp.setAttribute('list', f.list);
        if (f.ph) inp.placeholder = f.ph;
        if (f.t === 'password') inp.autocomplete = 'new-password';
      }
      inp.id = 'cf_' + f.k; inp.setAttribute('data-key', f.k);
      cell.appendChild(inp);
      if (f.h) { var h = document.createElement('div'); h.className = 'help'; h.textContent = f.h; cell.appendChild(h); }
      row.appendChild(lab); row.appendChild(cell); box.appendChild(row);
    });
  }
  ['fSensor', 'fDetect', 'fSched', 'fPeople', 'fWebsite'].forEach(buildForm);
  $('cf_schedule_mode').addEventListener('change', applyModeVisibility);
  $('cf_sync_only_if_played').addEventListener('change', applyModeVisibility);
  $('cf_fpp_host').addEventListener('input', applyModeVisibility);
  (function () {
    var n = document.createElement('div'); n.id = 'fppNeeded'; n.className = 'msg warn'; n.style.display = 'none';
    n.appendChild(document.createTextNode('Enter your Falcon Player’s address first: '));
    var go = document.createElement('button'); go.type = 'button'; go.className = 'b'; go.textContent = 'Open Sensor & Schedule';
    go.onclick = function () { gotoTab('settings'); var r = $('row_fpp_host'); if (r && r.scrollIntoView) r.scrollIntoView(); };
    n.appendChild(go); $('row_sync_only_if_played').lastChild.appendChild(n);
  })();
  $('cf_connection').addEventListener('change', applyConnection);
  (function () { var n = document.createElement('div'); n.id = 'usbDetected'; n.className = 'msg'; $('row_usb_device').lastChild.appendChild(n); })();
  var dl = document.createElement('datalist'); dl.id = 'ccPorts'; $('cc').appendChild(dl);

  function fillForms() {
    Object.keys(FIELDS).forEach(function (box) {
      FIELDS[box].forEach(function (f) {
        if (f.t === 'schedules') { fillSchedules(); return; }
        var el = $('cf_' + f.k);
        if (f.t === 'checkbox') el.checked = !!cfg[f.k];
        else if (f.k === 'secret') { el.value = ''; el.placeholder = cfg.secret_set ? '•••••••• (saved – leave blank to keep)' : 'paste the secret here'; }
        else el.value = cfg[f.k];
      });
    });
    populateUsb();
    populateIfaces();
    if (typeof laneInit === 'function') laneInit();
    applyConnection();
    applyModeVisibility();
  }
  function collect(boxes) {
    var out = {};
    boxes.forEach(function (box) {
      FIELDS[box].forEach(function (f) {
        if (f.t === 'schedules') { out.schedules = schedRows().filter(function (x) { return x.days.length && x.start && x.end; }); return; }
        var el = $('cf_' + f.k);
        out[f.k] = (f.t === 'checkbox') ? el.checked : el.value;
      });
    });
    return out;
  }
  var toastTimer = null;
  function toast(text, ok) {
    var t = document.getElementById('ccToast');
    if (!t) { t = document.createElement('div'); t.id = 'ccToast'; document.body.appendChild(t); }
    t.textContent = text; t.className = (ok ? '' : 'bad ') + 'show';
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.className = ok ? '' : 'bad'; }, ok ? 3000 : 6000);
  }
  var saving = false;
  function save(boxes, msgEl) {
    if (saving) return Promise.resolve(false);           // ignore a second click while the first save is still going
    saving = true;
    var btns = Array.prototype.slice.call(document.querySelectorAll('#cc button.primary'));
    btns.forEach(function (b) { b.disabled = true; });
    setMsg(msgEl, 'Saving… (a small Pi can take a few seconds)', true);
    function done() { saving = false; btns.forEach(function (b) { b.disabled = false; }); }
    return api('save_config', collect(boxes)).then(function (r) {
      setMsg(msgEl, r.ok ? 'Saved – the counter picks this up within a second.' : (r.error || 'Save failed'), r.ok);
      toast(r.ok ? 'Saved ✓' : (r.error || 'Save failed'), r.ok);
      return api('config').then(function (c) { cfg = c; fillForms(); done(); return r.ok; });
    }).catch(function () {
      setMsg(msgEl, 'Save failed (could not reach the counter).', false); toast('Save failed – could not reach the counter', false); done();
    });
  }
  $('btnSaveSettings').onclick = function () { save(['fSensor', 'fDetect', 'fSched'], $('saveMsg1')); };
  $('btnSaveWeb').onclick = function () { save(['fWebsite'], $('saveMsg2')); };
  $('btnSavePeople').onclick = function () { save(['fPeople'], $('saveMsgPeople')); };
  $('btnTestPeople').onclick = function () {
    save(['fPeople'], $('saveMsgPeople')).then(function () { sendCommand({ cmd: 'people_test' }, $('saveMsgPeople')); });
  };
  $('btnPollPeople').onclick = function () { sendCommand({ cmd: 'people_poll' }, $('saveMsgPeople')); };

  /* ---------- commands ---------- */
  function sendCommand(payload, msgEl, waitForFinal) {
    setMsg(msgEl, 'Working…', true);
    return api('command', payload).then(function (r) {
      if (!r.ok) { setMsg(msgEl, r.error || 'Failed', false); return; }
      var tries = 0;
      (function poll() {
        api('status').then(function (s) {
          var res = s.status && s.status.result;
          if (res && res.id === r.id && !res.pending) { setMsg(msgEl, res.message, res.ok); refresh(); return; }
          if (res && res.id === r.id && res.pending) setMsg(msgEl, res.message, true);
          if (++tries < 40) setTimeout(poll, 700); else setMsg(msgEl, 'No response from the counter service.', false);
        });
      })();
    }).catch(function () { setMsg(msgEl, 'Could not reach the counter.', false); });
  }
  $('btnPlus').onclick = function () { sendCommand({ cmd: 'adjust', delta: 1 }, $('cmdMsg')); };
  $('btnMinus').onclick = function () { sendCommand({ cmd: 'adjust', delta: -1 }, $('cmdMsg')); };
  $('btnReset').onclick = function () {
    if (confirm('Reset tonight’s count to zero?')) sendCommand({ cmd: 'reset_today' }, $('cmdMsg'));
  };
  $('btnTest').onclick = function () {
    var on = !(status && status.test_mode);
    sendCommand({ cmd: 'test_mode', on: on }, $('cmdMsg'));
  };
  $('btnCal').onclick = function () {
    if (confirm('Make sure no cars or people are in front of the sensor. Continue?')) sendCommand({ cmd: 'calibrate' }, $('cmdMsg'));
  };
  $('btnTestWeb').onclick = function () { sendCommand({ cmd: 'sync_test' }, $('saveMsg2')); };
  $('btnSyncNow').onclick = function () { sendCommand({ cmd: 'sync_now' }, $('saveMsg2')); };
  Array.prototype.forEach.call(document.querySelectorAll('[data-svc]'), function (b) {
    b.onclick = function () {
      setMsg($('svcMsg'), 'Working…', true);
      api('service', { op: b.getAttribute('data-svc') }).then(function (r) {
        setMsg($('svcMsg'), r.output || 'Done', true); setTimeout(refresh, 1500);
      });
    };
  });
  function renderUart() {
    var m = $('uartMsg'), b = $('btnRebootUart'), u = portInfo.uart;
    if (!m) return;
    b.style.display = (u === 'needs_restart') ? '' : 'none';
    if (u === 'ready') setMsg(m, 'The Pi’s serial port is switched on and ready.', true);
    else if (u === 'needs_restart') { m.textContent = 'The serial port is switched on, but the Pi needs one restart to finish. Press the button – the counter comes back by itself in about a minute.'; m.className = 'msg warn'; }
    else if (u === 'not_enabled') { m.textContent = 'The serial port is not switched on yet. Run the installer again (sudo ./install.sh) and restart the Pi.'; m.className = 'msg warn'; }
    else setMsg(m, '', true);
  }
  function rebootPi(msgEl) {
    if (!confirm('Restart the Pi now? The counter will be offline for about a minute.')) return;
    setMsg(msgEl, 'Restarting… this page will reconnect when the Pi is back (about a minute).', true);
    api('service', { op: 'reboot' }).then(function (r) { if (r.output && r.output !== 'Done') setMsg(msgEl, r.output, false); });
  }
  $('btnRebootUart').onclick = function () { rebootPi($('uartMsg')); };
  $('btnRebootLog').onclick = function () { rebootPi($('svcMsg')); };
  $('btnLog').onclick = loadLog;
  function loadLog() {
    api('log').then(function (r) {
      $('logBox').textContent = r.log || '(log is empty)';
      $('logBox').scrollTop = 1e9;
      var has = !!(r.errors && r.errors.trim());
      $('errBox').style.display = $('errLabel').style.display = has ? '' : 'none';
      $('errBox').textContent = r.errors || '';
    });
  }

  /* ---------- rendering ---------- */
  function bars(chartId, labelId, labels, values) {
    var chart = $(chartId), lab = $(labelId), max = Math.max.apply(null, values.concat([1]));
    chart.textContent = ''; lab.textContent = '';
    values.forEach(function (v, i) {
      var col = document.createElement('div'); col.className = 'col';
      var val = document.createElement('div'); val.className = 'val'; val.textContent = v > 0 ? v : '';
      var bar = document.createElement('div'); bar.className = 'bar'; bar.style.height = Math.round(v / max * 100) + '%';
      col.appendChild(val); col.appendChild(bar); chart.appendChild(col);
      var s = document.createElement('span'); s.textContent = labels[i]; lab.appendChild(s);
    });
  }

  function renderStatus() {
    if (!running || !status) {
      pill('pillDaemon', 'Service: not running', 'bad'); pill('pillSensor', 'Sensor: –', ''); pill('pillCounting', 'Counting: –', '');
      $('warnings').textContent = '';
      return;
    }
    var s = status;
    $('ccVersion').textContent = 'v' + s.version;
    pill('pillDaemon', 'Service: running', 'ok');
    pill('pillSensor', s.sensor.ok ? 'Sensor: OK (' + s.sensor.fps + ' Hz)' : 'Sensor: no data', s.sensor.ok ? 'ok' : 'bad');
    var c = s.counting;
    pill('pillCounting', (s.test_mode ? 'TEST MODE – not recording' : (c.active ? 'Counting: ON' : 'Counting: paused')) + ' (' + c.reason + ')', s.test_mode ? 'warn' : (c.active ? 'ok' : 'warn'));
    var ps = $('pillSync');
    if (s.sync_enabled) {
      ps.style.display = '';
      var sy = s.sync || {};
      pill('pillSync', sy.last_ok === false ? 'Website: last upload failed' : (sy.last_ok_ts ? 'Website: synced ' + fmtClock(sy.last_ok_ts) : 'Website: waiting for first upload'), sy.last_ok === false ? 'bad' : 'ok');
    } else { ps.style.display = 'none'; }

    var w = $('warnings'); w.textContent = '';
    (s.warnings || []).forEach(function (t) { var d = document.createElement('div'); d.className = 'warnbox'; d.textContent = t; w.appendChild(d); });

    $('tonightLabel').textContent = 'Tonight (' + s.show_date + ')';
    $('vTonight').textContent = s.today.total;
    $('vTonightSub').textContent = s.today.first_ts ? 'First car ' + fmtClock(s.today.first_ts) : 'No cars yet';
    $('vSeason').textContent = s.season_total;
    $('vOff').textContent = s.today.offhours;
    var nights = stats ? stats.days.filter(function (d) { return d.total > 0; }).length : 0;
    $('vSeasonSub').textContent = nights + ' night' + (nights === 1 ? '' : 's') + ' recorded';

    var d = s.detector, dist = s.sensor.distance_cm;
    $('vDist').textContent = dist === null ? 'no return' : dist + ' cm';
    $('laneNow').textContent = dist === null ? 'no return' : dist + ' cm';
    var st = $('vState'); st.textContent = s.calibrating ? 'calibrating' : d.state; st.className = 'pill ' + (d.state === 'present' ? 'ok' : (d.state === 'confirming' ? 'warn' : ''));
    $('vSensorDetail').textContent = 'signal ' + s.sensor.strength + (s.test_mode ? ' · test cars: ' + s.test_count : '');
    var scale = Math.max(d.max_cm * 1.3, 400);
    $('zone').style.left = (d.min_cm / scale * 100) + '%';
    $('zone').style.width = ((d.max_cm - d.min_cm) / scale * 100) + '%';
    var m = $('marker'); m.style.left = (dist === null ? 99.5 : Math.min(99.5, dist / scale * 100)) + '%';
    m.className = 'marker' + (d.in_zone ? ' hit' : '');
    $('btnTest').textContent = s.test_mode ? 'Stop test mode' : 'Test mode';
    $('btnTest').classList.toggle('on', !!s.test_mode);

    var hrs = s.today.hours, lo = 24, hi = -1;
    hrs.forEach(function (v, h) { if (v > 0) { lo = Math.min(lo, h); hi = Math.max(hi, h); } });
    if (hi < 0) { lo = 17; hi = 22; }
    while (hi - lo < 5) { if (hi < 23) hi++; if (hi - lo < 5 && lo > 0) lo--; }
    var L = [], V = [];
    for (var h = lo; h <= hi; h++) { L.push(h); V.push(hrs[h]); }
    bars('chartHours', 'labelsHours', L, V);

    renderPeople(s);

    var sy2 = s.sync || {};
    var txt = sy2.ts ? ('Last attempt ' + fmtTime(sy2.ts) + ' – ' + (sy2.skipped ? 'nothing to send' : sy2.last_ok ? 'OK' : 'FAILED') + ': ' + sy2.message) : 'No upload attempted yet.';
    if (sy2.last_ok_ts) txt += '  Last successful upload: ' + fmtTime(sy2.last_ok_ts) + '.';
    $('syncStatus').textContent = txt;
    $('syncStatus').className = 'msg' + (sy2.ts ? (sy2.last_ok ? ' ok' : ' bad') : '');
  }

  function renderPeople(s) {
    var pp = s.people || {}, st = pp.status || {}, on = !!pp.enabled;
    $('cardPeople').style.display = on ? '' : 'none';
    $('cardDevices').style.display = on ? '' : 'none';
    if (on) {
      var t = pp.today || {};
      $('vPeople').textContent = t.samples ? t.people : '–';
      $('vPeopleSub').textContent = t.samples ? 'Peak at once: ' + t.people_peak : 'No readings in show hours yet';
      $('vDevices').textContent = st.latest_count === null || st.latest_count === undefined ? '–' : st.latest_count;
      $('vDevicesSub').textContent = st.latest_ts ? 'Reading from ' + fmtClock(st.latest_ts) : (st.ok === false ? 'Board not reachable' : 'Waiting for first check');
    }
    var el = $('peopleStatus');
    if (!on) { el.textContent = 'Not enabled. Tick “Use BaldrickSignals”, enter the board’s address and press Save.'; el.className = 'msg'; }
    else if (!st.last_poll) { el.textContent = 'Waiting for the first check of ' + pp.host + '…'; el.className = 'msg'; }
    else {
      var txt = 'Last check ' + fmtTime(st.last_poll) + ' – ' + (st.ok ? 'OK' : 'FAILED: ' + st.error);
      if (st.ok) txt += '. Newest reading: ' + st.latest_count + ' device(s) at ' + fmtTime(st.latest_ts) + '; the board holds ' + st.rows + ' rows. Checking every ' + pp.interval_min + ' min during the show.';
      if (st.clock_warning) txt += '  ' + st.clock_warning;
      el.textContent = txt; el.className = 'msg ' + (st.ok ? 'ok' : 'bad');
    }
    var tr = pp.test, box = $('peopleTestBox');
    if (tr && tr.ts) {
      box.style.display = '';
      $('peopleLines').textContent = (tr.lines || []).join('\n') || '(nothing received)';
      var d = (tr.url ? 'Read from ' + tr.url + '. ' : '') + (tr.ok ? 'Detected – time: ' + tr.time_col + ' · count: ' + tr.count_col + ' · ' + tr.rows + ' rows. ' + (tr.message || '') + (tr.note ? ' ' + tr.note : '') : (tr.message || 'Failed'));
      $('peopleDetect').textContent = d; $('peopleDetect').className = 'msg ' + (tr.ok ? 'ok' : 'bad');
    } else { box.style.display = 'none'; }
  }

  function renderStats() {
    if (!stats) return;
    var days = stats.days.filter(function (d) { return d.total > 0 || d.offhours > 0 || d.people > 0; });
    var last = days.slice(-14);
    bars('chartDays', 'labelsDays', last.map(function (d) { return d.date.slice(5); }), last.map(function (d) { return d.total; }));
    var tb = $('tblDays'); tb.textContent = '';
    var hasPeople = days.some(function (d) { return d.people !== undefined; });
    var head = $('tblHead'); head.textContent = '';
    ['Night', 'Cars', 'Off-hours', 'First car', 'Last car'].concat(hasPeople ? ['People (est.)', 'Peak'] : []).forEach(function (h, i) {
      var th = document.createElement('th'); th.textContent = h; if (i === 1 || i === 2 || i > 4) th.className = 'n'; head.appendChild(th);
    });
    days.slice(-30).reverse().forEach(function (d) {
      var tr = document.createElement('tr');
      var cells = [d.date, d.total, d.offhours, fmtClock(d.first_ts), fmtClock(d.last_ts)];
      if (hasPeople) cells.push(d.people === undefined ? '–' : d.people, d.people_peak === undefined ? '–' : d.people_peak);
      cells.forEach(function (v, i) {
        var td = document.createElement('td'); td.textContent = v; if (i === 1 || i === 2 || i > 4) td.className = 'n'; tr.appendChild(td);
      });
      tb.appendChild(tr);
    });
    var prev = days.filter(function (d) { return !status || d.date < status.show_date; }).slice(-1)[0];
    $('vLast').textContent = prev ? prev.total : '–';
    $('vLastSub').textContent = prev ? prev.date : '';
  }


  // ---- Setup checklist ---------------------------------------------------
  function gotoTab(t) { var b = document.querySelector('#tabs [data-tab="' + t + '"]'); if (b) b.click(); }
  function setupHidden() { try { return localStorage.getItem('ccSetupHidden') === '1'; } catch (e) { return false; } }
  $('setupHide').onclick = function () { try { localStorage.setItem('ccSetupHidden', '1'); } catch (e) {} $('setupBox').style.display = 'none'; };
  function renderSetup() {
    if (!cfg) return;
    var sensorOk = !!(running && status && status.sensor && status.sensor.ok);
    var web = !!(cfg.sync_enabled && cfg.wp_url && cfg.secret_set);
    var items = [
      { done: sensorOk, req: true, t: 'Connect the lidar sensor', d: sensorOk ? 'Sensor is sending readings.' : (cfg.connection === 'gpio' && portInfo.uart === 'needs_restart' ? 'Restart the Pi once to finish switching on the serial port.' : 'Plug it in and choose how it is connected.'), tab: 'settings' },
      { done: !!(cfg.schedules && cfg.schedules.length) || cfg.schedule_mode === 'always', req: false, t: 'Set your show hours', d: 'Cars are only counted during these hours.', tab: 'settings' },
      { done: (cfg.lane_count !== 'both' && cfg.lane_split_cm > 0) || cfg.min_cm > 30, req: false, t: 'Choose which lane to count (optional)', d: 'Avoids counting cars twice on a two-way road.', tab: 'settings' },
      { done: web, req: true, t: 'Connect to your website', d: web ? 'Uploading to ' + cfg.wp_url + '.' : 'Enter your website address and secret.', tab: 'website' }
    ];
    items.push({ done: cfg.schedule_mode === 'playing' && !!cfg.fpp_host, req: false, t: 'Follow your Falcon Player (optional)', d: 'Only count while your show is playing.', tab: 'settings' });
    items.push({ done: !!(cfg.people_enabled && cfg.people_host), req: false, t: 'Count phones with a BaldrickSignals board (optional)', d: 'Estimates how many people are watching.', tab: 'people' });
    var box = $('setupList'); box.textContent = '';
    items.forEach(function (it) {
      var row = document.createElement('div'); row.className = 'setup-item' + (it.done ? ' done' : '');
      var ico = document.createElement('div'); ico.className = 'setup-ico'; ico.textContent = it.done ? '✓' : '';
      var txt = document.createElement('div'); txt.className = 'setup-txt';
      var b = document.createElement('b'); b.textContent = it.t; var sp = document.createElement('span'); sp.textContent = it.d;
      txt.appendChild(b); txt.appendChild(sp);
      row.appendChild(ico); row.appendChild(txt);
      if (!it.done) { var go = document.createElement('button'); go.className = 'b'; go.textContent = 'Set up'; go.onclick = function () { gotoTab(it.tab); }; row.appendChild(go); }
      box.appendChild(row);
    });
    var needed = items.some(function (it) { return it.req && !it.done; });
    $('setupBox').style.display = (needed && !setupHidden()) ? '' : 'none';
  }

  // ---- One-lane helper ----------------------------------------------------
  var laneNear = null, laneFar = null;
  function sampleDistance(n) {
    var vals = [];
    function one(i) {
      return api('status').then(function (r) {
        var d = r.status && r.status.sensor ? r.status.sensor.distance_cm : null;
        if (d !== null && d !== undefined) vals.push(d);
      }).then(function () {
        if (i + 1 >= n) return vals;
        return new Promise(function (res) { setTimeout(res, 250); }).then(function () { return one(i + 1); });
      });
    }
    return one(0).then(function (v) {
      if (v.length < Math.ceil(n / 2)) return null;
      v.sort(function (a, b) { return a - b; }); return v[Math.floor(v.length / 2)];
    });
  }
  function radioVal(name) {
    var el = d_q('input[name=' + name + ']:checked'); return el ? el.value : '';
  }
  function d_q(sel) { return document.querySelector(sel); }
  function setRadio(name, val) {
    Array.prototype.forEach.call(document.querySelectorAll('input[name=' + name + ']'), function (r) { r.checked = (r.value === val); });
  }
  function laneWord(dir) { return dir === 'entering' ? 'coming in' : 'leaving'; }
  function laneSplitNow() {
    if (laneNear !== null && laneFar !== null && laneFar >= laneNear + 30) return Math.round((laneNear + laneFar) / 2);
    return cfg && cfg.lane_split_cm > 0 ? cfg.lane_split_cm : 0;
  }
  function laneDescribe() {
    var near = radioVal('laneNearIs'), count = radioVal('laneCount');
    var far = near === 'entering' ? 'exiting' : 'entering';
    var nearCounted = count === 'both' || count === near, farCounted = count === 'both' || count === far;
    function paint(id, on) { var e = $(id); e.setAttribute('class', 'lane-box ' + (on ? 'counted' : 'ignored')); }
    paint('laneNearBox', nearCounted); paint('laneFarBox', farCounted);
    $('laneNearTxt').textContent = 'Near lane – cars ' + laneWord(near) + (nearCounted ? '  ✓ counted' : '  ✗ ignored');
    $('laneFarTxt').textContent = 'Far lane – cars ' + laneWord(far) + (farCounted ? '  ✓ counted' : '  ✗ ignored');
    $('laneRecord').style.display = count === 'both' ? 'none' : '';
  }
  function laneCurrentText() {
    var m = $('laneCurrent');
    if (!cfg || !radioVal('laneNearIs')) return;
    if (cfg.lane_count !== 'both' && cfg.lane_split_cm > 0) {
      var which = cfg.lane_count === cfg.lane_near_is ? 'near' : 'far';
      m.textContent = 'Now counting only cars ' + laneWord(cfg.lane_count) + ' (the ' + which + ' lane). Cars in the other lane are ignored. The lanes are split at ' + cfg.lane_split_cm + ' cm.';
      m.className = 'msg ok';
    } else {
      m.textContent = 'Now counting both lanes.' + (cfg.min_cm > 30 ? ' (Readings closer than ' + cfg.min_cm + ' cm are ignored – see Detection below.)' : '');
      m.className = 'msg';
    }
  }
  function laneInit() {
    if (!cfg) return;
    setRadio('laneNearIs', cfg.lane_near_is || 'exiting');
    setRadio('laneCount', cfg.lane_count || 'both');
    laneDescribe(); laneUpdate(); laneCurrentText();
  }
  function laneUpdate() {
    $('laneNearV').textContent = laneNear === null ? '' : laneNear + ' cm';
    $('laneFarV').textContent = laneFar === null ? '' : laneFar + ' cm';
    var count = radioVal('laneCount'), both = laneNear !== null && laneFar !== null;
    var readingsOk = both && laneFar >= laneNear + 30;
    var m = $('laneMsg');
    var haveSplit = readingsOk || (cfg && cfg.lane_split_cm > 0 && !both);
    $('laneApply').disabled = !(count === 'both' || haveSplit);
    if (count === 'both') { setMsg(m, 'Both lanes will be counted.', true); return; }
    if (both && !readingsOk) setMsg(m, 'The two readings are too close together – check the near lane is really closer to the sensor than the far lane.', false);
    else if (readingsOk) setMsg(m, 'The lanes will be split at ' + Math.round((laneNear + laneFar) / 2) + ' cm.', true);
    else if (cfg && cfg.lane_split_cm > 0) setMsg(m, 'Using the lane split you already saved (' + cfg.lane_split_cm + ' cm). Record both lanes again only if you moved the sensor.', true);
    else setMsg(m, 'Record the near lane and the far lane so the counter knows where they are.', true);
  }
  function laneRecord(which) {
    var m = $('laneMsg'); setMsg(m, 'Reading… hold still for a moment.', true);
    sampleDistance(8).then(function (v) {
      if (v === null) { setMsg(m, 'The sensor sees nothing there. Put a car or person in that lane, hold still and try again.', false); return; }
      if (which === 'near') laneNear = v; else laneFar = v;
      laneUpdate();
    });
  }
  $('laneNear').onclick = function () { laneRecord('near'); };
  $('laneFar').onclick = function () { laneRecord('far'); };
  Array.prototype.forEach.call(document.querySelectorAll('input[name=laneNearIs], input[name=laneCount]'), function (r) {
    r.onchange = function () { laneDescribe(); laneUpdate(); };
  });
  function laneRefresh(msg, ok) {
    return api('config').then(function (c) { cfg = c; fillForms(); renderSetup(); setMsg($('laneMsg'), msg, ok); });
  }
  $('laneApply').onclick = function () {
    var near = radioVal('laneNearIs'), count = radioVal('laneCount');
    var body = { lane_near_is: near, lane_count: count };
    var legacy = cfg.lane_split_cm === 0 && cfg.min_cm > 30;        // an older version ignored a lane by raising the nearest distance
    if (count === 'both') {
      body.lane_split_cm = 0;
    } else {
      body.lane_split_cm = laneSplitNow();
      if (!body.lane_split_cm) { setMsg($('laneMsg'), 'Record both lanes first.', false); return; }
      var farCounted = count !== near;
      if (laneFar !== null && farCounted && cfg.max_cm < laneFar + 50) body.max_cm = laneFar + 150;   // far lane must be inside the range
    }
    if (legacy) body.min_cm = 30;
    $('laneApply').disabled = true; setMsg($('laneMsg'), 'Saving…', true);
    api('save_config', body).then(function (r) {
      if (!r.ok) { setMsg($('laneMsg'), r.error || 'Save failed', false); laneUpdate(); return; }
      var done = count === 'both' ? 'Saved. Both lanes will be counted.'
        : 'Saved. Counting only cars ' + laneWord(count) + ' (the ' + (count === near ? 'near' : 'far') + ' lane). Drive a car through each way to check it counts once.';
      return laneRefresh(done, true).then(function () { laneNear = laneFar = null; laneUpdate(); setMsg($('laneMsg'), done, true); });
    }).catch(function () { setMsg($('laneMsg'), 'Could not reach the counter.', false); laneUpdate(); });
  };
  $('laneReset').onclick = function () {
    var body = { lane_count: 'both', lane_split_cm: 0 };
    if (cfg.lane_split_cm === 0 && cfg.min_cm > 30) body.min_cm = 30;
    api('save_config', body).then(function (r) {
      laneNear = laneFar = null;
      return laneRefresh(r.ok ? 'Both lanes will be counted again.' : 'Save failed', r.ok);
    }).then(function () { laneInit(); });
  };

  // ---- Network connection choice (Ethernet / Wi-Fi) for the board and the Falcon Player ----
  var ifaceList = [];
  function ifaceLabel(i) {
    return (i.kind === 'wifi' ? 'Wi-Fi' : 'Ethernet') + ' (' + i.name + ')' + (i.ip ? ' – ' + i.ip : (i.up ? '' : ' – not connected'));
  }
  function populateIfaces() {
    ['people_interface', 'fpp_interface'].forEach(function (k) {
      var sel = $('cf_' + k); if (!sel) return;
      var cur = cfg ? (cfg[k] || '') : sel.value;
      sel.textContent = '';
      function opt(v, t) { var o = document.createElement('option'); o.value = v; o.textContent = t; sel.appendChild(o); }
      opt('', 'Automatic (recommended)');
      ifaceList.forEach(function (i) { opt(i.name, ifaceLabel(i)); });
      if (cur && !ifaceList.some(function (i) { return i.name === cur; })) opt(cur, cur + ' (not found)');
      sel.value = cur;
    });
  }
  function loadIfaces() {
    return api('ifaces').then(function (r) { ifaceList = r.ifaces || []; populateIfaces(); }).catch(function () {});
  }

  // ---- Wi-Fi tab --------------------------------------------------------------------------
  var wifi = { st: null, nets: [], out: [], busy: false, poll: null, open: null, loaded: false };
  var ICON_ON = '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2.5 9a14 14 0 0 1 19 0"/><path d="M5.8 12.6a9.4 9.4 0 0 1 12.4 0"/><path d="M9 16.2a4.8 4.8 0 0 1 6 0"/><circle cx="12" cy="19.6" r="1" fill="currentColor"/></svg>';
  var ICON_OFF = '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2.5 9a14 14 0 0 1 19 0" opacity=".35"/><path d="M5.8 12.6a9.4 9.4 0 0 1 12.4 0" opacity=".35"/><path d="M9 16.2a4.8 4.8 0 0 1 6 0" opacity=".35"/><path d="M3 3l18 18"/></svg>';
  function signalWord(n) { return n >= 67 ? 'excellent' : n >= 50 ? 'good' : n >= 30 ? 'fair' : 'weak'; }
  function bars(n) {
    var b = document.createElement('span'); b.className = 'bars l' + (n >= 67 ? 4 : n >= 50 ? 3 : n >= 30 ? 2 : 1);
    b.title = 'Signal ' + n + '%'; for (var i = 0; i < 4; i++) b.appendChild(document.createElement('i'));
    return b;
  }
  function showProgress(title, text, cls) {
    $('wifiProgress').style.display = ''; $('wifiProgTitle').textContent = title;
    var m = $('wifiProgMsg'); m.textContent = text || ''; m.className = 'msg' + (cls === 'ok' ? ' ok' : cls === 'bad' ? ' bad' : cls === 'warn' ? ' warn' : '');
  }
  function wifiRenderStatus() {
    var st = wifi.st; if (!st) return;
    var un = $('wifiUnavailable'), main = $('wifiMain');
    if (!st.available) {
      var why = st.reason === 'nmcli_missing' ? 'This page changes Wi-Fi through NetworkManager, and it is not installed on this system. You can still set Wi-Fi up with your system’s own tools (for example the network settings on the desktop, or the command  sudo nmtui ).'
        : st.reason === 'nm_not_running' ? 'NetworkManager is not running, so this system manages Wi-Fi some other way. Use your system’s own network tools to join a network (for example  sudo nmtui  or the Raspberry Pi imager’s Wi-Fi settings).'
        : (st.error || 'The Wi-Fi helper is not available.');
      $('wifiUnavailableMsg').textContent = why; un.style.display = ''; main.style.display = 'none'; return;
    }
    un.style.display = 'none'; main.style.display = '';
    if (!st.has_device) {
      $('wifiIco').innerHTML = ICON_OFF; $('wifiIco').className = 'wifi-ico off';
      $('wifiTitle').textContent = 'No Wi-Fi adapter found'; $('wifiSub').textContent = 'This board has no working Wi-Fi. Use the Ethernet port, or plug in a USB Wi-Fi adapter.';
      $('wifiPower').disabled = true; $('wifiPower').checked = false; return;
    }
    var on = !!st.radio_on, conn = on && st.ip;
    $('wifiIco').innerHTML = on ? ICON_ON : ICON_OFF; $('wifiIco').className = 'wifi-ico ' + (conn ? 'ok' : on ? 'warn' : 'off');
    $('wifiTitle').textContent = !on ? 'Wi-Fi is off' : conn ? 'Connected to ' + (st.ssid || 'a network') : 'Not connected';
    var sub = [];
    if (conn) { sub.push('Address ' + st.ip); if (st.signal) sub.push('signal ' + signalWord(st.signal)); }
    if (st.ethernet) sub.push('Ethernet cable is connected');
    $('wifiSub').textContent = sub.join(' · ') || (on ? 'Pick a network below.' : 'Switch it on to join a network.');
    $('wifiPower').disabled = wifi.busy; $('wifiPower').checked = on;
  }
  function wifiLoadStatus() {
    return api('wifi_status').then(function (r) { wifi.st = r; wifiRenderStatus(); return r; }).catch(function () { return null; });
  }
  function wifiRenderList() {
    var box = $('wifiList'); box.textContent = '';
    var msg = $('wifiListMsg');
    if (wifi.st && wifi.st.available && wifi.st.has_device && !wifi.st.radio_on) { setMsg(msg, 'Turn Wi-Fi on to see networks.', false); return; }
    if (!wifi.nets.length) setMsg(msg, wifi.loaded ? 'No networks found. Move closer to your router and scan again.' : 'Scanning…', !wifi.loaded);
    else setMsg(msg, '', true);
    wifi.nets.forEach(function (n) {
      var wrap = document.createElement('div'); wrap.className = 'wifi-net' + (n.in_use ? ' in-use' : '') + (wifi.open === n.ssid ? ' open' : '');
      var row = document.createElement('button'); row.type = 'button'; row.className = 'wifi-row';
      row.setAttribute('aria-expanded', wifi.open === n.ssid ? 'true' : 'false');
      row.appendChild(bars(n.signal));
      var nm = document.createElement('span'); nm.className = 'wifi-name'; nm.textContent = n.ssid; row.appendChild(nm);
      var bd = document.createElement('span'); bd.className = 'wifi-badges';
      if (n.in_use) { var b1 = document.createElement('span'); b1.className = 'badge ok'; b1.textContent = 'Connected'; bd.appendChild(b1); }
      else if (n.saved) { var b2 = document.createElement('span'); b2.className = 'badge'; b2.textContent = 'Saved'; bd.appendChild(b2); }
      row.appendChild(bd);
      var lk = document.createElement('span'); lk.className = 'lock'; lk.title = n.secure ? 'Needs a password' : 'Open network';
      lk.textContent = n.secure ? '🔒' : ''; row.appendChild(lk);
      row.onclick = function () { wifi.open = (wifi.open === n.ssid) ? null : n.ssid; wifiRenderList(); };
      wrap.appendChild(row);
      if (wifi.open === n.ssid) wrap.appendChild(wifiForm(n));
      box.appendChild(wrap);
    });
    (wifi.out || []).forEach(function (ssid) {
      var wrap = document.createElement('div'); wrap.className = 'wifi-net';
      var row = document.createElement('div'); row.className = 'wifi-row static';
      var nm = document.createElement('span'); nm.className = 'wifi-name'; nm.textContent = ssid;
      var bd = document.createElement('span'); bd.className = 'wifi-badges';
      var b = document.createElement('span'); b.className = 'badge'; b.textContent = 'Saved – not in range'; bd.appendChild(b);
      var fg = document.createElement('button'); fg.type = 'button'; fg.className = 'b'; fg.textContent = 'Forget'; fg.onclick = function () { wifiForget(ssid); };
      row.appendChild(nm); row.appendChild(bd); row.appendChild(fg); wrap.appendChild(row); box.appendChild(wrap);
    });
  }
  function wifiForm(n) {
    var f = document.createElement('div'); f.className = 'wifi-form';
    function note(t, cls) { var d = document.createElement('div'); d.className = 'help' + (cls ? ' ' + cls : ''); d.textContent = t; f.appendChild(d); return d; }
    function btn(t, cls, fn) { var b = document.createElement('button'); b.type = 'button'; b.className = 'b ' + (cls || ''); b.textContent = t; b.onclick = fn; return b; }
    var row = document.createElement('div'); row.className = 'row';
    if (!n.supported) {
      note(n.security === 'enterprise' ? 'This is a business or school network (it needs a username). It can’t be set up from this page.' : n.security === 'wep' ? 'This network uses an old, insecure security type (WEP) that isn’t supported here.' : 'This kind of network isn’t supported here.');
    } else if (n.in_use) {
      note('You are connected to this network.');
      if (n.saved) { row.appendChild(btn('Forget this network', 'danger', function () { wifiForget(n.ssid); })); f.appendChild(row); }
    } else if (n.saved) {
      note('The password for this network is already saved.');
      row.appendChild(btn('Connect', 'primary', function () { startConnect({ ssid: n.ssid, security: n.security, use_saved: true }); }));
      row.appendChild(btn('Forget', 'danger', function () { wifiForget(n.ssid); })); f.appendChild(row);
    } else if (n.secure) {
      var pw = document.createElement('input'); pw.type = 'password'; pw.placeholder = 'Network password'; pw.autocomplete = 'off'; pw.maxLength = 64;
      var show = document.createElement('label'); show.className = 'showpw';
      var cb = document.createElement('input'); cb.type = 'checkbox'; cb.onchange = function () { pw.type = cb.checked ? 'text' : 'password'; };
      show.appendChild(cb); show.appendChild(document.createTextNode(' Show password'));
      var go = btn('Connect', 'primary', function () { startConnect({ ssid: n.ssid, security: n.security, password: pw.value }); });
      go.disabled = true;
      pw.oninput = function () { go.disabled = pw.value.length < 8; };
      pw.onkeydown = function (e) { if (e.key === 'Enter' && !go.disabled) go.click(); };
      f.appendChild(pw); f.appendChild(show); row.appendChild(go); f.appendChild(row);
      note('A Wi-Fi password is 8 to 63 characters.');
    } else {
      note('This is an open network: it has no password, so others nearby could read what is sent over it.', 'warn');
      row.appendChild(btn('Connect anyway', 'primary', function () { startConnect({ ssid: n.ssid, security: 'open' }); })); f.appendChild(row);
    }
    return f;
  }
  function wifiScan(rescan) {
    var b = $('wifiScan'); b.disabled = true; b.textContent = 'Scanning…';
    if (rescan) setMsg($('wifiListMsg'), 'Looking for networks… this can take several seconds.', true);
    return api(rescan ? 'wifi_scan&rescan=1' : 'wifi_scan').then(function (r) {
      wifi.loaded = true;
      if (r.ok) { wifi.nets = r.networks || []; wifi.out = r.saved_out_of_range || []; wifiRenderList(); }
      else { wifi.nets = []; wifi.out = []; wifiRenderList(); if (!(wifi.st && !wifi.st.radio_on)) setMsg($('wifiListMsg'), r.error || 'Scan failed.', false); }
    }).catch(function () { setMsg($('wifiListMsg'), 'Could not reach the Pi.', false); })
      .then(function () { b.disabled = false; b.textContent = 'Scan again'; });
  }
  function wifiOpen() {
    wifiLoadStatus().then(function (st) {
      if (st && st.available && st.has_device && st.radio_on) wifiScan(!wifi.loaded);
      else wifiRenderList();
    });
  }
  function wifiRisky() { var s = wifi.st; return !!(s && s.radio_on && (s.via_wifi || !s.ethernet)); }
  function wifiSetPower(on) {
    var pm = $('wifiPowerMsg'); $('wifiPower').disabled = true; wifi.busy = true;
    setMsg(pm, on ? 'Turning Wi-Fi on…' : 'Turning Wi-Fi off…', true);
    return api('wifi_power', { on: on }).then(function (r) {
      wifi.busy = false;
      if (!r.ok) { setMsg(pm, r.error || 'Could not change Wi-Fi.', false); return wifiLoadStatus(); }
      setMsg(pm, on ? 'Wi-Fi is on.' : 'Wi-Fi is off.', true);
      wifi.nets = []; wifi.out = []; wifi.loaded = false;
      return wifiLoadStatus().then(function () { if (on) { wifiRenderList(); setTimeout(function () { wifiScan(true); }, 1500); } else wifiRenderList(); });
    }).catch(function () {
      wifi.busy = false;
      setMsg(pm, on ? 'Lost contact with the Pi. Reload in a moment.' : 'Wi-Fi was switched off and this page lost contact with the Pi. Plug in Ethernet or switch Wi-Fi back on at the Pi.', false);
    });
  }
  $('wifiPower').onchange = function () {
    var want = this.checked;
    if (!want && wifiRisky()) { this.checked = true; $('wifiOffWarn').style.display = ''; return; }
    $('wifiOffWarn').style.display = 'none'; wifiSetPower(want);
  };
  $('wifiOffYes').onclick = function () { $('wifiOffWarn').style.display = 'none'; wifiSetPower(false); };
  $('wifiOffNo').onclick = function () { $('wifiOffWarn').style.display = 'none'; };
  $('wifiScan').onclick = function () { wifiScan(true); };
  function wifiForget(ssid) {
    setMsg($('wifiListMsg'), 'Forgetting ' + ssid + '…', true);
    api('wifi_forget', { ssid: ssid }).then(function (r) {
      setMsg($('wifiListMsg'), r.ok ? 'Forgot ' + ssid + '.' : (r.error || 'Could not forget it.'), r.ok);
      wifi.open = null; return wifiLoadStatus().then(function () { return wifiScan(false); });
    }).catch(function () { setMsg($('wifiListMsg'), 'Could not reach the Pi.', false); });
  }
  function startConnect(p) {
    if (wifi.busy) return;
    wifi.busy = true; $('wifiPower').disabled = true;
    var prevTs = (wifi.st && wifi.st.result) ? wifi.st.result.ts : 0;
    var viaWifi = !!(wifi.st && wifi.st.via_wifi);
    showProgress('Connecting to ' + p.ssid + '…', 'This can take up to a minute.' + (viaWifi
      ? '\nYou are viewing this page over Wi-Fi, so it may disconnect while the Pi switches networks. If it does, join the same network on your phone or computer, then open this page at the Pi’s new address (your router’s device list shows it, or run  hostname -I  on the Pi). The result will be shown here.'
      : ''), 'warn');
    function poll(t0, lost) {
      clearTimeout(wifi.poll);
      wifi.poll = setTimeout(function () {
        api('wifi_status').then(function (st) {
          wifi.st = st; wifiRenderStatus();
          var r = st.result;
          if (r && r.ts !== prevTs && r.ssid === p.ssid && r.state === 'ok') {
            wifi.busy = false; wifiRenderStatus();
            showProgress('Connected to ' + p.ssid, 'The Pi’s address on this network is ' + (r.ip || 'unknown') + '.' + (viaWifi && r.ip ? '\nIf this page stops responding, open  http://' + r.ip + '/  instead.' : ''), 'ok');
            wifi.open = null; wifiScan(false); return;
          }
          if (r && r.ts !== prevTs && r.ssid === p.ssid && r.state === 'failed') {
            wifi.busy = false; wifiRenderStatus();
            showProgress('Could not connect to ' + p.ssid, (r.message || 'Check the password and try again.') + '\nThe Pi went back to its previous network if it could.', 'bad');
            wifiScan(false); return;
          }
          if (Date.now() - t0 > 180000) { wifi.busy = false; wifiRenderStatus(); showProgress('Still waiting…', 'The Pi has not reported back. Reload this page; if it is unreachable, check the Pi’s network at its own screen or cable.', 'bad'); return; }
          poll(t0, false);
        }).catch(function () {
          if (Date.now() - t0 > 300000) { wifi.busy = false; wifiRenderStatus(); showProgress('Lost contact with the Pi', 'Join the same network as the Pi on this device, then reload and open the Pi’s new address.', 'bad'); return; }
          showProgress('Connecting to ' + p.ssid + '…', 'Lost contact with the Pi. That is normal while it moves to another network.\nJoin the same network on this device and open the Pi’s new address; this page keeps trying.', 'warn');
          poll(t0, true);
        });
      }, 2000);
    }
    var t0 = Date.now();
    api('wifi_connect', p).then(function (r) {
      if (!r.ok) { wifi.busy = false; wifiRenderStatus(); showProgress('Could not start', r.error || 'Something went wrong.', 'bad'); return; }
      poll(t0, false);
    }).catch(function () { poll(t0, true); });
  }
  $('wifiHidSec').onchange = function () { $('wifiHidPwRow').style.display = this.value === 'open' ? 'none' : ''; };
  $('wifiHidGo').onclick = function () {
    var ssid = $('wifiHidSsid').value, sec = $('wifiHidSec').value, pw = $('wifiHidPw').value, m = $('wifiHidMsg');
    if (!ssid) { setMsg(m, 'Type the network name.', false); return; }
    if (sec !== 'open' && (pw.length < 8 || pw.length > 64)) { setMsg(m, 'A Wi-Fi password is 8 to 63 characters.', false); return; }
    setMsg(m, '', true);
    startConnect({ ssid: ssid, security: sec === 'open' ? 'open' : 'wpa-psk', password: sec === 'open' ? '' : pw, hidden: true });
  };

  function refresh() {
    return api('status').then(function (r) { running = r.running; status = r.status; renderStatus(); renderStats(); renderSetup(); }).catch(function () {
      running = false; renderStatus();
    });
  }
  function refreshStats() { api('stats').then(function (s) { stats = s; renderStats(); renderStatus(); }); }

  $('csvLink').href = AJAX + '&action=csv';
  api('config').then(function (c) { cfg = c; fillForms(); renderSetup(); });
  loadIfaces();
  api('ports').then(function (p) {
    portInfo = p; dl.textContent = '';
    renderUart(); renderSetup();
    (p.all || []).forEach(function (x) { var o = document.createElement('option'); o.value = x; dl.appendChild(o); });
    populateUsb();
  });
  (function () { var h = (location.hash || '').replace('#', ''); if (h) gotoTab(h); })();
  refreshStats(); refresh();
  var currentTab = function () { return $('tab-dash').style.display === 'none' ? 'other' : 'dash'; };
  (function loop() {
    // one request at a time, never faster than the Pi can answer; relaxed on other tabs and paused when the page is in the background
    var wait = document.hidden ? 5000 : (currentTab() === 'dash' ? 1500 : 4000);
    setTimeout(function () {
      if (document.hidden) return loop();
      Promise.resolve(refresh()).then(loop, loop);
    }, wait);
  })();
  setInterval(function () { if (!document.hidden) refreshStats(); }, 20000);
})();
</script>
