<?php
require dirname(__DIR__, 2) . '/app/Services/AI/AtomicJsonStore.php';
$store = new App\Services\AI\AtomicJsonStore($argv[1]);
for ($i = 0; $i < 15; $i++) $store->mutate(static function (array &$v) { $v['count'] = ($v['count'] ?? 0) + 1; usleep(1000); });
