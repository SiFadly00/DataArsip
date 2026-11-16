<?php
require_once __DIR__ . '/storage.php';

$error = '';
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
$configured = is_file($adminPath);
$documents = [];

try {
    ensureStorage($storagePath, $privateKeyPath, $publicKeyPath, $documentsPath);
    $configured = is_file($adminPath);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireCsrfToken();
        $action = $_POST['action'] ?? '';

        if ($action === 'setup' && !$configured) {
            $password = $_POST['password'] ?? '';
            $confirmation = $_POST['password_confirmation'] ?? '';
            if (!is_string($password) || strlen($password) < 12) {
                throw new UserVisibleException('Kata sandi harus memiliki minimal 12 karakter.');
            }
            if (!is_string($confirmation) || !hash_equals($password, $confirmation)) {
                throw new UserVisibleException('Konfirmasi kata sandi tidak cocok.');
            }
            $adminJson = json_encode(['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
            withStorageLock($storagePath, function () use ($adminPath, $adminJson): void {
                if (is_file($adminPath)) {
                    throw new UserVisibleException('Akun admin sudah dibuat. Silakan masuk.');
                }
                if ($adminJson === false || file_put_contents($adminPath, $adminJson . "\n", LOCK_EX) === false) {
                    throw new RuntimeException('Unable to save the admin account.');
                }
            });
            $_SESSION['authenticated'] = true;
            session_regenerate_id(true);
            $_SESSION['notice'] = 'Akun admin berhasil dibuat.';
            redirectToHome();
        }

        if ($action === 'login' && $configured) {
            $password = $_POST['password'] ?? '';
            $admin = json_decode((string) file_get_contents($adminPath), true);
            if (!is_string($password) || !is_array($admin)
                || !isset($admin['password_hash'])
                || !password_verify($password, $admin['password_hash'])) {
                throw new UserVisibleException('Kata sandi tidak benar.');
            }
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['notice'] = 'Berhasil masuk.';
            redirectToHome();
        }

        if (empty($_SESSION['authenticated'])) {
            throw new UserVisibleException('Silakan masuk sebagai admin terlebih dahulu.');
        }

        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['notice'] = 'Anda telah keluar.';
            redirectToHome();
        }

        if ($action === 'save_document') {
            $id = postString('id', 64);
            $title = postString('title', 160);
            $category = postString('category', 80);
            $content = postString('content', 100000);
            if ($title === '' || $content === '') {
                throw new UserVisibleException('Judul dan isi dokumen wajib diisi.');
            }

            withStorageLock($storagePath, function () use ($id, $title, $category, $content, $documentsPath, $privateKeyPath, $publicKeyPath): void {
                $records = readRecords($documentsPath);
                $existingIndex = null;
                $createdAt = gmdate(DATE_ATOM);
                foreach ($records as $index => $record) {
                    if (is_array($record) && ($record['id'] ?? null) === $id && $id !== '') {
                        $existingIndex = $index;
                        $previous = decryptDocument($record, $privateKeyPath);
                        $createdAt = (string) ($previous['created_at'] ?? $createdAt);
                        break;
                    }
                }
                if ($id !== '' && $existingIndex === null) {
                    throw new UserVisibleException('Dokumen yang akan diubah tidak ditemukan.');
                }
                $document = [
                    'id' => $id !== '' ? $id : bin2hex(random_bytes(16)),
                    'title' => $title,
                    'category' => $category,
                    'content' => $content,
                    'created_at' => $createdAt,
                    'updated_at' => gmdate(DATE_ATOM),
                ];
                $encryptedRecord = encryptDocument($document, $publicKeyPath);
                if ($existingIndex === null) {
                    $records[] = $encryptedRecord;
                } else {
                    $records[$existingIndex] = $encryptedRecord;
                }
                writeRecords($documentsPath, $records);
            });
            $_SESSION['notice'] = 'Dokumen berhasil disimpan dan dienkripsi.';
            redirectToHome();
        }

        if ($action === 'delete_document') {
            $id = postString('id', 64);
            withStorageLock($storagePath, function () use ($id, $documentsPath): void {
                $records = readRecords($documentsPath);
                $remaining = array_values(array_filter(
                    $records,
                    static fn($record): bool => !is_array($record) || ($record['id'] ?? null) !== $id
                ));
                if (count($remaining) === count($records)) {
                    throw new UserVisibleException('Dokumen tidak ditemukan.');
                }
                writeRecords($documentsPath, $remaining);
            });
            $_SESSION['notice'] = 'Dokumen berhasil dihapus.';
            redirectToHome();
        }

        throw new UserVisibleException('Aksi tidak dikenali.');
    }

    if (!empty($_SESSION['authenticated'])) {
        $documents = withStorageLock($storagePath, function () use ($documentsPath, $privateKeyPath): array {
            $decrypted = [];
            foreach (readRecords($documentsPath) as $record) {
                if (!is_array($record)) {
                    throw new RuntimeException('The encrypted archive contains an invalid record.');
                }
                $decrypted[] = decryptDocument($record, $privateKeyPath);
            }
            usort($decrypted, static fn(array $a, array $b): int => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
            return $decrypted;
        });
    }
} catch (UserVisibleException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Arsip admin error: ' . $exception->getMessage());
    $error = 'Terjadi kesalahan internal. Periksa log PHP atau coba lagi.';
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$authenticated = !empty($_SESSION['authenticated']);
