<?php
if (!defined('IS_CREATOR') || IS_CREATOR !== 1) {
    http_response_code(403);
    require __DIR__ . '/forbidden.php';
    exit;
}
