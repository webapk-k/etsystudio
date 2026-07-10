<?php
/* One-click launcher: pick a free port, start the app server, wait until it's
   ready, THEN open the browser. Started by start-etsyai.bat. Keep the window open. */

chdir(__DIR__);
$php = PHP_BINARY ?: 'C:\\xampp\\php\\php.exe';

$cands = [8123, 8181, 8321, 8899, 9191, 5959, 7654, 9797, 8080];
$net   = @shell_exec('netstat -ano -p TCP 2>NUL');
$port  = $cands[0];
foreach ($cands as $p) {
    if (!$net || !preg_match('/:' . $p . '\s+\S+\s+LISTENING/i', $net)) { $port = $p; break; }
}
$url = "http://localhost:$port/";

echo "============================================\n";
echo "  Etsy Listing Studio\n";
echo "  Address:  $url\n";
echo "  (Keep this window open. Close it to stop.)\n";
echo "============================================\n";

$cmd = '"' . $php . '" -d opcache.enable_cli=0 -S localhost:' . $port . ' -t ' . escapeshellarg(__DIR__);

$openBrowser = function () use ($url) {
    if (!getenv('ELS_NO_BROWSER')) { @pclose(@popen('start "" ' . $url, 'r')); }
};

if (function_exists('proc_open')) {
    $proc = @proc_open($cmd, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    if (is_resource($proc)) {
        // wait until the server accepts a connection, then open the browser (no race)
        for ($i = 0; $i < 50; $i++) {
            $f = @fsockopen('127.0.0.1', $port, $e, $s, 0.25);
            if ($f) { fclose($f); break; }
            usleep(200000);
        }
        $openBrowser();
        proc_close($proc);   // keeps serving until this window is closed
        exit;
    }
}

// Fallback: open browser, then run the blocking server.
$openBrowser();
passthru($cmd);
