<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/secure/bootstrap/variables.php';

$mysqli = $GLOBALS['DB'];

$theme = 0; 

if (!empty($GLOBALS['AUTH_USER']['public_id'])) {
    $stmt = $GLOBALS['DB']->prepare("
        SELECT theme 
        FROM users 
        WHERE public_id = ? 
        LIMIT 1
    ");
    $stmt->bind_param('s', $GLOBALS['AUTH_USER']['public_id']);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $theme = (int)$row['theme'];
    }

    $stmt->close();
}
?>

<style>
:root {
<?php if ($theme === 0): ?>
  /* DARK */
  --background-color: #000000;
  --bg: #000000;
  --text-color: #ffffff;

  --font-size-main: 12px;

  --primary-color: #EB4F8B;
  --secondary-color: #FF5143;
  --accent: #ff2d78;
  --danger: #ff4d4d;

  --card-color: #110816;
  --card: #110816;

  --gradient-color: linear-gradient(to right, #FF5143, #FF1B73);
  --accent-gradient: linear-gradient(135deg, #ff2d78 0%, #ff6b6b 100%);

  --neon-pink: #ff3b7a;
  --neon-pink-2: #ff6aa6;

  --nav-glass: rgba(12, 4, 18, 0.55);
  --nav-border: rgba(255, 255, 255, 0.05);
  --nav-inside-glow: rgba(255, 35, 110, 0.06);
  --nav-shadow: rgba(0, 0, 0, 0.55);

<?php else: ?>
  /* LIGHT */
  --background-color: #FFFFFF;
  --bg: #FFFFFF;
  --text-color: #000000;

  --font-size-main: 12px;

  --primary-color: #EB4F8B;
  --secondary-color: #FF5143;
  --accent: #ff2d78;
  --danger: #ff4d4d;

  --card-color: #d0d0d0;
  --card: #d0d0d0;

  --gradient-color: linear-gradient(to right, #FF5143, #FF1B73);
  --accent-gradient: linear-gradient(135deg, #ff2d78 0%, #ff6b6b 100%);

  --neon-pink: #ff3b7a;
  --neon-pink-2: #ff6aa6;

  --nav-glass: rgba(255, 255, 255, 0.7);
  --nav-border: rgba(0, 0, 0, 0.05);
  --nav-inside-glow: rgba(255, 35, 110, 0.04);
  --nav-shadow: rgba(0, 0, 0, 0.1);
<?php endif; ?>
}
</style>