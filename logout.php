<?php
// logout.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
logoutUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logging out...</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
  <meta http-equiv="refresh" content="1;url=<?php echo BASE_URL; ?>index.php">
</head>
<body>
  <div class="loading-overlay active">
    <div class="loading-spinner"></div>
    <div class="loading-text">Logging out...</div>
  </div>
  <script>
    setTimeout(function () { window.location.href = '<?php echo BASE_URL; ?>index.php'; }, 900);
  </script>
</body>
</html>