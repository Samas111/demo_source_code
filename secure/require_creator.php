<?php
if (!defined('IS_CREATOR') || IS_CREATOR !== 1) {
    http_response_code(403);
    require $_SERVER['DOCUMENT_ROOT'] . '/secure/forbidden.php';
    exit;
}
