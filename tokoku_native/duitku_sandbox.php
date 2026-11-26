<?php
declare(strict_types=1);
/**
 * Halaman checkout SANDBOX Duitku (bergaya Duitku, dengan animasi).
 * Transaksi dibuat sungguhan di sandbox Duitku (API v2 inquiry) sehingga mendapat kode reference,
 * nomor VA, atau QRIS asli. Pembayaran dilakukan lewat simulator Duitku; status lunas hanya
 * ditandai setelah Duitku mengonfirmasinya (callback / cek status), bukan dari tombol di halaman ini.
 */
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/duitku_lib.php';

const SB_SIMULATOR = 'https://sandbox.duitku.com/payment/demo/demosuccesstransaction.aspx';

function sb_e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function sb_rp($v): string { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }
function sb_json(array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d);
    exit;
}
$sbAjax = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['cek']);
function sb_stop(string $judul, string $pesan): never {
    global $sbAjax;
    if ($sbAjax) sb_json(['ok' => false, 'pesan' => $pesan], 403);
    http_response_code(403);
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= sb_e($judul) ?></title>
    <style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f2a5c;color:#fff;font-family:system-ui,sans-serif;padding:20px}
    .b{max-width:420px;background:#fff;color:#1b2a47;border-radius:14px;padding:28px;text-align:center}h1{font-size:18px;margin:0 0 8px}p{margin:0 0 18px;font-size:14px;line-height:1.6;color:#53627f}
    a{display:inline-block;background:#0b5ed7;color:#fff;text-decoration:none;padding:10px 18px;border-radius:9px;font-size:14px}</style></head>
    <body><div class="b"><h1><?= sb_e($judul) ?></h1><p><?= sb_e($pesan) ?></p><a href="store.php?orders=1">Kembali ke toko</a></div></body></html><?php
    exit;
}

// ---- Wajib login sebagai pelanggan toko online ----
$customer = null;
if (!empty($_SESSION['auth']['id'])) {
    $s = $pdo->prepare("SELECT u.id_user, u.nama, u.username FROM tbl_user u JOIN tbl_akun_pelanggan m ON m.id_user=u.id_user WHERE u.id_user=? AND u.role='user'");
    $s->execute([(int)$_SESSION['auth']['id']]);
    $customer = $s->fetch() ?: null;
}
if (!$customer) {
    if ($sbAjax) sb_json(['ok' => false, 'pesan' => 'Sesi habis. Silakan login ulang.'], 401);
    header('Location: store.php?account=1'); exit;
}

$orderId = (int)($_GET['order'] ?? $_POST['order'] ?? 0);
$qOrder = $pdo->prepare('SELECT t.* FROM tbl_transaksi t JOIN tbl_akun_pelanggan a ON a.id_pelanggan=t.id_pelanggan WHERE t.id_transaksi=? AND a.id_user=?');
$qOrder->execute([$orderId, (int)$customer['id_user']]);
$order = $qOrder->fetch();
if (!$order) sb_stop('Pesanan tidak ditemukan', 'Pesanan ini tidak ada atau bukan milik akun Anda.');
if (duitku_produksi()) sb_stop('Halaman sandbox tidak tersedia', 'Mode Duitku sedang Production. Halaman ini hanya untuk mode Sandbox.');
if (!duitku_aktif()) sb_stop('Duitku belum aktif', 'Admin belum mengaktifkan atau melengkapi pengaturan Duitku.');
if ($order['metode_pembayaran'] !== 'duitku') sb_stop('Bukan pesanan Duitku', 'Pesanan ini tidak memakai pembayaran Duitku.');

if (empty($_SESSION['sbx_token'])) $_SESSION['sbx_token'] = bin2hex(random_bytes(16));
$token = (string)$_SESSION['sbx_token'];

// Kanal: [paymentCode, nama, tipe, singkatan logo, warna]
$GRUP = [
    'E-Wallet' => [
        ['DA', 'DANA', 'ewallet', 'DANA', '#118eea'],
        ['OV', 'OVO', 'ewallet', 'OVO', '#4c2a86'],
        ['SA', 'ShopeePay', 'ewallet', 'SPay', '#ee4d2d'],
    ],
    'QRIS' => [
        ['NQ', 'QRIS by NOBU', 'qris', 'QRIS', '#e11d2e'],
        ['SP', 'QRIS by SHOPEEPAY', 'qris', 'QRIS', '#ee4d2d'],
    ],
    'Virtual Account' => [
        ['BC', 'BCA Virtual Account', 'va', 'BCA', '#0b5aa8'],
        ['M2', 'Mandiri Virtual Account', 'va', 'MDR', '#003d79'],
        ['BR', 'BRI Virtual Account (BRIVA)', 'va', 'BRI', '#00529c'],
        ['I1', 'BNI Virtual Account', 'va', 'BNI', '#e8672a'],
    ],
    'Kartu' => [
        ['VC', 'Kartu Kredit', 'kartu', 'CC', '#374151'],
    ],
];
$KODE_VALID = [];
foreach ($GRUP as $list) foreach ($list as $m) $KODE_VALID[] = $m[0];

// ---- Aksi AJAX ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['cek'])) {
    // Cek status ke Duitku (dipanggil berkala oleh halaman)
    if (isset($_GET['cek'])) {
        $info = ['ok' => true, 'status' => (string)$order['status_pembayaran']];
        if ($order['status_pembayaran'] === 'menunggu' && !empty($order['duitku_reference'])) {
            try {
                $r = duitku_sync($pdo, $order);
                $info['duitku'] = $r;
                if ($r === 'lunas') {
                    $d = json_decode((string)($order['duitku_data'] ?? ''), true);
                    if (!empty($d['kode'])) $pdo->prepare('UPDATE tbl_transaksi SET duitku_kode_bayar=? WHERE id_transaksi=?')->execute([substr((string)$d['kode'], 0, 10), $orderId]);
                }
            } catch (Throwable $e) { $info['duitku'] = 'error'; }
            $qOrder->execute([$orderId, (int)$customer['id_user']]);
            $o2 = $qOrder->fetch();
            $info['status'] = $o2 ? (string)$o2['status_pembayaran'] : $info['status'];
        }
        sb_json($info);
    }

    if (!hash_equals($token, (string)($_POST['token'] ?? ''))) sb_json(['ok' => false, 'pesan' => 'Sesi tidak valid. Muat ulang halaman.'], 400);

    if (($_POST['aksi'] ?? '') === 'buat') {
        $kode = strtoupper((string)($_POST['kode'] ?? ''));
        if (!in_array($kode, $KODE_VALID, true)) sb_json(['ok' => false, 'pesan' => 'Metode pembayaran tidak dikenal.'], 400);
        if ($order['status_pembayaran'] !== 'menunggu' || (!empty($order['batas_bayar']) && $order['batas_bayar'] < date('Y-m-d H:i:s'))) {
            sb_json(['ok' => false, 'pesan' => 'Pesanan sudah kedaluwarsa, dibatalkan, atau lunas.'], 409);
        }
        // Metode sudah dipilih sebelumnya: pakai data yang sama (Duitku menolak merchantOrderId ganda)
        $ada = json_decode((string)($order['duitku_data'] ?? ''), true);
        if (is_array($ada) && !empty($ada['reference'])) sb_json(['ok' => true, 'data' => $ada, 'dipakai_ulang' => true]);

        $st = $pdo->prepare('SELECT d.jumlah, d.harga_satuan, p.nama_produk FROM tbl_detail_transaksi d JOIN tbl_produk p ON p.id_produk=d.id_produk WHERE d.id_transaksi=?');
        $items = [];
        try {
            $st->execute([$orderId]);
            foreach ($st->fetchAll() as $r) $items[] = [$r['nama_produk'], (float)$r['harga_satuan'], (int)$r['jumlah']];
        } catch (Throwable $e) { $items = []; }
        $email = preg_replace('/[^a-z0-9._-]/', '', strtolower((string)$customer['username'])) . '@example.com';
        try {
            $res = duitku_inquiry([
                'id_transaksi' => $orderId, 'kode_invoice' => $order['kode_invoice'], 'total' => (int)round((float)$order['total_belanja']),
                'email' => $email, 'nama' => (string)$customer['nama'], 'items' => $items,
                'deskripsi' => 'Pesanan ' . $order['kode_invoice'] . ' - ' . ($NAMA_TOKO ?? 'Toko'),
            ], $kode);
        } catch (Throwable $e) {
            sb_json(['ok' => false, 'pesan' => $e->getMessage()], 502);
        }
        $data = ['kode' => $kode] + $res;
        $pdo->prepare('UPDATE tbl_transaksi SET duitku_reference=?, duitku_kode_bayar=?, duitku_payment_url=?, duitku_data=? WHERE id_transaksi=?')
            ->execute([$res['reference'], $kode, $res['paymentUrl'] !== '' ? $res['paymentUrl'] : null, json_encode($data), $orderId]);
        sb_json(['ok' => true, 'data' => $data]);
    }
    sb_json(['ok' => false, 'pesan' => 'Aksi tidak dikenal.'], 400);
}

// ---- Data tampilan ----
$s = $pdo->prepare('SELECT d.*, p.nama_produk FROM tbl_detail_transaksi d JOIN tbl_produk p ON p.id_produk=d.id_produk WHERE d.id_transaksi=?');
$s->execute([$orderId]);
$items = $s->fetchAll();
$sudahLunas = $order['status_pembayaran'] === 'dibayar';
$nonMenunggu = !$sudahLunas && $order['status_pembayaran'] !== 'menunggu';
$sisa = 0;
if ($order['status_pembayaran'] === 'menunggu' && !empty($order['batas_bayar'])) $sisa = max(0, strtotime((string)$order['batas_bayar']) - time());
$bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$ts  = strtotime((string)($order['tanggal'] ?? 'now')) ?: time();
$tgl = date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
$backUrl = 'store.php?order=' . $orderId . '&dari=duitku';
$namaToko = (string)($NAMA_TOKO ?? 'Toko');
$dataAda = json_decode((string)($order['duitku_data'] ?? ''), true);
$cfg = [
    'token' => $token, 'order' => $orderId, 'back' => $backUrl, 'sisa' => $sisa,
    'total' => (int)round((float)$order['total_belanja']), 'invoice' => (string)$order['kode_invoice'],
    'lunas' => $sudahLunas, 'nonaktif' => $nonMenunggu, 'simulator' => SB_SIMULATOR,
    'data' => (is_array($dataAda) && !empty($dataAda['reference'])) ? $dataAda : null,
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= sb_e($namaToko) ?> · Checkout Sandbox</title>
<style>
:root{--ink:#16233f;--mut:#667592;--line:#e3e9f3;--blue:#0b5ed7;--navy:#12306b;--ok:#12a36b;--bg1:#13306d;--bg2:#1f9aa6}
*{box-sizing:border-box}
html,body{margin:0}
body{min-height:100vh;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);
  background:linear-gradient(135deg,var(--bg1),#1b6e9a 55%,var(--bg2));background-size:200% 200%;animation:bgshift 18s ease-in-out infinite alternate;
  display:grid;place-items:center;padding:28px 16px;position:relative;overflow-x:hidden}
@keyframes bgshift{to{background-position:100% 100%}}
.dots{position:fixed;inset:0;pointer-events:none;opacity:.18;
  background-image:radial-gradient(#fff 1.6px,transparent 1.7px);background-size:22px 22px;
  -webkit-mask-image:radial-gradient(ellipse at 50% 40%,#000 25%,transparent 75%);mask-image:radial-gradient(ellipse at 50% 40%,#000 25%,transparent 75%);
  animation:dots 30s linear infinite}
@keyframes dots{to{background-position:220px 110px}}
.sandbox-tag{position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:3;background:#fff3cd;color:#7a5200;border:1px solid #f1d58a;border-radius:999px;padding:5px 14px;font-size:12px;font-weight:600;box-shadow:0 6px 18px #0003}
.shell{position:relative;z-index:2;width:min(920px,100%);background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 30px 80px #0007;
  animation:pop .6s cubic-bezier(.2,.9,.3,1.1) both}
@keyframes pop{from{opacity:0;transform:translateY(26px) scale(.97)}}
.top{display:flex;align-items:center;gap:12px;padding:16px 22px;border-bottom:1px solid var(--line)}
.top a.back{width:34px;height:34px;display:grid;place-items:center;border-radius:50%;color:var(--ink);text-decoration:none;transition:background .2s,transform .2s}
.top a.back:hover{background:#eef3fb;transform:translateX(-2px)}
.top h1{margin:0;font-size:19px;font-weight:700;flex:1}
.timer{font-variant-numeric:tabular-nums;font-size:13px;font-weight:700;color:var(--navy);background:#eaf1ff;border-radius:999px;padding:6px 12px;display:flex;align-items:center;gap:7px}
.timer i{width:8px;height:8px;border-radius:50%;background:var(--blue);animation:pulse 1.4s infinite}
.timer.low{background:#fdecec;color:#b42318}.timer.low i{background:#d92d20}
@keyframes pulse{50%{transform:scale(1.7);opacity:.4}}
.cols{display:grid;grid-template-columns:1fr 1.05fr;min-height:520px}
.left{background:repeating-linear-gradient(135deg,#f6f8fc 0 10px,#f1f4fa 10px 20px);padding:18px;display:grid;gap:14px;align-content:start}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 10px #1b2a4714;padding:16px 18px}
.shop{display:flex;gap:16px;align-items:center}
.shop .logo{width:64px;height:64px;border-radius:14px;background:linear-gradient(135deg,#ffb84d,#ff8a1f);display:grid;place-items:center;font-size:30px;flex-shrink:0;animation:float 3.2s ease-in-out infinite}
@keyframes float{50%{transform:translateY(-5px) rotate(-3deg)}}
.kv{display:grid;gap:10px;font-size:13px;flex:1;min-width:0}
.kv div{padding-bottom:9px;border-bottom:1px solid var(--line)}.kv div:last-child{border:0;padding:0}
.kv span{display:block;color:var(--mut);font-size:12px;margin-bottom:2px}
.kv b{font-weight:600;word-break:break-all}
.card h2{margin:0 0 10px;font-size:12px;letter-spacing:.08em;color:var(--navy)}
.row{display:flex;justify-content:space-between;gap:12px;font-size:13px;padding:7px 0;border-bottom:1px solid var(--line)}
.row:last-child{border:0}.row.tot{font-weight:700;font-size:14px}
.right{padding:18px;display:flex;flex-direction:column;min-width:0;position:relative;overflow:hidden}
.right h2{margin:0 0 12px;font-size:15px;display:flex;justify-content:space-between;align-items:center}
.right h2 small{font-size:10px;color:var(--mut);font-weight:600;border:1px dashed #b9c5dc;border-radius:6px;padding:3px 7px}
.view{animation:viewin .35s ease both}
@keyframes viewin{from{opacity:0;transform:translateX(18px)}}
.view.back{animation-name:viewback}
@keyframes viewback{from{opacity:0;transform:translateX(-18px)}}
.grp{margin-bottom:10px;border:1px solid var(--line);border-radius:10px;overflow:hidden}
.grp>button{all:unset;box-sizing:border-box;width:100%;display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:#f3f6fb;font-size:13px;font-weight:700;cursor:pointer}
.grp>button:focus-visible{outline:2px solid var(--blue);outline-offset:-2px}
.grp>button svg{transition:transform .3s}.grp.open>button svg{transform:rotate(180deg)}
.grp .list{display:grid;grid-template-rows:0fr;transition:grid-template-rows .35s ease}
.grp.open .list{grid-template-rows:1fr}
.grp .list>div{overflow:hidden}
.m{all:unset;box-sizing:border-box;width:100%;display:flex;align-items:center;gap:13px;padding:12px 14px;border-top:1px solid var(--line);cursor:pointer;background:#fff;transition:background .2s,padding-left .2s}
.m:hover{background:#f5f8ff;padding-left:18px}
.m:focus-visible{outline:2px solid var(--blue);outline-offset:-2px}
.m .lg{width:46px;height:30px;border-radius:6px;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;letter-spacing:.02em;flex-shrink:0}
.m b{display:block;font-size:13px}.m small{display:block;color:var(--mut);font-size:11px;margin-top:2px}
.m .ch{margin-left:auto;color:#9aa8c2;transition:transform .2s}.m:hover .ch{transform:translateX(3px)}
.detail .hd{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.detail .hd button{all:unset;cursor:pointer;width:30px;height:30px;display:grid;place-items:center;border-radius:50%;transition:background .2s}
.detail .hd button:hover{background:#eef3fb}.detail .hd button:focus-visible{outline:2px solid var(--blue)}
.detail .hd .lg{width:46px;height:30px;border-radius:6px;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800}
.detail .hd b{font-size:14px}
.box{border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:12px;text-align:center}
.box .lbl{font-size:12px;color:var(--mut);margin-bottom:6px}
.big{font-size:22px;font-weight:800;letter-spacing:.06em;font-variant-numeric:tabular-nums}
.copy{margin-top:10px;font:inherit;font-size:12px;font-weight:600;color:var(--blue);background:#eaf1ff;border:0;border-radius:8px;padding:7px 14px;cursor:pointer;transition:background .2s}
.copy:hover{background:#dbe8ff}
.qrwrap{position:relative;width:188px;height:188px;margin:0 auto;border-radius:10px;overflow:hidden;background:#fff;border:1px solid var(--line);padding:8px}
.qrwrap canvas{width:100%;height:100%;image-rendering:pixelated;display:block}
.qrwrap::after{content:"";position:absolute;left:0;right:0;height:46px;top:-46px;background:linear-gradient(180deg,transparent,#0b5ed744,transparent);border-bottom:2px solid #0b5ed7aa;animation:scan 2.4s ease-in-out infinite}
@keyframes scan{to{top:100%}}
.field{display:grid;gap:6px;text-align:left;margin-bottom:12px;font-size:12px;color:var(--mut)}
.field input{font:inherit;font-size:14px;color:var(--ink);padding:11px 12px;border:1px solid #cfd8e8;border-radius:9px;outline:none;transition:border-color .2s,box-shadow .2s}
.field input:focus{border-color:var(--blue);box-shadow:0 0 0 3px #0b5ed722}
.sum{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:#f3f6fb;border-radius:10px;font-size:13px;margin-bottom:12px}
.sum b{font-size:16px}
.pay{width:100%;font:inherit;font-weight:700;font-size:14px;color:#fff;background:var(--blue);border:0;border-radius:10px;padding:13px;cursor:pointer;position:relative;overflow:hidden;transition:transform .15s,background .2s}
.pay:hover{background:#0a54c1}.pay:active{transform:scale(.98)}
.pay:focus-visible{outline:3px solid #0b5ed755;outline-offset:2px}
.pay::after{content:"";position:absolute;inset:0;background:linear-gradient(100deg,transparent 30%,#fff5 50%,transparent 70%);transform:translateX(-100%);animation:shine 3.2s infinite}
@keyframes shine{40%,100%{transform:translateX(100%)}}
.note{font-size:11px;color:var(--mut);text-align:center;margin:10px 0 0;line-height:1.5}
/* proses & sukses */
.state{position:absolute;inset:0;background:#fff;display:none;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:24px;z-index:4}
.state.on{display:flex;animation:fade .3s both}
@keyframes fade{from{opacity:0}}
.spin{width:64px;height:64px;border-radius:50%;border:5px solid #e3ecfb;border-top-color:var(--blue);animation:rot .9s linear infinite;margin-bottom:18px}
@keyframes rot{to{transform:rotate(360deg)}}
.steps{list-style:none;margin:16px 0 0;padding:0;display:grid;gap:9px;text-align:left;font-size:13px;color:var(--mut)}
.steps li{display:flex;align-items:center;gap:10px;transition:color .3s}
.steps li i{width:18px;height:18px;border-radius:50%;border:2px solid #cfd8e8;display:grid;place-items:center;transition:.3s}
.steps li.now{color:var(--ink)}.steps li.now i{border-color:var(--blue);border-top-color:transparent;animation:rot .8s linear infinite}
.steps li.done{color:var(--ink)}.steps li.done i{background:var(--ok);border-color:var(--ok);animation:none}
.steps li.done i::after{content:"";width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg) translate(-1px,-1px)}
.okc{width:96px;height:96px;margin-bottom:14px}
.okc circle{fill:none;stroke:var(--ok);stroke-width:5;stroke-dasharray:283;stroke-dashoffset:283;transform-origin:center;transform:rotate(-90deg);animation:draw .7s .1s ease forwards}
.okc path{fill:none;stroke:var(--ok);stroke-width:6;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:60;stroke-dashoffset:60;animation:draw .45s .7s ease forwards}
@keyframes draw{to{stroke-dashoffset:0}}
.okwrap{position:relative;display:grid;place-items:center}
.okwrap::before{content:"";position:absolute;width:96px;height:96px;border-radius:50%;background:#12a36b33;animation:ring 1.4s .9s ease-out infinite}
@keyframes ring{from{transform:scale(1);opacity:.9}to{transform:scale(1.9);opacity:0}}
.state h3{margin:0 0 6px;font-size:20px}.state p{margin:0;color:var(--mut);font-size:13px;line-height:1.6}
.state .go{margin-top:18px;display:inline-block;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:11px 22px;border-radius:10px}
.bar{height:4px;width:200px;background:#e3ecfb;border-radius:4px;overflow:hidden;margin-top:14px}
.bar i{display:block;height:100%;width:0;background:var(--ok);animation:fill 4s linear forwards}
@keyframes fill{to{width:100%}}
.err{margin-top:10px;color:#b42318;font-size:12px;text-align:center;min-height:16px}
canvas.conf{position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:9}
@media(max-width:760px){.cols{grid-template-columns:1fr}.left{order:2}.shell{margin-top:28px}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
.refbox{display:grid;gap:6px;text-align:center;border:1px solid var(--line);border-radius:10px;padding:12px;margin-bottom:12px}
.refbox .lbl{font-size:12px;color:var(--mut)}
.refbox .big{font-size:15px;letter-spacing:.03em;font-family:ui-monospace,Consolas,monospace;word-break:break-all}
.sim{border:1px dashed #9db6e6;background:#f4f8ff;border-radius:10px;padding:14px;margin-bottom:12px;font-size:12px;line-height:1.6;color:#2b3c5e}
.sim b{color:var(--navy)}.sim ol{margin:6px 0 12px;padding-left:18px}
.btn-link{display:block;text-align:center;text-decoration:none;font-weight:700;font-size:13px;color:#fff;background:var(--navy);border-radius:9px;padding:11px;transition:background .2s,transform .15s}
.btn-link:hover{background:#0d2554}.btn-link:active{transform:scale(.98)}
.btn-link.alt{background:#eaf1ff;color:var(--blue);margin-top:8px}.btn-link.alt:hover{background:#dbe8ff}
.waitrow{display:flex;align-items:center;gap:10px;font-size:12px;color:var(--mut);padding:10px 12px;border-radius:10px;background:#f3f6fb}
.waitrow i{width:9px;height:9px;border-radius:50%;background:var(--blue);animation:pulse 1.4s infinite;flex-shrink:0}
.waitrow button{all:unset;margin-left:auto;cursor:pointer;color:var(--blue);font-weight:700;font-size:12px}
.waitrow button:focus-visible{outline:2px solid var(--blue);border-radius:4px}
.qrsvg{width:196px;height:196px;margin:0 auto;padding:8px;border:1px solid var(--line);border-radius:10px;background:#fff;position:relative;overflow:hidden;animation:pop .5s both}
.qrsvg svg{width:100%;height:100%;display:block}
.qrsvg::after{content:"";position:absolute;left:0;right:0;height:46px;top:-46px;background:linear-gradient(180deg,transparent,#0b5ed744,transparent);border-bottom:2px solid #0b5ed7aa;animation:scan 2.4s ease-in-out infinite}
.lockn{font-size:11px;color:var(--mut);text-align:center;margin:0 0 12px}
</style>
</head>
<body>
<div class="dots" aria-hidden="true"></div>
<div class="sandbox-tag">Mode sandbox Duitku · transaksi uji, tanpa uang sungguhan</div>

<main class="shell">
  <div class="top">
    <a class="back" href="<?= sb_e($backUrl) ?>" aria-label="Kembali ke toko"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
    <h1><?= sb_e($namaToko) ?></h1>
    <?php if ($sisa > 0): ?><div class="timer" id="timer"><i></i><span id="tt">--:--</span></div><?php endif ?>
  </div>

  <div class="cols">
    <section class="left" aria-label="Ringkasan pesanan">
      <div class="card shop">
        <div class="logo" aria-hidden="true">📦</div>
        <div class="kv">
          <div><span>Id Pemesanan</span><b><?= sb_e($order['kode_invoice']) ?></b></div>
          <div><span>Tanggal Transaksi</span><b><?= sb_e($tgl) ?></b></div>
          <div><span>Catatan</span><b>Pesanan <?= sb_e($order['kode_invoice']) ?> - <?= sb_e($namaToko) ?></b></div>
        </div>
      </div>
      <div class="card">
        <h2>DATA PEMESANAN</h2>
        <?php foreach ($items as $it): ?>
          <div class="row"><span><?= sb_e($it['nama_produk']) ?> × <?= (int)$it['jumlah'] ?></span><span><?= sb_rp($it['subtotal']) ?></span></div>
        <?php endforeach ?>
        <div class="row tot"><span>Total</span><span><?= sb_rp($order['total_belanja']) ?></span></div>
      </div>
    </section>

    <section class="right" id="right" aria-live="polite">
      <div id="main"></div>

      <div class="state" id="stProses" role="status">
        <div class="spin"></div>
        <h3>Pembayaran diterima Duitku</h3>
        <p>Memverifikasi ke toko…</p>
        <ul class="steps" id="steps">
          <li><i></i>Pembayaran terdeteksi di Duitku</li>
          <li><i></i>Mencocokkan nominal pesanan</li>
          <li><i></i>Mengonfirmasi ke toko</li>
        </ul>
      </div>

      <div class="state" id="stOk" role="status">
        <div class="okwrap"><svg class="okc" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="45"/><path d="M30 52l14 14 27-30"/></svg></div>
        <h3>Pembayaran berhasil</h3>
        <p id="okInfo"></p>
        <div class="bar"><i></i></div>
        <p style="margin-top:10px;font-size:12px">Mengalihkan kembali ke toko…</p>
        <a class="go" href="<?= sb_e($backUrl) ?>">Kembali ke toko sekarang</a>
      </div>

      <div class="state" id="stEnd">
        <h3 id="endT">Pesanan tidak bisa dibayar</h3>
        <p id="endP">Waktu pembayaran habis atau pesanan sudah dibatalkan.</p>
        <a class="go" href="<?= sb_e($backUrl) ?>">Kembali ke toko</a>
      </div>
    </section>
  </div>
</main>
<canvas class="conf" id="conf" aria-hidden="true"></canvas>

<script>
(function(){
  var CFG = <?= json_encode($cfg, JSON_UNESCAPED_SLASHES) ?>;
  var GRUP = <?= json_encode($GRUP, JSON_UNESCAPED_UNICODE) ?>;
  var $ = function(id){ return document.getElementById(id); };
  var main = $('main'), busy = false, pollT = null, selesai = false;
  var rp = function(n){ return 'Rp ' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
  var esc = function(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };
  var CHEV = '<svg class="ch" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
  var DOWN = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
  var BACK = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>';

  function find(k){ var r = null; Object.keys(GRUP).forEach(function(g){ GRUP[g].forEach(function(m){ if (m[0] === k) r = m; }); }); return r; }
  function salin(btn, teks, awal){
    (navigator.clipboard ? navigator.clipboard.writeText(teks) : Promise.reject()).catch(function(){}).then(function(){ btn.textContent = 'Tersalin'; setTimeout(function(){ btn.textContent = awal; }, 1500); });
  }

  /* ---------- daftar metode ---------- */
  function showList(){
    var html = '<div class="view"><h2>Pilih Metode Pembayaran <small>SANDBOX</small></h2>', first = true;
    Object.keys(GRUP).forEach(function(g){
      html += '<div class="grp' + (first ? ' open' : '') + '"><button type="button" aria-expanded="' + first + '">' + esc(g) + DOWN + '</button><div class="list"><div>';
      GRUP[g].forEach(function(m){
        html += '<button type="button" class="m" data-k="' + m[0] + '"><span class="lg" style="background:' + m[4] + '">' + esc(m[3]) + '</span><span><b>' + esc(m[1]) + '</b><small>Tanpa Biaya Layanan</small></span>' + CHEV + '</button>';
      });
      html += '</div></div></div>';
      first = false;
    });
    html += '<div class="err" id="err"></div></div>';
    main.innerHTML = html;
    main.querySelectorAll('.grp>button').forEach(function(b){
      b.addEventListener('click', function(){ var p = b.parentNode, o = p.classList.toggle('open'); b.setAttribute('aria-expanded', o); });
    });
    main.querySelectorAll('.m').forEach(function(b){ b.addEventListener('click', function(){ pilih(b.dataset.k); }); });
  }

  /* ---------- buat transaksi di Duitku ---------- */
  function pilih(k){
    if (busy) return; busy = true;
    var m = find(k);
    main.innerHTML = '<div class="view" style="text-align:center;padding:60px 10px"><div class="spin" style="margin:0 auto 16px"></div><b>Membuat transaksi di Duitku…</b><p class="note">' + esc(m ? m[1] : '') + '</p></div>';
    var fd = new FormData();
    fd.append('aksi', 'buat'); fd.append('token', CFG.token); fd.append('order', CFG.order); fd.append('kode', k);
    fetch('duitku_sandbox.php?order=' + CFG.order, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json().catch(function(){ return { ok: false, pesan: 'Respons server tidak valid.' }; }); })
      .catch(function(){ return { ok: false, pesan: 'Tidak dapat terhubung ke server.' }; })
      .then(function(d){
        busy = false;
        if (d.ok) { CFG.data = d.data; showDetail(d.data); }
        else { showList(); var e = $('err'); if (e) e.textContent = d.pesan || 'Gagal membuat transaksi.'; }
      });
  }

  /* ---------- detail: QRIS / VA / link + referensi + simulator ---------- */
  function showDetail(D){
    var m = find(D.kode) || [D.kode, 'Duitku', 'va', D.kode, '#334'];
    var body = '';
    if (D.qrString) {
      body += '<div class="box"><div class="lbl">Pindai QRIS ini</div><div class="qrsvg" id="qr"></div><p class="note" style="margin-top:10px">Total ' + rp(D.amount || CFG.total) + '</p></div>';
    } else if (D.vaNumber) {
      body += '<div class="box"><div class="lbl">Nomor Virtual Account</div><div class="big" id="vaN">' + esc(D.vaNumber.replace(/(\d{4})(?=\d)/g, '$1 ')) + '</div><button type="button" class="copy" id="cpVa">Salin nomor</button></div>';
    } else if (D.paymentUrl) {
      body += '<div class="box"><div class="lbl">Selesaikan di halaman pembayaran ' + esc(m[1]) + '</div><a class="btn-link" href="' + esc(D.paymentUrl) + '" target="_blank" rel="noopener">Buka halaman pembayaran</a></div>';
    }
    body += '<div class="refbox"><span class="lbl">Kode referensi Duitku</span><span class="big" id="refN">' + esc(D.reference) + '</span><button type="button" class="copy" id="cpRef" style="justify-self:center;margin:0">Salin kode referensi</button></div>';
    body += '<div class="sim"><b>Bayar lewat simulator Duitku (sandbox)</b><ol><li>Buka simulator di bawah.</li><li>Tempel ' + (D.vaNumber && !D.qrString ? 'nomor VA' : 'kode referensi') + ' di atas pada isian yang tersedia, lalu jalankan pembayaran sukses.</li><li>Kembali ke halaman ini. Status berubah otomatis setelah Duitku mengonfirmasi.</li></ol>'
      + '<a class="btn-link" href="' + esc(CFG.simulator) + '" target="_blank" rel="noopener">Buka simulator pembayaran Duitku</a></div>';
    body += '<div class="waitrow" role="status"><i></i><span id="wtxt">Menunggu pembayaran dari Duitku…</span><button type="button" id="cekNow">Cek status</button></div>';
    main.innerHTML = '<div class="view detail"><div class="hd"><span class="lg" style="background:' + m[4] + '">' + esc(m[3]) + '</span><b>' + esc(m[1]) + '</b></div>'
      + '<p class="lockn">Metode terkunci untuk pesanan ini.</p>' + body + '<div class="err" id="err"></div></div>';
    var cv = $('cpVa'); if (cv) cv.addEventListener('click', function(){ salin(cv, D.vaNumber, 'Salin nomor'); });
    var cr = $('cpRef'); cr.addEventListener('click', function(){ salin(cr, D.reference, 'Salin kode referensi'); });
    $('cekNow').addEventListener('click', function(){ cek(true); });
    if (D.qrString) tampilQR(D.qrString);
    mulaiPoll();
  }

  function tampilQR(teks){
    var el = $('qr');
    function gambar(){
      try { var q = qrcode(0, 'M'); q.addData(teks); q.make(); el.innerHTML = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true }); }
      catch (e) { el.innerHTML = '<p class="note" style="padding:10px;word-break:break-all">' + esc(teks) + '</p>'; }
    }
    if (window.qrcode) return gambar();
    var s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js';
    s.onload = gambar;
    s.onerror = function(){ el.innerHTML = '<p class="note" style="padding:10px;word-break:break-all">Pustaka QR gagal dimuat (cek internet). Kode QRIS:<br>' + esc(teks) + '</p>'; };
    document.head.appendChild(s);
  }

  /* ---------- cek status ke Duitku ---------- */
  function mulaiPoll(){ if (pollT) clearInterval(pollT); pollT = setInterval(function(){ cek(false); }, 4000); }
  function cek(manual){
    if (selesai) return;
    var t = $('wtxt'); if (manual && t) t.textContent = 'Memeriksa ke Duitku…';
    fetch('duitku_sandbox.php?cek=1&order=' + CFG.order, { cache: 'no-store', credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (j.status === 'dibayar') { selesai = true; clearInterval(pollT); terdeteksi(); }
        else if (j.status && j.status !== 'menunggu') { selesai = true; clearInterval(pollT); $('stEnd').classList.add('on'); }
        else if (manual && t) t.textContent = 'Belum ada pembayaran. Menunggu dari Duitku…';
      }).catch(function(){ if (manual && t) t.textContent = 'Gagal memeriksa, mencoba lagi…'; });
  }

  /* ---------- pembayaran terkonfirmasi Duitku: animasi lalu kembali ---------- */
  function terdeteksi(){
    var P = $('stProses'); P.classList.add('on');
    var lis = $('steps').children, i = 0;
    for (var j = 0; j < lis.length; j++) lis[j].className = '';
    lis[0].className = 'now';
    var tm = setInterval(function(){
      if (i < lis.length) { lis[i].className = 'done'; i++; if (i < lis.length) lis[i].className = 'now'; }
      if (i >= lis.length) { clearInterval(tm); setTimeout(sukses, 300); }
    }, 650);
  }
  function sukses(){
    var m = CFG.data ? find(CFG.data.kode) : null;
    $('stProses').classList.remove('on');
    $('okInfo').textContent = rp(CFG.total) + ' dibayar via ' + (m ? m[1] : 'Duitku') + '.';
    $('stOk').classList.add('on');
    confetti();
    setTimeout(function(){ location.href = CFG.back; }, 4200);
  }

  function confetti(){
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var cv = $('conf'), c = cv.getContext('2d'), W = cv.width = innerWidth, H = cv.height = innerHeight;
    var col = ['#0b5ed7', '#12a36b', '#ffb84d', '#ee4d2d', '#8b5cf6'], P = [];
    for (var i = 0; i < 90; i++) P.push({ x: W / 2 + (Math.random() - .5) * 200, y: H * .45, vx: (Math.random() - .5) * 11, vy: -Math.random() * 12 - 3, r: Math.random() * 6 + 3, a: Math.random() * 6, c: col[i % 5] });
    var t0 = performance.now();
    (function f(t){
      c.clearRect(0, 0, W, H);
      P.forEach(function(p){ p.vy += .28; p.x += p.vx; p.y += p.vy; p.a += .15; c.save(); c.translate(p.x, p.y); c.rotate(p.a); c.fillStyle = p.c; c.fillRect(-p.r, -p.r / 2, p.r * 2, p.r); c.restore(); });
      if (t - t0 < 2600) requestAnimationFrame(f); else c.clearRect(0, 0, W, H);
    })(t0);
  }

  /* ---------- hitung mundur ---------- */
  var tt = $('tt'), tm2 = $('timer'), n = CFG.sisa;
  function tick(){
    if (!tt) return;
    var m = Math.floor(n / 60), s = n % 60;
    tt.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    if (n <= 60) tm2.classList.add('low');
  }
  if (tt) {
    tick();
    setInterval(function(){
      if (n > 0) { n--; tick(); if (n === 0 && !selesai) { clearInterval(pollT); $('stEnd').classList.add('on'); } }
    }, 1000);
  }

  /* ---------- awal ---------- */
  if (CFG.lunas) {
    $('okInfo').textContent = 'Pesanan ini sudah lunas.';
    $('stOk').classList.add('on');
    setTimeout(function(){ location.href = CFG.back; }, 3500);
  } else if (CFG.nonaktif) {
    $('stEnd').classList.add('on');
  } else if (CFG.data) {
    showDetail(CFG.data);
  } else {
    showList();
  }
})();
</script>
</body>
</html>
