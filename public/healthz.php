<?php

namespace Liuch\DmarcSrg;

ob_start();

try {
    require realpath(__DIR__ . '/..') . '/init.php';

    if (Core::instance() === null) {
        throw new \RuntimeException('Core instance is null');
    }

    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(200);
    echo "ok\n";
} catch (\Throwable $e) {
    ob_end_clean();
    error_log('healthz failed: ' . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(503);
    echo "fail\n";
}
