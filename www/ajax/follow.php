<?php

error_reporting(E_ALL);

require __DIR__ . '/../secure/logic.php';

$mysqli = new mysqli(DB_LOGIN, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset('utf8mb4');

if (
    empty($_COOKIE['dancefy_token']) ||
    !preg_match('/^[a-f0-9]{64}$/', $_COOKIE['dancefy_token']) ||
    empty($_POST['public_id'])
) {
    http_response_code(401);
    exit;
}

$target_public = $_POST['public_id'];
$tokenHash = hash('sha256', $_COOKIE['dancefy_token']);

$q = $mysqli->prepare("
    SELECT u.public_id
    FROM user_tokens t
    JOIN users u ON u.user_id = t.user_id
    WHERE t.token_hash = ?
    LIMIT 1
");
$q->bind_param("s", $tokenHash);
$q->execute();
$q->bind_result($viewer_public);
$q->fetch();
$q->close();

if (!$viewer_public || $viewer_public === $target_public) {
    http_response_code(400);
    exit;
}

$check = $mysqli->prepare("
    SELECT 1
    FROM user_follows
    WHERE follower_public_id = ?
      AND followed_public_id = ?
    LIMIT 1
");
$check->bind_param("ss", $viewer_public, $target_public);
$check->execute();
$check->store_result();

if ($check->num_rows === 1) {

    $del = $mysqli->prepare("
        DELETE FROM user_follows
        WHERE follower_public_id = ?
          AND followed_public_id = ?
    ");
    $del->bind_param("ss", $viewer_public, $target_public);
    $del->execute();

    $state = 'unfollowed';

} else {

    $ins = $mysqli->prepare("
        INSERT INTO user_follows (follower_public_id, followed_public_id)
        VALUES (?, ?)
    ");
    $ins->bind_param("ss", $viewer_public, $target_public);
    $ins->execute();

    $state = 'followed';


    $is_creator = 0;

    $c = $mysqli->prepare("
        SELECT is_creator
        FROM users
        WHERE public_id = ?
        LIMIT 1
    ");
    $c->bind_param("s", $target_public);
    $c->execute();
    $c->bind_result($is_creator);
    $c->fetch();
    $c->close();

    if ((int)$is_creator === 1) {

        $exists = $mysqli->prepare("
            SELECT 1
            FROM notifications
            WHERE recipient_public_id = ?
              AND actor_public_id = ?
              AND title = 'Nový sledující'
            LIMIT 1
        ");
        $exists->bind_param("ss", $target_public, $viewer_public);
        $exists->execute();
        $exists->store_result();

        if ($exists->num_rows === 0) {
            $uname = null;

            $u = $mysqli->prepare("
                SELECT username
                FROM users
                WHERE public_id = ?
                LIMIT 1
            ");
            $u->bind_param("s", $viewer_public);
            $u->execute();
            $u->bind_result($uname);
            $u->fetch();
            $u->close();

            if (!$uname) {
                $uname = 'Uživatel';
            }

            $notif = $mysqli->prepare("
                INSERT INTO notifications (
                    recipient_public_id,
                    actor_public_id,
                    title,
                    content
                ) VALUES (?, ?, ?, ?)
            ");

            $title = 'Nový sledující';
            $content = $uname . ' vás začal sledovat';

            $notif->bind_param(
                "ssss",
                $target_public,
                $viewer_public,
                $title,
                $content
            );
            $notif->execute();
            $notif->close();

        }

        $exists->close();
    }
}

echo json_encode(['state' => $state]);
usleep(150000);
