#!/usr/bin/env node
/**
 * Bagel Boyz NJ — Local Print Bridge
 * ==================================
 * Runs on any always-on machine on the shop's wifi (Raspberry Pi, old laptop,
 * the back-office PC). Polls bagelboyznj.com for kitchen tickets and writes
 * them straight to the Star TSP143IIIW over the LAN.
 *
 *      internet                     shop wifi
 *   ┌────────────┐   HTTPS GET    ┌────────┐   TCP :9100   ┌─────────┐
 *   │ bagelboyz  │ ◄───────────── │ bridge │ ────────────► │ TSP143  │
 *   │   .com     │ ──── job ────► │        │               │  IIIW   │
 *   └────────────┘                └────────┘               └─────────┘
 *
 * WHY THIS EXISTS
 * ---------------
 * The TSP100III has no CloudPRNT firmware, so it can't fetch jobs itself.
 * Pushing from the iPad hits the HTTPS→HTTP mixed-content block that every
 * iOS browser enforces (Chrome on iPad is WebKit underneath, so it behaves
 * exactly like Safari). The bridge makes an ordinary OUTBOUND https call —
 * nothing to unblock, no port forwarding, no static WAN IP — and then talks
 * to the printer on the local network where plain TCP is fine.
 *
 * Net effect: tickets print automatically whether or not anyone is looking
 * at the iPad, and the iPad becomes purely a display.
 *
 * Zero npm dependencies — only Node built-ins.
 *
 * RUN IT
 *   cd backend
 *   cp .env.example .env        # fill in the BB_PRINT_* values below
 *   node src/print-bridge.js    # test in the foreground first
 *   pm2 start ecosystem.config.js --only bb-print-bridge
 *   pm2 save && pm2 startup     # survive a reboot
 */

'use strict';

const net = require('net');
const https = require('https');
const http = require('http');
const { URL } = require('url');

try { require('dotenv').config(); } catch (_) { /* dotenv is optional here */ }

/* ============================================================
   CONFIG
   ============================================================ */
const CFG = {
  siteUrl:    process.env.BB_SITE_URL     || 'https://bagelboyznj.com',
  location:   process.env.BB_PRINT_LOC    || 'holmdel',
  pollKey:    process.env.BB_PRINT_KEY    || '',
  printerIp:  process.env.BB_PRINTER_IP   || '',
  printerPort: parseInt(process.env.BB_PRINTER_PORT || '9100', 10),
  pollMs:     parseInt(process.env.BB_PRINT_POLL_MS || '4000', 10),
  hostLabel:  process.env.BB_PRINT_HOST   || require('os').hostname(),
};

function log(level, msg) {
  const ts = new Date().toISOString().replace('T', ' ').slice(0, 19);
  console.log(`[${ts}] ${level.padEnd(5)} ${msg}`);
}

if (!CFG.pollKey || !CFG.printerIp) {
  log('FATAL', 'BB_PRINT_KEY and BB_PRINTER_IP must both be set in backend/.env');
  log('FATAL', 'BB_PRINT_KEY must match printing.poll_key in includes/order-config.php');
  process.exit(1);
}

/* ============================================================
   HTTP
   ============================================================ */
function request(urlStr, method = 'GET') {
  return new Promise((resolve, reject) => {
    const url = new URL(urlStr);
    const lib = url.protocol === 'http:' ? http : https;

    const req = lib.request(
      { method, hostname: url.hostname, port: url.port || undefined,
        path: url.pathname + url.search, timeout: 15000,
        headers: { 'User-Agent': 'BagelBoyzPrintBridge/1.0', 'Accept': 'application/json' } },
      (res) => {
        let body = '';
        res.setEncoding('utf8');
        res.on('data', (c) => { body += c; });
        res.on('end', () => {
          try {
            resolve({ status: res.statusCode, data: JSON.parse(body) });
          } catch (e) {
            reject(new Error(`Bad JSON from server (HTTP ${res.statusCode}): ${body.slice(0, 200)}`));
          }
        });
      }
    );

    req.on('timeout', () => { req.destroy(new Error('Request timed out')); });
    req.on('error', reject);
    req.end();
  });
}

const endpoint = (extra = '') =>
  `${CFG.siteUrl.replace(/\/$/, '')}/print/bridge.php` +
  `?loc=${encodeURIComponent(CFG.location)}` +
  `&key=${encodeURIComponent(CFG.pollKey)}` +
  `&host=${encodeURIComponent(CFG.hostLabel)}${extra}`;

/* ============================================================
   PRINTER  —  raw Star Line Mode bytes over TCP 9100
   ============================================================ */
function sendToPrinter(buffer) {
  return new Promise((resolve, reject) => {
    const socket = new net.Socket();
    let settled = false;

    const done = (err) => {
      if (settled) return;
      settled = true;
      socket.destroy();
      err ? reject(err) : resolve();
    };

    socket.setTimeout(10000);
    socket.on('timeout', () => done(new Error('Printer did not respond (timeout)')));
    socket.on('error', (e) => done(e));

    socket.connect(CFG.printerPort, CFG.printerIp, () => {
      socket.write(buffer, () => {
        // Give the printer a moment to pull the bytes off the wire before
        // we tear the socket down, or the tail of the ticket can be lost.
        setTimeout(() => { socket.end(); done(null); }, 400);
      });
    });
  });
}

/* ============================================================
   MAIN LOOP
   ============================================================ */
let consecutiveErrors = 0;
let lastErrorLogged = '';
let running = true;

async function tick() {
  // Drain the whole queue each pass — a breakfast rush can stack several
  // tickets between polls, and they should all print immediately.
  for (let drained = 0; drained < 20; drained++) {
    const res = await request(endpoint());

    if (res.status !== 200 || !res.data.ok) {
      throw new Error(res.data && res.data.message ? res.data.message : `HTTP ${res.status}`);
    }
    if (res.data.empty) return;

    const { job_token: token, order_code: code, payload } = res.data;
    const buffer = Buffer.from(payload, 'base64');

    try {
      await sendToPrinter(buffer);
      await request(endpoint(`&ack=${encodeURIComponent(token)}&result=ok`), 'POST');
      log('OK', `printed ${code} (${buffer.length} bytes)`);
    } catch (err) {
      // Tell the server it failed so the job is marked rather than silently
      // vanishing — staff can then reprint from the iPad.
      await request(endpoint(`&ack=${encodeURIComponent(token)}&result=fail`), 'POST').catch(() => {});
      throw new Error(`printer write failed for ${code}: ${err.message}`);
    }
  }
}

async function loop() {
  while (running) {
    try {
      await tick();
      if (consecutiveErrors > 0) {
        log('OK', 'recovered — back to normal');
        consecutiveErrors = 0;
        lastErrorLogged = '';
      }
    } catch (err) {
      consecutiveErrors++;
      // Don't spam identical errors; log the first, then every 15th.
      if (err.message !== lastErrorLogged || consecutiveErrors % 15 === 0) {
        log('ERROR', `${err.message}${consecutiveErrors > 1 ? ` (x${consecutiveErrors})` : ''}`);
        lastErrorLogged = err.message;
      }
    }

    // Back off when something is broken so a downed printer or dropped wifi
    // doesn't hammer the server, but stay responsive when healthy.
    const wait = consecutiveErrors === 0
      ? CFG.pollMs
      : Math.min(60000, CFG.pollMs * Math.pow(2, Math.min(consecutiveErrors, 4)));

    await new Promise((r) => setTimeout(r, wait));
  }
}

/* ============================================================
   STARTUP
   ============================================================ */
log('INFO', '─'.repeat(58));
log('INFO', 'Bagel Boyz NJ — Print Bridge');
log('INFO', `location : ${CFG.location}`);
log('INFO', `server   : ${CFG.siteUrl}`);
log('INFO', `printer  : ${CFG.printerIp}:${CFG.printerPort}`);
log('INFO', `poll     : every ${CFG.pollMs}ms`);
log('INFO', '─'.repeat(58));

// Fail fast and loudly if the printer isn't reachable at boot — far better
// than discovering it during the 7 AM rush.
const probe = new net.Socket();
probe.setTimeout(5000);
probe.on('connect', () => {
  log('OK', 'printer reachable');
  probe.destroy();
  loop();
});
probe.on('timeout', () => {
  log('WARN', `printer at ${CFG.printerIp}:${CFG.printerPort} did not answer — check it is on and the IP is right`);
  log('WARN', 'starting anyway; will keep retrying');
  probe.destroy();
  loop();
});
probe.on('error', (e) => {
  log('WARN', `printer probe failed: ${e.message}`);
  log('WARN', 'starting anyway; will keep retrying');
  probe.destroy();
  loop();
});
probe.connect(CFG.printerPort, CFG.printerIp);

const shutdown = (sig) => {
  log('INFO', `${sig} — shutting down`);
  running = false;
  setTimeout(() => process.exit(0), 300);
};
process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
