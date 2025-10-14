<?php
// Server-Sent Events endpoint
// Emits: ping, activity:new, activity:deleted based on a shared last-event file.

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

$root = dirname(__DIR__);
$eventFile = $root . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'stream' . DIRECTORY_SEPARATOR . '_last_event.json';

@set_time_limit(0);
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);

function sse_send(string $event, array $data): void {
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush(); @flush();
}

// Initial ping so clients know we’re alive
sse_send('ping', ['ts'=>time()]);

$lastMTime = 0;
$maxLoops = 120; // ~4 minutes at 2s interval
for ($i=0; $i<$maxLoops; $i++) {
    clearstatcache();
    $mt = @filemtime($eventFile) ?: 0;
    if ($mt > $lastMTime) {
        $lastMTime = $mt;
        $raw = @file_get_contents($eventFile) ?: '';
        $obj = json_decode($raw, true);
        if (is_array($obj) && isset($obj['type'])) {
            $etype = $obj['type'];
            if (strpos($etype, 'activity:') === 0) {
                sse_send($etype, $obj);
            }
        }
    }
    // heartbeat every 10s
    if ($i % 5 === 4) { sse_send('ping', ['ts'=>time()]); }
    sleep(2);
}
