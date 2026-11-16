<?php
/** @var array $documents */
/** @var string $error */
/** @var mixed $notice */
/** @var string $csrfToken */
?>
<section class="dashboard-head">
    <div>
        <p class="eyebrow">RUANG KERJA ADMIN <span class="secure-badge">● TERLINDUNGI</span></p>
        <h1>Arsip <em>dokumen.</em></h1>
        <p class="intro-copy">Kelola dokumen dengan perlindungan enkripsi simetris dan asimetris.</p>
    </div>
    <div class="count-card"><strong><?= count($documents) ?></strong><span>dokumen<br>tersimpan</span></div>
</section>

<?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="alert alert-success"><?= e((string) $notice) ?></div><?php endif; ?>

<section class="workspace-grid">
    <section class="panel create-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">DOKUMEN BARU</p><h2>Tambah ke arsip</h2></div>
            <span class="heading-icon">＋</span>
        </div>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="save_document">
            <input type="hidden" name="id" value="">
            <label>Judul dokumen
                <input type="text" name="title" maxlength="160" placeholder="Contoh: Notulen rapat tahunan" required>
            </label>
            <label>Kategori <span class="optional">(opsional)</span>
                <input type="text" name="category" maxlength="80" placeholder="Contoh: Administrasi">
            </label>
            <label>Isi dokumen
                <textarea name="content" rows="8" maxlength="100000" placeholder="Tuliskan isi atau ringkasan dokumen..." required></textarea>
            </label>
            <p class="field-help">Seluruh judul, kategori, isi, dan waktu dokumen dienkripsi sebelum disimpan.</p>
            <button class="button button-primary button-wide" type="submit">Simpan dokumen terenkripsi <span>→</span></button>
        </form>
    </section>

    <section class="archive-section">
        <div class="archive-heading">
            <div><p class="eyebrow">KOLEKSI TERENKRIPSI</p><h2>Dokumen tersimpan</h2></div>
            <span class="archive-count"><?= count($documents) ?> item</span>
        </div>
        <?php if ($documents === []): ?>
            <div class="empty-state"><span class="empty-icon">▤</span><strong>Arsip masih kosong</strong><p>Tambahkan dokumen pertama menggunakan formulir di sebelah kiri.</p></div>
        <?php else: ?>
            <div class="document-list">
                <?php foreach ($documents as $document): ?>
                    <?php
                    $documentId = (string) ($document['id'] ?? '');
                    $documentTitle = (string) ($document['title'] ?? '');
                    $documentCategory = (string) ($document['category'] ?? '');
                    $documentContent = (string) ($document['content'] ?? '');
                    ?>
                    <article class="document-card">
                        <div class="document-topline">
                            <span class="document-icon">▤</span>
                            <span class="document-category"><?= e($documentCategory !== '' ? $documentCategory : 'Tanpa kategori') ?></span>
                            <span class="lock-mark" title="Dokumen terenkripsi">⌑</span>
                        </div>
                        <h3><?= e($documentTitle) ?></h3>
                        <p class="document-content"><?= nl2br(e($documentContent)) ?></p>
                        <div class="document-meta">
                            <span>Diperbarui <?= e((string) ($document['updated_at'] ?? '')) ?></span>
                            <div class="document-actions">
                                <details class="edit-details">
                                    <summary class="text-action">Ubah</summary>
                                    <div class="edit-popover">
                                        <form method="post" class="stack-form">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="save_document">
                                            <input type="hidden" name="id" value="<?= e($documentId) ?>">
                                            <label>Judul dokumen<input type="text" name="title" maxlength="160" value="<?= e($documentTitle) ?>" required></label>
                                            <label>Kategori<input type="text" name="category" maxlength="80" value="<?= e($documentCategory) ?>"></label>
                                            <label>Isi dokumen<textarea name="content" rows="7" maxlength="100000" required><?= e($documentContent) ?></textarea></label>
                                            <button class="button button-primary button-wide" type="submit">Simpan perubahan</button>
                                        </form>
                                    </div>
                                </details>
                                <form method="post" onsubmit="return confirm('Hapus dokumen ini dari arsip?');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete_document">
                                    <input type="hidden" name="id" value="<?= e($documentId) ?>">
                                    <button class="text-action text-danger" type="submit">Hapus</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="crypto-note"><span>⌑</span><p><strong>Enkripsi aktif</strong> — Setiap dokumen memakai kunci AES-256-GCM acak. Kunci tersebut dibungkus RSA-OAEP dan hanya dapat dibuka dengan kunci privat server.</p></div>
    </section>
</section>
