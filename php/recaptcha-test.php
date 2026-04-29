<?php
/**
 * Bagel Boyz NJ - reCAPTCHA debug page.
 * Tests the verification pipeline without sending any email.
 *
 * Visit /php/recaptcha-test.php in a browser and click the button.
 * The JSON response shows whether the token was valid, the action, and the score.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/recaptcha-verify.php';

    $configPath = __DIR__ . '/smtp-config.php';
    if (!file_exists($configPath)) {
        echo json_encode(['ok' => false, 'message' => 'smtp-config.php is missing on the server.']);
        exit;
    }
    $config = require $configPath;

    $token  = $_POST['g-recaptcha-response'] ?? '';
    $action = $_POST['action'] ?? 'debug_test';

    $result = bb_verify_recaptcha($token, $action, $config);
    $result['action_sent']   = $action;
    $result['token_preview'] = $token ? substr($token, 0, 24) . '...(' . strlen($token) . ' chars)' : '(empty)';
    $result['secret_set']    = !empty($config['recaptcha_secret']) && $config['recaptcha_secret'] !== 'YOUR_RECAPTCHA_V3_SECRET_KEY';
    $result['min_score']     = $config['recaptcha_min_score'] ?? 0.5;

    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$siteKey = '6LeYldAsAAAAAL_JmEU0vho10-yvPB7D2WSm9c8P';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>reCAPTCHA Debug | Bagel Boyz</title>
  <meta name="robots" content="noindex,nofollow">
  <script>window.BB_RECAPTCHA_SITE_KEY = '<?= $siteKey ?>';</script>
  <script src="https://www.google.com/recaptcha/enterprise.js?render=<?= $siteKey ?>" async defer></script>
  <style>
    body { font-family: -apple-system, system-ui, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 20px; color: #222; }
    h1 { font-size: 1.4rem; }
    button { padding: 12px 20px; font-size: 1rem; background: #d4901e; color: white; border: 0; border-radius: 6px; cursor: pointer; margin-right: 8px; margin-bottom: 8px; }
    button:disabled { opacity: 0.5; cursor: wait; }
    pre { background: #1e1e1e; color: #dcdcdc; padding: 16px; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; line-height: 1.4; }
    .ok { color: #4ade80; font-weight: 600; }
    .fail { color: #f87171; font-weight: 600; }
    label { font-size: 0.9rem; color: #555; }
    input[type=text] { padding: 8px; font-size: 0.95rem; width: 240px; border: 1px solid #ccc; border-radius: 4px; }
    .row { margin-bottom: 16px; }
  </style>
</head>
<body>
  <h1>reCAPTCHA Enterprise debug</h1>
  <p>This page calls the same <code>bb_verify_recaptcha()</code> helper as the real forms but does <strong>not</strong> send any email. Use it to confirm tokens validate before trusting the live forms.</p>

  <div class="row">
    <label for="action">Action name:</label><br>
    <input type="text" id="action" value="catering_inquiry">
    &nbsp;<small>(try <code>catering_inquiry</code>, <code>careers_apply</code>, or anything else)</small>
  </div>

  <button id="run">Run reCAPTCHA test</button>
  <button id="run-mismatch" title="Sends a token for action X but tells the server to expect action Y. Should fail with action mismatch.">Test action mismatch (should fail)</button>
  <button id="run-empty" title="Sends an empty token. Should fail with 'missing token'.">Test empty token (should fail)</button>

  <h3>Result</h3>
  <pre id="out">Click a button to run a test.</pre>

  <script>
    const out = document.getElementById('out');
    const actionInput = document.getElementById('action');

    function run(opts) {
      const useToken = opts.useToken !== false;
      const tokenAction = opts.tokenAction || actionInput.value || 'catering_inquiry';
      const serverAction = opts.serverAction || actionInput.value || 'catering_inquiry';

      out.textContent = 'Working...';
      [...document.querySelectorAll('button')].forEach(b => b.disabled = true);

      const tokenPromise = useToken
        ? new Promise((resolve, reject) => {
            if (typeof grecaptcha === 'undefined' || !grecaptcha.enterprise) {
              return reject(new Error('grecaptcha.enterprise not loaded yet — wait a moment and retry.'));
            }
            grecaptcha.enterprise.ready(() => {
              grecaptcha.enterprise.execute(window.BB_RECAPTCHA_SITE_KEY, { action: tokenAction })
                .then(resolve).catch(reject);
            });
          })
        : Promise.resolve('');

      tokenPromise
        .then(token => {
          const fd = new FormData();
          fd.append('g-recaptcha-response', token);
          fd.append('action', serverAction);
          return fetch('recaptcha-test.php', { method: 'POST', body: fd });
        })
        .then(res => res.text())
        .then(text => {
          let pretty;
          try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) { pretty = text; }
          out.textContent = pretty;
        })
        .catch(err => {
          out.textContent = 'JS error: ' + err.message;
        })
        .finally(() => {
          [...document.querySelectorAll('button')].forEach(b => b.disabled = false);
        });
    }

    document.getElementById('run').addEventListener('click', () => run({}));
    document.getElementById('run-mismatch').addEventListener('click', () => {
      run({ tokenAction: 'foo_action', serverAction: 'bar_action' });
    });
    document.getElementById('run-empty').addEventListener('click', () => run({ useToken: false }));
  </script>
</body>
</html>
