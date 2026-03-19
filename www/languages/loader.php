<?php

require_once __DIR__ . '/../secure/logic.php';

$langFile = __DIR__ . '/cz.php';


$lang = 1;

$publicId = $_SESSION['public_id'] ?? null;

if ($publicId && isset($conn)) {
    $stmt = $conn->prepare("SELECT language FROM users WHERE public_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $publicId);
        $stmt->execute();
        $stmt->bind_result($dbLang);

        if ($stmt->fetch()) {
            $lang = (int) $dbLang;
        }

        $stmt->close();
    }
}

if ($lang === 2) {
    $langFile = __DIR__ . '/uk.php';
}

if (!file_exists($langFile)) {
    $langFile = __DIR__ . '/cz.php';
}

$T = require $langFile;
