<?php
/** @var string $error */
/** @var mixed $notice */
/** @var bool $configured */
/** @var string $csrfToken */
?>
<section class="auth-layout">
    <div class="auth-intro">
        <p class="eyebrow">SISTEM INFORMASI · ARSIP DIGITAL</p>
        <h1>Dokumen penting,<br><em>terjaga aman.</em></h1>
        <p class="intro-copy">Ruang pengelolaan arsip dengan enkripsi berlapis. Isi dokumen hanya dibuka setelah admin berhasil masuk.</p>
        <div class="crypto-summary">
            <span class="crypto-icon">⌑</span>
            <div><strong>Perlindungan hibrida</strong><small>AES-256-GCM + RSA-OAEP</small></div>
        </div>
    </div>
    <section class="auth-card">
        <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($notice !== ''): ?><div class="alert alert-success"><?= e((string) $notice) ?></div><?php endif; ?>
        <?php if (!$configured): ?>
            <p class="eyebrow">PENYIAPAN AWAL</p>
            <h2>Buat akun admin</h2>
            <p class="muted">Tentukan kata sandi admin untuk melindungi akses arsip.</p>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="setup">
                <label>Kata sandi
                    <input type="password" name="password" minlength="12" autocomplete="new-password" required>
                </label>
                <label>Ulangi kata sandi
                    <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
                </label>
                <p class="field-help">Minimal 12 karakter. Kata sandi disimpan sebagai hash.</p>
                <button class="button button-primary button-wide" type="submit">Buat akun admin <span>→</span></button>
            </form>
        <?php else: ?>
            <p class="eyebrow">AREA TERBATAS</p>
            <h2>Masuk sebagai admin</h2>
            <p class="muted">Autentikasi diperlukan untuk melihat atau mengelola arsip.</p>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="login">
                <label>Kata sandi admin
                    <input type="password" name="password" autocomplete="current-password" required autofocus>
                </label>
                <button class="button button-primary button-wide" type="submit">Masuk ke arsip <span>→</span></button>
            </form>
        <?php endif; ?>
        <p class="card-footnote"><span class="status-dot"></span> Koneksi lokal · Kunci privat tersimpan di server</p>
    </section>
</section>
