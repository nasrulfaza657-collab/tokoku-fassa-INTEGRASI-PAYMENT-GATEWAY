# Toko Fassa (`tokoku_native`)

Aplikasi **kasir toko + toko online** berbasis PHP native dan MySQL/MariaDB. Satu aplikasi untuk panel admin/kasir (POS), katalog toko online untuk pelanggan, laporan penjualan, dan pembayaran online lewat **Duitku**.

> Dibuat untuk berjalan di XAMPP (localhost). Tanpa framework dan tanpa Composer.

## Fitur

**Kasir dan admin**
- Katalog produk, keranjang POS, scan barcode produk dan kartu RFID (scanner USB mode keyboard)
- Member dengan diskon, kartu member cetak
- Struk thermal 58/80 mm dengan barcode invoice, serta invoice A4 yang bisa disimpan sebagai PDF
- Laporan berperiode (ringkasan, penjualan, produk, pelanggan, kasir) dengan unduhan PDF dan Excel
- Pengaturan toko, metode pembayaran, rekening bank, aturan transaksi, dan akun staf
- Peringatan stok menipis di katalog dan dashboard
- Menu pengujian database: View, JOIN, Subquery, Stored Procedure, Function, Trigger, COMMIT/ROLLBACK

**Toko online (`store.php`)**
- Pelanggan mendaftar dan login sendiri
- Pembayaran: QRIS toko (unggah bukti), transfer bank, dan **Duitku** (QRIS, virtual account, e-wallet, kartu)
- Status lunas otomatis lewat callback dan cek status ke Duitku
- Pesanan yang belum dibayar kedaluwarsa otomatis dan stok dikembalikan

**Halaman checkout sandbox Duitku (`duitku_sandbox.php`)**
- Hanya aktif saat mode Duitku = Sandbox
- Membuat transaksi sungguhan di sandbox Duitku, lalu menampilkan QRIS asli, nomor VA, dan kode referensi
- Pembayaran dilakukan lewat simulator Duitku. Lunas hanya ditandai setelah Duitku mengonfirmasi
- Animasi untuk pergantian layar, pemindaian QR, dan konfirmasi berhasil

## Kebutuhan

| Komponen | Versi / catatan |
|---|---|
| PHP | **8.1 atau lebih baru** |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `zip` (laporan Excel), `openssl`, dan `curl` (disarankan) |
| Database | MySQL / MariaDB |
| Web server | Apache (XAMPP) |
| Internet | Diperlukan untuk Duitku, Chart.js, html2pdf, pustaka QR, dan Google Fonts (dimuat dari CDN) |

## Instalasi

1. Salin folder `tokoku_native` ke `C:\xampp\htdocs\`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP.
3. Buat database `db_tokoku2` di phpMyAdmin (`http://localhost/phpmyadmin`), lalu impor file SQL Anda.
   - Repositori ini **tidak menyertakan dump SQL**. Siapkan sendiri file `db_tokoku2.sql`.
   - Tabel tambahan (`tbl_pengaturan`, `tbl_member`, `tbl_detail_transaksi`, tabel akun pelanggan, kolom Duitku, dan lainnya) dibuat otomatis saat aplikasi dibuka.
4. Periksa koneksi di `config.php`. Bawaan XAMPP: host `localhost`, user `root`, password kosong.
5. Buka:
   - Panel admin/kasir: `http://localhost/tokoku_native/index.php`
   - Toko online: `http://localhost/tokoku_native/store.php`

### Akun awal

| Role | Username | Password |
|---|---|---|
| Admin | `adminbaru` | `Admin123!` |
| Kasir | `kasir` | `Kasir123!` |

**Ganti password segera** lewat Pengaturan > Akun Saya. Jangan memakai akun awal ini di server yang bisa diakses publik.

## Pembayaran Duitku

### Menyiapkan
1. Daftar di Duitku dan buat project di dashboard **sandbox** untuk mendapat *Merchant code* dan *API key*.
2. Login admin, buka **Pengaturan > Transaksi & Member**, panel **Payment gateway Duitku**.
3. Nyalakan saklar, pilih mode **Sandbox**, isi Merchant code dan API key, lalu simpan.
4. Untuk menerima callback, isi **URL publik aplikasi** dengan alamat yang bisa dibuka dari internet. Di localhost callback tidak akan sampai, tetapi cek status otomatis tetap berjalan.

### Mencoba transaksi sandbox
1. Login di toko online, isi keranjang dengan total **minimal Rp 10.000**.
2. Pilih metode **Duitku**, lalu klik **Buat pesanan & lanjut bayar**.
3. Di halaman sandbox, pilih metode. QRIS asli, nomor VA, dan kode referensi akan tampil.
4. Buka simulator Duitku, tempel kode referensi (atau nomor VA), lalu jalankan pembayaran sukses:
   `https://sandbox.duitku.com/payment/demo/demosuccesstransaction.aspx`
5. Halaman memeriksa status tiap 4 detik. Setelah Duitku mengonfirmasi, pesanan menjadi lunas.

Panduan langkah demi langkah ada di [`docs/Panduan_Transaksi_Duitku_Sandbox.pdf`](docs/Panduan_Transaksi_Duitku_Sandbox.pdf).

### Catatan
- Simulator Duitku dikelola Duitku. Menurut dokumentasinya, simulator itu terutama untuk Virtual Account dan Gudang Voucher. Jika QRIS tertentu tidak bisa dibayar di sana, coba Virtual Account.
- Setelah metode dipilih, metode itu terkunci untuk pesanan tersebut (Duitku menolak `merchantOrderId` yang sama dipakai dua kali).
- Mode **Production** tidak memakai halaman sandbox. Checkout langsung diarahkan ke halaman pembayaran Duitku asli.

## Struktur folder

```
tokoku_native/
├── index.php            # panel admin/kasir: login, menu, semua aksi form
├── store.php            # toko online untuk pelanggan
├── config.php           # koneksi DB, helper pengaturan, metode pembayaran
├── duitku_lib.php       # integrasi Duitku (invoice, inquiry, cek status, tandai lunas)
├── duitku_callback.php  # endpoint notifikasi pembayaran Duitku
├── duitku_sandbox.php   # halaman checkout sandbox Duitku
├── invoice.php          # invoice A4 (cetak / simpan PDF)
├── struk.php            # struk thermal
├── kartu_member.php     # kartu member
├── barcode.php          # label barcode produk dan kartu staf
├── laporan_view.php     # tampilan laporan
├── laporan_lib.php      # query laporan dan pembuat file .xlsx
├── assets/              # style.css, barcode.js, qris.png (opsional), uploads/
├── docs/                # panduan PDF
├── DOKUMENTASI.md       # dokumentasi lengkap fitur, database, dan riwayat perbaikan
└── README.md
```

Dokumentasi lengkap (tabel database, hak akses, laporan, scanner, member, riwayat perbaikan) ada di [`DOKUMENTASI.md`](DOKUMENTASI.md).

## Keamanan

- Gunakan di localhost atau jaringan tepercaya. Sebelum dipublikasikan, tambahkan HTTPS, pembatasan percobaan login, dan CSRF pada form selain Pengaturan.
- **Jangan commit kredensial.** Simpan password database di luar repositori, dan pertimbangkan menambahkan `config.php` serta `assets/uploads/` ke `.gitignore`.
- API key Duitku disimpan di tabel `tbl_pengaturan` pada database Anda, bukan di berkas kode.
- Folder `assets/uploads/` memblokir eksekusi PHP lewat `.htaccess`.
- Callback Duitku diverifikasi dengan signature dan nominal pesanan dicocokkan sebelum ditandai lunas.

## Lisensi

Belum ditentukan. Tambahkan berkas `LICENSE` sebelum repositori dipublikasikan.
