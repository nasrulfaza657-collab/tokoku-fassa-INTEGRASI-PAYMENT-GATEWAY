<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/duitku_lib.php';

/**
 * Invoice A4 yang bisa dicetak / disimpan sebagai PDF lewat dialog cetak browser.
 * Akses: staf (admin/kasir) untuk semua transaksi, pelanggan toko online hanya untuk pesanannya sendiri.
 * Struk thermal untuk kasir tetap di struk.php.
 */
if (empty($_SESSION['auth']['id'])) {
    http_response_code(403);
    exit('Silakan login terlebih dahulu.');
}
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function rp($v): string { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }

$role  = (string)($_SESSION['auth']['role'] ?? '');
$idUser = (int)$_SESSION['auth']['id'];
$id    = (int)($_GET['id'] ?? 0);
$isStaf = in_array($role, ['admin', 'kasir'], true);

$sql = 'SELECT t.*, p.nama_pelanggan, p.kota, u.nama AS nama_kasir FROM tbl_transaksi t
        LEFT JOIN tbl_pelanggan p ON p.id_pelanggan=t.id_pelanggan
        LEFT JOIN tbl_user u ON u.id_user=t.id_user
        WHERE t.id_transaksi=?';
$args = [$id];
if (!$isStaf) {
    if ($role !== 'user') { http_response_code(403); exit('Akses ditolak.'); }
    $sql .= ' AND EXISTS (SELECT 1 FROM tbl_akun_pelanggan a WHERE a.id_pelanggan=t.id_pelanggan AND a.id_user=?)';
    $args[] = $idUser;
}
$st = $pdo->prepare($sql);
$st->execute($args);
$o = $st->fetch();

$items = [];
if ($o) {
    $st = $pdo->prepare('SELECT d.jumlah, d.harga_satuan, d.subtotal, pr.nama_produk FROM tbl_detail_transaksi d JOIN tbl_produk pr ON pr.id_produk=d.id_produk WHERE d.id_transaksi=? ORDER BY d.id_detail');
    $st->execute([$id]);
    $items = $st->fetchAll();
}

$status = $o ? (string)$o['status_pembayaran'] : '';
$lunas  = $status === 'dibayar';
$label  = ['dibayar' => 'LUNAS', 'menunggu' => 'MENUNGGU PEMBAYARAN', 'dibatalkan' => 'DIBATALKAN', 'kedaluwarsa' => 'KEDALUWARSA'][$status] ?? strtoupper($status);
$kode   = $o ? ($o['kode_invoice'] ?: ('TRX' . $o['id_transaksi'])) : '';
$metode = '';
if ($o) {
    $metode = $PAYMENT_METHODS[$o['metode_pembayaran'] ?? '']['label'] ?? ($o['metode_pembayaran'] ? ucfirst((string)$o['metode_pembayaran']) : 'Manual');
    $kanal = !empty($o['duitku_kode_bayar']) ? duitku_nama_kanal((string)$o['duitku_kode_bayar']) : '';
    if ($kanal !== '') $metode .= ' · ' . $kanal;
}
$subtotal = $o ? (float)$o['total_belanja'] + (float)($o['diskon_member'] ?? 0) : 0;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice <?= e($kode ?: '#' . $id) ?></title>
<style>
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #e5e7eb; color: #111827; font: 14px/1.5 "Segoe UI", Arial, sans-serif; }
  .tools { max-width: 210mm; margin: 14px auto 0; display: flex; gap: 8px; padding: 0 10px; }
  .tools button, .tools a { padding: 10px 16px; border: 0; border-radius: 8px; background: #5634d6; color: #fff; font: 600 14px sans-serif; text-decoration: none; cursor: pointer; }
  .tools a { background: #475569; }
  .sheet { position: relative; max-width: 210mm; min-height: 150mm; margin: 12px auto 30px; background: #fff; padding: 16mm 15mm; box-shadow: 0 2px 14px rgba(0,0,0,.12); overflow: hidden; }
  .head { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #111827; padding-bottom: 12px; }
  .brand { display: flex; gap: 12px; align-items: center; }
  .brand img { width: 56px; height: 56px; object-fit: contain; }
  .brand h1 { margin: 0; font-size: 22px; }
  .brand p { margin: 2px 0 0; color: #475569; font-size: 12.5px; }
  .doc { text-align: right; }
  .doc h2 { margin: 0; font-size: 26px; letter-spacing: 2px; }
  .doc .no { font-weight: 700; }
  .pill { display: inline-block; margin-top: 6px; padding: 3px 12px; border: 2px solid #111827; border-radius: 6px; font-weight: 800; letter-spacing: 1px; font-size: 13px; }
  .pill.ok { color: #067647; border-color: #067647; }
  .pill.bad { color: #b42318; border-color: #b42318; }
  .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 16px 0; }
  .meta h4 { margin: 0 0 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
  .meta p { margin: 0; }
  table { width: 100%; border-collapse: collapse; margin-top: 4px; }
  th { background: #111827; color: #fff; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; text-align: left; padding: 8px 10px; }
  td { padding: 9px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
  th.r, td.r { text-align: right; }
  .sum { margin: 12px 0 0 auto; width: 280px; }
  .sum .row { display: flex; justify-content: space-between; padding: 4px 0; }
  .sum .tot { border-top: 2px solid #111827; margin-top: 4px; padding-top: 8px; font-size: 18px; font-weight: 800; }
  .bcbox { margin-top: 18px; width: 60mm; }
  .bcbox svg { width: 100%; height: 14mm; display: block; }
  .bcbox .num { font-size: 11px; letter-spacing: 1px; }
  .foot { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #94a3b8; text-align: center; color: #475569; font-size: 12.5px; }
  .wm { position: absolute; top: 46%; left: 50%; transform: translate(-50%,-50%) rotate(-24deg); font-size: 64px; font-weight: 900; color: rgba(180,35,24,.10); border: 8px solid rgba(180,35,24,.10); padding: 4px 24px; border-radius: 12px; pointer-events: none; white-space: nowrap; }
  @keyframes stamp { 0% { transform: scale(2.4) rotate(-8deg); opacity: 0; } 60% { transform: scale(.94) rotate(-3deg); opacity: 1; } 100% { transform: scale(1) rotate(-3deg); } }
  @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
  .sheet { animation: rise .5s both; }
  .pill.ok { animation: stamp .6s .25s both; }
  @media (prefers-reduced-motion: reduce) { .sheet, .pill.ok { animation: none; } }
  @media print {
    .sheet, .pill.ok { animation: none !important; }
    body { background: #fff; }
    .tools { display: none !important; }
    .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; min-height: 0; }
    th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
<div class="tools">
  <button onclick="window.print()">🖨 Cetak / Simpan PDF</button>
  <?php if ($isStaf && $o && $lunas): ?><a href="struk.php?id=<?= (int)$o['id_transaksi'] ?>&print=1" target="_blank">Struk thermal</a><?php endif ?>
  <a href="<?= $isStaf ? 'index.php?page=bayar&id=' . $id : 'store.php?order=' . $id ?>">← Kembali</a>
</div>
<div class="sheet">
<?php if (!$o): ?>
  <h2>Invoice tidak ditemukan</h2>
<?php else: ?>
  <?php if (!$lunas): ?><div class="wm"><?= $status === 'menunggu' ? 'BELUM LUNAS' : e($label) ?></div><?php endif ?>
  <div class="head">
    <div class="brand">
      <?php if ($LOGO_TOKO): ?><img src="<?= e($LOGO_TOKO) ?>" alt=""><?php endif ?>
      <div>
        <h1><?= e($NAMA_TOKO) ?></h1>
        <?php if ($ALAMAT_TOKO !== ''): ?><p><?= e($ALAMAT_TOKO) ?></p><?php endif ?>
        <?php if ($TELEPON_TOKO !== ''): ?><p>Telp <?= e($TELEPON_TOKO) ?></p><?php endif ?>
      </div>
    </div>
    <div class="doc">
      <h2>INVOICE</h2>
      <div class="no"><?= e($kode) ?></div>
      <span class="pill <?= $lunas ? 'ok' : ($status === 'menunggu' ? '' : 'bad') ?>"><?= e($label) ?></span>
    </div>
  </div>

  <div class="meta">
    <div>
      <h4>Ditagihkan kepada</h4>
      <p><b><?= e($o['nama_pelanggan'] ?: 'Pelanggan Umum') ?></b><?= !empty($o['id_member']) ? ' (Member)' : '' ?></p>
      <?php if (!empty($o['kota'])): ?><p><?= e($o['kota']) ?></p><?php endif ?>
    </div>
    <div>
      <h4>Rincian</h4>
      <p>Tanggal: <b><?= e($o['tanggal']) ?></b></p>
      <p>Metode bayar: <b><?= e($metode) ?></b></p>
      <?php if ($lunas && $o['dibayar_pada']): ?><p>Dibayar pada: <b><?= e($o['dibayar_pada']) ?></b></p><?php endif ?>
      <?php if (!$lunas && $status === 'menunggu' && $o['batas_bayar']): ?><p>Batas bayar: <b><?= e($o['batas_bayar']) ?></b></p><?php endif ?>
      <?php if (!empty($o['duitku_reference'])): ?><p>Ref. Duitku: <b><?= e($o['duitku_reference']) ?></b></p><?php endif ?>
      <?php if (!empty($o['nama_kasir'])): ?><p>Kasir: <b><?= e($o['nama_kasir']) ?></b></p><?php endif ?>
    </div>
  </div>

  <table>
    <thead><tr><th style="width:36px">No</th><th>Produk</th><th class="r">Harga</th><th class="r" style="width:50px">Qty</th><th class="r">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
      <tr><td><?= $i + 1 ?></td><td><?= e($it['nama_produk']) ?></td><td class="r"><?= rp($it['harga_satuan']) ?></td><td class="r"><?= (int)$it['jumlah'] ?></td><td class="r"><?= rp($it['subtotal']) ?></td></tr>
    <?php endforeach ?>
    <?php if (!$items): ?><tr><td colspan="5" style="text-align:center;color:#64748b">Transaksi manual (tanpa rincian produk)</td></tr><?php endif ?>
    </tbody>
  </table>

  <div class="sum">
    <?php if ((float)($o['diskon_member'] ?? 0) > 0): ?>
    <div class="row"><span>Subtotal</span><span><?= rp($subtotal) ?></span></div>
    <div class="row"><span>Diskon member <?= (float)$o['diskon_member_persen'] ?>%</span><span>-<?= rp($o['diskon_member']) ?></span></div>
    <?php endif ?>
    <div class="row tot"><span>TOTAL</span><span><?= rp($o['total_belanja']) ?></span></div>
  </div>

  <div class="bcbox"><svg class="bc" data-code="<?= e($kode) ?>"></svg><div class="num"><?= e($kode) ?></div></div>

  <div class="foot"><?= e($FOOTER_STRUK !== '' ? $FOOTER_STRUK : 'Terima kasih sudah berbelanja!') ?><br>Dokumen ini dibuat otomatis oleh sistem <?= e($NAMA_TOKO) ?>.</div>
<?php endif ?>
</div>
<script src="assets/barcode.js"></script>
<?php if (isset($_GET['print']) && $o): ?><script>window.addEventListener('load',function(){setTimeout(function(){window.print()},300)})</script><?php endif ?>
</body>
</html>
