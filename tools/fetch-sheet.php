<?php
$url = 'https://docs.google.com/spreadsheets/d/1OrEFXnfDNQawMcLHqwMoO6FqsbLkFjIgeQbWaUSp-9c/gviz/tq?tqx=out:csv&gid=0';
$ctx = stream_context_create([
    'http' => [
        'header' => "User-Agent: Mozilla/5.0\r\n",
        'timeout' => 25,
        'follow_location' => 1,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);
$data = @file_get_contents($url, false, $ctx);
if ($data === false) {
    fwrite(STDERR, "FAIL\n");
    exit(1);
}
echo "OK len=" . strlen($data) . PHP_EOL;
echo substr($data, 0, 600);
file_put_contents(__DIR__ . '/sheet.csv', $data);
echo PHP_EOL . "saved";
