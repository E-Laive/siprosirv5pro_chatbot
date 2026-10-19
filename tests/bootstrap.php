<?php
// Tests must never touch real conversations, provider settings, sessions or POS.
$testRoot = dirname(__DIR__) . '/build/test-runtime-' . bin2hex(random_bytes(6));
foreach (['', '/cache', '/logs', '/session', '/ai_storage', '/security'] as $suffix) if (!is_dir($testRoot . $suffix)) mkdir($testRoot . $suffix, 0700, true);
define('WRITEPATH', $testRoot . DIRECTORY_SEPARATOR);
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
$aiTestConfig = config(\Config\AiAnalyst::class);
$aiTestConfig->apiKey = '';
$aiTestConfig->baseUrl = 'https://audit.invalid/v1';
$aiTestConfig->storageDriver = 'database';
$aiTestConfig->allowAnalyticalSandboxWhenPosOffline = false;
