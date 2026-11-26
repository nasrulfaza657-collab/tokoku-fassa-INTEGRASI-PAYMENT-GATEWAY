-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2026 at 05:41 AM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_tokoku2`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CariPelanggan` (IN `p_id` INT)   BEGIN
    SELECT *
    FROM tbl_pelanggan
    WHERE id_pelanggan = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `HitungPelanggan` (OUT `jumlah` INT)   BEGIN
    SELECT COUNT(*)
    INTO jumlah
    FROM tbl_pelanggan;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `LaporanTransaksi` (IN `p_id_pelanggan` INT)   BEGIN
    SELECT 
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan,
        p.kota,
        t.total_belanja
    FROM tbl_transaksi t
    JOIN tbl_pelanggan p 
    ON t.id_pelanggan = p.id_pelanggan
    WHERE t.id_pelanggan = p_id_pelanggan;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `TambahPelanggan` (IN `p_nama` VARCHAR(100), IN `p_kota` VARCHAR(50))   BEGIN
    INSERT INTO tbl_pelanggan (nama_pelanggan, kota)
    VALUES (p_nama, p_kota);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `TampilPelanggan` ()   BEGIN
    SELECT * FROM tbl_pelanggan;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `HitungDiskon` (`harga` DECIMAL(12,2), `diskon` DECIMAL(5,2)) RETURNS DECIMAL(12,2) DETERMINISTIC NO SQL BEGIN
    RETURN harga * (diskon / 100);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `HitungPajak` (`harga` DECIMAL(12,2)) RETURNS DECIMAL(12,2) DETERMINISTIC NO SQL BEGIN
    RETURN harga * 0.11;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `HitungTotal` (`harga` DECIMAL(12,2), `jumlah` INT) RETURNS DECIMAL(12,2) DETERMINISTIC NO SQL BEGIN
    RETURN harga * jumlah;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `KategoriBelanja` (`total` DECIMAL(12,2)) RETURNS VARCHAR(30) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC NO SQL BEGIN
    RETURN CASE
        WHEN total >= 1000000 THEN 'Pelanggan Gold'
        WHEN total >= 500000 THEN 'Pelanggan Silver'
        ELSE 'Pelanggan Reguler'
    END;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `KonversiNilai` (`nilai` INT) RETURNS VARCHAR(5) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC NO SQL BEGIN
    RETURN CASE
        WHEN nilai >= 85 THEN 'A'
        WHEN nilai >= 75 THEN 'B'
        WHEN nilai >= 65 THEN 'C'
        ELSE 'D'
    END;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `log_pelanggan`
--

CREATE TABLE `log_pelanggan` (
  `id_log` int(11) NOT NULL,
  `aktivitas` varchar(50) DEFAULT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_pelanggan`
--

INSERT INTO `log_pelanggan` (`id_log`, `aktivitas`, `id_pelanggan`, `waktu`) VALUES
(1, 'INSERT PELANGGAN', 6, '2026-11-09 04:11:20'),
(2, 'INSERT PELANGGAN', 7, '2026-11-11 00:56:22'),
(3, 'INSERT PELANGGAN', 8, '2026-11-23 02:18:54'),
(4, 'INSERT PELANGGAN', 9, '2026-11-23 02:35:58'),
(5, 'INSERT PELANGGAN', 10, '2026-11-23 02:37:51'),
(6, 'INSERT PELANGGAN', 11, '2026-11-23 02:38:59'),
(7, 'INSERT PELANGGAN', 12, '2026-11-23 03:04:31'),
(8, 'INSERT PELANGGAN', 13, '2026-11-23 03:05:30'),
(9, 'INSERT PELANGGAN', 14, '2026-11-23 03:49:43'),
(10, 'INSERT PELANGGAN', 15, '2026-11-23 03:52:37'),
(11, 'INSERT PELANGGAN', 16, '2026-11-23 03:54:24'),
(12, 'INSERT PELANGGAN', 17, '2026-11-23 04:01:24'),
(13, 'INSERT PELANGGAN', 18, '2026-11-23 04:04:16'),
(14, 'INSERT PELANGGAN', 19, '2026-11-23 04:16:49'),
(15, 'INSERT PELANGGAN', 20, '2026-11-23 05:48:08'),
(16, 'INSERT PELANGGAN', 21, '2026-11-23 05:50:51'),
(17, 'INSERT PELANGGAN', 22, '2026-11-23 05:54:19'),
(18, 'INSERT PELANGGAN', 23, '2026-11-25 06:01:45'),
(19, 'INSERT PELANGGAN', 24, '2026-11-26 01:48:11');

-- --------------------------------------------------------

--
-- Table structure for table `log_perubahan_pelanggan`
--

CREATE TABLE `log_perubahan_pelanggan` (
  `id_log` int(11) NOT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `nama_lama` varchar(100) DEFAULT NULL,
  `nama_baru` varchar(100) DEFAULT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_perubahan_pelanggan`
--

INSERT INTO `log_perubahan_pelanggan` (`id_log`, `id_pelanggan`, `nama_lama`, `nama_baru`, `waktu`) VALUES
(1, 4, 'Dewi', 'Rina Wijaya', '2026-11-09 04:12:31');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_akun_pelanggan`
--

CREATE TABLE `tbl_akun_pelanggan` (
  `id_user` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_akun_pelanggan`
--

INSERT INTO `tbl_akun_pelanggan` (`id_user`, `id_pelanggan`, `dibuat_pada`) VALUES
(6, 23, '2026-11-25 06:01:45'),
(7, 24, '2026-11-26 01:48:11');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_detail_transaksi`
--

CREATE TABLE `tbl_detail_transaksi` (
  `id_detail` int(11) NOT NULL,
  `id_transaksi` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_detail_transaksi`
--

INSERT INTO `tbl_detail_transaksi` (`id_detail`, `id_transaksi`, `id_produk`, `jumlah`, `harga_satuan`, `subtotal`) VALUES
(1, 5, 4, 1, 4000.00, 4000.00),
(2, 6, 5, 1, 3500.00, 3500.00),
(3, 7, 5, 2, 3500.00, 7000.00),
(4, 8, 4, 3, 4000.00, 12000.00),
(5, 9, 3, 1, 17000.00, 17000.00),
(6, 9, 4, 1, 4000.00, 4000.00),
(7, 10, 5, 1, 3500.00, 3500.00),
(8, 11, 5, 1, 3500.00, 3500.00),
(9, 12, 5, 1, 3500.00, 3500.00),
(10, 13, 4, 1, 4000.00, 4000.00),
(11, 14, 5, 1, 3500.00, 3500.00),
(12, 15, 5, 1, 3500.00, 3500.00),
(13, 16, 5, 1, 3500.00, 3500.00),
(14, 17, 4, 1, 4000.00, 4000.00),
(15, 18, 5, 5, 3500.00, 17500.00),
(16, 19, 5, 2, 3500.00, 7000.00),
(17, 20, 5, 1, 3500.00, 3500.00),
(18, 21, 3, 2, 17000.00, 34000.00),
(19, 22, 4, 1, 4000.00, 4000.00),
(20, 23, 4, 1, 4000.00, 4000.00),
(21, 24, 4, 7, 4000.00, 28000.00),
(22, 25, 4, 1, 4000.00, 4000.00),
(23, 27, 5, 1, 3500.00, 3500.00),
(24, 28, 5, 1, 3500.00, 3500.00),
(25, 29, 4, 2, 4000.00, 8000.00),
(26, 29, 5, 2, 3500.00, 7000.00),
(27, 30, 4, 1, 4000.00, 4000.00),
(28, 30, 5, 1, 3500.00, 3500.00),
(29, 31, 5, 1, 3500.00, 3500.00),
(30, 32, 2, 1, 18000.00, 18000.00),
(31, 32, 4, 1, 4000.00, 4000.00),
(32, 33, 5, 1, 3500.00, 3500.00),
(33, 34, 5, 1, 3500.00, 3500.00),
(34, 35, 4, 1, 4000.00, 4000.00),
(35, 35, 5, 1, 3500.00, 3500.00),
(36, 36, 5, 1, 3500.00, 3500.00),
(37, 37, 5, 1, 3500.00, 3500.00),
(38, 38, 5, 1, 3500.00, 3500.00),
(39, 39, 5, 1, 3500.00, 3500.00),
(40, 40, 5, 1, 3500.00, 3500.00),
(41, 41, 5, 1, 3500.00, 3500.00),
(42, 42, 4, 1, 4000.00, 4000.00),
(43, 43, 4, 1, 4000.00, 4000.00),
(44, 44, 4, 1, 4000.00, 4000.00),
(45, 45, 4, 1, 4000.00, 4000.00),
(46, 46, 3, 1, 17000.00, 17000.00),
(47, 47, 2, 1, 18000.00, 18000.00),
(48, 47, 3, 1, 17000.00, 17000.00),
(49, 48, 4, 1, 4000.00, 4000.00),
(50, 49, 2, 1, 18000.00, 18000.00),
(51, 50, 2, 1, 18000.00, 18000.00),
(52, 51, 3, 1, 17000.00, 17000.00),
(53, 52, 4, 1, 4000.00, 4000.00),
(54, 53, 5, 1, 3500.00, 3500.00),
(55, 54, 5, 1, 3500.00, 3500.00),
(56, 55, 5, 1, 3500.00, 3500.00),
(57, 56, 5, 1, 3500.00, 3500.00),
(58, 57, 4, 1, 4000.00, 4000.00),
(59, 58, 1, 1, 78000.00, 78000.00),
(60, 58, 2, 1, 18000.00, 18000.00),
(61, 59, 5, 1, 3500.00, 3500.00),
(62, 60, 5, 1, 3500.00, 3500.00),
(63, 61, 5, 1, 3500.00, 3500.00),
(64, 62, 5, 1, 3500.00, 3500.00),
(65, 63, 5, 1, 3500.00, 3500.00);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_member`
--

CREATE TABLE `tbl_member` (
  `id_member` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `kode_member` varchar(32) DEFAULT NULL,
  `kode_rfid` char(64) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `diskon_persen` decimal(5,2) NOT NULL DEFAULT 5.00,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `bergabung_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_member`
--

INSERT INTO `tbl_member` (`id_member`, `id_pelanggan`, `kode_member`, `kode_rfid`, `no_hp`, `diskon_persen`, `aktif`, `bergabung_pada`) VALUES
(1, 8, '300000000001', '1981717ae6abf3f29565eba6997b804a29a89f4067fbd9d230644a389189e3c3', '085746863781', 10.00, 1, '2026-11-23 03:03:18'),
(2, 1, '300000000002', 'c598c262cbb54f55b9994bcdbb016b727c15decc17b10e3757822ab326a96837', '0812918232718', 6.00, 1, '2026-11-23 03:22:54');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pelanggan`
--

CREATE TABLE `tbl_pelanggan` (
  `id_pelanggan` int(11) NOT NULL,
  `nama_pelanggan` varchar(100) DEFAULT NULL,
  `kota` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_pelanggan`
--

INSERT INTO `tbl_pelanggan` (`id_pelanggan`, `nama_pelanggan`, `kota`) VALUES
(1, 'Andi', 'Ponorogo'),
(2, 'Budi', 'Madiun'),
(3, 'Citra', 'Surabaya'),
(4, 'Rina Wijaya', 'Ponorogo'),
(5, 'Dewi', 'Ponorogo'),
(6, 'Rina', 'Ponorogo'),
(7, 'Administrator', ''),
(8, 'faza', ''),
(9, 'Pelanggan Umum', ''),
(12, 'faza', ''),
(13, 'faza', ''),
(23, 'fazanh', 'ponorogo'),
(24, 'fazanh', 'ponorogo');

--
-- Triggers `tbl_pelanggan`
--
DELIMITER $$
CREATE TRIGGER `after_insert_pelanggan` AFTER INSERT ON `tbl_pelanggan` FOR EACH ROW BEGIN
    INSERT INTO log_pelanggan (aktivitas, id_pelanggan)
    VALUES ('INSERT PELANGGAN', NEW.id_pelanggan);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_update_pelanggan` AFTER UPDATE ON `tbl_pelanggan` FOR EACH ROW BEGIN
    INSERT INTO log_perubahan_pelanggan (id_pelanggan, nama_lama, nama_baru)
    VALUES (OLD.id_pelanggan, OLD.nama_pelanggan, NEW.nama_pelanggan);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pengaturan`
--

CREATE TABLE `tbl_pengaturan` (
  `kunci` varchar(60) NOT NULL,
  `nilai` text DEFAULT NULL,
  `diubah_pada` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_pengaturan`
--

INSERT INTO `tbl_pengaturan` (`kunci`, `nilai`, `diubah_pada`) VALUES
('alamat_toko', 'Ponorogo, Jawa Timur', '2026-11-23 03:39:10'),
('catatan_transfer', 'Makasih Dah Beli😊😘', '2026-11-25 06:25:27'),
('duitku_aktif', '1', '2026-11-26 02:02:19'),
('duitku_api_key', '6de12dcc7238e10614171430a19054ca', '2026-11-26 02:02:19'),
('duitku_base_url', '', '2026-11-26 02:02:19'),
('duitku_merchant_code', 'DS38611', '2026-11-26 02:02:19'),
('duitku_mode', 'sandbox', '2026-11-26 02:02:19'),
('duitku_schema', '2', '2026-11-26 03:58:59'),
('footer_struk', 'Terima kasih sudah berbelanja!', '2026-11-23 03:39:10'),
('lebar_struk', '80', '2026-11-23 03:39:10'),
('logo_toko', 'assets/uploads/toko/cc39f4c2b0c957ad.png', '2026-11-23 04:16:04'),
('member_diskon_default', '7', '2026-11-25 06:15:57'),
('metode_aktif', 'tunai,qris,debit', '2026-11-23 03:49:21'),
('nama_bank', 'seabank', '2026-11-25 06:25:27'),
('nama_pemilik_rekening', 'FAZA STORE', '2026-11-25 06:25:27'),
('nama_toko', 'Toko Fassa', '2026-11-23 03:39:10'),
('nomor_rekening', '901330402850', '2026-11-25 06:25:27'),
('payment_timeout_hours', '24', '2026-11-23 03:49:21'),
('qris_gambar', 'assets/uploads/toko/a1af8c460b443b67.jpg', '2026-11-23 03:52:21'),
('stok_menipis', '5', '2026-11-23 03:49:21'),
('struk_barcode', '1', '2026-11-23 04:07:06'),
('telepon_toko', '', '2026-11-23 03:39:10'),
('umum_dirapikan', '1', '2026-11-25 01:27:18');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_produk`
--

CREATE TABLE `tbl_produk` (
  `id_produk` int(11) NOT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `harga` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stok` int(11) NOT NULL DEFAULT 0,
  `deskripsi` text DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `diskon` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gambar` varchar(500) DEFAULT NULL,
  `terjual` int(11) NOT NULL DEFAULT 0,
  `rating` decimal(2,1) NOT NULL DEFAULT 5.0,
  `barcode` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_produk`
--

INSERT INTO `tbl_produk` (`id_produk`, `nama_produk`, `kategori`, `harga`, `stok`, `deskripsi`, `dibuat_pada`, `diskon`, `gambar`, `terjual`, `rating`, `barcode`) VALUES
(1, 'Beras Premium 5 Kg', 'Sembako', 78000.00, 19, 'Beras premium kemasan 5 kilogram', '2026-11-11 00:53:48', 0.00, 'assets/uploads/produk/3c8c779ca653dc8d.png', 1, 5.0, '200000000001'),
(2, 'Minyak Goreng 1 L', 'Sembako', 18000.00, 25, 'Minyak goreng kemasan 1 liter', '2026-11-11 00:53:48', 0.00, 'assets/uploads/produk/e14dc4dcaf1f17b2.jpg', 4, 5.0, '200000000002'),
(3, 'Gula Pasir 1 Kg', 'Sembako', 17000.00, 19, 'Gula pasir kemasan 1 kilogram', '2026-11-11 00:53:48', 0.00, 'assets/uploads/produk/80abe32791c0a0d0.jpg', 6, 5.0, '8992745000174'),
(4, 'Air Mineral 600 ml', 'Minuman', 4000.00, 28, 'Air mineral botol 600 ml', '2026-11-11 00:53:48', 0.00, 'assets/uploads/produk/6ffa832ecdfba322.webp', 18, 5.0, '8996001600269'),
(5, 'Mi Instan', 'Makanan', 3500.00, 30, 'Mi instan aneka rasa', '2026-11-11 00:53:48', 0.00, 'assets/uploads/produk/34c7b901ddfdf117.webp', 24, 5.0, '123456789012');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_transaksi`
--

CREATE TABLE `tbl_transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `total_belanja` decimal(12,2) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `status_pembayaran` enum('menunggu','dibayar','dibatalkan','kedaluwarsa') NOT NULL DEFAULT 'dibayar',
  `metode_pembayaran` varchar(30) DEFAULT NULL,
  `kode_invoice` varchar(30) DEFAULT NULL,
  `batas_bayar` datetime DEFAULT NULL,
  `dibayar_pada` datetime DEFAULT NULL,
  `bukti_bayar` varchar(255) DEFAULT NULL,
  `id_member` int(11) DEFAULT NULL,
  `diskon_member` decimal(12,2) NOT NULL DEFAULT 0.00,
  `diskon_member_persen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `duitku_reference` varchar(60) DEFAULT NULL,
  `duitku_payment_url` varchar(255) DEFAULT NULL,
  `duitku_kode_bayar` varchar(10) DEFAULT NULL,
  `duitku_data` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_transaksi`
--

INSERT INTO `tbl_transaksi` (`id_transaksi`, `tanggal`, `id_pelanggan`, `total_belanja`, `id_user`, `status_pembayaran`, `metode_pembayaran`, `kode_invoice`, `batas_bayar`, `dibayar_pada`, `bukti_bayar`, `id_member`, `diskon_member`, `diskon_member_persen`, `duitku_reference`, `duitku_payment_url`, `duitku_kode_bayar`, `duitku_data`) VALUES
(1, '2026-10-01', 1, 250000.00, NULL, 'dibayar', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(2, '2026-10-02', 2, 350000.00, NULL, 'dibayar', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(3, '2026-10-03', 1, 150000.00, NULL, 'dibayar', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(4, '2026-10-04', 3, 500000.00, NULL, 'dibayar', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(5, '2026-11-11', 7, 4000.00, 1, 'dibayar', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(6, '2026-11-23', 8, 3500.00, 1, 'dibayar', 'qris', 'POS261123091854', '2026-11-24 09:18:54', '2026-11-23 09:18:57', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(7, '2026-11-23', 9, 7000.00, 1, 'dibayar', 'qris', 'POS261123093558', '2026-11-24 09:35:58', '2026-11-23 09:36:03', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(8, '2026-11-23', 9, 12000.00, 1, 'dibayar', 'qris', 'POS261123093751', '2026-11-24 09:37:51', '2026-11-23 09:37:53', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(9, '2026-11-23', 9, 21000.00, 1, 'dibayar', 'debit', 'POS261123093859', NULL, '2026-11-23 09:38:59', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(10, '2026-11-23', 12, 3500.00, 1, 'dibayar', 'tunai', 'POS261123100431', NULL, '2026-11-23 10:04:31', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(11, '2026-11-23', 13, 3500.00, 2, 'dibayar', 'debit', 'POS261123100530', NULL, '2026-11-23 10:05:30', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(12, '2026-11-23', 8, 3150.00, 2, 'dibayar', 'qris', 'POS261123100942', '2026-11-24 10:09:42', '2026-11-23 10:09:47', NULL, 1, 350.00, 10.00, NULL, NULL, NULL, NULL),
(13, '2026-11-23', 1, 3760.00, 1, 'dibayar', 'qris', 'POS261123102308', '2026-11-24 10:23:08', '2026-11-23 10:24:42', NULL, 2, 240.00, 6.00, NULL, NULL, NULL, NULL),
(14, '2026-11-23', 9, 3500.00, 1, 'kedaluwarsa', 'qris', 'POS261123104943', '2026-11-24 10:49:43', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(15, '2026-11-23', 9, 3500.00, 1, 'dibayar', 'qris', 'POS261123105237', '2026-11-24 10:52:37', '2026-11-23 10:53:27', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(16, '2026-11-23', 9, 3500.00, 1, 'dibayar', 'debit', 'POS261123105424', NULL, '2026-11-23 10:54:24', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(17, '2026-11-23', 1, 3760.00, 1, 'dibayar', 'qris', 'POS261123105746', '2026-11-24 10:57:46', '2026-11-23 10:58:04', NULL, 2, 240.00, 6.00, NULL, NULL, NULL, NULL),
(18, '2026-11-23', 9, 17500.00, 1, 'dibayar', 'qris', 'POS261123110124', '2026-11-24 11:01:24', '2026-11-23 11:01:28', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(19, '2026-11-23', 9, 7000.00, 1, 'dibayar', 'qris', 'POS261123110416', '2026-11-24 11:04:16', '2026-11-23 11:04:19', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(20, '2026-11-23', 9, 3500.00, 2, 'dibayar', 'qris', 'POS261123111649', '2026-11-24 11:16:49', '2026-11-23 11:16:52', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(21, '2026-11-23', 9, 34000.00, 1, 'dibayar', 'qris', 'POS261123124808', '2026-11-24 12:48:08', '2026-11-23 12:48:47', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(22, '2026-11-23', 9, 4000.00, 1, 'dibayar', 'qris', 'POS261123125051', '2026-11-24 12:50:51', '2026-11-23 12:54:09', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(23, '2026-11-23', 9, 4000.00, 1, 'dibayar', 'qris', 'POS261123125419', '2026-11-23 12:59:19', '2026-11-23 12:58:09', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(24, '2026-11-25', 9, 28000.00, 1, 'dibayar', 'qris', 'POS26112500024', '2026-11-25 09:56:01', '2026-11-25 09:51:05', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(25, '2026-11-25', 9, 4000.00, 2, 'dibayar', 'qris', 'POS26112500025', '2026-11-25 11:15:14', '2026-11-25 11:10:17', NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(26, '2026-11-25', 12, 10000.00, 1, 'dibayar', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(27, '2026-11-25', 23, 3500.00, NULL, 'dibayar', 'qris', 'WEB26112588C554', '2026-11-26 13:02:10', '2026-11-25 13:02:52', 'assets/uploads/bukti/f3a41c5bddbaf2b4e72b.png', NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(28, '2026-11-25', 23, 3500.00, NULL, 'dibatalkan', 'qris', 'WEB261125037B5B', '2026-11-26 13:03:36', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(29, '2026-11-25', 23, 15000.00, NULL, 'dibatalkan', 'qris', 'WEB261125F8A12D', '2026-11-26 13:04:20', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(30, '2026-11-25', 23, 7500.00, NULL, 'dibatalkan', 'qris', 'WEB2611251EFAF2', '2026-11-26 13:08:09', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(31, '2026-11-25', 23, 3500.00, NULL, 'dibayar', 'qris', 'WEB26112566F9E3', '2026-11-26 13:11:59', '2026-11-25 13:12:17', 'assets/uploads/bukti/8f08f46113e1bad74b04.png', NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(32, '2026-11-25', 23, 22000.00, NULL, 'menunggu', 'qris', 'WEB2611253C7198', '2026-11-26 13:16:26', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(33, '2026-11-25', 23, 3500.00, NULL, 'menunggu', 'transfer', 'WEB26112597B6FD', '2026-11-26 13:25:38', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(34, '2026-11-25', 23, 3500.00, NULL, 'menunggu', 'transfer', 'WEB2611257DA8E8', '2026-11-26 13:28:29', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(35, '2026-11-25', 23, 7500.00, NULL, 'dibayar', 'qris', 'WEB261125E08770', '2026-11-26 13:39:04', '2026-11-25 13:39:23', 'assets/uploads/bukti/ce3dd929661209d2041c.png', NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(36, '2026-11-26', 24, 3500.00, NULL, 'menunggu', 'qris', 'WEB261126A311A7', '2026-11-27 08:48:22', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(37, '2026-11-26', 24, 3500.00, NULL, 'dibatalkan', 'duitku', 'WEB26112688C486', '2026-11-26 10:02:54', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(38, '2026-11-26', 24, 3500.00, NULL, 'dibatalkan', 'duitku', 'WEB2611266E4FE8', '2026-11-26 10:03:00', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(39, '2026-11-26', 24, 3500.00, NULL, 'dibatalkan', 'duitku', 'WEB261126AB2F2B', '2026-11-26 10:10:26', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(40, '2026-11-26', 24, 3500.00, NULL, 'dibatalkan', 'duitku', 'WEB261126D09F3A', '2026-11-26 10:10:29', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(41, '2026-11-26', 24, 3500.00, NULL, 'menunggu', 'qris', 'WEB26112662466E', '2026-11-27 09:10:31', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(42, '2026-11-26', 24, 4000.00, NULL, 'dibatalkan', 'duitku', 'WEB2611268677AC', '2026-11-26 10:10:48', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(43, '2026-11-26', 24, 4000.00, NULL, 'dibatalkan', 'duitku', 'WEB2611267FF556', '2026-11-26 10:11:49', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(44, '2026-11-26', 24, 4000.00, NULL, 'dibatalkan', 'duitku', 'WEB261126C917E9', '2026-11-26 10:29:04', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(45, '2026-11-26', 24, 4000.00, NULL, 'kedaluwarsa', 'duitku', 'WEB2611260CC386', '2026-11-26 10:31:01', NULL, NULL, NULL, 0.00, 0.00, 'DS3861126K5HRSJBAAPD0I15', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126K5HRSJBAAPD0I15', NULL, NULL),
(46, '2026-11-26', 24, 17000.00, NULL, 'dibayar', 'duitku', 'WEB26112624AC75', '2026-11-26 10:32:29', '2026-11-26 09:34:18', NULL, NULL, 0.00, 0.00, 'DS38611264YLKYUNB0ZDNMMB', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS38611264YLKYUNB0ZDNMMB', NULL, NULL),
(47, '2026-11-26', 24, 35000.00, NULL, 'dibayar', 'duitku', 'WEB2611262EC05C', '2026-11-26 10:34:44', '2026-11-26 09:36:19', NULL, NULL, 0.00, 0.00, 'DS3861126KC8TQCWCTT23RJW', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126KC8TQCWCTT23RJW', NULL, NULL),
(48, '2026-11-26', 24, 4000.00, NULL, 'kedaluwarsa', 'duitku', 'WEB2611268EEC3B', '2026-11-26 10:39:47', NULL, NULL, NULL, 0.00, 0.00, 'DS3861126M4F17HPBQ8PELX4', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126M4F17HPBQ8PELX4', NULL, NULL),
(49, '2026-11-26', 24, 18000.00, NULL, 'dibayar', 'duitku', 'WEB261126C2C8B1', '2026-11-26 10:43:31', '2026-11-26 09:44:52', NULL, NULL, 0.00, 0.00, 'DS38611268EDOIJKM0CJPWNV', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS38611268EDOIJKM0CJPWNV', NULL, NULL),
(50, '2026-11-26', 24, 18000.00, NULL, 'dibayar', 'duitku', 'WEB261126A2596D', '2026-11-26 11:17:50', '2026-11-26 10:23:49', NULL, NULL, 0.00, 0.00, 'DS3861126GEWPAWXZ0WX5TY1', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126GEWPAWXZ0WX5TY1', NULL, NULL),
(51, '2026-11-26', 24, 17000.00, NULL, 'dibayar', 'duitku', 'WEB2611267E5820', '2026-11-26 11:28:47', '2026-11-26 10:29:49', NULL, NULL, 0.00, 0.00, 'DS3861126MILZQYDI8LKHF84', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126MILZQYDI8LKHF84', NULL, NULL),
(52, '2026-11-26', 24, 4000.00, NULL, 'kedaluwarsa', 'duitku', 'WEB2611267851D2', '2026-11-26 11:34:08', NULL, NULL, NULL, 0.00, 0.00, 'DS3861126EVSZQR2YSI5EYRW', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126EVSZQR2YSI5EYRW', NULL, NULL),
(53, '2026-11-26', 24, 3500.00, NULL, 'dibayar', 'duitku', 'WEB2611269FBE85', '2026-11-26 11:41:09', '2026-11-26 10:41:51', NULL, NULL, 0.00, 0.00, 'DS38611261HN05ROSGNG86WA', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS38611261HN05ROSGNG86WA', NULL, NULL),
(54, '2026-11-26', 24, 3500.00, NULL, 'menunggu', 'duitku', 'WEB2611268AA586', '2026-11-26 11:42:49', NULL, NULL, NULL, 0.00, 0.00, 'DS3861126OJXPB5KDDPU5XKU', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126OJXPB5KDDPU5XKU', NULL, NULL),
(55, '2026-11-26', 24, 3500.00, NULL, 'dibayar', 'qris', 'WEB261126A28DFF', '2026-11-27 10:43:43', '2026-11-26 10:44:15', 'assets/uploads/bukti/d6fc8e4cb52aa2c1af0d.png', NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(56, '2026-11-26', 24, 3500.00, NULL, 'menunggu', 'duitku', 'WEB261126088718', '2026-11-26 11:46:08', NULL, NULL, NULL, 0.00, 0.00, 'DS3861126360KUBYOP6QBEK0', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126360KUBYOP6QBEK0', NULL, NULL),
(57, '2026-11-26', 24, 4000.00, NULL, 'dibayar', 'duitku', 'WEB261126975951', '2026-11-26 11:48:48', '2026-11-26 10:51:23', NULL, NULL, 0.00, 0.00, 'DS3861126S6X0HF2TAIVQHLE', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126S6X0HF2TAIVQHLE', NULL, NULL),
(58, '2026-11-26', 24, 96000.00, NULL, 'dibayar', 'duitku', 'WEB261126395497', '2026-11-26 11:51:36', '2026-11-26 10:52:33', NULL, NULL, 0.00, 0.00, 'DS3861126B1YZ5SLMUYJF0CE', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS3861126B1YZ5SLMUYJF0CE', 'BC', NULL),
(59, '2026-11-26', 24, 3500.00, NULL, 'dibayar', 'duitku', 'WEB26112618D133', '2026-11-26 11:53:09', '2026-11-26 10:56:15', NULL, NULL, 0.00, 0.00, 'DS38611260IBST2YM3JB7TX8', 'https://app-sandbox.duitku.com/redirect_checkout?reference=DS38611260IBST2YM3JB7TX8', 'NQ', NULL),
(60, '2026-11-26', 24, 3500.00, NULL, 'dibayar', 'duitku', 'WEB261126F00395', '2026-11-26 11:59:11', '2026-11-26 11:01:11', NULL, NULL, 0.00, 0.00, 'DS3861126P463ZG1M8RX7YVU', 'https://sandbox.duitku.com/topup/topupdirectv2.aspx?ref=NQ26GH2TBO2JP2J5PB2', 'NQ', '{\"kode\":\"NQ\",\"reference\":\"DS3861126P463ZG1M8RX7YVU\",\"vaNumber\":\"\",\"qrString\":\"00020101021226670016COM.NOBUBANK.WWW01189360050300000904680214260408000000020303AUT51440014ID.CO.QRIS.WWW0303AUT0215ID202604080000252046399530336054073500.005802ID5923DUITKU TEMP UAT TESTING6006BEKASI61051771262720108MB135QTW0619NQ26GH2TBO2JP2J5PB20703A010804POSP99180002000108202611266304870D\",\"paymentUrl\":\"https:\\/\\/sandbox.duitku.com\\/topup\\/topupdirectv2.aspx?ref=NQ26GH2TBO2JP2J5PB2\",\"amount\":3500}'),
(61, '2026-11-26', 24, 3500.00, NULL, 'dibatalkan', 'qris', 'WEB26112658CA8F', '2026-11-27 11:05:40', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(62, '2026-11-26', 24, 3500.00, NULL, 'dibatalkan', 'transfer', 'WEB2611264388D3', '2026-11-27 11:06:00', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL),
(63, '2026-11-26', 24, 3500.00, NULL, 'dibayar', 'duitku', 'WEB2611260AB718', '2026-11-26 12:35:48', '2026-11-26 11:36:32', NULL, NULL, 0.00, 0.00, 'DS38611268KQ6RPXHN22IU5O', 'https://sandbox.duitku.com/topup/topupdirectv2.aspx?ref=NQ26FDP5OZSILW457JM', 'NQ', '{\"kode\":\"NQ\",\"reference\":\"DS38611268KQ6RPXHN22IU5O\",\"vaNumber\":\"\",\"qrString\":\"00020101021226670016COM.NOBUBANK.WWW01189360050300000904680214260408000000020303AUT51440014ID.CO.QRIS.WWW0303AUT0215ID202604080000252046399530336054073500.005802ID5923DUITKU TEMP UAT TESTING6006BEKASI61051771262720108MB135QUK0619NQ26FDP5OZSILW457JM0703A010804POSP99180002000108202611266304C3BC\",\"paymentUrl\":\"https:\\/\\/sandbox.duitku.com\\/topup\\/topupdirectv2.aspx?ref=NQ26FDP5OZSILW457JM\",\"amount\":3500}');

--
-- Triggers `tbl_transaksi`
--
DELIMITER $$
CREATE TRIGGER `before_insert_transaksi` BEFORE INSERT ON `tbl_transaksi` FOR EACH ROW BEGIN
    IF NEW.total_belanja < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Total belanja tidak boleh negatif';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user`
--

CREATE TABLE `tbl_user` (
  `id_user` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','kasir','user') NOT NULL DEFAULT 'kasir',
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `kode_kartu` char(64) DEFAULT NULL,
  `kode_rfid` char(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_user`
--

INSERT INTO `tbl_user` (`id_user`, `nama`, `username`, `password`, `role`, `dibuat_pada`, `kode_kartu`, `kode_rfid`) VALUES
(1, 'Administrator', 'admin', '$2y$12$6pi2c/p8Z5C0rHrASciCUOAER4H5tqnAUdk1b8KkpDMnKZokwtJ7K', 'admin', '2026-11-11 00:53:48', NULL, 'c598c262cbb54f55b9994bcdbb016b727c15decc17b10e3757822ab326a96837'),
(2, 'Kasir1', 'user', '$2y$12$YGMwTJxsODPVrIlLyFnCWOq3AM5uA1CatJ9BTOGAfbD2vETy2xTta', 'kasir', '2026-11-11 00:53:48', NULL, '1981717ae6abf3f29565eba6997b804a29a89f4067fbd9d230644a389189e3c3'),
(3, 'Administrator Toko', 'adminbaru', '$2y$12$8Lqtb6dlIr237fbCaHvVaOKg2Ondu2aguoDuRoLso5kA3ogAAZR5u', 'admin', '2026-11-23 01:45:31', NULL, NULL),
(4, 'Petugas Kasir', 'kasir', '$2y$12$0Ck2smAB/jpkvL.OWgZcvehPqcWFTWgbAOs23oUFxwhKUrS/EKJji', 'kasir', '2026-11-23 01:45:31', NULL, NULL),
(5, 'baru', 'fassa', '$2y$12$lniJMm9RTIE/d9vAGrHVKOEmbDHphNhX9cr/LIFSdBplYf6ItJ5Mu', 'kasir', '2026-11-25 02:38:17', NULL, NULL),
(6, 'fazanh', 'faza', '$2y$12$bIDF63hGNoWALdWguY6ut.H0ffUwtza3dLFAZY5lFbZX21y3HpgV2', 'user', '2026-11-25 06:01:45', NULL, NULL),
(7, 'fazanh', 'fazaz', '$2y$12$EBsHCaJ4rk5EMrqsomdDreu1eEr80O2AU/G7UmwzZ3N8u1Cuiba9G', 'user', '2026-11-26 01:48:11', NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_pelanggan`
-- (See below for the actual view)
--
CREATE TABLE `view_pelanggan` (
`id_pelanggan` int(11)
,`nama_pelanggan` varchar(100)
,`kota` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_total_belanja`
-- (See below for the actual view)
--
CREATE TABLE `view_total_belanja` (
`id_pelanggan` int(11)
,`nama_pelanggan` varchar(100)
,`total_transaksi` bigint(21)
,`total_belanja` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_transaksi_pelanggan`
-- (See below for the actual view)
--
CREATE TABLE `view_transaksi_pelanggan` (
`id_transaksi` int(11)
,`tanggal` date
,`nama_pelanggan` varchar(100)
,`kota` varchar(50)
,`total_belanja` decimal(12,2)
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `log_pelanggan`
--
ALTER TABLE `log_pelanggan`
  ADD PRIMARY KEY (`id_log`);

--
-- Indexes for table `log_perubahan_pelanggan`
--
ALTER TABLE `log_perubahan_pelanggan`
  ADD PRIMARY KEY (`id_log`);

--
-- Indexes for table `tbl_akun_pelanggan`
--
ALTER TABLE `tbl_akun_pelanggan`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `id_pelanggan` (`id_pelanggan`);

--
-- Indexes for table `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `fk_detail_produk` (`id_produk`),
  ADD KEY `idx_detail_transaksi` (`id_transaksi`);

--
-- Indexes for table `tbl_member`
--
ALTER TABLE `tbl_member`
  ADD PRIMARY KEY (`id_member`),
  ADD UNIQUE KEY `uk_member_pelanggan` (`id_pelanggan`),
  ADD UNIQUE KEY `uk_member_kode` (`kode_member`),
  ADD UNIQUE KEY `uk_member_rfid` (`kode_rfid`);

--
-- Indexes for table `tbl_pelanggan`
--
ALTER TABLE `tbl_pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`);

--
-- Indexes for table `tbl_pengaturan`
--
ALTER TABLE `tbl_pengaturan`
  ADD PRIMARY KEY (`kunci`);

--
-- Indexes for table `tbl_produk`
--
ALTER TABLE `tbl_produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD UNIQUE KEY `uk_produk_barcode` (`barcode`),
  ADD KEY `idx_produk_nama` (`nama_produk`),
  ADD KEY `idx_produk_kategori` (`kategori`);

--
-- Indexes for table `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `id_pelanggan` (`id_pelanggan`),
  ADD KEY `idx_transaksi_user` (`id_user`);

--
-- Indexes for table `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uk_user_kartu` (`kode_kartu`),
  ADD UNIQUE KEY `uk_user_rfid` (`kode_rfid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `log_pelanggan`
--
ALTER TABLE `log_pelanggan`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `log_perubahan_pelanggan`
--
ALTER TABLE `log_perubahan_pelanggan`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `tbl_member`
--
ALTER TABLE `tbl_member`
  MODIFY `id_member` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_pelanggan`
--
ALTER TABLE `tbl_pelanggan`
  MODIFY `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `tbl_produk`
--
ALTER TABLE `tbl_produk`
  MODIFY `id_produk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

-- --------------------------------------------------------

--
-- Structure for view `view_pelanggan`
--
DROP TABLE IF EXISTS `view_pelanggan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_pelanggan`  AS SELECT `tbl_pelanggan`.`id_pelanggan` AS `id_pelanggan`, `tbl_pelanggan`.`nama_pelanggan` AS `nama_pelanggan`, `tbl_pelanggan`.`kota` AS `kota` FROM `tbl_pelanggan` ;

-- --------------------------------------------------------

--
-- Structure for view `view_total_belanja`
--
DROP TABLE IF EXISTS `view_total_belanja`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_total_belanja`  AS SELECT `p`.`id_pelanggan` AS `id_pelanggan`, `p`.`nama_pelanggan` AS `nama_pelanggan`, count(`t`.`id_transaksi`) AS `total_transaksi`, coalesce(sum(`t`.`total_belanja`),0) AS `total_belanja` FROM (`tbl_pelanggan` `p` left join `tbl_transaksi` `t` on(`p`.`id_pelanggan` = `t`.`id_pelanggan`)) GROUP BY `p`.`id_pelanggan`, `p`.`nama_pelanggan` ;

-- --------------------------------------------------------

--
-- Structure for view `view_transaksi_pelanggan`
--
DROP TABLE IF EXISTS `view_transaksi_pelanggan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_transaksi_pelanggan`  AS SELECT `t`.`id_transaksi` AS `id_transaksi`, `t`.`tanggal` AS `tanggal`, `p`.`nama_pelanggan` AS `nama_pelanggan`, `p`.`kota` AS `kota`, `t`.`total_belanja` AS `total_belanja` FROM (`tbl_transaksi` `t` join `tbl_pelanggan` `p` on(`t`.`id_pelanggan` = `p`.`id_pelanggan`)) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_akun_pelanggan`
--
ALTER TABLE `tbl_akun_pelanggan`
  ADD CONSTRAINT `fk_akun_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `tbl_pelanggan` (`id_pelanggan`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_akun_user` FOREIGN KEY (`id_user`) REFERENCES `tbl_user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  ADD CONSTRAINT `fk_detail_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbl_produk` (`id_produk`),
  ADD CONSTRAINT `fk_detail_transaksi` FOREIGN KEY (`id_transaksi`) REFERENCES `tbl_transaksi` (`id_transaksi`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_member`
--
ALTER TABLE `tbl_member`
  ADD CONSTRAINT `fk_member_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `tbl_pelanggan` (`id_pelanggan`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  ADD CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`id_user`) REFERENCES `tbl_user` (`id_user`) ON DELETE SET NULL,
  ADD CONSTRAINT `tbl_transaksi_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `tbl_pelanggan` (`id_pelanggan`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
