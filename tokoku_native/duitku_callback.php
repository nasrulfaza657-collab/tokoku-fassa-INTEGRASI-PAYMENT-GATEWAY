<?php
declare(strict_types=1);
/**
 * Endpoint notifikasi pembayaran Duitku (callbackUrl).
 * Harus bisa diakses publik oleh server Duitku (bukan http://localhost).
 * Tidak memakai sesi/login: keaslian dijamin oleh signature HMAC-SHA256.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/duitku_lib.php';

header('Content-Type: text/plain; charset=utf-8');

function cb_exit(int $code, string $msg): never {
    http_response_code($code);
    exit($msg);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') cb_exit(405, 'Method Not Allowed');
if (!duitku_aktif()) cb_exit(503, 'Duitku tidak aktif');

$merchantCode    = (string)($_POST['merchantCode'] ?? '');
$amount          = (string)($_POST['amount'] ?? '');
$merchantOrderId = (string)($_POST['merchantOrderId'] ?? '');
$resultCode      = (string)($_POST['resultCode'] ?? '');
$paymentCode     = (string)($_POST['paymentCode'] ?? '');
$signature       = (string)($_POST['signature'] ?? '');

if ($merchantCode === '' || $amount === '' || $merchantOrderId === '' || $signature === '') cb_exit(400, 'Bad Parameter');
if (!hash_equals(setting('duitku_merchant_code'), $merchantCode)) cb_exit(400, 'Bad Merchant');
$sigHmac = duitku_hmac($merchantCode . $amount . $merchantOrderId);
$sigMd5  = md5($merchantCode . $amount . $merchantOrderId . setting('duitku_api_key'));
if (!hash_equals($sigHmac, strtolower($signature)) && !hash_equals($sigMd5, strtolower($signature))) cb_exit(400, 'Bad Signature');

$st = $pdo->prepare("SELECT id_transaksi, total_belanja FROM tbl_transaksi WHERE kode_invoice=? AND metode_pembayaran='duitku' LIMIT 1");
$st->execute([$merchantOrderId]);
$trx = $st->fetch();
if (!$trx) cb_exit(404, 'Order not found');

// Hanya resultCode 00 yang berarti lunas. Status gagal diabaikan: pesanan kedaluwarsa sendiri sesuai batas waktu.
if ($resultCode !== '00') cb_exit(200, 'OK');

if ((int)round((float)$amount) !== (int)round((float)$trx['total_belanja'])) cb_exit(400, 'Amount mismatch');

try {
    duitku_mark_paid($pdo, (int)$trx['id_transaksi'], $paymentCode);
} catch (Throwable $e) {
    cb_exit(500, 'Server error'); // Duitku akan mencoba mengirim ulang
}
cb_exit(200, 'OK');
