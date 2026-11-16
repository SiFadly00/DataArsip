<?php
declare(strict_types=1);

final class UserVisibleException extends RuntimeException
{
}

$storagePath = getenv('ARSIP_STORAGE_PATH') ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'utsweb_private';
$storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);
$documentsPath = $storagePath . DIRECTORY_SEPARATOR . 'documents.json';
$privateKeyPath = $storagePath . DIRECTORY_SEPARATOR . 'private.pem';
$publicKeyPath = $storagePath . DIRECTORY_SEPARATOR . 'public.pem';
$adminPath = $storagePath . DIRECTORY_SEPARATOR . 'admin.json';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_start();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ensureStorage(string $storagePath, string $privateKeyPath, string $publicKeyPath, string $documentsPath): void
{
    if (!is_dir($storagePath) && !mkdir($storagePath, 0700, true) && !is_dir($storagePath)) {
        throw new RuntimeException('Unable to create the private storage directory.');
    }

    $lock = fopen($storagePath . DIRECTORY_SEPARATOR . 'initialize.lock', 'c+');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Unable to lock private storage initialization.');
    }

    try {
        if (!is_file($privateKeyPath)) {
            $opensslConfigPath = $storagePath . DIRECTORY_SEPARATOR . 'openssl.cnf';
            if (!is_file($opensslConfigPath)
                && file_put_contents($opensslConfigPath, "[req]\ndistinguished_name = dn\n[dn]\n", LOCK_EX) === false) {
                throw new RuntimeException('Unable to prepare OpenSSL key generation.');
            }
            $key = openssl_pkey_new([
                'config' => $opensslConfigPath,
                'private_key_bits' => 3072,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            if ($key === false || !openssl_pkey_export($key, $privatePem, null, ['config' => $opensslConfigPath])) {
                throw new RuntimeException('Unable to generate the RSA key pair.');
            }
            $details = openssl_pkey_get_details($key);
            if ($details === false || !isset($details['key'])) {
                throw new RuntimeException('Unable to export the RSA public key.');
            }
            if (file_put_contents($privateKeyPath, $privatePem, LOCK_EX) === false
                || file_put_contents($publicKeyPath, $details['key'], LOCK_EX) === false) {
                throw new RuntimeException('Unable to save the RSA key pair.');
            }
        } elseif (!is_file($publicKeyPath)) {
            $privateKey = openssl_pkey_get_private((string) file_get_contents($privateKeyPath));
            $details = $privateKey === false ? false : openssl_pkey_get_details($privateKey);
            if ($details === false || !isset($details['key'])
                || file_put_contents($publicKeyPath, $details['key'], LOCK_EX) === false) {
                throw new RuntimeException('Unable to restore the RSA public key.');
            }
        }

        if (!is_file($documentsPath)
            && file_put_contents($documentsPath, "[]\n", LOCK_EX) === false) {
            throw new RuntimeException('Unable to initialize the encrypted archive.');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function withStorageLock(string $storagePath, callable $callback)
{
    $lock = fopen($storagePath . DIRECTORY_SEPARATOR . 'documents.lock', 'c+');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Unable to lock the archive.');
    }

    try {
        return $callback();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function readRecords(string $documentsPath): array
{
    $contents = file_get_contents($documentsPath);
    if ($contents === false) {
        throw new RuntimeException('Unable to read the archive.');
    }
    $records = json_decode($contents, true);
    if (!is_array($records)) {
        throw new RuntimeException('The encrypted archive is not valid JSON.');
    }
    return $records;
}

function writeRecords(string $documentsPath, array $records): void
{
    $contents = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($contents === false || file_put_contents($documentsPath, $contents . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Unable to save the encrypted archive.');
    }
}

function encryptDocument(array $document, string $publicKeyPath): array
{
    $publicKey = openssl_pkey_get_public((string) file_get_contents($publicKeyPath));
    if ($publicKey === false) {
        throw new RuntimeException('Unable to load the RSA public key.');
    }

    $symmetricKey = random_bytes(32);
    $nonce = random_bytes(12);
    $plaintext = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($plaintext === false
        || !openssl_public_encrypt($symmetricKey, $wrappedKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new RuntimeException('Unable to encrypt the archive document.');
    }

    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $symmetricKey, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) {
        throw new RuntimeException('Unable to encrypt the archive document.');
    }

    return [
        'id' => $document['id'],
        'wrapped_key' => base64_encode($wrappedKey),
        'nonce' => base64_encode($nonce),
        'tag' => base64_encode($tag),
        'ciphertext' => base64_encode($ciphertext),
    ];
}

function decryptDocument(array $record, string $privateKeyPath): array
{
    foreach (['id', 'wrapped_key', 'nonce', 'tag', 'ciphertext'] as $field) {
        if (!isset($record[$field]) || !is_string($record[$field])) {
            throw new RuntimeException('The encrypted archive contains an invalid record.');
        }
    }

    $privateKey = openssl_pkey_get_private((string) file_get_contents($privateKeyPath));
    $wrappedKey = base64_decode($record['wrapped_key'], true);
    $nonce = base64_decode($record['nonce'], true);
    $tag = base64_decode($record['tag'], true);
    $ciphertext = base64_decode($record['ciphertext'], true);
    if ($privateKey === false || $wrappedKey === false || $nonce === false || $tag === false
        || $ciphertext === false
        || !openssl_private_decrypt($wrappedKey, $symmetricKey, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new RuntimeException('Unable to decrypt an archive document.');
    }

    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $symmetricKey, OPENSSL_RAW_DATA, $nonce, $tag);
    $document = $plaintext === false ? null : json_decode($plaintext, true);
    if (!is_array($document)) {
        throw new RuntimeException('Unable to decrypt an archive document.');
    }
    return $document;
}

function requireCsrfToken(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        throw new UserVisibleException('Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.');
    }
}

function redirectToHome(): never
{
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), true, 303);
    exit;
}

function postString(string $name, int $maxLength): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        throw new UserVisibleException('Data formulir tidak valid.');
    }
    $value = trim($value);
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new UserVisibleException('Salah satu kolom melewati batas panjang yang diizinkan.');
    }
    return $value;
}
