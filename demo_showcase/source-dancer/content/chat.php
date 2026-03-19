<?php
require __DIR__ . '/../../secure/logic.php';
require_once __DIR__ . '/../../secure/auth.php';
require_once __DIR__ . '/../../languages/loader.php';

$me = $_SESSION['public_id'] ?? null;
if (!$me) { 
    header("/registration/register-server-logic/auto.php");
    exit;
}

/** * We gather all unique 'contact_ids' from:
 * 1. DMs (Both ways)
 * 2. Registrations (Using openclasses.public_id as the teacher/recipient)
 * 3. Follows (Following you OR you following them)
 */
$stmt = $conn->prepare("
    SELECT 
        u.contact_id,
        up.name AS display_name,
        up.pfp_path,
        m.last_time,
        m.last_msg,
        m.unread_count
    FROM (
        /* Inner subquery to filter out ghost accounts early */
        SELECT contact_id FROM (
            /* 1. Direct Messages */
            SELECT DISTINCT IF(sender_public_id = ?, receiver_public_id, sender_public_id) AS contact_id
            FROM dms
            WHERE sender_public_id = ? OR receiver_public_id = ?

            UNION

            /* 2. Registrations: Corrected to use o.public_id for the class author */
            SELECT DISTINCT IF(o.public_id = ?, r.public_id, o.public_id) AS contact_id
            FROM openclass_registrations r
            JOIN openclasses o ON r.openclass_id = o.openclass_id
            WHERE (o.public_id = ? OR r.public_id = ?)
              AND o.date >= CURDATE()
              AND r.storno = 0

            UNION

            /* 3. Follows */
            SELECT DISTINCT IF(follower_public_id = ?, followed_public_id, follower_public_id) AS contact_id
            FROM user_follows
            WHERE follower_public_id = ? OR followed_public_id = ?
        ) AS raw_contacts
        WHERE contact_id IS NOT NULL 
          AND contact_id != '' 
          AND contact_id != 'NONE'
          AND contact_id != ? -- Ensure I don't chat with myself
    ) AS u
    LEFT JOIN user_profile up ON u.contact_id = up.public_id
    LEFT JOIN (
        /* Subquery for latest message preview */
        SELECT 
            IF(sender_public_id = ?, receiver_public_id, sender_public_id) AS chat_partner,
            MAX(created_at) AS last_time,
            (SELECT message FROM dms 
             WHERE (sender_public_id = chat_partner AND receiver_public_id = ?) 
                OR (sender_public_id = ? AND receiver_public_id = chat_partner)
             ORDER BY id DESC LIMIT 1) AS last_msg,
            SUM(CASE WHEN receiver_public_id = ? AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count
        FROM dms
        WHERE sender_public_id = ? OR receiver_public_id = ?
        GROUP BY chat_partner
    ) AS m ON u.contact_id = m.chat_partner
    ORDER BY COALESCE(m.last_time, '1970-01-01') DESC, up.name ASC
");

// 16 placeholders now (added one for the self-chat filter)
$stmt->bind_param('ssssssssssssssss', 
    $me, $me, $me,           // DM Union
    $me, $me, $me,           // Reg Union
    $me, $me, $me,           // Follow Union
    $me,                     // Self-filter
    $me, $me, $me, $me, $me, $me // Preview Subquery
);

$stmt->execute();
$chats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<div class="list-container">
    <div class="chats-list">
        <?php if (empty($chats)): ?>
            <div style="padding: 40px; text-align: center; color: rgba(255,255,255,0.3);">
                Zatím žádné kontakty ani zprávy.
            </div>
        <?php endif; ?>

        <?php foreach($chats as $c): 
            $displayName = !empty($c['display_name']) ? $c['display_name'] : "Uživatel " . $c['contact_id'];
            $pfp = !empty($c['pfp_path']) ? '../' . $c['pfp_path'] : '../assets/default-avatar.png';
            
            $isUnread = ((int)($c['unread_count'] ?? 0) > 0);
            $hasMessage = !empty($c['last_msg']);
            $previewText = $hasMessage ? htmlspecialchars($c['last_msg']) : "Klepnutím zahájíte chat...";
            $previewOpacity = (!$hasMessage) ? 'style="opacity: 0.2;"' : '';
        ?>
            <a href="/messages/chat.php?public_id=<?= urlencode($c['contact_id']) ?>" class="chat-item <?= $isUnread ? 'unread-bg' : '' ?>">
                <div class="avatar-wrapper">
                    <img src="<?= htmlspecialchars($pfp) ?>" alt="" class="list-avatar" style="width:48px; height:48px; object-fit:cover; border-radius:50%;">
                    <?php if($isUnread): ?>
                        <div class="unread-dot"></div>
                    <?php endif; ?>
                </div>

                <div class="chat-info" <?= $previewOpacity ?>>
                    <div class="top-row">
                        <span class="contact-name <?= $isUnread ? 'unread-bold' : '' ?>">
                            <?= htmlspecialchars($displayName) ?>
                        </span>
                    </div>
                    <div class="preview">
                        <span class="msg-text <?= $isUnread ? 'unread-bold' : '' ?>">
                            <?= $previewText ?>
                        </span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        <div style="height: 80px;"></div> </div>
</div>