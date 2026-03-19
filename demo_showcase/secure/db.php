<?php
$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

if ($mysqli->connect_error) {
    http_response_code(500);
    exit;
}
