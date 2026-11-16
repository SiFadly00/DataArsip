<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/controller.php';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Arsip Aman — Sistem Informasi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="./" aria-label="Arsip Aman beranda">
        <span class="brand-mark">A</span>
        <span>arsip<span class="brand-light">aman</span></span>
    </a>
    <?php if ($authenticated): ?>
        <form method="post" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="logout">
            <button class="button button-quiet" type="submit">Keluar <span aria-hidden="true">↗</span></button>
        </form>
    <?php endif; ?>
</header>

<main class="page-shell">
    <?php if (!$authenticated): ?>
        <?php require __DIR__ . '/views/auth.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/views/dashboard.php'; ?>
    <?php endif; ?>
</main>
<footer class="site-footer"><span>ARSIP AMAN</span><span>Sistem Informasi · Enkripsi Hibrida</span></footer>
</body>
</html>
