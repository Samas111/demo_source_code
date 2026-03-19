<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/logic.php';

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

if ($mysqli->connect_error) {
    die("DB connection failed");
}

$result = $mysqli->query("
    SELECT 
        id,
        openclass_id,
        title,
        address,
        DATE_FORMAT(date, '%d.%m.') AS formatted_date,
        start_time,
        latitude,
        longitude,
        cover_image
    FROM openclasses
    WHERE latitude IS NOT NULL
      AND longitude IS NOT NULL
      AND canceled = 0
      AND STR_TO_DATE(CONCAT(date, ' ', start_time), '%Y-%m-%d %H:%i') >= NOW()
    ORDER BY date ASC, start_time ASC
");


$classes = [];

while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

?>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<div id="map"></div>

<div 
    style="display: none;"
    id="map-data" 
    data-classes='<?= json_encode($classes, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
</div>

</body>
</html>
