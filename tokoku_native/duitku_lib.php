<?php
declare(strict_types=1);

/**
 * Integrasi payment gateway Duitku (Duitku POP / Create Invoice).
 * Dipakai oleh store.php (checkout), duitku_callback.php (notifikasi) dan index.php (admin).
 *
 * Alur:
 *  1. Checkout  -> duitku_create_invoice()  -> pelanggan diarahkan ke paymentUrl Duitku.
 *  2. Duitku    -> POST ke duitku_callback.php (signature HMAC-SHA256 diverifikasi) -> duitku_mark_paid().
 *  3. Cadangan  -> duitku_sync() memanggil API transactionStatus saat pelanggan kembali ke toko
 *                  atau menekan "Cek status" (berguna di localhost, saat callback belum bisa masuk).
 */

const DUITKU_EXPIRY_MENIT = 60;

function duitku_aktif(): bool {
    return setting('duitku_aktif') === '1'
        && setting('duitku_merchant_code') !== ''
        && setting('duitku_api_key') !== '';
}

function duitku_produksi(): bool {
    return setting('duitku_mode') === 'production';
}

function duitku_endpoint(string $nama): string {
    $prod = duitku_produksi();
    return match ($nama) {
        'invoice' => $prod ? 'https://api-prod.duitku.com/api/merchant/createInvoice'
                           : 'https://api-sandbox.duitku.com/api/merchant/createInvoice',
        'inquiry' => $prod ? 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry'
                           : 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry',
        'status'  => $prod ? 'https://passport.duitku.com/webapi/api/merchant/transactionStatus'
                           : 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus',
        default   => throw new InvalidArgumentException('Endpoint Duitku tidak dikenal.'),
    };
}

/** Signature HMAC-SHA256 (hex huruf kecil) dengan API key toko. */
function duitku_hmac(string $stringToSign): string {
    return hash_hmac('sha256', $stringToSign, setting('duitku_api_key'));
}

/** URL dasar aplikasi untuk callback & return. Diisi di Pengaturan; jika kosong ditebak dari request. */
function duitku_base_url(): string {
    $set = rtrim(trim(setting('duitku_base_url')), '/');
    if ($set !== '') return $set;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host  = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir   = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function duitku_callback_url(): string { return duitku_base_url() . '/duitku_callback.php'; }

/** Nama saluran pembayaran Duitku (paymentCode dari callback) untuk ditampilkan di invoice. */
function duitku_nama_kanal(string $kode): string {
    static $m = [
        'VC' => 'Kartu Kredit', 'BC' => 'BCA Virtual Account', 'M2' => 'Mandiri Virtual Account',
        'VA' => 'Maybank Virtual Account', 'I1' => 'BNI Virtual Account', 'B1' => 'CIMB Niaga Virtual Account',
        'BT' => 'Permata Virtual Account', 'A1' => 'ATM Bersama', 'AG' => 'Bank Artha Graha',
        'NC' => 'Bank Neo Commerce', 'BR' => 'BRI Virtual Account (BRIVA)', 'S1' => 'Bank Sahabat Sampoerna',
        'DM' => 'Danamon Virtual Account', 'BV' => 'BSI Virtual Account', 'FT' => 'Ritel (Pegadaian/Alfa/Pos)',
        'IR' => 'Indomaret', 'OV' => 'OVO', 'SA' => 'ShopeePay', 'LF' => 'LinkAja', 'LA' => 'LinkAja',
        'DA' => 'DANA', 'SP' => 'QRIS ShopeePay', 'NQ' => 'QRIS', 'GQ' => 'QRIS Gudang Voucher',
        'SQ' => 'QRIS', 'DN' => 'Indodana Paylater', 'AT' => 'Atome', 'JP' => 'Jenius Pay',
        'T1' => 'Tokopedia Card', 'T2' => 'Tokopedia E-Wallet', 'T3' => 'Tokopedia',
    ];
    return $m[strtoupper($kode)] ?? '';
}

/** Tambah kolom Duitku di tbl_transaksi (sekali saja, ditandai di tbl_pengaturan). */
function duitku_ensure_schema(PDO $pdo): void {
    if (setting('duitku_schema') === '2') return;
    $st = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tbl_transaksi'");
    $have = array_map('strtolower', $st->fetchAll(PDO::FETCH_COLUMN));
    $add = [
        'duitku_reference'   => 'ADD COLUMN duitku_reference varchar(60) DEFAULT NULL',
        'duitku_payment_url' => 'ADD COLUMN duitku_payment_url varchar(255) DEFAULT NULL',
        'duitku_kode_bayar'  => 'ADD COLUMN duitku_kode_bayar varchar(10) DEFAULT NULL',
        'duitku_data'        => 'ADD COLUMN duitku_data text DEFAULT NULL',
    ];
    $todo = [];
    foreach ($add as $c => $ddl) if (!in_array($c, $have, true)) $todo[] = $ddl;
    if ($todo) $pdo->exec('ALTER TABLE tbl_transaksi ' . implode(', ', $todo));
    pengaturan_simpan($pdo, ['duitku_schema' => '2']);
}

/** POST JSON ke Duitku. Mengembalikan [kodeHttp, dataArray]. */
function duitku_post(string $url, array $body, array $headers = []): array {
    $json = (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hdr  = array_merge(['Content-Type: application/json', 'Content-Length: ' . strlen($json)], $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $hdr,
        ]);
        $res  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
    } else {
        // Cadangan tanpa cURL: memakai stream PHP (butuh allow_url_fopen=On dan ekstensi openssl).
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $hdr),
            'content'       => $json,
            'timeout'       => 30,
            'ignore_errors' => true, // tetap baca isi respons saat HTTP 4xx/5xx
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
        }
        $err = $res === false ? (error_get_last()['message'] ?? 'koneksi gagal') : '';
    }
    if ($res === false || $code === 0) {
        throw new RuntimeException('Tidak dapat terhubung ke Duitku: ' . $err
            . ' (Pastikan internet aktif. Jika berisi "SSL"/"certificate", periksa curl.cainfo / openssl.cafile di php.ini.)');
    }
    $data = json_decode((string)$res, true);
    return [$code, is_array($data) ? $data : []];
}

/**
 * Buat invoice di Duitku. $o: kode_invoice, total (int rupiah), email, nama, telepon, items[[nama,harga,qty]], deskripsi.
 * Mengembalikan ['reference' => ..., 'paymentUrl' => ...].
 */
function duitku_create_invoice(array $o): array {
    $merchant = setting('duitku_merchant_code');
    $ts  = (string)(int)round(microtime(true) * 1000);
    $sig = duitku_hmac($merchant . $ts);
    $total = (int)$o['total'];

    $body = [
        'paymentAmount'   => $total,
        'merchantOrderId' => (string)$o['kode_invoice'],
        'productDetails'  => mb_substr((string)$o['deskripsi'], 0, 255),
        'email'           => (string)$o['email'],
        'customerVaName'  => mb_substr((string)$o['nama'], 0, 20),
        'callbackUrl'     => duitku_callback_url(),
        'returnUrl'       => duitku_base_url() . '/store.php?order=' . (int)$o['id_transaksi'] . '&dari=duitku',
        'expiryPeriod'    => DUITKU_EXPIRY_MENIT,
    ];
    if (!empty($o['telepon'])) $body['phoneNumber'] = mb_substr((string)$o['telepon'], 0, 50);

    // itemDetails hanya dikirim jika jumlahnya persis sama dengan total (Duitku mewajibkan harga bulat)
    $items = []; $sum = 0;
    foreach ($o['items'] as [$nama, $harga, $qty]) {
        if (abs($harga - round($harga)) > 0.0001) { $items = []; $sum = -1; break; }
        $items[] = ['name' => mb_substr((string)$nama, 0, 50), 'price' => (int)round($harga), 'quantity' => (int)$qty];
        $sum += (int)round($harga) * (int)$qty;
    }
    if ($items && $sum === $total) $body['itemDetails'] = $items;

    [$code, $data] = duitku_post(duitku_endpoint('invoice'), $body, [
        'x-duitku-signature:'    . $sig,
        'x-duitku-timestamp:'    . $ts,
        'x-duitku-merchantcode:' . $merchant,
    ]);
    $status = (string)($data['statusCode'] ?? '');
    if ($code !== 200 || $status !== '00' || empty($data['paymentUrl']) || empty($data['reference'])) {
        $pesan = (string)($data['statusMessage'] ?? $data['Message'] ?? $data['message'] ?? '');
        if ($code === 401) $pesan = 'Merchant code / API key ditolak (cek Pengaturan, dan pastikan mode sandbox/production sesuai).';
        throw new RuntimeException('Duitku menolak pembuatan pembayaran' . ($pesan !== '' ? ': ' . $pesan : ' (HTTP ' . $code . ').'));
    }
    $url = (string)$data['paymentUrl'];
    if (!duitku_url_aman($url)) throw new RuntimeException('Duitku mengembalikan URL pembayaran yang tidak valid.');
    return ['reference' => (string)$data['reference'], 'paymentUrl' => $url];
}

/** Hanya izinkan redirect ke HTTPS di domain duitku.com. */
function duitku_url_aman(string $url): bool {
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host'])) return false;
    $h = strtolower($p['host']);
    return $h === 'duitku.com' || str_ends_with($h, '.duitku.com');
}

/**
 * Tanyakan status transaksi ke Duitku. Mengembalikan array respons (statusCode: 00 lunas, 01 proses, 02 gagal/kedaluwarsa).
 * Signature MD5 dipakai transaksi API v2 (inquiry); HMAC-SHA256 dipakai invoice POP. Keduanya dicoba bergantian.
 */
function duitku_check_status(string $merchantOrderId): array {
    $merchant = setting('duitku_merchant_code');
    $last = 0;
    foreach ([md5($merchant . $merchantOrderId . setting('duitku_api_key')), duitku_hmac($merchant . $merchantOrderId)] as $sig) {
        [$code, $data] = duitku_post(duitku_endpoint('status'), [
            'merchantCode'    => $merchant,
            'merchantOrderId' => $merchantOrderId,
            'signature'       => $sig,
        ]);
        $last = $code;
        if ($code === 200 && isset($data['statusCode'])) return $data;
    }
    throw new RuntimeException('Gagal memeriksa status ke Duitku (HTTP ' . $last . ').');
}

/**
 * Buat transaksi langsung per metode lewat API v2 (inquiry). Mengembalikan reference, vaNumber, qrString, paymentUrl.
 * $o: kode_invoice, total (int), email, nama, items[[nama,harga,qty]], deskripsi, id_transaksi.
 */
function duitku_inquiry(array $o, string $metode): array {
    $merchant = setting('duitku_merchant_code');
    $total = (int)$o['total'];
    $body = [
        'merchantCode'    => $merchant,
        'paymentAmount'   => $total,
        'paymentMethod'   => $metode,
        'merchantOrderId' => (string)$o['kode_invoice'],
        'productDetails'  => mb_substr((string)$o['deskripsi'], 0, 255),
        'additionalParam' => '',
        'merchantUserInfo'=> '',
        'customerVaName'  => mb_substr((string)$o['nama'], 0, 20),
        'email'           => (string)$o['email'],
        'callbackUrl'     => duitku_callback_url(),
        'returnUrl'       => duitku_base_url() . '/store.php?order=' . (int)$o['id_transaksi'] . '&dari=duitku',
        'signature'       => md5($merchant . (string)$o['kode_invoice'] . $total . setting('duitku_api_key')),
        'expiryPeriod'    => DUITKU_EXPIRY_MENIT,
    ];
    $items = []; $sum = 0;
    foreach ($o['items'] as [$nama, $harga, $qty]) {
        if (abs($harga - round($harga)) > 0.0001) { $items = []; $sum = -1; break; }
        $items[] = ['name' => mb_substr((string)$nama, 0, 50), 'price' => (int)round($harga), 'quantity' => (int)$qty];
        $sum += (int)round($harga) * (int)$qty;
    }
    if ($items && $sum === $total) $body['itemDetails'] = $items;

    [$code, $data] = duitku_post(duitku_endpoint('inquiry'), $body);
    if ($code !== 200 || (string)($data['statusCode'] ?? '') !== '00' || empty($data['reference'])) {
        $pesan = (string)($data['Message'] ?? $data['statusMessage'] ?? $data['message'] ?? '');
        if ($code === 401) $pesan = 'Merchant code / API key ditolak (cek Pengaturan, pastikan mode sandbox cocok).';
        throw new RuntimeException('Duitku menolak transaksi' . ($pesan !== '' ? ': ' . $pesan : ' (HTTP ' . $code . ').'));
    }
    $pu = (string)($data['paymentUrl'] ?? '');
    return [
        'reference'  => (string)$data['reference'],
        'vaNumber'   => (string)($data['vaNumber'] ?? ''),
        'qrString'   => (string)($data['qrString'] ?? ''),
        'paymentUrl' => ($pu !== '' && duitku_url_aman($pu)) ? $pu : '',
        'amount'     => (int)($data['amount'] ?? $total),
    ];
}

/**
 * Tandai transaksi lunas (idempoten). Mengembalikan 'lunas' | 'sudah' | 'tidak_ada'.
 * Jika pesanan sempat kedaluwarsa/dibatalkan (stok sudah dikembalikan), stok dipesan ulang sebisanya.
 */
function duitku_mark_paid(PDO $pdo, int $idT, string $kodeBayar = ''): string {
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status_pembayaran FROM tbl_transaksi WHERE id_transaksi=? FOR UPDATE');
        $st->execute([$idT]);
        $s0 = $st->fetchColumn();
        if ($s0 === false) { $pdo->rollBack(); return 'tidak_ada'; }
        if ($s0 === 'dibayar') { $pdo->rollBack(); return 'sudah'; }
        if ($s0 === 'kedaluwarsa' || $s0 === 'dibatalkan') {
            $pdo->prepare('UPDATE tbl_produk p JOIN tbl_detail_transaksi d ON d.id_produk=p.id_produk SET p.stok=GREATEST(p.stok-d.jumlah,0) WHERE d.id_transaksi=?')
                ->execute([$idT]);
        }
        $pdo->prepare("UPDATE tbl_transaksi SET status_pembayaran='dibayar', dibayar_pada=?, duitku_kode_bayar=? WHERE id_transaksi=?")
            ->execute([date('Y-m-d H:i:s'), $kodeBayar !== '' ? substr($kodeBayar, 0, 10) : null, $idT]);
        $pdo->prepare('UPDATE tbl_produk p JOIN tbl_detail_transaksi d ON d.id_produk=p.id_produk SET p.terjual=p.terjual+d.jumlah WHERE d.id_transaksi=?')
            ->execute([$idT]);
        $pdo->commit();
        return 'lunas';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Sinkronkan satu pesanan Duitku yang masih 'menunggu' dengan status di Duitku.
 * Mengembalikan 'lunas' | 'proses' | 'gagal' | 'lewati'.
 */
function duitku_sync(PDO $pdo, array $order): string {
    if (($order['metode_pembayaran'] ?? '') !== 'duitku' || ($order['status_pembayaran'] ?? '') !== 'menunggu' || !duitku_aktif()) return 'lewati';
    $r = duitku_check_status((string)$order['kode_invoice']);
    $sc = (string)$r['statusCode'];
    if ($sc === '00') {
        if (isset($r['amount']) && (int)round((float)$r['amount']) !== (int)round((float)$order['total_belanja'])) {
            throw new RuntimeException('Nominal pembayaran di Duitku tidak sama dengan total pesanan. Hubungi admin.');
        }
        duitku_mark_paid($pdo, (int)$order['id_transaksi']);
        return 'lunas';
    }
    return $sc === '01' ? 'proses' : 'gagal';
}

// Pastikan kolom Duitku ada setiap file ini dimuat (murah: dicek lewat tbl_pengaturan).
if (isset($pdo) && $pdo instanceof PDO) duitku_ensure_schema($pdo);
