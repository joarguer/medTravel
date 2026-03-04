<?php
// Minimal Socket.IO connection test for /realtime
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>MedTravel Realtime Test</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .log { padding: 8px 10px; margin: 6px 0; background: #f5f5f5; border: 1px solid #ddd; }
        .ok { border-color: #4caf50; }
        .err { border-color: #f44336; }
        code { background: #eee; padding: 2px 4px; }
    </style>
</head>
<body>
    <h1>MedTravel Realtime Test</h1>
    <p>Socket base URL: <code id="base-url"></code></p>
    <p>Socket path: <code>/realtime/socket.io</code></p>
    <div id="logs"></div>

    <script>
        (function() {
            var baseUrl = window.MT_REALTIME_BASE_URL || 'https://medtravel.com.co';
            document.getElementById('base-url').textContent = baseUrl;

            function log(msg, cls) {
                var el = document.createElement('div');
                el.className = 'log' + (cls ? ' ' + cls : '');
                el.textContent = msg;
                document.getElementById('logs').appendChild(el);
            }

            if (typeof io === 'undefined') {
                log('Socket.IO client not loaded. Check CDN.', 'err');
                return;
            }

            log('Connecting to ' + baseUrl + ' with path /realtime/socket.io ...');

            var socket = io(baseUrl, {
                path: '/realtime/socket.io',
                transports: ['websocket', 'polling']
            });

            socket.on('connect', function() {
                log('connect: ' + socket.id, 'ok');
            });

            socket.on('connected', function(payload) {
                log('connected event: ' + JSON.stringify(payload), 'ok');
            });

            socket.on('disconnect', function(reason) {
                log('disconnect: ' + reason, 'err');
            });

            socket.on('connect_error', function(err) {
                log('connect_error: ' + (err && err.message ? err.message : String(err)), 'err');
            });
        })();
    </script>
</body>
</html>
