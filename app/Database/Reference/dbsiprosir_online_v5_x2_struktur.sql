/*
 Navicat Premium Dump SQL

 Source Server         : Lokal_MAriadb
 Source Server Type    : MariaDB
 Source Server Version : 101119 (10.11.19-MariaDB)
 Source Host           : localhost:3307
 Source Schema         : dbsiprosir_online_v5_x2

 Target Server Type    : MariaDB
 Target Server Version : 101119 (10.11.19-MariaDB)
 File Encoding         : 65001

 Date: 16/10/2026 14:54:32
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for migrations
-- ----------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `namespace` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 43 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblapi_idempotency
-- ----------------------------
DROP TABLE IF EXISTS `tblapi_idempotency`;
CREATE TABLE `tblapi_idempotency`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `request_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `request_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PROCESSING',
  `response_code` smallint(5) UNSIGNED NULL DEFAULT NULL,
  `response_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uq_api_idempotency_scope_request`(`scope` ASC, `request_id` ASC) USING BTREE,
  INDEX `idx_api_idempotency_expiry`(`expires_at` ASC, `status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 405 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblbank_ledger
-- ----------------------------
DROP TABLE IF EXISTS `tblbank_ledger`;
CREATE TABLE `tblbank_ledger`  (
  `id_ledger` bigint(20) NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_bank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `no_referensi` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `posting_role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reversal_of_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `jenis` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'MANUAL',
  `kategori` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `debit` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `saldo_akhir` decimal(18, 2) NOT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id_ledger`) USING BTREE,
  UNIQUE INDEX `uq_tblbank_ledger_sync_uid`(`sync_uid` ASC) USING BTREE,
  UNIQUE INDEX `uq_ledger_source_role`(`source_type` ASC, `source_id` ASC, `id_bank` ASC, `posting_role` ASC) USING BTREE,
  INDEX `idx_bank_tanggal`(`id_bank` ASC, `tanggal` ASC) USING BTREE,
  INDEX `idx_ledger_source_uid`(`source_uid` ASC) USING BTREE,
  INDEX `idx_ledger_reversal_uid`(`reversal_of_uid` ASC) USING BTREE,
  INDEX `idx_ledger_no_referensi`(`no_referensi` ASC) USING BTREE,
  CONSTRAINT `fk_ledger_bank` FOREIGN KEY (`id_bank`) REFERENCES `tbldata_bank` (`id_bank`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_ledger_nominal_nonnegative` CHECK (`debit` >= 0 and `kredit` >= 0),
  CONSTRAINT `chk_ledger_single_side` CHECK (`debit` > 0 and `kredit` = 0 or `kredit` > 0 and `debit` = 0)
) ENGINE = InnoDB AUTO_INCREMENT = 344 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblbusiness_number_sequence
-- ----------------------------
DROP TABLE IF EXISTS `tblbusiness_number_sequence`;
CREATE TABLE `tblbusiness_number_sequence`  (
  `document_key` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `writer_code` varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `current_value` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_key`, `writer_code`) USING BTREE,
  CONSTRAINT `chk_business_number_sequence_value` CHECK (`current_value` >= 0)
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbldata_bank
-- ----------------------------
DROP TABLE IF EXISTS `tbldata_bank`;
CREATE TABLE `tbldata_bank`  (
  `id_bank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama_bank` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `no_rekening` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '-',
  `atas_nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '-',
  `jenis` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'BANK',
  `saldo` decimal(18, 2) NULL DEFAULT 0.00,
  `biaya_admin` decimal(18, 2) NULL DEFAULT 0.00,
  `status` tinyint(4) NULL DEFAULT 1,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(4) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_bank`) USING BTREE,
  UNIQUE INDEX `uq_tbldata_bank_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `idx_sync`(`sync` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbldata_gudang
-- ----------------------------
DROP TABLE IF EXISTS `tbldata_gudang`;
CREATE TABLE `tbldata_gudang`  (
  `id_gudang` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lokasi` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_gudang`, `nama`) USING BTREE,
  UNIQUE INDEX `uq_tbldata_gudang_sync_uid`(`sync_uid` ASC) USING BTREE,
  UNIQUE INDEX `uq_tbldata_gudang_id`(`id_gudang` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbldata_member
-- ----------------------------
DROP TABLE IF EXISTS `tbldata_member`;
CREATE TABLE `tbldata_member`  (
  `id_member` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `kelas_member` int(11) NULL DEFAULT 0,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `alamat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `no_hp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `status` tinyint(4) NULL DEFAULT 1,
  `is_unlimited` tinyint(4) NULL DEFAULT 0,
  `masa_berlaku` date NULL DEFAULT NULL,
  `poin` int(11) NULL DEFAULT 0,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime NULL DEFAULT current_timestamp(),
  `hapus` int(11) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(4) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  `sync_phone_normalized` varchar(24) GENERATED ALWAYS AS (case when `hapus` = 0 and nullif(trim(`no_hp`),'') is not null then case when regexp_replace(`no_hp`,'[^0-9]','') like '0%' then concat('62',substr(regexp_replace(`no_hp`,'[^0-9]',''),2)) else regexp_replace(`no_hp`,'[^0-9]','') end else NULL end) PERSISTENT,
  PRIMARY KEY (`id_member`) USING BTREE,
  UNIQUE INDEX `uq_tbldata_member_sync_uid`(`sync_uid` ASC) USING BTREE,
  UNIQUE INDEX `no_hp`(`no_hp` ASC) USING BTREE,
  INDEX `idx_nama`(`nama` ASC) USING BTREE,
  INDEX `idx_hp`(`no_hp` ASC) USING BTREE,
  INDEX `idx_sync`(`sync` ASC) USING BTREE,
  INDEX `idx_sync_member_phone_normalized`(`sync_phone_normalized` ASC, `id_member` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbldata_supiller
-- ----------------------------
DROP TABLE IF EXISTS `tbldata_supiller`;
CREATE TABLE `tbldata_supiller`  (
  `id_supiller` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `alamat` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `kota` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `provinsi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `negara` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `kodepos` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `telepon` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `fax` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `kontak` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_supiller`) USING BTREE,
  UNIQUE INDEX `uq_tbldata_supiller_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbldatabarang
-- ----------------------------
DROP TABLE IF EXISTS `tbldatabarang`;
CREATE TABLE `tbldatabarang`  (
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `kategori` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `merek` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_rak` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_sub` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `harga_beli` decimal(15, 2) NULL DEFAULT 0.00,
  `harga_jual` decimal(15, 2) NULL DEFAULT 0.00,
  `markup_mode` enum('NORMAL','%','+') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'NORMAL',
  `markup_value` decimal(15, 2) NULL DEFAULT 0.00,
  `min_stok` int(11) NULL DEFAULT 0,
  `ph` int(11) NULL DEFAULT 0,
  `kode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_group` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` int(11) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `sync_plu_normalized` varchar(40) GENERATED ALWAYS AS (case when coalesce(`hapus`,0) = 0 and nullif(trim(`kode`),'') is not null then ucase(trim(regexp_replace(`kode`,'[[:space:]]+',' '))) else NULL end) PERSISTENT,
  PRIMARY KEY (`id_produk`) USING BTREE,
  UNIQUE INDEX `uq_tbldatabarang_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `nama`(`nama` ASC) USING BTREE,
  INDEX `last_sync`(`last_sync` ASC) USING BTREE,
  INDEX `idx_barang_rak`(`id_rak` ASC) USING BTREE,
  INDEX `idx_barang_sub_rak`(`id_rak` ASC, `id_sub` ASC) USING BTREE,
  INDEX `idx_sync_product_plu_normalized`(`sync_plu_normalized` ASC, `id_produk` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbldatabarang_expired
-- ----------------------------
DROP TABLE IF EXISTS `tbldatabarang_expired`;
CREATE TABLE `tbldatabarang_expired`  (
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kode_batch` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_lokasi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'TOKO',
  `tanggal_masuk` date NULL DEFAULT NULL,
  `tanggal_expired` date NOT NULL,
  `jumlah_awal` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `jumlah` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `keterangan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`sync_uid`) USING BTREE,
  UNIQUE INDEX `uq_expired_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `idx_batch_product`(`id_produk` ASC) USING BTREE,
  INDEX `idx_batch_product_expiry`(`id_produk` ASC, `id_lokasi` ASC, `tanggal_expired` ASC, `kode_batch` ASC) USING BTREE,
  CONSTRAINT `fk_expired_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbldatabarang` (`id_produk`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbldatabarang_serial
-- ----------------------------
DROP TABLE IF EXISTS `tbldatabarang_serial`;
CREATE TABLE `tbldatabarang_serial`  (
  `id_serial` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_produk` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nomor` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipe` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SN',
  `id_lokasi` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'TOKO',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'TERSEDIA',
  `keterangan` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  `sync_serial_normalized` varchar(120) GENERATED ALWAYS AS (case when coalesce(`hapus`,0) = 0 and nullif(trim(`nomor`),'') is not null then case when ucase(trim(`tipe`)) = 'IMEI' then regexp_replace(`nomor`,'[^0-9]','') else ucase(trim(regexp_replace(`nomor`,'[[:space:]]+',' '))) end else NULL end) PERSISTENT,
  PRIMARY KEY (`id_serial`) USING BTREE,
  UNIQUE INDEX `nomor`(`nomor` ASC) USING BTREE,
  UNIQUE INDEX `uq_serial_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `id_produk_status`(`id_produk` ASC, `status` ASC) USING BTREE,
  INDEX `idx_sync_serial_normalized`(`sync_serial_normalized` ASC, `id_serial` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbldatabarang_stok
-- ----------------------------
DROP TABLE IF EXISTS `tbldatabarang_stok`;
CREATE TABLE `tbldatabarang_stok`  (
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_lokasi` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'TOKO',
  `stok` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `sync` int(11) NULL DEFAULT 1,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_produk`, `id_lokasi`) USING BTREE,
  CONSTRAINT `fk_stok_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbldatabarang` (`id_produk`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblgaji
-- ----------------------------
DROP TABLE IF EXISTS `tblgaji`;
CREATE TABLE `tblgaji`  (
  `id_gaji` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_karyawan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `periode_awal` date NULL DEFAULT NULL,
  `periode_akhir` date NULL DEFAULT NULL,
  `tanggal` datetime NULL DEFAULT NULL,
  `total_penghasilan` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `total_potongan` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `total_bayar` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `id_bank` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'DRAFT',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_gaji`) USING BTREE,
  UNIQUE INDEX `uq_gaji_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `id_karyawan`(`id_karyawan` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblgaji_detail
-- ----------------------------
DROP TABLE IF EXISTS `tblgaji_detail`;
CREATE TABLE `tblgaji_detail`  (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_gaji` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_komponen` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_komponen` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipe` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` decimal(18, 2) NOT NULL DEFAULT 1.00,
  `satuan_qty` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `tarif` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `nominal` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `sync` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_detail`) USING BTREE,
  UNIQUE INDEX `uq_gaji_detail_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `id_gaji`(`id_gaji` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 26 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblharga_diskon
-- ----------------------------
DROP TABLE IF EXISTS `tblharga_diskon`;
CREATE TABLE `tblharga_diskon`  (
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `diskon` decimal(11, 0) NOT NULL,
  `dari` date NOT NULL,
  `sampai` date NOT NULL,
  `satuan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `min` int(11) NOT NULL DEFAULT 1,
  `status` int(11) NOT NULL,
  `id_admin` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mode_diskon` int(11) NOT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_produk`, `dari`, `sampai`, `satuan`) USING BTREE,
  UNIQUE INDEX `uq_tblharga_diskon_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblharga_grosir
-- ----------------------------
DROP TABLE IF EXISTS `tblharga_grosir`;
CREATE TABLE `tblharga_grosir`  (
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_grosir` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `min` int(11) NOT NULL,
  `harga_jual` decimal(15, 2) NULL DEFAULT 0.00,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_produk`, `min`) USING BTREE,
  UNIQUE INDEX `uq_tblharga_grosir_sync_uid`(`sync_uid` ASC) USING BTREE,
  CONSTRAINT `fk_grosir_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbldatabarang` (`id_produk`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblharga_member
-- ----------------------------
DROP TABLE IF EXISTS `tblharga_member`;
CREATE TABLE `tblharga_member`  (
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `harga_jual` decimal(15, 2) NULL DEFAULT 0.00,
  `harga_diskon` decimal(15, 2) NULL DEFAULT 0.00,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_produk`, `id_kelas`) USING BTREE,
  UNIQUE INDEX `uq_tblharga_member_sync_uid`(`sync_uid` ASC) USING BTREE,
  CONSTRAINT `fk_member_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbldatabarang` (`id_produk`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblharga_satuan
-- ----------------------------
DROP TABLE IF EXISTS `tblharga_satuan`;
CREATE TABLE `tblharga_satuan`  (
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_satuan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `barcode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `isi` decimal(18, 4) NOT NULL DEFAULT 1.0000,
  `harga_jual` decimal(15, 2) NULL DEFAULT 0.00,
  `poin` int(11) NULL DEFAULT 0,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `sync_barcode_normalized` varchar(100) GENERATED ALWAYS AS (case when coalesce(`hapus`,0) = 0 and nullif(trim(`barcode`),'') is not null then ucase(trim(regexp_replace(`barcode`,'[[:space:]]+',' '))) else NULL end) PERSISTENT,
  PRIMARY KEY (`id_produk`, `nama_satuan`) USING BTREE,
  UNIQUE INDEX `uq_tblharga_satuan_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `idx_tblharga_satuan_barcode_active`(`barcode` ASC, `hapus` ASC, `id_produk` ASC) USING BTREE,
  INDEX `idx_sync_unit_barcode_normalized`(`sync_barcode_normalized` ASC, `id_produk` ASC) USING BTREE,
  CONSTRAINT `fk_satuan_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbldatabarang` (`id_produk`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbljurnal_pembalik
-- ----------------------------
DROP TABLE IF EXISTS `tbljurnal_pembalik`;
CREATE TABLE `tbljurnal_pembalik`  (
  `id_pembalik` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `request_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `tanggal` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `source_type_asal` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_id_asal` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `alasan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('FINAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'FINAL',
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SYSTEM',
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_pembalik`) USING BTREE,
  UNIQUE INDEX `uq_jurnal_pembalik_sync_uid`(`sync_uid` ASC) USING BTREE,
  UNIQUE INDEX `uq_jurnal_pembalik_sumber`(`source_type_asal` ASC, `source_id_asal` ASC) USING BTREE,
  UNIQUE INDEX `uq_jurnal_pembalik_request_id`(`request_id` ASC) USING BTREE,
  INDEX `idx_jurnal_pembalik_tanggal`(`tanggal` ASC) USING BTREE,
  INDEX `idx_jurnal_pembalik_sync`(`sync` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblkaryawan
-- ----------------------------
DROP TABLE IF EXISTS `tblkaryawan`;
CREATE TABLE `tblkaryawan`  (
  `id_karyawan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_user` int(11) NULL DEFAULT NULL,
  `nama` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jabatan` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `telepon` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `alamat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `tanggal_masuk` date NULL DEFAULT NULL,
  `gaji_pokok` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_karyawan`) USING BTREE,
  UNIQUE INDEX `uq_karyawan_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `id_user`(`id_user` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblkas_kategori
-- ----------------------------
DROP TABLE IF EXISTS `tblkas_kategori`;
CREATE TABLE `tblkas_kategori`  (
  `id_kategori` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama_kategori` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipe` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'KELUAR',
  `sistem` tinyint(1) NOT NULL DEFAULT 0,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_kategori`) USING BTREE,
  UNIQUE INDEX `uq_kas_kategori_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `tipe_hapus`(`tipe` ASC, `hapus` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblkas_mutasi
-- ----------------------------
DROP TABLE IF EXISTS `tblkas_mutasi`;
CREATE TABLE `tblkas_mutasi`  (
  `id_mutasi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL,
  `jenis` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kategori` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `no_referensi` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `debit` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `saldo_akhir` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `id_bank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_mutasi`) USING BTREE,
  UNIQUE INDEX `uq_kas_mutasi_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `id_shift_tanggal`(`id_shift` ASC, `tanggal` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblkas_shift
-- ----------------------------
DROP TABLE IF EXISTS `tblkas_shift`;
CREATE TABLE `tblkas_shift`  (
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tanggal_buka` datetime NOT NULL,
  `tanggal_tutup` datetime NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `modal_awal` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `total_penjualan_tunai` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `total_kas_masuk` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `total_kas_keluar` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `total_setor` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `saldo_sistem` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `saldo_fisik` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `selisih` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'AKTIF',
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_shift`) USING BTREE,
  UNIQUE INDEX `uq_kas_shift_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `tanggal_buka_status`(`tanggal_buka` ASC, `status` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblkategori
-- ----------------------------
DROP TABLE IF EXISTS `tblkategori`;
CREATE TABLE `tblkategori`  (
  `kategori` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `sync_name_normalized` varchar(30) GENERATED ALWAYS AS (case when coalesce(`hapus`,0) = 0 then ucase(trim(regexp_replace(`kategori`,'[[:space:]]+',' '))) else NULL end) PERSISTENT,
  PRIMARY KEY (`kategori`) USING BTREE,
  UNIQUE INDEX `uq_tblkategori_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `idx_sync_category_name_normalized`(`sync_name_normalized` ASC, `kategori` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblkomponen_gaji
-- ----------------------------
DROP TABLE IF EXISTS `tblkomponen_gaji`;
CREATE TABLE `tblkomponen_gaji`  (
  `id_komponen` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama_komponen` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipe` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PENGHASILAN',
  `sistem` tinyint(1) NOT NULL DEFAULT 0,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id_komponen`) USING BTREE,
  UNIQUE INDEX `uq_komponen_gaji_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblledger_rekalkulasi
-- ----------------------------
DROP TABLE IF EXISTS `tblledger_rekalkulasi`;
CREATE TABLE `tblledger_rekalkulasi`  (
  `id_rekalkulasi` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sumber_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_bank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL,
  `no_referensi` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jenis` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `kategori` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `debit` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `saldo_akhir_rekalkulasi` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `urutan_jenis` tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
  `source_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `dibuat_pada` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `diperbarui_pada` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_rekalkulasi`) USING BTREE,
  UNIQUE INDEX `uq_ledger_rekalkulasi_sumber`(`sumber_uid` ASC) USING BTREE,
  INDEX `idx_ledger_rekalkulasi_urut`(`id_bank` ASC, `tanggal` ASC, `no_referensi` ASC, `urutan_jenis` ASC, `sumber_uid` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 777 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblledger_rekalkulasi_state
-- ----------------------------
DROP TABLE IF EXISTS `tblledger_rekalkulasi_state`;
CREATE TABLE `tblledger_rekalkulasi_state`  (
  `id_bank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `saldo_pembuka` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `tanggal_terakhir` datetime NULL DEFAULT NULL,
  `referensi_terakhir` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `urutan_jenis_terakhir` tinyint(3) UNSIGNED NULL DEFAULT NULL,
  `sumber_uid_terakhir` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `saldo_akhir` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `hitung_ulang_dari` datetime NULL DEFAULT NULL,
  `status_proses` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SIAP',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `diproses_pada` datetime(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id_bank`) USING BTREE,
  INDEX `idx_ledger_rekalkulasi_state`(`status_proses` ASC, `hitung_ulang_dari` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbllegacy_stock_opening
-- ----------------------------
DROP TABLE IF EXISTS `tbllegacy_stock_opening`;
CREATE TABLE `tbllegacy_stock_opening`  (
  `id_opening` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `legacy_source` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_database` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_job_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `source_checksum` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_lokasi` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'MIGRASI',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `status` enum('DRAFT','FINAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'DRAFT',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `finalized_at` datetime(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id_opening`) USING BTREE,
  UNIQUE INDEX `uq_legacy_stock_opening_uid`(`sync_uid` ASC) USING BTREE,
  UNIQUE INDEX `uq_legacy_stock_opening_source`(`legacy_source` ASC, `source_database` ASC, `id_lokasi` ASC) USING BTREE,
  INDEX `idx_legacy_stock_opening_location`(`id_lokasi` ASC, `tanggal` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbllegacy_stock_opening_detail
-- ----------------------------
DROP TABLE IF EXISTS `tbllegacy_stock_opening_detail`;
CREATE TABLE `tbllegacy_stock_opening_detail`  (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_opening` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_product_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` decimal(18, 4) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id_detail`) USING BTREE,
  UNIQUE INDEX `uq_legacy_stock_opening_detail_uid`(`sync_uid` ASC) USING BTREE,
  UNIQUE INDEX `uq_legacy_stock_opening_product`(`id_opening` ASC, `id_produk` ASC) USING BTREE,
  INDEX `idx_legacy_stock_opening_detail_product`(`id_produk` ASC) USING BTREE,
  CONSTRAINT `fk_legacy_stock_opening_detail_header` FOREIGN KEY (`id_opening`) REFERENCES `tbllegacy_stock_opening` (`id_opening`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_legacy_stock_opening_detail_product` FOREIGN KEY (`id_produk`) REFERENCES `tbldatabarang` (`id_produk`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_legacy_stock_opening_qty` CHECK (`qty` > 0)
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblmember_poin_log
-- ----------------------------
DROP TABLE IF EXISTS `tblmember_poin_log`;
CREATE TABLE `tblmember_poin_log`  (
  `id_poin` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_member` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_transaksi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `jenis` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PENYESUAIAN',
  `masuk` int(11) NOT NULL DEFAULT 0,
  `keluar` int(11) NOT NULL DEFAULT 0,
  `saldo_akhir` int(11) NOT NULL DEFAULT 0,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id_poin`) USING BTREE,
  UNIQUE INDEX `uq_member_poin_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `idx_member_poin_member_tanggal`(`id_member` ASC, `tanggal` ASC) USING BTREE,
  INDEX `idx_member_poin_transaksi`(`id_transaksi` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblmember_setting
-- ----------------------------
DROP TABLE IF EXISTS `tblmember_setting`;
CREATE TABLE `tblmember_setting`  (
  `id_kelas` int(11) NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama_kelas` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `diskon_global_persen` decimal(5, 2) NULL DEFAULT 0.00,
  `min_belanja` decimal(15, 2) NULL DEFAULT 0.00,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_kelas`) USING BTREE,
  UNIQUE INDEX `uq_tblmember_setting_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblmerek
-- ----------------------------
DROP TABLE IF EXISTS `tblmerek`;
CREATE TABLE `tblmerek`  (
  `merek` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `sync_name_normalized` varchar(20) GENERATED ALWAYS AS (case when coalesce(`hapus`,0) = 0 then ucase(trim(regexp_replace(`merek`,'[[:space:]]+',' '))) else NULL end) PERSISTENT,
  PRIMARY KEY (`merek`) USING BTREE,
  UNIQUE INDEX `uq_tblmerek_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `idx_sync_brand_name_normalized`(`sync_name_normalized` ASC, `merek` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpengaturan
-- ----------------------------
DROP TABLE IF EXISTS `tblpengaturan`;
CREATE TABLE `tblpengaturan`  (
  `kode` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `grup` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'umum',
  `nilai` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NOT NULL DEFAULT 1,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `nama` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `tipe` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`kode`) USING BTREE,
  UNIQUE INDEX `uq_pengaturan_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `grup`(`grup` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpengaturan_transaksi
-- ----------------------------
DROP TABLE IF EXISTS `tblpengaturan_transaksi`;
CREATE TABLE `tblpengaturan_transaksi`  (
  `nama` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '1',
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` datetime NULL DEFAULT NULL,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`nama`) USING BTREE,
  UNIQUE INDEX `uq_pengaturan_transaksi_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpermission
-- ----------------------------
DROP TABLE IF EXISTS `tblpermission`;
CREATE TABLE `tblpermission`  (
  `kode_permission` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama_permission` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `grup` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`kode_permission`) USING BTREE,
  UNIQUE INDEX `uq_permission_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpiutangpembelian
-- ----------------------------
DROP TABLE IF EXISTS `tblpiutangpembelian`;
CREATE TABLE `tblpiutangpembelian`  (
  `id_piutang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_pembelian` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `total` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `dibayar` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `status` enum('Lunas','Belum') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Belum',
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `jatuh_tempo` date NOT NULL DEFAULT '2019-11-28',
  `terakhir_bayar` datetime NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_supiller` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NULL DEFAULT 1,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_piutang`) USING BTREE,
  UNIQUE INDEX `uq_piutangpembelian_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpiutangpembelian_log
-- ----------------------------
DROP TABLE IF EXISTS `tblpiutangpembelian_log`;
CREATE TABLE `tblpiutangpembelian_log`  (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_piutang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jumlah` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `mode_pembayaran` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `id_pembayaran` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_bank` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'CASH',
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_detail`) USING BTREE,
  UNIQUE INDEX `id_detail`(`id_detail` ASC) USING BTREE,
  UNIQUE INDEX `uq_piutangpembelian_log_sync_uid`(`sync_uid` ASC) USING BTREE,
  UNIQUE INDEX `uq_payable_payment_reference`(`id_pembayaran` ASC) USING BTREE,
  INDEX `idx_payable_payment_debt_date`(`id_piutang` ASC, `tanggal` ASC, `id_detail` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 58 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpiutangpenjualan
-- ----------------------------
DROP TABLE IF EXISTS `tblpiutangpenjualan`;
CREATE TABLE `tblpiutangpenjualan`  (
  `id_piutang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_transaksi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_member` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `total` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `dibayar` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `sisa` decimal(18, 2) GENERATED ALWAYS AS (`total` - `dibayar`) VIRTUAL,
  `status` enum('Lunas','Belum') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Belum',
  `tanggal` datetime NULL DEFAULT current_timestamp(),
  `jatuh_tempo` date NOT NULL,
  `terakhir_bayar` datetime NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(4) NULL DEFAULT 1,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_piutang`) USING BTREE,
  UNIQUE INDEX `uniq_piutang_transaksi`(`id_transaksi` ASC) USING BTREE,
  UNIQUE INDEX `uq_piutangpenjualan_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `idx_member`(`id_member` ASC) USING BTREE,
  INDEX `idx_status`(`status` ASC) USING BTREE,
  INDEX `idx_sync`(`sync` ASC) USING BTREE,
  INDEX `idx_transaksi`(`id_transaksi` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpiutangpenjualan_log
-- ----------------------------
DROP TABLE IF EXISTS `tblpiutangpenjualan_log`;
CREATE TABLE `tblpiutangpenjualan_log`  (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_piutang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jumlah` decimal(18, 2) NOT NULL,
  `tanggal` datetime NULL DEFAULT current_timestamp(),
  `mode_pembayaran` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'TUNAI',
  `id_bank` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'CASH',
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `no_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '-',
  `request_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(4) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_detail`) USING BTREE,
  UNIQUE INDEX `uq_piutangpenjualan_log_sync_uid`(`sync_uid` ASC) USING BTREE,
  UNIQUE INDEX `uniq_piutang_request_id`(`request_id` ASC) USING BTREE,
  INDEX `idx_piutang`(`id_piutang` ASC) USING BTREE,
  INDEX `idx_piutang_no_ref`(`no_ref` ASC) USING BTREE,
  INDEX `idx_sync`(`sync` ASC) USING BTREE,
  CONSTRAINT `fk_piutang_log` FOREIGN KEY (`id_piutang`) REFERENCES `tblpiutangpenjualan` (`id_piutang`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 33 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpos_hold
-- ----------------------------
DROP TABLE IF EXISTS `tblpos_hold`;
CREATE TABLE `tblpos_hold`  (
  `id_hold` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL,
  `id_member` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `customer` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `total` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `item_count` int(11) NOT NULL DEFAULT 0,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'AKTIF',
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_hold`) USING BTREE,
  INDEX `idx_pos_hold_status`(`status` ASC, `hapus` ASC, `tanggal` ASC) USING BTREE,
  INDEX `idx_pos_hold_admin`(`id_admin` ASC, `hapus` ASC, `tanggal` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblproduk_foto
-- ----------------------------
DROP TABLE IF EXISTS `tblproduk_foto`;
CREATE TABLE `tblproduk_foto`  (
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `media_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `storage_key` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `thumbnail_key` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `mime_type` varchar(50) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'image/webp',
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `thumbnail_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `width` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `height` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `thumbnail_width` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `thumbnail_height` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `file_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `thumbnail_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `versi` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `status_foto` enum('AKTIF','DIHAPUS') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'AKTIF',
  `id_admin` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id_produk`) USING BTREE,
  UNIQUE INDEX `uq_tblproduk_foto_media_uid`(`media_uid` ASC) USING BTREE,
  INDEX `idx_tblproduk_foto_status_updated`(`status_foto` ASC, `updated_at` ASC) USING BTREE,
  CONSTRAINT `fk_tblproduk_foto_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbldatabarang` (`id_produk`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblproduk_foto_transfer
-- ----------------------------
DROP TABLE IF EXISTS `tblproduk_foto_transfer`;
CREATE TABLE `tblproduk_foto_transfer`  (
  `id_transfer` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `arah` enum('UPLOAD','DOWNLOAD','DELETE') CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `target_version` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `target_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `local_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `status_transfer` enum('PENDING','PROCESSING','SUCCESS','RETRY','FAILED','CANCELLED') CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'PENDING',
  `attempt_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `next_retry_at` datetime(6) NULL DEFAULT NULL,
  `last_error_code` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `last_error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `locked_by` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `locked_at` datetime(6) NULL DEFAULT NULL,
  `completed_at` datetime(6) NULL DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_transfer`) USING BTREE,
  UNIQUE INDEX `uq_tblproduk_foto_transfer_uuid`(`transfer_uuid` ASC) USING BTREE,
  INDEX `idx_tblproduk_foto_transfer_ready`(`status_transfer` ASC, `next_retry_at` ASC, `id_transfer` ASC) USING BTREE,
  INDEX `idx_tblproduk_foto_transfer_product`(`id_produk` ASC, `arah` ASC, `status_transfer` ASC, `id_transfer` ASC) USING BTREE,
  CONSTRAINT `fk_tblproduk_foto_transfer_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbldatabarang` (`id_produk`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblproduk_import_job
-- ----------------------------
DROP TABLE IF EXISTS `tblproduk_import_job`;
CREATE TABLE `tblproduk_import_job`  (
  `id_job` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_sha256` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mode_import` enum('TAMBAH_SAJA','TAMBAH_DAN_PERBARUI') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'TAMBAH_SAJA',
  `auto_create_master` tinyint(1) NOT NULL DEFAULT 0,
  `status_job` enum('PREVIEW','VALID','INVALID','PROCESSING','COMPLETED','FAILED','CANCELLED','EXPIRED') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PREVIEW',
  `total_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `valid_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `error_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skipped_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `inserted_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `processed_at` datetime NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_job`) USING BTREE,
  UNIQUE INDEX `uq_produk_import_job_uid`(`job_uid` ASC) USING BTREE,
  INDEX `idx_produk_import_job_status`(`status_job` ASC, `expires_at` ASC) USING BTREE,
  INDEX `idx_produk_import_job_admin`(`id_admin` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblproduk_import_row
-- ----------------------------
DROP TABLE IF EXISTS `tblproduk_import_row`;
CREATE TABLE `tblproduk_import_row`  (
  `id_row` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `sheet_name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `row_number` int(10) UNSIGNED NOT NULL,
  `entity_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `action_type` enum('BARU','UPDATE','SKIP','ERROR') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `can_import` tinyint(1) NOT NULL DEFAULT 0,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `errors_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_row`) USING BTREE,
  UNIQUE INDEX `uq_produk_import_row`(`job_uid` ASC, `sheet_name` ASC, `row_number` ASC) USING BTREE,
  INDEX `idx_produk_import_row_preview`(`job_uid` ASC, `sheet_name` ASC, `action_type` ASC, `row_number` ASC) USING BTREE,
  CONSTRAINT `fk_produk_import_row_job` FOREIGN KEY (`job_uid`) REFERENCES `tblproduk_import_job` (`job_uid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpurchase_order
-- ----------------------------
DROP TABLE IF EXISTS `tblpurchase_order`;
CREATE TABLE `tblpurchase_order`  (
  `id_po` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tanggal` datetime NULL DEFAULT NULL,
  `tanggal_estimasi` date NULL DEFAULT NULL,
  `id_supiller` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `masuk_ke` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subtotal` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `tambahan` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `potongan` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `total` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'DRAFT',
  `id_rencana` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_pembelian` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_po`) USING BTREE,
  UNIQUE INDEX `uq_po_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `id_supiller`(`id_supiller` ASC) USING BTREE,
  INDEX `status`(`status` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpurchase_order_dp
-- ----------------------------
DROP TABLE IF EXISTS `tblpurchase_order_dp`;
CREATE TABLE `tblpurchase_order_dp`  (
  `id_dp` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_po` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NULL DEFAULT NULL,
  `id_bank` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `nominal` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `sync` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_dp`) USING BTREE,
  INDEX `id_po`(`id_po` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpurchase_order_item
-- ----------------------------
DROP TABLE IF EXISTS `tblpurchase_order_item`;
CREATE TABLE `tblpurchase_order_item`  (
  `id_po_item` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_po` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kategori` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `satuan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PCS',
  `isi` decimal(18, 4) NOT NULL DEFAULT 1.0000,
  `qty_order` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `harga` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `catatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `sync` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_po_item`) USING BTREE,
  INDEX `id_po`(`id_po` ASC) USING BTREE,
  INDEX `id_produk`(`id_produk` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpurchase_order_terima
-- ----------------------------
DROP TABLE IF EXISTS `tblpurchase_order_terima`;
CREATE TABLE `tblpurchase_order_terima`  (
  `id_terima` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_po` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NULL DEFAULT NULL,
  `id_supiller` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `masuk_ke` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `no_surat_jalan` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `no_faktur_supplier` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'DITERIMA',
  `id_pembelian` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `sync` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_terima`) USING BTREE,
  INDEX `id_po`(`id_po` ASC) USING BTREE,
  INDEX `status`(`status` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblpurchase_order_terima_detail
-- ----------------------------
DROP TABLE IF EXISTS `tblpurchase_order_terima_detail`;
CREATE TABLE `tblpurchase_order_terima_detail`  (
  `id_terima_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_terima` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_po` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_po_item` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kategori` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `satuan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PCS',
  `isi` decimal(18, 4) NOT NULL DEFAULT 1.0000,
  `qty_terima` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `harga` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `sync` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_terima_detail`) USING BTREE,
  UNIQUE INDEX `uq_po_terima_detail_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `id_terima`(`id_terima` ASC) USING BTREE,
  INDEX `id_po_item`(`id_po_item` ASC) USING BTREE,
  INDEX `id_produk`(`id_produk` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 52 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblrak
-- ----------------------------
DROP TABLE IF EXISTS `tblrak`;
CREATE TABLE `tblrak`  (
  `id_rak` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama_rak` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_rak`) USING BTREE,
  UNIQUE INDEX `uq_tblrak_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblrak_sub
-- ----------------------------
DROP TABLE IF EXISTS `tblrak_sub`;
CREATE TABLE `tblrak_sub`  (
  `id_rak` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_sub` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama_sub` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `sync` int(11) NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_rak`, `id_sub`) USING BTREE,
  UNIQUE INDEX `uq_tblrak_sub_sync_uid`(`sync_uid` ASC) USING BTREE,
  CONSTRAINT `fk_rak_sub` FOREIGN KEY (`id_rak`) REFERENCES `tblrak` (`id_rak`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblrencanapembelian
-- ----------------------------
DROP TABLE IF EXISTS `tblrencanapembelian`;
CREATE TABLE `tblrencanapembelian`  (
  `id_rencana` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tanggal` datetime NOT NULL,
  `tanggal_rencana` date NULL DEFAULT NULL,
  `id_supiller` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `masuk_ke` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'DRAFT',
  `id_pembelian` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` int(11) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_rencana`) USING BTREE,
  UNIQUE INDEX `uq_rencana_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `status_tanggal_rencana`(`status` ASC, `tanggal_rencana` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblrencanapembelian_detail
-- ----------------------------
DROP TABLE IF EXISTS `tblrencanapembelian_detail`;
CREATE TABLE `tblrencanapembelian_detail`  (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `id_rencana` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `kategori` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `satuan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PCS',
  `isi` decimal(10, 2) NOT NULL DEFAULT 1.00,
  `jumlah` decimal(15, 2) NOT NULL DEFAULT 0.00,
  `estimasi_harga` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `sync` int(11) NOT NULL DEFAULT 1,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_detail`) USING BTREE,
  UNIQUE INDEX `uq_rencana_detail_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `id_rencana`(`id_rencana` ASC) USING BTREE,
  INDEX `id_produk`(`id_produk` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblreturpembelian
-- ----------------------------
DROP TABLE IF EXISTS `tblreturpembelian`;
CREATE TABLE `tblreturpembelian`  (
  `id_retur` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_pembelian` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NULL DEFAULT NULL,
  `tipe_retur` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'KEMBALI_DANA',
  `total_retur` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `potong_hutang` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `dana_kembali` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'FINAL',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_bank` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `sync` int(11) NOT NULL DEFAULT 1,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_retur`) USING BTREE,
  INDEX `id_pembelian`(`id_pembelian` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblreturpembelian_detail
-- ----------------------------
DROP TABLE IF EXISTS `tblreturpembelian_detail`;
CREATE TABLE `tblreturpembelian_detail`  (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_retur` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_pembelian` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_detail_beli` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `id_produk` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_produk` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `satuan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PCS',
  `isi_satuan` decimal(10, 2) NOT NULL DEFAULT 1.00,
  `jumlah` decimal(15, 2) NOT NULL DEFAULT 0.00,
  `qty_dasar` decimal(15, 2) NOT NULL DEFAULT 0.00,
  `harga_beli` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `diskon_item` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `subtotal_retur` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `id_lokasi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'FINAL',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `sync` int(11) NOT NULL DEFAULT 1,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_detail`) USING BTREE,
  INDEX `id_retur`(`id_retur` ASC) USING BTREE,
  INDEX `id_pembelian`(`id_pembelian` ASC) USING BTREE,
  INDEX `id_produk`(`id_produk` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 32 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblreturpenjualan
-- ----------------------------
DROP TABLE IF EXISTS `tblreturpenjualan`;
CREATE TABLE `tblreturpenjualan`  (
  `id_retur` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_transaksi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `tipe_retur` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'TUNAI',
  `total_retur` decimal(15, 2) NULL DEFAULT 0.00,
  `potong_piutang` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `potongan_retur` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `alasan_potongan_retur` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `dana_kembali` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `status` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'DRAFT',
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_bank` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `sync` int(11) NULL DEFAULT 1,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_retur`) USING BTREE,
  INDEX `idx_retur_jual_transaksi`(`id_transaksi` ASC) USING BTREE,
  INDEX `idx_retur_jual_tanggal_status`(`tanggal` ASC, `status` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblreturpenjualan_detail
-- ----------------------------
DROP TABLE IF EXISTS `tblreturpenjualan_detail`;
CREATE TABLE `tblreturpenjualan_detail`  (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_retur` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_transaksi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_detail_jual` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `id_produk` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_produk` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `satuan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PCS',
  `isi_satuan` decimal(10, 2) NULL DEFAULT 1.00,
  `jumlah` decimal(10, 2) NULL DEFAULT 0.00,
  `qty_dasar` decimal(10, 2) NULL DEFAULT 0.00,
  `harga_beli` decimal(15, 2) NULL DEFAULT 0.00,
  `harga_jual` decimal(15, 2) NULL DEFAULT 0.00,
  `diskon_item` decimal(15, 2) NULL DEFAULT 0.00,
  `subtotal_retur` decimal(15, 2) NULL DEFAULT 0.00,
  `id_lokasi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'TOKO',
  `kondisi_barang` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'BAIK',
  `aksi_stok` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'KEMBALI_STOK',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `barang` decimal(15, 2) NOT NULL DEFAULT 0.00,
  `cash` decimal(15, 2) NOT NULL DEFAULT 0.00,
  `sync` int(11) NULL DEFAULT 1,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_retur`, `id_transaksi`, `id_produk`, `satuan`, `status`) USING BTREE,
  UNIQUE INDEX `id_detail`(`id_detail` ASC) USING BTREE,
  INDEX `idx_retur_detail_retur`(`id_retur` ASC) USING BTREE,
  INDEX `idx_retur_detail_transaksi_item`(`id_transaksi` ASC, `id_detail_jual` ASC) USING BTREE,
  INDEX `idx_retur_detail_produk_lokasi`(`id_produk` ASC, `id_lokasi` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 54 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblrole
-- ----------------------------
DROP TABLE IF EXISTS `tblrole`;
CREATE TABLE `tblrole`  (
  `id_role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `nama_role` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `sistem` tinyint(1) NOT NULL DEFAULT 0,
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_role`) USING BTREE,
  UNIQUE INDEX `uq_role_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblrole_permission
-- ----------------------------
DROP TABLE IF EXISTS `tblrole_permission`;
CREATE TABLE `tblrole_permission`  (
  `id_role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kode_permission` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_role`, `kode_permission`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsaldo_posting_log
-- ----------------------------
DROP TABLE IF EXISTS `tblsaldo_posting_log`;
CREATE TABLE `tblsaldo_posting_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref_tabel` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ref_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `no_referensi` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_bank` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `posting_role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'MAIN',
  `reversal_of_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `debit` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `tanggal` datetime NULL DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uq_saldo_posting_source_role`(`ref_tabel` ASC, `ref_id` ASC, `id_bank` ASC, `posting_role` ASC) USING BTREE,
  INDEX `idx_saldo_posting_source_uid`(`source_uid` ASC) USING BTREE,
  INDEX `idx_saldo_posting_reversal`(`reversal_of_uid` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 329 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsatuan
-- ----------------------------
DROP TABLE IF EXISTS `tblsatuan`;
CREATE TABLE `tblsatuan`  (
  `satuan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `sync_name_normalized` varchar(20) GENERATED ALWAYS AS (case when coalesce(`hapus`,0) = 0 then ucase(trim(regexp_replace(`satuan`,'[[:space:]]+',' '))) else NULL end) PERSISTENT,
  PRIMARY KEY (`satuan`) USING BTREE,
  UNIQUE INDEX `uq_tblsatuan_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `idx_sync_unit_name_normalized`(`sync_name_normalized` ASC, `satuan` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsecurity_audit
-- ----------------------------
DROP TABLE IF EXISTS `tblsecurity_audit`;
CREATE TABLE `tblsecurity_audit`  (
  `id_audit` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `actor_username` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `actor_role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `action_code` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT NULL CHECK (json_valid(`after_json`)),
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_audit`) USING BTREE,
  INDEX `idx_security_audit_created`(`created_at` ASC) USING BTREE,
  INDEX `idx_security_audit_action`(`action_code` ASC, `created_at` ASC) USING BTREE,
  INDEX `idx_security_audit_entity`(`entity_type` ASC, `entity_id` ASC, `created_at` ASC) USING BTREE,
  INDEX `idx_security_audit_actor`(`actor_user_id` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblstok
-- ----------------------------
DROP TABLE IF EXISTS `tblstok`;
CREATE TABLE `tblstok`  (
  `id_log` bigint(20) NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `source_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `no_referensi` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `posting_role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_target` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'TOKO',
  `penambahan` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `pengurangan` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `stok_akhir` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `id_admin` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `sync` int(11) NULL DEFAULT 1,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id_log`) USING BTREE,
  UNIQUE INDEX `uniq_stok_sync_uid`(`sync_uid` ASC) USING BTREE,
  INDEX `id_produk`(`id_produk` ASC) USING BTREE,
  INDEX `tanggal`(`tanggal` ASC) USING BTREE,
  INDEX `idx_stok_source`(`source_type` ASC, `source_id` ASC, `id_produk` ASC, `id_target` ASC, `posting_role` ASC) USING BTREE,
  INDEX `idx_stok_reference`(`no_referensi` ASC) USING BTREE,
  CONSTRAINT `fk_log_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbldatabarang` (`id_produk`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `chk_stok_mutasi_nonnegative` CHECK (`penambahan` >= 0 and `pengurangan` >= 0),
  CONSTRAINT `chk_stok_mutasi_single_side` CHECK (`penambahan` > 0 and `pengurangan` = 0 or `pengurangan` > 0 and `penambahan` = 0)
) ENGINE = InnoDB AUTO_INCREMENT = 249 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblstok_opname
-- ----------------------------
DROP TABLE IF EXISTS `tblstok_opname`;
CREATE TABLE `tblstok_opname`  (
  `id_opname` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL,
  `id_produk` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_gudang` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stok_sistem` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `stok_fisik` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `selisih` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_opname`) USING BTREE,
  INDEX `tanggal_id_gudang`(`tanggal` ASC, `id_gudang` ASC) USING BTREE,
  INDEX `id_produk_id_gudang`(`id_produk` ASC, `id_gudang` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblstok_posting_log
-- ----------------------------
DROP TABLE IF EXISTS `tblstok_posting_log`;
CREATE TABLE `tblstok_posting_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref_tabel` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ref_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `no_referensi` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `posting_role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_lokasi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `masuk` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `keluar` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `tanggal` datetime NULL DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uniq_stok_posting_ref`(`ref_tabel` ASC, `ref_id` ASC, `id_produk` ASC, `id_lokasi` ASC, `posting_role` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 243 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblstok_rekalkulasi
-- ----------------------------
DROP TABLE IF EXISTS `tblstok_rekalkulasi`;
CREATE TABLE `tblstok_rekalkulasi`  (
  `id_rekalkulasi` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sumber_uid` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ref_tabel` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ref_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_lokasi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL,
  `masuk` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `keluar` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `stok_akhir_rekalkulasi` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `source_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `dibuat_pada` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `diperbarui_pada` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_rekalkulasi`) USING BTREE,
  UNIQUE INDEX `uq_stok_rekalkulasi_sumber`(`sumber_uid` ASC) USING BTREE,
  INDEX `idx_stok_rekalkulasi_urut`(`id_produk` ASC, `id_lokasi` ASC, `tanggal` ASC, `ref_tabel` ASC, `ref_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 329 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblstok_rekalkulasi_state
-- ----------------------------
DROP TABLE IF EXISTS `tblstok_rekalkulasi_state`;
CREATE TABLE `tblstok_rekalkulasi_state`  (
  `id_produk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_lokasi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `saldo_pembuka` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `tanggal_terakhir` datetime NULL DEFAULT NULL,
  `ref_tabel_terakhir` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `ref_id_terakhir` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `stok_akhir` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `hitung_ulang_dari` datetime NULL DEFAULT NULL,
  `status_proses` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SIAP',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `diproses_pada` datetime(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id_produk`, `id_lokasi`) USING BTREE,
  INDEX `idx_stok_rekalkulasi_state`(`status_proses` ASC, `hitung_ulang_dari` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_capture_queue
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_capture_queue`;
CREATE TABLE `tblsync_capture_queue`  (
  `id_capture` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `capture_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation` enum('UPSERT','DELETE') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_version` bigint(20) UNSIGNED NOT NULL,
  `source_hint` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'LOCAL',
  `status_capture` enum('PENDING','PROCESSING','PUBLISHED','RETRY','DEAD_LETTER') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PENDING',
  `attempt_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `next_retry_at` datetime(6) NULL DEFAULT NULL,
  `last_error_code` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `last_error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `captured_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `processed_at` datetime(6) NULL DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_capture`) USING BTREE,
  UNIQUE INDEX `uq_sync_capture_uuid`(`capture_uuid` ASC) USING BTREE,
  UNIQUE INDEX `uq_sync_capture_aggregate`(`aggregate_type` ASC, `aggregate_id` ASC) USING BTREE,
  INDEX `idx_sync_capture_ready`(`status_capture` ASC, `next_retry_at` ASC, `id_capture` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4568 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_conflict
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_conflict`;
CREATE TABLE `tblsync_conflict`  (
  `conflict_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `event_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `device_id` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `entity_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `entity_id` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_id` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `local_version` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `remote_version` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `local_checksum` char(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `remote_checksum` char(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `conflict_code` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `conflict_summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status_conflict` enum('OPEN','RESOLVED_LOCAL','RESOLVED_REMOTE','RESOLVED_MERGED','IGNORED') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'OPEN',
  `resolution_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `detected_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `resolved_at` datetime(6) NULL DEFAULT NULL,
  `resolved_by` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  PRIMARY KEY (`conflict_uuid`) USING BTREE,
  UNIQUE INDEX `uq_sync_conflict_event_code`(`event_uuid` ASC, `conflict_code` ASC) USING BTREE,
  INDEX `idx_sync_conflict_open`(`status_conflict` ASC, `detected_at` ASC) USING BTREE,
  INDEX `idx_sync_conflict_aggregate`(`aggregate_type` ASC, `aggregate_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_cursor
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_cursor`;
CREATE TABLE `tblsync_cursor`  (
  `id_cursor` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `peer_device_id` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `stream_name` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SERVER_EVENT',
  `last_server_seq` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `last_push_at` datetime(6) NULL DEFAULT NULL,
  `last_pull_at` datetime(6) NULL DEFAULT NULL,
  `last_ack_at` datetime(6) NULL DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cursor`) USING BTREE,
  UNIQUE INDEX `uq_sync_cursor_peer_stream`(`peer_device_id` ASC, `stream_name` ASC) USING BTREE,
  INDEX `idx_sync_cursor_seq`(`last_server_seq` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_event
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_event`;
CREATE TABLE `tblsync_event`  (
  `server_seq` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `source_device_id` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `source_platform` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `entity_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `entity_id` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_id` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation` enum('UPSERT','DELETE','STATE_CHANGE','REVERSAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `schema_version` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `payload_checksum` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `occurred_at` datetime(6) NOT NULL,
  `server_received_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`server_seq`) USING BTREE,
  UNIQUE INDEX `uq_sync_event_uuid`(`event_uuid` ASC) USING BTREE,
  INDEX `idx_sync_event_aggregate`(`aggregate_type` ASC, `aggregate_id` ASC, `entity_version` ASC) USING BTREE,
  INDEX `idx_sync_event_device`(`source_device_id` ASC, `server_seq` ASC) USING BTREE,
  INDEX `idx_sync_event_received`(`server_received_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 979 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_identity_audit
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_identity_audit`;
CREATE TABLE `tblsync_identity_audit`  (
  `audit_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `identity_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `identity_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `identity_mask` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `owner_sync_uid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `owner_business_id` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `conflicting_sync_uid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `conflicting_business_id` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status_audit` enum('OPEN','RESOLVED','IGNORED') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'OPEN',
  `resolution_action` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `detected_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `last_seen_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `resolved_at` datetime(6) NULL DEFAULT NULL,
  `resolved_by` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`audit_uuid`) USING BTREE,
  UNIQUE INDEX `uq_sync_identity_audit_pair`(`identity_type` ASC, `identity_hash` ASC, `owner_sync_uid` ASC, `conflicting_sync_uid` ASC) USING BTREE,
  INDEX `idx_sync_identity_audit_open`(`status_audit` ASC, `identity_type` ASC, `detected_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_identity_quarantine
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_identity_quarantine`;
CREATE TABLE `tblsync_identity_quarantine`  (
  `quarantine_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `event_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_device_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `aggregate_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `aggregate_id` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `identity_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `identity_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `identity_mask` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `owner_sync_uid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `owner_business_id` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `payload_checksum` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quarantine_code` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quarantine_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status_quarantine` enum('OPEN','RESOLVED','IGNORED') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'OPEN',
  `detected_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `resolved_at` datetime(6) NULL DEFAULT NULL,
  `resolved_by` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `resolution_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`quarantine_uuid`) USING BTREE,
  UNIQUE INDEX `uq_sync_identity_quarantine_event`(`event_uuid` ASC) USING BTREE,
  INDEX `idx_sync_identity_quarantine_open`(`status_quarantine` ASC, `identity_type` ASC, `detected_at` ASC) USING BTREE,
  INDEX `idx_sync_identity_quarantine_aggregate`(`aggregate_type` ASC, `aggregate_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_inbox
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_inbox`;
CREATE TABLE `tblsync_inbox`  (
  `id_inbox` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `server_seq` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `source_device_id` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `entity_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `entity_id` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_id` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation` enum('UPSERT','DELETE','STATE_CHANGE','REVERSAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `payload_checksum` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status_inbox` enum('RECEIVED','APPLYING','APPLIED','RETRY','CONFLICT','REJECTED','DEAD_LETTER') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'RECEIVED',
  `attempt_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `next_retry_at` datetime(6) NULL DEFAULT NULL,
  `last_error_code` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `last_error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `received_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `applied_at` datetime(6) NULL DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_inbox`) USING BTREE,
  UNIQUE INDEX `uq_sync_inbox_event`(`event_uuid` ASC) USING BTREE,
  UNIQUE INDEX `uq_sync_inbox_server_seq`(`server_seq` ASC) USING BTREE,
  INDEX `idx_sync_inbox_status`(`status_inbox` ASC, `received_at` ASC) USING BTREE,
  INDEX `idx_sync_inbox_aggregate`(`aggregate_type` ASC, `aggregate_id` ASC, `entity_version` ASC) USING BTREE,
  INDEX `idx_sync_inbox_retry`(`status_inbox` ASC, `next_retry_at` ASC, `server_seq` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_outbox
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_outbox`;
CREATE TABLE `tblsync_outbox`  (
  `id_outbox` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `batch_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `source_device_id` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `source_platform` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'DESKTOP',
  `entity_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `entity_id` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_id` varchar(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation` enum('UPSERT','DELETE','STATE_CHANGE','REVERSAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `schema_version` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `payload_checksum` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status_outbox` enum('PENDING','SENDING','ACKED','RETRY','CONFLICT','DEAD_LETTER') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PENDING',
  `attempt_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `next_retry_at` datetime NULL DEFAULT NULL,
  `last_error_code` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `last_error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `occurred_at` datetime(6) NOT NULL,
  `locked_at` datetime(6) NULL DEFAULT NULL,
  `acked_at` datetime(6) NULL DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_outbox`) USING BTREE,
  UNIQUE INDEX `uq_sync_outbox_event`(`event_uuid` ASC) USING BTREE,
  INDEX `idx_sync_outbox_ready`(`status_outbox` ASC, `next_retry_at` ASC, `id_outbox` ASC) USING BTREE,
  INDEX `idx_sync_outbox_aggregate`(`aggregate_type` ASC, `aggregate_id` ASC, `entity_version` ASC) USING BTREE,
  INDEX `idx_sync_outbox_device`(`source_device_id` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_return_effect_log
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_return_effect_log`;
CREATE TABLE `tblsync_return_effect_log`  (
  `id_effect` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `effect_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `return_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_piutang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `total_before` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `total_after` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `paid_before` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `paid_after` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_effect`) USING BTREE,
  UNIQUE INDEX `uq_sync_return_effect`(`effect_type` ASC, `return_id` ASC) USING BTREE,
  INDEX `idx_sync_return_effect_debt`(`id_piutang` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_run
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_run`;
CREATE TABLE `tblsync_run`  (
  `run_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `device_id` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `direction` enum('HANDSHAKE','PUSH','PULL','BIDIRECTIONAL','BOOTSTRAP','RECONCILE') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status_run` enum('RUNNING','SUCCESS','PARTIAL','FAILED','CANCELLED') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'RUNNING',
  `cursor_before` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `cursor_after` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `events_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `events_applied` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `events_duplicate` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `events_retry` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `events_conflict` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `events_rejected` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `bytes_in` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `bytes_out` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `duration_ms` bigint(20) UNSIGNED NULL DEFAULT NULL,
  `app_version` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `schema_version` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `error_code` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `started_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `finished_at` datetime(6) NULL DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`run_uuid`) USING BTREE,
  INDEX `idx_sync_run_device_time`(`device_id` ASC, `started_at` ASC) USING BTREE,
  INDEX `idx_sync_run_status_time`(`status_run` ASC, `started_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_schema_version
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_schema_version`;
CREATE TABLE `tblsync_schema_version`  (
  `component` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `schema_version` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `schema_fingerprint` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `min_app_version` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`component`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_stock_baseline
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_stock_baseline`;
CREATE TABLE `tblsync_stock_baseline`  (
  `baseline_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_device_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status_baseline` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'RECEIVING',
  `expected_items` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `expected_chunks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `received_items` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `received_chunks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `content_checksum` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `captured_at` datetime(6) NOT NULL,
  `finalized_at` datetime(6) NULL DEFAULT NULL,
  `applied_at` datetime(6) NULL DEFAULT NULL,
  `last_error_code` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`baseline_uuid`) USING BTREE,
  INDEX `idx_sync_stock_baseline_device`(`source_device_id` ASC, `created_at` ASC) USING BTREE,
  INDEX `idx_sync_stock_baseline_status`(`status_baseline` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_stock_baseline_chunk
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_stock_baseline_chunk`;
CREATE TABLE `tblsync_stock_baseline_chunk`  (
  `baseline_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `chunk_index` int(10) UNSIGNED NOT NULL,
  `event_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_count` int(10) UNSIGNED NOT NULL,
  `chunk_checksum` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `received_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`baseline_uuid`, `chunk_index`) USING BTREE,
  UNIQUE INDEX `uq_sync_stock_baseline_chunk_event`(`event_uuid` ASC) USING BTREE,
  CONSTRAINT `fk_sync_stock_baseline_chunk_header` FOREIGN KEY (`baseline_uuid`) REFERENCES `tblsync_stock_baseline` (`baseline_uuid`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_stock_baseline_detail
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_stock_baseline_detail`;
CREATE TABLE `tblsync_stock_baseline_detail`  (
  `baseline_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_no` int(10) UNSIGNED NOT NULL,
  `chunk_index` int(10) UNSIGNED NOT NULL,
  `product_sync_uid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `location_sync_uid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `target_qty` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `id_produk_snapshot` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_lokasi_snapshot` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`baseline_uuid`, `item_no`) USING BTREE,
  UNIQUE INDEX `uq_sync_stock_baseline_product_location`(`baseline_uuid` ASC, `product_sync_uid` ASC, `location_sync_uid` ASC) USING BTREE,
  INDEX `idx_sync_stock_baseline_detail_chunk`(`baseline_uuid` ASC, `chunk_index` ASC) USING BTREE,
  CONSTRAINT `fk_sync_stock_baseline_detail_header` FOREIGN KEY (`baseline_uuid`) REFERENCES `tblsync_stock_baseline` (`baseline_uuid`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_stock_recovery_log
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_stock_recovery_log`;
CREATE TABLE `tblsync_stock_recovery_log`  (
  `id_recovery` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `recovery_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `event_aggregate_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `aggregate_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `business_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_device_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_lokasi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expected_qty` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `trigger_posted_qty` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `recovered_qty` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `final_delta` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `status_recovery` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'RECOVERED',
  `message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_recovery`) USING BTREE,
  UNIQUE INDEX `uq_sync_stock_recovery_event_item`(`event_aggregate_id` ASC, `id_produk` ASC, `id_lokasi` ASC) USING BTREE,
  UNIQUE INDEX `uq_sync_stock_recovery_uuid`(`recovery_uuid` ASC) USING BTREE,
  INDEX `idx_sync_stock_recovery_device`(`source_device_id` ASC, `created_at` ASC) USING BTREE,
  INDEX `idx_sync_stock_recovery_status`(`status_recovery` ASC, `created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tblsync_transaction_identity
-- ----------------------------
DROP TABLE IF EXISTS `tblsync_transaction_identity`;
CREATE TABLE `tblsync_transaction_identity`  (
  `aggregate_type` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `business_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `transaction_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `published_version` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `last_operation` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'POST',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`aggregate_type`, `business_id`) USING BTREE,
  UNIQUE INDEX `uq_sync_transaction_uuid`(`transaction_uuid` ASC) USING BTREE,
  INDEX `idx_sync_transaction_publish`(`published_version` ASC, `row_version` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbltransaksi_kas
-- ----------------------------
DROP TABLE IF EXISTS `tbltransaksi_kas`;
CREATE TABLE `tbltransaksi_kas`  (
  `id_transaksi_kas` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `request_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `tanggal` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `jenis` enum('MASUK','KELUAR','TRANSFER') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_bank_asal` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_bank_tujuan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `nominal` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `id_kategori` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `kategori` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('FINAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'FINAL',
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SYSTEM',
  `hapus` tinyint(1) NOT NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_transaksi_kas`) USING BTREE,
  UNIQUE INDEX `uq_transaksi_kas_sync_uid`(`sync_uid` ASC) USING BTREE,
  UNIQUE INDEX `uq_transaksi_kas_request_id`(`request_id` ASC) USING BTREE,
  INDEX `idx_transaksi_kas_tanggal`(`tanggal` ASC) USING BTREE,
  INDEX `idx_transaksi_kas_jenis`(`jenis` ASC) USING BTREE,
  INDEX `idx_transaksi_kas_bank_asal`(`id_bank_asal` ASC) USING BTREE,
  INDEX `idx_transaksi_kas_bank_tujuan`(`id_bank_tujuan` ASC) USING BTREE,
  INDEX `idx_transaksi_kas_shift`(`id_shift` ASC) USING BTREE,
  INDEX `idx_transaksi_kas_sync`(`sync` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbltransaksipembelian
-- ----------------------------
DROP TABLE IF EXISTS `tbltransaksipembelian`;
CREATE TABLE `tbltransaksipembelian`  (
  `id_pembelian` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_supiller` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `status` enum('Sukses','Batal','Proses') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Sukses',
  `total` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `bayar` decimal(18, 2) NULL DEFAULT 0.00,
  `id_faktur` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `id_po` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `masuk_ke` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `tambahan` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `potongan` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `mode_pembayaran` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `id_bank` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `keterangan` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `id_admin` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `sync` int(11) NULL DEFAULT 1,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_pembelian`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbltransaksipembelian_detail
-- ----------------------------
DROP TABLE IF EXISTS `tbltransaksipembelian_detail`;
CREATE TABLE `tbltransaksipembelian_detail`  (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `line_uid` char(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT uuid(),
  `id_produk` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `kategori` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `satuan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `isi` decimal(10, 2) NOT NULL DEFAULT 1.00,
  `jumlah` decimal(15, 2) NOT NULL DEFAULT 0.00,
  `harga` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_pembelian` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `masuk_ke` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `id_supiller` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `diskon` decimal(18, 2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(18, 2) NULL DEFAULT 0.00,
  `sync` int(11) NOT NULL DEFAULT 0,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `token_sync` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_produk`, `satuan`, `id_pembelian`) USING BTREE,
  UNIQUE INDEX `id_detail`(`id_detail` ASC) USING BTREE,
  UNIQUE INDEX `uq_pembelian_detail_line_uid`(`line_uid` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 51 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbltransaksipenjualan
-- ----------------------------
DROP TABLE IF EXISTS `tbltransaksipenjualan`;
CREATE TABLE `tbltransaksipenjualan`  (
  `id_transaksi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `no_faktur` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `id_member` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `customer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `total_kotor` decimal(15, 0) NOT NULL DEFAULT 0,
  `potongan` decimal(15, 0) NOT NULL DEFAULT 0,
  `potongan_poin` int(11) NOT NULL,
  `ppn` decimal(15, 0) NOT NULL DEFAULT 0,
  `grand_total` decimal(15, 0) NOT NULL DEFAULT 0,
  `bayar` decimal(15, 0) NOT NULL DEFAULT 0,
  `kembalian` decimal(15, 0) NOT NULL DEFAULT 0,
  `mode_pembayaran` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'TUNAI',
  `id_bank` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'CASH',
  `id_shift` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `source_type` enum('NORMAL','LEGACY_HISTORY') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'NORMAL',
  `legacy_source` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `legacy_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `sync` int(11) NOT NULL DEFAULT 0,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_transaksi`) USING BTREE,
  UNIQUE INDEX `uk_penjualan_legacy_source`(`legacy_source` ASC, `legacy_id` ASC) USING BTREE,
  INDEX `idx_tanggal`(`tanggal` ASC) USING BTREE,
  INDEX `idx_member`(`id_member` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbltransaksipenjualan_bank
-- ----------------------------
DROP TABLE IF EXISTS `tbltransaksipenjualan_bank`;
CREATE TABLE `tbltransaksipenjualan_bank`  (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `id_transaksi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_bank` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nominal` decimal(18, 2) NULL DEFAULT 0.00,
  `no_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '-',
  `tanggal` datetime NULL DEFAULT current_timestamp(),
  `sync` tinyint(4) NULL DEFAULT 1,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_detail`) USING BTREE,
  INDEX `idx_transaksi`(`id_transaksi` ASC) USING BTREE,
  INDEX `idx_bank`(`id_bank` ASC) USING BTREE,
  INDEX `idx_sync`(`sync` ASC) USING BTREE,
  CONSTRAINT `fk_tb_bank` FOREIGN KEY (`id_bank`) REFERENCES `tbldata_bank` (`id_bank`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbltransaksipenjualan_detail
-- ----------------------------
DROP TABLE IF EXISTS `tbltransaksipenjualan_detail`;
CREATE TABLE `tbltransaksipenjualan_detail`  (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `line_uid` char(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT uuid(),
  `id_transaksi` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_produk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kategori` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_group` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `satuan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `isi_satuan` decimal(18, 4) NOT NULL DEFAULT 1.0000,
  `harga_beli` decimal(15, 0) NOT NULL,
  `harga_jual` decimal(15, 0) NOT NULL,
  `jumlah` decimal(10, 2) NOT NULL,
  `diskon_item` decimal(15, 0) NOT NULL DEFAULT 0,
  `total_subtotal` decimal(15, 0) NOT NULL,
  `poin` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'Retail',
  `terjual` int(11) NOT NULL DEFAULT 0,
  `id_lokasi` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'TOKO',
  `sync` int(11) NOT NULL DEFAULT 0,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `last_sync` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id_detail`) USING BTREE,
  UNIQUE INDEX `uq_penjualan_detail_line_uid`(`line_uid` ASC) USING BTREE,
  INDEX `fk_jual_detail`(`id_transaksi` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 68 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbltransferbarang
-- ----------------------------
DROP TABLE IF EXISTS `tbltransferbarang`;
CREATE TABLE `tbltransferbarang`  (
  `id_transfer` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_produk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jumlah` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `qty_input` decimal(18, 4) NOT NULL DEFAULT 0.0000,
  `satuan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PCS',
  `isi` decimal(18, 4) NOT NULL DEFAULT 1.0000,
  `jenis_dari` enum('TOKO','GUDANG','LAINNYA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `dari` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jenis_ke` enum('TOKO','GUDANG','LAINNYA') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ke` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NULL DEFAULT current_timestamp(),
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `sync` tinyint(1) NOT NULL DEFAULT 1,
  `last_sync` datetime NULL DEFAULT NULL,
  `token_sync` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id_transfer`, `id_produk`, `dari`, `ke`) USING BTREE,
  INDEX `idx_produk`(`id_produk` ASC) USING BTREE,
  INDEX `idx_tanggal`(`tanggal` ASC) USING BTREE,
  INDEX `idx_sync`(`sync` ASC) USING BTREE,
  INDEX `idx_dari`(`dari` ASC) USING BTREE,
  INDEX `idx_ke`(`ke` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tbluser
-- ----------------------------
DROP TABLE IF EXISTS `tbluser`;
CREATE TABLE `tbluser`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sync_uid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `credential_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `password_changed_at` datetime NULL DEFAULT NULL,
  `nama_lengkap` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `id_role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'OWNER',
  `status` tinyint(1) NULL DEFAULT 1,
  `hapus` tinyint(1) NULL DEFAULT 0,
  `row_version` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `sync` tinyint(1) NULL DEFAULT 1,
  `last_login` datetime NULL DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `username`(`username` ASC) USING BTREE,
  UNIQUE INDEX `uq_user_sync_uid`(`sync_uid` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 15 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Procedure structure for sp_finalize_legacy_stock_opening
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_finalize_legacy_stock_opening`;
delimiter ;;
CREATE PROCEDURE `sp_finalize_legacy_stock_opening`(IN p_id_opening VARCHAR(120))
BEGIN
    DECLARE v_done TINYINT DEFAULT 0;
    DECLARE v_header_uid CHAR(36);
    DECLARE v_tanggal DATETIME;
    DECLARE v_admin VARCHAR(50);
    DECLARE v_lokasi VARCHAR(20);
    DECLARE v_status VARCHAR(10);
    DECLARE v_detail_uid CHAR(36);
    DECLARE v_produk VARCHAR(20);
    DECLARE v_qty DECIMAL(18,4);

    DECLARE detail_cursor CURSOR FOR
        SELECT `sync_uid`,`id_produk`,`qty`
        FROM `tbllegacy_stock_opening_detail`
        WHERE `id_opening`=p_id_opening
        ORDER BY `id_detail`;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done=1;

    SELECT `sync_uid`,`tanggal`,`id_admin`,`id_lokasi`,`status`
    INTO v_header_uid,v_tanggal,v_admin,v_lokasi,v_status
    FROM `tbllegacy_stock_opening`
    WHERE `id_opening`=p_id_opening
    LIMIT 1;

    IF v_header_uid IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Final stok awal ditolak: header tidak ditemukan.';
    END IF;
    IF COALESCE(v_status,'')<>'FINAL' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Final stok awal ditolak: header belum FINAL.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM `tbllegacy_stock_opening_detail`
        WHERE `id_opening`=p_id_opening LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Final stok awal ditolak: detail masih kosong.';
    END IF;

    OPEN detail_cursor;
    detail_loop: LOOP
        FETCH detail_cursor INTO v_detail_uid,v_produk,v_qty;
        IF v_done=1 THEN
            LEAVE detail_loop;
        END IF;
        CALL `sp_post_stok_v2`(
            'LEGACY_STOCK_OPENING',
            v_detail_uid,
            v_header_uid,
            p_id_opening,
            v_produk,
            v_lokasi,
            'OPENING',
            v_qty,
            0,
            v_tanggal,
            v_admin,
            CONCAT('Stok awal migrasi ',p_id_opening)
        );
    END LOOP;
    CLOSE detail_cursor;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_post_po_receipt_line_v2
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_post_po_receipt_line_v2`;
delimiter ;;
CREATE PROCEDURE `sp_post_po_receipt_line_v2`(IN p_detail_id bigint unsigned)
receipt_post: BEGIN
    DECLARE v_receipt varchar(30);
    DECLARE v_product varchar(50);
    DECLARE v_location varchar(50);
    DECLARE v_admin varchar(50);
    DECLARE v_date datetime;
    DECLARE v_status varchar(20);
    DECLARE v_deleted tinyint;
    DECLARE v_header_deleted tinyint;
    DECLARE v_qty decimal(18,4);
    DECLARE v_contents decimal(18,4);
    IF COALESCE(@DISABLE_TRIGGER,0)<>0 THEN LEAVE receipt_post; END IF;
    SELECT d.id_terima,d.id_produk,d.hapus,d.qty_terima,d.isi,
           h.masuk_ke,h.id_admin,h.tanggal,h.status,h.hapus
    INTO v_receipt,v_product,v_deleted,v_qty,v_contents,
         v_location,v_admin,v_date,v_status,v_header_deleted
    FROM tblpurchase_order_terima_detail d
    JOIN tblpurchase_order_terima h ON h.id_terima=d.id_terima
    WHERE d.id_terima_detail=p_detail_id;
    IF COALESCE(v_deleted,1)<>0 OR COALESCE(v_header_deleted,1)<>0
       OR COALESCE(v_status,'') NOT IN ('DITERIMA','FINAL') THEN
        LEAVE receipt_post;
    END IF;
    IF COALESCE(v_qty,0)<=0 OR COALESCE(v_contents,0)<=0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Penerimaan PO: qty dan isi harus positif.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM tblstok_posting_log
        WHERE ref_tabel='PURCHASE_ORDER_RECEIPT' AND ref_id=CONCAT(p_detail_id)
          AND (id_produk<>v_product OR id_lokasi<>v_location OR posting_role<>'MAIN')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Penerimaan PO terposting: identitas stok tidak boleh berubah.';
    END IF;
    CALL sp_post_stok_v2(
        'PURCHASE_ORDER_RECEIPT',CONCAT(p_detail_id),NULL,v_receipt,
        v_product,v_location,'MAIN',v_qty*v_contents,0,
        COALESCE(v_date,NOW()),COALESCE(NULLIF(v_admin,''),'SYSTEM'),
        CONCAT('Penerimaan PO ',v_receipt)
    );
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_post_saldo
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_post_saldo`;
delimiter ;;
CREATE PROCEDURE `sp_post_saldo`(IN p_ref_tabel VARCHAR(80),
    IN p_ref_id VARCHAR(120),
    IN p_id_bank VARCHAR(50),
    IN p_debit DECIMAL(18,2),
    IN p_kredit DECIMAL(18,2),
    IN p_tanggal DATETIME,
    IN p_id_admin VARCHAR(50),
    IN p_jenis VARCHAR(50),
    IN p_kategori VARCHAR(80),
    IN p_keterangan TEXT)
BEGIN
    DECLARE v_type VARCHAR(40);
    DECLARE v_role VARCHAR(30) DEFAULT 'MAIN';
    SET v_type=CASE LOWER(TRIM(COALESCE(p_ref_tabel,'')))
        WHEN 'tbltransaksipenjualan' THEN 'SALE'
        WHEN 'sync_transaction_sale' THEN 'SALE'
        WHEN 'tblpiutangpenjualan_log' THEN 'RECEIVABLE_PAYMENT'
        WHEN 'tbltransaksipembelian' THEN 'PURCHASE'
        WHEN 'sync_transaction_purchase' THEN 'PURCHASE'
        WHEN 'tblpiutangpembelian_log' THEN 'PAYABLE_PAYMENT'
        WHEN 'tblpurchase_order_dp' THEN 'PURCHASE_ORDER_DP'
        WHEN 'sync_payroll' THEN 'SALARY'
        WHEN 'sync_transaction_sale_return' THEN 'SALE_RETURN'
        WHEN 'sync_transaction_purchase_return' THEN 'PURCHASE_RETURN'
        ELSE UPPER(TRIM(COALESCE(NULLIF(p_ref_tabel,''),'LEGACY')))
    END;
    IF v_type IN ('RECEIVABLE_PAYMENT','PURCHASE_ORDER_DP')
       AND UPPER(COALESCE(p_ref_id,'')) LIKE '%-DP%' THEN
        SET v_role='DP';
    ELSEIF UPPER(COALESCE(p_jenis,'')) LIKE 'BATAL\_%' THEN
        SET v_role='REVERSAL';
    ELSEIF UPPER(COALESCE(p_jenis,'')) IN ('TRANSFER','SETOR') THEN
        SET v_role=IF(COALESCE(p_kredit,0)>0,'TRANSFER_OUT','TRANSFER_IN');
    END IF;

    IF v_role='REVERSAL' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Gunakan sp_post_saldo_v2 untuk jurnal pembalik.';
    END IF;

    CALL `sp_post_saldo_v2`(
        v_type,p_ref_id,NULL,p_ref_id,p_id_bank,v_role,p_debit,p_kredit,p_tanggal,
        p_id_admin,p_jenis,p_kategori,p_keterangan,NULL,NULL
    );
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_post_saldo_v2
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_post_saldo_v2`;
delimiter ;;
CREATE PROCEDURE `sp_post_saldo_v2`(IN p_source_type varchar(40),
    IN p_source_id varchar(120),
    IN p_source_uid char(36),
    IN p_no_referensi varchar(120),
    IN p_id_bank varchar(50),
    IN p_posting_role varchar(30),
    IN p_debit decimal(18,2),
    IN p_kredit decimal(18,2),
    IN p_tanggal datetime,
    IN p_id_admin varchar(50),
    IN p_jenis varchar(50),
    IN p_kategori varchar(80),
    IN p_keterangan text,
    IN p_reversal_of_uid char(36),
    IN p_id_shift varchar(30))
BEGIN
    DECLARE v_source_type varchar(40);
    DECLARE v_source_id varchar(120);
    DECLARE v_reference varchar(120);
    DECLARE v_role varchar(30);
    DECLARE v_posting_id bigint unsigned DEFAULT NULL;
    DECLARE v_ledger_count int DEFAULT 0;
    DECLARE v_existing_debit decimal(18,2) DEFAULT 0.00;
    DECLARE v_existing_kredit decimal(18,2) DEFAULT 0.00;
    DECLARE v_existing_reference varchar(120);
    DECLARE v_existing_source_uid char(36);
    DECLARE v_existing_reversal_uid char(36);
    DECLARE v_existing_shift varchar(30);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET @LEDGER_CANONICAL_WRITE=0;
        RESIGNAL;
    END;

    SET @LEDGER_CANONICAL_WRITE=0;
    SET v_source_type=UPPER(TRIM(COALESCE(p_source_type,'')));
    SET v_source_id=TRIM(COALESCE(p_source_id,''));
    SET v_reference=TRIM(COALESCE(NULLIF(p_no_referensi,''),p_source_id,''));
    SET v_role=UPPER(TRIM(COALESCE(NULLIF(p_posting_role,''),'MAIN')));

    IF v_source_type='' OR v_source_id='' OR v_reference='' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: sumber dan referensi wajib diisi.';
    END IF;
    IF COALESCE(TRIM(p_id_bank),'')='' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: akun kas/bank wajib diisi.';
    END IF;
    IF COALESCE(p_debit,0)<0 OR COALESCE(p_kredit,0)<0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: mutasi tidak boleh negatif.';
    END IF;
    IF COALESCE(p_debit,0)>0 AND COALESCE(p_kredit,0)>0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: debit dan kredit tidak boleh bersamaan.';
    END IF;
    IF COALESCE(p_debit,0)=0 AND COALESCE(p_kredit,0)=0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: nilai mutasi harus lebih dari nol.';
    END IF;
    IF v_role='REVERSAL' AND COALESCE(TRIM(p_reversal_of_uid),'')='' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: pembalik wajib menunjuk ledger asli.';
    END IF;
    IF v_role<>'REVERSAL' AND COALESCE(TRIM(p_reversal_of_uid),'')<>'' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: ledger utama tidak boleh memiliki reversal_of_uid.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM `tbldata_bank`
        WHERE `id_bank`=p_id_bank AND COALESCE(`hapus`,0)=0
          AND COALESCE(`status`,1)=1
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: akun kas/bank tidak aktif atau tidak ditemukan.';
    END IF;
    IF v_role='REVERSAL' AND NOT EXISTS (
        SELECT 1 FROM `tblbank_ledger`
        WHERE `sync_uid`=p_reversal_of_uid
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: ledger asli pembalik tidak ditemukan.';
    END IF;
    IF v_role='REVERSAL' AND EXISTS (
        SELECT 1 FROM `tblbank_ledger`
        WHERE `reversal_of_uid`=p_reversal_of_uid
          AND `posting_role`='REVERSAL'
          AND NOT (
              `source_type`=v_source_type AND `source_id`=v_source_id
              AND `id_bank`=p_id_bank AND `posting_role`=v_role
          )
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo ditolak: ledger sudah pernah dibalik.';
    END IF;

    INSERT IGNORE INTO `tblsaldo_posting_log`(
        `ref_tabel`,`ref_id`,`source_uid`,`no_referensi`,`id_bank`,`id_shift`,
        `posting_role`,`reversal_of_uid`,`debit`,`kredit`,`tanggal`,`created_at`
    ) VALUES (
        v_source_type,v_source_id,NULLIF(TRIM(p_source_uid),''),v_reference,p_id_bank,
        NULLIF(TRIM(p_id_shift),''),v_role,NULLIF(TRIM(p_reversal_of_uid),''),
        COALESCE(p_debit,0),COALESCE(p_kredit,0),COALESCE(p_tanggal,NOW()),NOW()
    );

    SELECT `id`,`debit`,`kredit`,`no_referensi`,`source_uid`,`reversal_of_uid`,`id_shift`
    INTO v_posting_id,v_existing_debit,v_existing_kredit,v_existing_reference,
         v_existing_source_uid,v_existing_reversal_uid,v_existing_shift
    FROM `tblsaldo_posting_log`
    WHERE `ref_tabel`=v_source_type AND `ref_id`=v_source_id
      AND `id_bank`=p_id_bank AND `posting_role`=v_role
    LIMIT 1
    FOR UPDATE;

    IF v_posting_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo gagal: log idempotensi tidak terbentuk.';
    END IF;
    IF ABS(v_existing_debit-COALESCE(p_debit,0))>0.01
       OR ABS(v_existing_kredit-COALESCE(p_kredit,0))>0.01
       OR COALESCE(v_existing_reference,'')<>v_reference
       OR COALESCE(v_existing_source_uid,'')
            <>COALESCE(NULLIF(TRIM(p_source_uid),''),'')
       OR COALESCE(v_existing_reversal_uid,'')
            <>COALESCE(NULLIF(TRIM(p_reversal_of_uid),''),'')
       OR COALESCE(v_existing_shift,'')
            <>COALESCE(NULLIF(TRIM(p_id_shift),''),'')
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Konflik idempotensi saldo: sumber sama memiliki isi berbeda.';
    END IF;

    SELECT COUNT(*) INTO v_ledger_count
    FROM `tblbank_ledger`
    WHERE `source_type`=v_source_type AND `source_id`=v_source_id
      AND `id_bank`=p_id_bank AND `posting_role`=v_role;

    IF v_ledger_count=0 THEN
        SET @LEDGER_CANONICAL_WRITE=1;
        INSERT INTO `tblbank_ledger`(
            `sync_uid`,`id_bank`,`tanggal`,`no_referensi`,`source_type`,`source_id`,
            `source_uid`,`posting_role`,`reversal_of_uid`,`jenis`,`kategori`,`id_shift`,
            `keterangan`,`debit`,`kredit`,`saldo_akhir`,`id_admin`,`created_at`
        ) VALUES (
            LOWER(UUID()),p_id_bank,COALESCE(p_tanggal,NOW()),v_reference,
            v_source_type,v_source_id,NULLIF(TRIM(p_source_uid),''),v_role,
            NULLIF(TRIM(p_reversal_of_uid),''),p_jenis,p_kategori,
            NULLIF(TRIM(p_id_shift),''),
            LEFT(COALESCE(NULLIF(p_keterangan,''),v_reference),255),
            COALESCE(p_debit,0),COALESCE(p_kredit,0),0,
            COALESCE(NULLIF(p_id_admin,''),'SYSTEM'),NOW(6)
        );
        SET @LEDGER_CANONICAL_WRITE=0;
    ELSEIF v_ledger_count=1 THEN
        SELECT `debit`,`kredit`,`no_referensi`,`source_uid`,`reversal_of_uid`,`id_shift`
        INTO v_existing_debit,v_existing_kredit,v_existing_reference,
             v_existing_source_uid,v_existing_reversal_uid,v_existing_shift
        FROM `tblbank_ledger`
        WHERE `source_type`=v_source_type AND `source_id`=v_source_id
          AND `id_bank`=p_id_bank AND `posting_role`=v_role
        LIMIT 1;

        IF ABS(v_existing_debit-COALESCE(p_debit,0))>0.01
           OR ABS(v_existing_kredit-COALESCE(p_kredit,0))>0.01
           OR COALESCE(v_existing_reference,'')<>v_reference
           OR COALESCE(v_existing_source_uid,'')
                <>COALESCE(NULLIF(TRIM(p_source_uid),''),'')
           OR COALESCE(v_existing_reversal_uid,'')
                <>COALESCE(NULLIF(TRIM(p_reversal_of_uid),''),'')
           OR COALESCE(v_existing_shift,'')
                <>COALESCE(NULLIF(TRIM(p_id_shift),''),'')
        THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Konflik idempotensi saldo: ledger sumber memiliki isi berbeda.';
        END IF;
    ELSE
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting saldo gagal: ledger sumber terduplikasi.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_post_stok
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_post_stok`;
delimiter ;;
CREATE PROCEDURE `sp_post_stok`(IN p_ref_tabel varchar(80),
    IN p_ref_id varchar(120),
    IN p_id_produk varchar(50),
    IN p_id_lokasi varchar(50),
    IN p_masuk decimal(18,4),
    IN p_keluar decimal(18,4),
    IN p_tanggal datetime,
    IN p_id_admin varchar(50),
    IN p_keterangan text)
BEGIN
    DECLARE v_source_type varchar(40);
    DECLARE v_role varchar(30) DEFAULT 'MAIN';

    SET v_source_type=CASE p_ref_tabel
        WHEN 'tbltransaksipenjualan_detail' THEN 'SALE'
        WHEN 'tbltransaksipembelian_detail' THEN 'PURCHASE'
        WHEN 'tblreturpenjualan_detail' THEN 'SALE_RETURN'
        WHEN 'tblreturpembelian_detail' THEN 'PURCHASE_RETURN'
        WHEN 'tblpurchase_order_terima_detail' THEN 'PURCHASE_ORDER_RECEIPT'
        WHEN 'tblstok_opname' THEN 'STOCK_OPNAME'
        WHEN 'tbltransferbarang_dari' THEN 'STOCK_TRANSFER'
        WHEN 'tbltransferbarang_ke' THEN 'STOCK_TRANSFER'
        ELSE UPPER(TRIM(COALESCE(p_ref_tabel,'LEGACY_STOCK')))
    END;
    SET v_role=CASE p_ref_tabel
        WHEN 'tbltransferbarang_dari' THEN 'TRANSFER_OUT'
        WHEN 'tbltransferbarang_ke' THEN 'TRANSFER_IN'
        ELSE 'MAIN'
    END;

    CALL sp_post_stok_v2(
        v_source_type,p_ref_id,NULL,p_ref_id,p_id_produk,p_id_lokasi,v_role,
        p_masuk,p_keluar,p_tanggal,p_id_admin,p_keterangan
    );
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_post_stok_v2
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_post_stok_v2`;
delimiter ;;
CREATE PROCEDURE `sp_post_stok_v2`(IN p_source_type varchar(40),
    IN p_source_id varchar(120),
    IN p_source_uid char(36),
    IN p_no_referensi varchar(120),
    IN p_id_produk varchar(50),
    IN p_id_lokasi varchar(50),
    IN p_posting_role varchar(30),
    IN p_masuk decimal(18,4),
    IN p_keluar decimal(18,4),
    IN p_tanggal datetime,
    IN p_id_admin varchar(50),
    IN p_keterangan text)
stock_posting: BEGIN
    DECLARE v_source_type varchar(40);
    DECLARE v_source_id varchar(120);
    DECLARE v_reference varchar(120);
    DECLARE v_role varchar(30);
    DECLARE v_stok decimal(18,4) DEFAULT 0.0000;
    DECLARE v_stok_baru decimal(18,4) DEFAULT 0.0000;
    DECLARE v_boleh_negatif tinyint DEFAULT 0;
    DECLARE v_existing int DEFAULT 0;
    DECLARE v_existing_masuk decimal(18,4) DEFAULT 0.0000;
    DECLARE v_existing_keluar decimal(18,4) DEFAULT 0.0000;
    DECLARE v_existing_reference varchar(120);
    DECLARE v_existing_source_uid char(36);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET @STOCK_CANONICAL_WRITE=0;
        RESIGNAL;
    END;

    SET @STOCK_CANONICAL_WRITE=0;
    SET v_source_type=UPPER(TRIM(COALESCE(p_source_type,'')));
    SET v_source_id=TRIM(COALESCE(p_source_id,''));
    SET v_reference=TRIM(COALESCE(NULLIF(p_no_referensi,''),p_source_id,''));
    SET v_role=UPPER(TRIM(COALESCE(NULLIF(p_posting_role,''),'MAIN')));

    IF v_source_type='' OR v_source_id='' OR v_reference='' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting stok ditolak: sumber dan referensi wajib diisi.';
    END IF;
    IF COALESCE(TRIM(p_id_produk),'')='' OR COALESCE(TRIM(p_id_lokasi),'')='' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting stok ditolak: produk dan lokasi wajib diisi.';
    END IF;
    IF COALESCE(p_masuk,0)<0 OR COALESCE(p_keluar,0)<0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting stok ditolak: mutasi tidak boleh negatif.';
    END IF;
    IF COALESCE(p_masuk,0)>0 AND COALESCE(p_keluar,0)>0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting stok ditolak: masuk dan keluar tidak boleh bersamaan.';
    END IF;
    IF COALESCE(p_masuk,0)=0 AND COALESCE(p_keluar,0)=0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting stok ditolak: nilai mutasi harus lebih dari nol.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM tbldatabarang
        WHERE id_produk=p_id_produk AND COALESCE(hapus,0)=0
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting stok ditolak: produk tidak aktif atau tidak ditemukan.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM tbldata_gudang
        WHERE id_gudang=p_id_lokasi AND COALESCE(hapus,0)=0
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting stok ditolak: lokasi tidak aktif atau tidak ditemukan.';
    END IF;

    SELECT COUNT(*)
    INTO v_existing
    FROM tblstok_posting_log
    WHERE ref_tabel=v_source_type
      AND ref_id=v_source_id
      AND id_produk=p_id_produk
      AND id_lokasi=p_id_lokasi
      AND posting_role=v_role;

    IF v_existing>0 THEN
        SELECT masuk,keluar,no_referensi,source_uid
        INTO v_existing_masuk,v_existing_keluar,v_existing_reference,v_existing_source_uid
        FROM tblstok_posting_log
        WHERE ref_tabel=v_source_type
          AND ref_id=v_source_id
          AND id_produk=p_id_produk
          AND id_lokasi=p_id_lokasi
          AND posting_role=v_role
        LIMIT 1;

        IF v_existing_masuk=COALESCE(p_masuk,0)
           AND v_existing_keluar=COALESCE(p_keluar,0)
           AND v_existing_reference=v_reference
           AND (
                COALESCE(TRIM(p_source_uid),'')=''
                OR COALESCE(v_existing_source_uid,'')=COALESCE(p_source_uid,'')
           )
        THEN
            LEAVE stock_posting;
        END IF;

        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Konflik posting stok: sumber sama membawa nilai berbeda.';
    END IF;

    SET @STOCK_CANONICAL_WRITE=1;
    INSERT IGNORE INTO tbldatabarang_stok(id_produk,id_lokasi,stok,sync)
    VALUES (p_id_produk,p_id_lokasi,0,1);

    SELECT stok
    INTO v_stok
    FROM tbldatabarang_stok
    WHERE id_produk=p_id_produk AND id_lokasi=p_id_lokasi
    FOR UPDATE;

    SET v_stok_baru=v_stok+COALESCE(p_masuk,0)-COALESCE(p_keluar,0);
    SELECT COALESCE(MAX(CASE WHEN nilai='1' THEN 1 ELSE 0 END),0)
    INTO v_boleh_negatif
    FROM tblpengaturan
    WHERE kode='stok_boleh_negatif' AND COALESCE(hapus,0)=0;

    IF v_stok_baru<0 AND v_boleh_negatif<>1 THEN
        SET @STOCK_CANONICAL_WRITE=0;
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Stok tidak mencukupi dan pengaturan stok negatif tidak diizinkan.';
    END IF;

    INSERT INTO tblstok_posting_log(
        ref_tabel,ref_id,source_uid,no_referensi,posting_role,
        id_produk,id_lokasi,masuk,keluar,tanggal,created_at
    ) VALUES (
        v_source_type,v_source_id,NULLIF(TRIM(p_source_uid),''),v_reference,v_role,
        p_id_produk,p_id_lokasi,COALESCE(p_masuk,0),COALESCE(p_keluar,0),
        COALESCE(p_tanggal,NOW()),NOW()
    );

    UPDATE tbldatabarang_stok
    SET stok=v_stok_baru,sync=1
    WHERE id_produk=p_id_produk AND id_lokasi=p_id_lokasi;

    INSERT INTO tblstok(
        sync_uid,source_type,source_id,source_uid,no_referensi,posting_role,
        id_produk,id_target,penambahan,pengurangan,stok_akhir,tanggal,
        id_admin,keterangan,status,sync,created_at
    ) VALUES (
        LOWER(UUID()),v_source_type,v_source_id,NULLIF(TRIM(p_source_uid),''),
        v_reference,v_role,p_id_produk,p_id_lokasi,
        COALESCE(p_masuk,0),COALESCE(p_keluar,0),v_stok_baru,
        COALESCE(p_tanggal,NOW()),
        COALESCE(NULLIF(TRIM(p_id_admin),''),'SYSTEM'),p_keterangan,0,1,NOW(6)
    );
    SET @STOCK_CANONICAL_WRITE=0;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_sync_audit_secondary_identities
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_sync_audit_secondary_identities`;
delimiter ;;
CREATE PROCEDURE `sp_sync_audit_secondary_identities`()
BEGIN
    INSERT INTO tblsync_identity_audit (
        audit_uuid,identity_type,identity_hash,identity_mask,
        owner_sync_uid,owner_business_id,conflicting_sync_uid,conflicting_business_id
    )
    SELECT UUID(),'MEMBER_PHONE',SHA2(a.sync_phone_normalized,256),
           CONCAT(LEFT(a.sync_phone_normalized,3),'***',RIGHT(a.sync_phone_normalized,3)),
           a.sync_uid,a.id_member,b.sync_uid,b.id_member
    FROM tbldata_member a
    JOIN tbldata_member b
      ON b.sync_phone_normalized=a.sync_phone_normalized
     AND b.id_member>a.id_member
    WHERE a.sync_phone_normalized IS NOT NULL
    ON DUPLICATE KEY UPDATE last_seen_at=CURRENT_TIMESTAMP(6);

    INSERT INTO tblsync_identity_audit (
        audit_uuid,identity_type,identity_hash,identity_mask,
        owner_sync_uid,owner_business_id,conflicting_sync_uid,conflicting_business_id
    )
    SELECT UUID(),'PRODUCT_PLU',SHA2(a.sync_plu_normalized,256),
           CONCAT(LEFT(a.sync_plu_normalized,4),'***'),
           a.sync_uid,a.id_produk,b.sync_uid,b.id_produk
    FROM tbldatabarang a
    JOIN tbldatabarang b
      ON b.sync_plu_normalized=a.sync_plu_normalized
     AND b.id_produk>a.id_produk
    WHERE a.sync_plu_normalized IS NOT NULL
    ON DUPLICATE KEY UPDATE last_seen_at=CURRENT_TIMESTAMP(6);

    INSERT INTO tblsync_identity_audit (
        audit_uuid,identity_type,identity_hash,identity_mask,
        owner_sync_uid,owner_business_id,conflicting_sync_uid,conflicting_business_id
    )
    SELECT UUID(),'PRODUCT_SERIAL',SHA2(a.sync_serial_normalized,256),
           CONCAT(LEFT(a.sync_serial_normalized,4),'***',RIGHT(a.sync_serial_normalized,3)),
           a.sync_uid,CAST(a.id_serial AS CHAR),b.sync_uid,CAST(b.id_serial AS CHAR)
    FROM tbldatabarang_serial a
    JOIN tbldatabarang_serial b
      ON b.sync_serial_normalized=a.sync_serial_normalized
     AND b.id_serial>a.id_serial
    WHERE a.sync_serial_normalized IS NOT NULL
    ON DUPLICATE KEY UPDATE last_seen_at=CURRENT_TIMESTAMP(6);

    INSERT INTO tblsync_identity_audit (
        audit_uuid,identity_type,identity_hash,identity_mask,
        owner_sync_uid,owner_business_id,conflicting_sync_uid,conflicting_business_id
    )
    SELECT UUID(),d.identity_type,SHA2(d.normalized_name,256),d.normalized_name,
           d.owner_uid,d.owner_name,d.conflict_uid,d.conflict_name
    FROM (
        SELECT 'MASTER_KATEGORI' identity_type,a.sync_name_normalized normalized_name,
               a.sync_uid owner_uid,a.kategori owner_name,b.sync_uid conflict_uid,b.kategori conflict_name
        FROM tblkategori a JOIN tblkategori b
          ON b.sync_name_normalized=a.sync_name_normalized AND b.kategori>a.kategori
        WHERE a.sync_name_normalized IS NOT NULL
        UNION ALL
        SELECT 'MASTER_MEREK',a.sync_name_normalized,a.sync_uid,a.merek,b.sync_uid,b.merek
        FROM tblmerek a JOIN tblmerek b
          ON b.sync_name_normalized=a.sync_name_normalized AND b.merek>a.merek
        WHERE a.sync_name_normalized IS NOT NULL
        UNION ALL
        SELECT 'MASTER_SATUAN',a.sync_name_normalized,a.sync_uid,a.satuan,b.sync_uid,b.satuan
        FROM tblsatuan a JOIN tblsatuan b
          ON b.sync_name_normalized=a.sync_name_normalized AND b.satuan>a.satuan
        WHERE a.sync_name_normalized IS NOT NULL
    ) d
    ON DUPLICATE KEY UPDATE last_seen_at=CURRENT_TIMESTAMP(6);
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_sync_capture_catalog
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_sync_capture_catalog`;
delimiter ;;
CREATE PROCEDURE `sp_sync_capture_catalog`(IN p_type varchar(80),IN p_uid char(36),IN p_deleted tinyint,IN p_version bigint unsigned)
  SQL SECURITY INVOKER
BEGIN
    INSERT INTO tblsync_capture_queue(capture_uuid,aggregate_type,aggregate_id,operation,entity_version,source_hint,status_capture,captured_at)
    VALUES(LOWER(UUID()),p_type,p_uid,IF(p_deleted=1,'DELETE','UPSERT'),p_version,COALESCE(NULLIF(@SYNC_SOURCE_DEVICE_ID,''),'LOCAL'),'PENDING',CURRENT_TIMESTAMP(6))
    ON DUPLICATE KEY UPDATE capture_uuid=VALUES(capture_uuid),operation=VALUES(operation),entity_version=VALUES(entity_version),source_hint=VALUES(source_hint),status_capture='PENDING',attempt_count=0,next_retry_at=NULL,last_error_code=NULL,last_error_message=NULL,captured_at=VALUES(captured_at),processed_at=NULL;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_sync_capture_source_transaction
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_sync_capture_source_transaction`;
delimiter ;;
CREATE PROCEDURE `sp_sync_capture_source_transaction`(IN p_aggregate_type varchar(80),
    IN p_business_id varchar(120),
    IN p_sync_uid char(36),
    IN p_captured_at datetime(6))
BEGIN
    DECLARE v_existing_uid char(36);
    DECLARE v_version bigint unsigned DEFAULT 1;

    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0
       AND EXISTS (
           SELECT 1 FROM information_schema.TABLES
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblsync_transaction_identity'
       )
       AND EXISTS (
           SELECT 1 FROM information_schema.TABLES
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblsync_capture_queue'
       ) THEN
        IF COALESCE(TRIM(p_aggregate_type),'')=''
           OR COALESCE(TRIM(p_business_id),'')=''
           OR COALESCE(TRIM(p_sync_uid),'')='' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Capture transaksi sumber ditolak: identitas wajib diisi.';
        END IF;

        SELECT MAX(`transaction_uuid`),COALESCE(MAX(`row_version`),1)
          INTO v_existing_uid,v_version
        FROM `tblsync_transaction_identity`
        WHERE `aggregate_type`=p_aggregate_type
          AND `business_id`=p_business_id;

        IF v_existing_uid IS NULL THEN
            SET v_version=1;
            INSERT INTO `tblsync_transaction_identity`(
                `aggregate_type`,`business_id`,`transaction_uuid`,
                `row_version`,`published_version`,`last_operation`
            ) VALUES (
                p_aggregate_type,p_business_id,LOWER(p_sync_uid),1,0,'POST'
            );
        ELSEIF LOWER(v_existing_uid)<>LOWER(p_sync_uid) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Capture transaksi sumber ditolak: UUID bisnis berbeda.';
        END IF;

        INSERT INTO `tblsync_capture_queue`(
            `capture_uuid`,`aggregate_type`,`aggregate_id`,`operation`,
            `entity_version`,`source_hint`,`status_capture`,`captured_at`
        ) VALUES (
            LOWER(UUID()),p_aggregate_type,LOWER(p_sync_uid),'UPSERT',
            v_version,LEFT(p_business_id,120),'PENDING',
            COALESCE(p_captured_at,CURRENT_TIMESTAMP(6))
        )
        ON DUPLICATE KEY UPDATE
            `operation`='UPSERT',
            `entity_version`=v_version,
            `source_hint`=LEFT(p_business_id,120),
            `status_capture`='PENDING',
            `attempt_count`=0,
            `next_retry_at`=NULL,
            `last_error_code`=NULL,
            `last_error_message`=NULL,
            `captured_at`=COALESCE(p_captured_at,CURRENT_TIMESTAMP(6)),
            `processed_at`=NULL,
            `updated_at`=CURRENT_TIMESTAMP(6);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_sync_capture_transaction
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_sync_capture_transaction`;
delimiter ;;
CREATE PROCEDURE `sp_sync_capture_transaction`(IN p_aggregate_type VARCHAR(80),
    IN p_business_id VARCHAR(120),
    IN p_operation VARCHAR(20),
    IN p_captured_at DATETIME(6))
BEGIN
    DECLARE v_exists INT DEFAULT 0;
    DECLARE v_uuid CHAR(36);
    DECLARE v_version BIGINT UNSIGNED DEFAULT 1;

    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN
        IF COALESCE(p_aggregate_type,'')='' OR COALESCE(p_business_id,'')='' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Capture transaksi ditolak: aggregate dan business ID wajib diisi.';
        END IF;

        SELECT COUNT(*) INTO v_exists
        FROM tblsync_transaction_identity
        WHERE aggregate_type=p_aggregate_type AND business_id=p_business_id;

        IF v_exists=0 THEN
            SET v_uuid=LOWER(UUID());
            INSERT INTO tblsync_transaction_identity(
                aggregate_type,business_id,transaction_uuid,row_version,published_version,last_operation
            ) VALUES(p_aggregate_type,p_business_id,v_uuid,1,0,p_operation);
            SET v_version=1;
        ELSE
            UPDATE tblsync_transaction_identity
            SET row_version=row_version+1,last_operation=p_operation,updated_at=CURRENT_TIMESTAMP(6)
            WHERE aggregate_type=p_aggregate_type AND business_id=p_business_id;
            SELECT transaction_uuid,row_version INTO v_uuid,v_version
            FROM tblsync_transaction_identity
            WHERE aggregate_type=p_aggregate_type AND business_id=p_business_id
            LIMIT 1;
        END IF;

        INSERT INTO tblsync_capture_queue(
            capture_uuid,aggregate_type,aggregate_id,operation,entity_version,
            source_hint,status_capture,captured_at
        ) VALUES(
            LOWER(UUID()),p_aggregate_type,v_uuid,'UPSERT',v_version,
            LEFT(p_business_id,120),'PENDING',COALESCE(p_captured_at,CURRENT_TIMESTAMP(6))
        )
        ON DUPLICATE KEY UPDATE
            operation='UPSERT',entity_version=v_version,source_hint=LEFT(p_business_id,120),
            status_capture='PENDING',attempt_count=0,next_retry_at=NULL,
            last_error_code=NULL,last_error_message=NULL,captured_at=COALESCE(p_captured_at,CURRENT_TIMESTAMP(6)),
            processed_at=NULL,updated_at=CURRENT_TIMESTAMP(6);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblbank_ledger
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_bank_ledger_guard_bi`;
delimiter ;;
CREATE TRIGGER `trg_bank_ledger_guard_bi` BEFORE INSERT ON `tblbank_ledger` FOR EACH ROW BEGIN
    IF COALESCE(@LEDGER_CANONICAL_WRITE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Ledger immutable: gunakan transaksi sumber dan trigger canonical.';
    END IF;
    IF COALESCE(NULLIF(TRIM(NEW.`sync_uid`),''),'')='' THEN
        SET NEW.`sync_uid`=LOWER(UUID());
    END IF;
    SET NEW.`source_type`=UPPER(TRIM(NEW.`source_type`));
    SET NEW.`source_id`=TRIM(NEW.`source_id`);
    SET NEW.`posting_role`=UPPER(TRIM(NEW.`posting_role`));
    SET NEW.`created_at`=COALESCE(NEW.`created_at`,CURRENT_TIMESTAMP(6));
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblbank_ledger
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_inkremental_saldo_ledger`;
delimiter ;;
CREATE TRIGGER `trg_inkremental_saldo_ledger` BEFORE INSERT ON `tblbank_ledger` FOR EACH ROW BEGIN
    DECLARE v_saldo decimal(18,2) DEFAULT 0.00;
    DECLARE v_found tinyint DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_found=0;

    IF COALESCE(@DISABLE_TRIGGER,0)=0 THEN
        SET v_found=1;
        SELECT COALESCE(`saldo`,0)
        INTO v_saldo
        FROM `tbldata_bank`
        WHERE `id_bank`=NEW.`id_bank` AND COALESCE(`hapus`,0)=0
        LIMIT 1
        FOR UPDATE;

        IF v_found=0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Ledger ditolak: akun kas/bank tidak ditemukan.';
        END IF;

        SET NEW.`saldo_akhir`=
            v_saldo+COALESCE(NEW.`debit`,0)-COALESCE(NEW.`kredit`,0);
        UPDATE `tbldata_bank`
        SET `saldo`=NEW.`saldo_akhir`,`sync`=1
        WHERE `id_bank`=NEW.`id_bank`;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblbank_ledger
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_bank_ledger_guard_bu`;
delimiter ;;
CREATE TRIGGER `trg_bank_ledger_guard_bu` BEFORE UPDATE ON `tblbank_ledger` FOR EACH ROW BEGIN
    IF COALESCE(@LEDGER_MAINTENANCE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Ledger immutable: perubahan baris tidak diizinkan.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblbank_ledger
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_bank_ledger_guard_bd`;
delimiter ;;
CREATE TRIGGER `trg_bank_ledger_guard_bd` BEFORE DELETE ON `tblbank_ledger` FOR EACH ROW BEGIN
    IF COALESCE(@LEDGER_MAINTENANCE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Ledger immutable: penghapusan baris tidak diizinkan.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_bank
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_bank_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_bank_bi` BEFORE INSERT ON `tbldata_bank` FOR EACH ROW BEGIN
    IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF;
    SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1);
    SET NEW.updated_at=CURRENT_TIMESTAMP(6);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_bank
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_bank_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_bank_bu` BEFORE UPDATE ON `tbldata_bank` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (
        NOT(NEW.nama_bank<=>OLD.nama_bank) OR NOT(NEW.no_rekening<=>OLD.no_rekening) OR
        NOT(NEW.atas_nama<=>OLD.atas_nama) OR NOT(NEW.jenis<=>OLD.jenis) OR
        NOT(NEW.biaya_admin<=>OLD.biaya_admin) OR NOT(NEW.status<=>OLD.status) OR
        NOT(NEW.hapus<=>OLD.hapus)
    ) THEN
        SET NEW.row_version=OLD.row_version+1;
        SET NEW.sync=1;
        SET NEW.updated_at=CURRENT_TIMESTAMP(6);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_bank
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_bank_prevent_update`;
delimiter ;;
CREATE TRIGGER `trg_bank_prevent_update` BEFORE UPDATE ON `tbldata_bank` FOR EACH ROW BEGIN
    IF OLD.id_bank <> NEW.id_bank
       AND (OLD.id_bank = 'CASH' OR OLD.id_bank = 'KAS_LACI') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'ID sistem utama CASH/KAS_LACI tidak boleh diubah.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_bank
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_bank_prevent_delete`;
delimiter ;;
CREATE TRIGGER `trg_bank_prevent_delete` BEFORE DELETE ON `tbldata_bank` FOR EACH ROW BEGIN
    IF OLD.id_bank = 'CASH' OR OLD.id_bank = 'KAS_LACI' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Data sistem utama CASH/KAS_LACI tidak boleh dihapus.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_bank
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_bank_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_bank_ai` AFTER INSERT ON `tbldata_bank` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN
        CALL sp_sync_capture_catalog('MASTER_BANK',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_bank
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_bank_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_bank_au` AFTER UPDATE ON `tbldata_bank` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN
        CALL sp_sync_capture_catalog('MASTER_BANK',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_gudang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gudang_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_gudang_bi` BEFORE INSERT ON `tbldata_gudang` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1); SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_gudang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gudang_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_gudang_bu` BEFORE UPDATE ON `tbldata_gudang` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.nama<=>OLD.nama) OR NOT(NEW.lokasi<=>OLD.lokasi) OR NOT(NEW.keterangan<=>OLD.keterangan) OR NOT(NEW.hapus<=>OLD.hapus)) THEN SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_gudang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gudang_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_gudang_ai` AFTER INSERT ON `tbldata_gudang` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MASTER_GUDANG',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_gudang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gudang_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_gudang_au` AFTER UPDATE ON `tbldata_gudang` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MASTER_GUDANG',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_member
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_bi` BEFORE INSERT ON `tbldata_member` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1); SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_member
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_bu` BEFORE UPDATE ON `tbldata_member` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.kelas_member<=>OLD.kelas_member) OR NOT(NEW.nama<=>OLD.nama) OR NOT(NEW.alamat<=>OLD.alamat) OR NOT(NEW.no_hp<=>OLD.no_hp) OR NOT(NEW.email<=>OLD.email) OR NOT(NEW.status<=>OLD.status) OR NOT(NEW.is_unlimited<=>OLD.is_unlimited) OR NOT(NEW.masa_berlaku<=>OLD.masa_berlaku) OR NOT(NEW.hapus<=>OLD.hapus)) THEN SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_member
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_ai` AFTER INSERT ON `tbldata_member` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MASTER_MEMBER',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_member
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_point_delta_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_point_delta_ai` AFTER INSERT ON `tbldata_member` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND COALESCE(NEW.poin,0)>0 THEN
        INSERT INTO tblmember_poin_log(sync_uid,id_member,jenis,masuk,keluar,saldo_akhir,tanggal,keterangan,id_admin,hapus,row_version,sync,updated_at)
        VALUES(LOWER(UUID()),NEW.id_member,'SALDO_AWAL',NEW.poin,0,NEW.poin,NOW(),'Saldo awal poin member',COALESCE(NULLIF(NEW.id_admin,''),'SYSTEM'),0,1,1,CURRENT_TIMESTAMP(6));
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_member
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_au` AFTER UPDATE ON `tbldata_member` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MASTER_MEMBER',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_member
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_point_delta_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_point_delta_au` AFTER UPDATE ON `tbldata_member` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND COALESCE(NEW.poin,0)<>COALESCE(OLD.poin,0) THEN
        INSERT INTO tblmember_poin_log(sync_uid,id_member,jenis,masuk,keluar,saldo_akhir,tanggal,keterangan,id_admin,hapus,row_version,sync,updated_at)
        VALUES(LOWER(UUID()),NEW.id_member,'PENYESUAIAN',GREATEST(NEW.poin-OLD.poin,0),GREATEST(OLD.poin-NEW.poin,0),NEW.poin,NOW(),'Mutasi poin dari transaksi/aplikasi',COALESCE(NULLIF(@SYNC_SOURCE_DEVICE_ID,''),'SYSTEM'),0,1,1,CURRENT_TIMESTAMP(6));
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_supiller
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_supplier_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_supplier_bi` BEFORE INSERT ON `tbldata_supiller` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1); SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_supiller
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_supplier_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_supplier_bu` BEFORE UPDATE ON `tbldata_supiller` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.nama<=>OLD.nama) OR NOT(NEW.alamat<=>OLD.alamat) OR NOT(NEW.kota<=>OLD.kota) OR NOT(NEW.provinsi<=>OLD.provinsi) OR NOT(NEW.negara<=>OLD.negara) OR NOT(NEW.kodepos<=>OLD.kodepos) OR NOT(NEW.telepon<=>OLD.telepon) OR NOT(NEW.fax<=>OLD.fax) OR NOT(NEW.email<=>OLD.email) OR NOT(NEW.kontak<=>OLD.kontak) OR NOT(NEW.hapus<=>OLD.hapus)) THEN SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_supiller
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_supplier_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_supplier_ai` AFTER INSERT ON `tbldata_supiller` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MASTER_SUPPLIER',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldata_supiller
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_supplier_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_supplier_au` AFTER UPDATE ON `tbldata_supiller` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MASTER_SUPPLIER',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_produk_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_produk_bi` BEFORE INSERT ON `tbldatabarang` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1); SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_barang_insert_calc`;
delimiter ;;
CREATE TRIGGER `trg_barang_insert_calc` BEFORE INSERT ON `tbldatabarang` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=1 THEN
        SET NEW.`sync`=0;
    ELSEIF COALESCE(@DISABLE_TRIGGER,0)=0 THEN
        IF NEW.`markup_mode` IN ('%','PERSEN') THEN
            SET NEW.`harga_jual`=NEW.`harga_beli`+(NEW.`harga_beli`*NEW.`markup_value`/100);
        ELSEIF NEW.`markup_mode` IN ('+','NOMINAL') THEN
            SET NEW.`harga_jual`=NEW.`harga_beli`+NEW.`markup_value`;
        END IF;
        SET NEW.`sync`=1;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_produk_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_produk_bu` BEFORE UPDATE ON `tbldatabarang` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.nama<=>OLD.nama) OR NOT(NEW.kategori<=>OLD.kategori) OR NOT(NEW.merek<=>OLD.merek) OR NOT(NEW.id_rak<=>OLD.id_rak) OR NOT(NEW.id_sub<=>OLD.id_sub) OR NOT(NEW.harga_beli<=>OLD.harga_beli) OR NOT(NEW.harga_jual<=>OLD.harga_jual) OR NOT(NEW.markup_mode<=>OLD.markup_mode) OR NOT(NEW.markup_value<=>OLD.markup_value) OR NOT(NEW.min_stok<=>OLD.min_stok) OR NOT(NEW.ph<=>OLD.ph) OR NOT(NEW.kode<=>OLD.kode) OR NOT(NEW.keterangan<=>OLD.keterangan) OR NOT(NEW.id_group<=>OLD.id_group) OR NOT(NEW.hapus<=>OLD.hapus)) THEN SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_barang_update_calc`;
delimiter ;;
CREATE TRIGGER `trg_barang_update_calc` BEFORE UPDATE ON `tbldatabarang` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=1 THEN
        SET NEW.`sync`=0;
    ELSEIF COALESCE(@DISABLE_TRIGGER,0)=0 THEN
        IF NOT(NEW.`harga_beli`<=>OLD.`harga_beli`)
           OR NOT(NEW.`markup_mode`<=>OLD.`markup_mode`)
           OR NOT(NEW.`markup_value`<=>OLD.`markup_value`) THEN
            IF NEW.`markup_mode` IN ('%','PERSEN') THEN
                SET NEW.`harga_jual`=NEW.`harga_beli`+(NEW.`harga_beli`*NEW.`markup_value`/100);
            ELSEIF NEW.`markup_mode` IN ('+','NOMINAL') THEN
                SET NEW.`harga_jual`=NEW.`harga_beli`+NEW.`markup_value`;
            END IF;
        END IF;
        SET NEW.`sync`=1;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_produk_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_produk_ai` AFTER INSERT ON `tbldatabarang` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MASTER_PRODUK',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_produk_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_produk_au` AFTER UPDATE ON `tbldatabarang` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MASTER_PRODUK',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_expired
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_expired_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_expired_bi` BEFORE INSERT ON `tbldatabarang_expired` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_expired
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_expired_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_expired_bu` BEFORE UPDATE ON `tbldatabarang_expired` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_expired
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_expired_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_expired_ai` AFTER INSERT ON `tbldatabarang_expired` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('PRODUCT_BATCH',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_expired
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_expired_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_expired_au` AFTER UPDATE ON `tbldatabarang_expired` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('PRODUCT_BATCH',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_serial
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_serial_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_serial_bi` BEFORE INSERT ON `tbldatabarang_serial` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_serial
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_serial_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_serial_bu` BEFORE UPDATE ON `tbldatabarang_serial` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_serial
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_serial_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_serial_ai` AFTER INSERT ON `tbldatabarang_serial` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('PRODUCT_SERIAL',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_serial
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_serial_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_serial_au` AFTER UPDATE ON `tbldatabarang_serial` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('PRODUCT_SERIAL',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_stok
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_balance_guard_bi`;
delimiter ;;
CREATE TRIGGER `trg_stok_balance_guard_bi` BEFORE INSERT ON `tbldatabarang_stok` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_CANONICAL_WRITE,0)<>1
       AND COALESCE(@STOCK_MAINTENANCE,0)<>1
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Saldo stok hanya boleh dibentuk oleh sp_post_stok_v2.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_stok
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_balance_guard_bu`;
delimiter ;;
CREATE TRIGGER `trg_stok_balance_guard_bu` BEFORE UPDATE ON `tbldatabarang_stok` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_CANONICAL_WRITE,0)<>1
       AND COALESCE(@STOCK_MAINTENANCE,0)<>1
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Saldo stok hanya boleh diubah oleh sp_post_stok_v2.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbldatabarang_stok
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_balance_guard_bd`;
delimiter ;;
CREATE TRIGGER `trg_stok_balance_guard_bd` BEFORE DELETE ON `tbldatabarang_stok` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_MAINTENANCE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Saldo stok tidak boleh dihapus langsung.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gaji_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_gaji_bi` BEFORE INSERT ON `tblgaji` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gaji_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_gaji_bu` BEFORE UPDATE ON `tblgaji` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gaji_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_gaji_ai` AFTER INSERT ON `tblgaji` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('PAYROLL',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_gaji_saldo_insert`;
delimiter ;;
CREATE TRIGGER `trg_gaji_saldo_insert` AFTER INSERT ON `tblgaji` FOR EACH ROW BEGIN
    IF (@DISABLE_TRIGGER IS NULL OR @DISABLE_TRIGGER=0)
       AND UPPER(COALESCE(NEW.status,''))='DIBAYAR'
       AND COALESCE(NEW.hapus,0)=0 AND COALESCE(NEW.total_bayar,0)>0
       AND COALESCE(NEW.id_bank,'')<>''
    THEN
        CALL `sp_post_saldo_v2`(
            'SALARY',NEW.id_gaji,NEW.sync_uid,NEW.id_gaji,NEW.id_bank,'MAIN',
            0,NEW.total_bayar,COALESCE(NEW.tanggal,NOW()),NEW.id_admin,
            'GAJI_KARYAWAN','Gaji Karyawan',CONCAT('Pembayaran gaji ',NEW.id_gaji),
            NULL,NEW.id_shift
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gaji_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_gaji_au` AFTER UPDATE ON `tblgaji` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('PAYROLL',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_gaji_saldo_update`;
delimiter ;;
CREATE TRIGGER `trg_gaji_saldo_update` AFTER UPDATE ON `tblgaji` FOR EACH ROW BEGIN
    IF (@DISABLE_TRIGGER IS NULL OR @DISABLE_TRIGGER=0)
       AND UPPER(COALESCE(OLD.status,''))<>'DIBAYAR'
       AND UPPER(COALESCE(NEW.status,''))='DIBAYAR'
       AND COALESCE(NEW.hapus,0)=0 AND COALESCE(NEW.total_bayar,0)>0
       AND COALESCE(NEW.id_bank,'')<>''
    THEN
        CALL `sp_post_saldo_v2`(
            'SALARY',NEW.id_gaji,NEW.sync_uid,NEW.id_gaji,NEW.id_bank,'MAIN',
            0,NEW.total_bayar,COALESCE(NEW.tanggal,NOW()),NEW.id_admin,
            'GAJI_KARYAWAN','Gaji Karyawan',CONCAT('Pembayaran gaji ',NEW.id_gaji),
            NULL,NEW.id_shift
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gaji_detail_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_gaji_detail_bi` BEFORE INSERT ON `tblgaji_detail` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gaji_detail_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_gaji_detail_bu` BEFORE UPDATE ON `tblgaji_detail` FOR EACH ROW BEGIN SET NEW.sync_uid=OLD.sync_uid; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gaji_detail_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_gaji_detail_ai` AFTER INSERT ON `tblgaji_detail` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblgaji SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_gaji=NEW.id_gaji; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblgaji_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_gaji_detail_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_gaji_detail_au` AFTER UPDATE ON `tblgaji_detail` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblgaji SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_gaji=NEW.id_gaji; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_diskon
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hd_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_hd_bi` BEFORE INSERT ON `tblharga_diskon` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_diskon
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hd_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_hd_ai` AFTER INSERT ON `tblharga_diskon` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tbldatabarang SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_produk=NEW.id_produk; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_diskon
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hd_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_hd_au` AFTER UPDATE ON `tblharga_diskon` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.diskon<=>OLD.diskon) OR NOT(NEW.dari<=>OLD.dari) OR NOT(NEW.sampai<=>OLD.sampai) OR NOT(NEW.satuan<=>OLD.satuan) OR NOT(NEW.min<=>OLD.min) OR NOT(NEW.status<=>OLD.status) OR NOT(NEW.mode_diskon<=>OLD.mode_diskon) OR NOT(NEW.hapus<=>OLD.hapus)) THEN UPDATE tbldatabarang SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_produk=NEW.id_produk; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_grosir
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hg_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_hg_bi` BEFORE INSERT ON `tblharga_grosir` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_grosir
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hg_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_hg_ai` AFTER INSERT ON `tblharga_grosir` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tbldatabarang SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_produk=NEW.id_produk; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_grosir
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hg_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_hg_au` AFTER UPDATE ON `tblharga_grosir` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.nama_grosir<=>OLD.nama_grosir) OR NOT(NEW.min<=>OLD.min) OR NOT(NEW.harga_jual<=>OLD.harga_jual) OR NOT(NEW.hapus<=>OLD.hapus)) THEN UPDATE tbldatabarang SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_produk=NEW.id_produk; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_member
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hm_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_hm_bi` BEFORE INSERT ON `tblharga_member` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_member
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hm_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_hm_ai` AFTER INSERT ON `tblharga_member` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tbldatabarang SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_produk=NEW.id_produk; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_member
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hm_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_hm_au` AFTER UPDATE ON `tblharga_member` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.id_kelas<=>OLD.id_kelas) OR NOT(NEW.harga_jual<=>OLD.harga_jual) OR NOT(NEW.harga_diskon<=>OLD.harga_diskon) OR NOT(NEW.hapus<=>OLD.hapus)) THEN UPDATE tbldatabarang SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_produk=NEW.id_produk; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_satuan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hs_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_hs_bi` BEFORE INSERT ON `tblharga_satuan` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_satuan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hs_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_hs_ai` AFTER INSERT ON `tblharga_satuan` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tbldatabarang SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_produk=NEW.id_produk; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblharga_satuan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_hs_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_hs_au` AFTER UPDATE ON `tblharga_satuan` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.barcode<=>OLD.barcode) OR NOT(NEW.isi<=>OLD.isi) OR NOT(NEW.harga_jual<=>OLD.harga_jual) OR NOT(NEW.poin<=>OLD.poin) OR NOT(NEW.hapus<=>OLD.hapus) OR NOT(NEW.nama_satuan<=>OLD.nama_satuan)) THEN UPDATE tbldatabarang SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_produk=NEW.id_produk; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbljurnal_pembalik
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_jurnal_pembalik_bi`;
delimiter ;;
CREATE TRIGGER `trg_jurnal_pembalik_bi` BEFORE INSERT ON `tbljurnal_pembalik` FOR EACH ROW BEGIN
    SET NEW.`sync_uid`=LOWER(COALESCE(NULLIF(TRIM(NEW.`sync_uid`),''),UUID()));
    SET NEW.`source_type_asal`=UPPER(TRIM(NEW.`source_type_asal`));
    SET NEW.`source_id_asal`=TRIM(NEW.`source_id_asal`);
    SET NEW.`status`='FINAL';
    SET NEW.`hapus`=0;
    SET NEW.`row_version`=GREATEST(COALESCE(NEW.`row_version`,1),1);
    SET NEW.`sync`=IF(COALESCE(@SYNC_APPLY_REMOTE,0)=1,0,1);

    IF NEW.`source_type_asal`='' OR NEW.`source_id_asal`='' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Jurnal pembalik ditolak: sumber transaksi wajib diisi.';
    END IF;
    IF CHAR_LENGTH(TRIM(COALESCE(NEW.`alasan`,'')))<5 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Jurnal pembalik ditolak: alasan minimal 5 karakter.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbljurnal_pembalik
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_jurnal_pembalik_bu`;
delimiter ;;
CREATE TRIGGER `trg_jurnal_pembalik_bu` BEFORE UPDATE ON `tbljurnal_pembalik` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_METADATA_ONLY,0)=0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Jurnal pembalik final tidak boleh diubah.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbljurnal_pembalik
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_jurnal_pembalik_bd`;
delimiter ;;
CREATE TRIGGER `trg_jurnal_pembalik_bd` BEFORE DELETE ON `tbljurnal_pembalik` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT='Jurnal pembalik final tidak boleh dihapus.';
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbljurnal_pembalik
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_jurnal_pembalik_ai`;
delimiter ;;
CREATE TRIGGER `trg_jurnal_pembalik_ai` AFTER INSERT ON `tbljurnal_pembalik` FOR EACH ROW BEGIN
    DECLARE v_done int DEFAULT 0;
    DECLARE v_count int DEFAULT 0;
    DECLARE v_uid char(36);
    DECLARE v_bank varchar(50);
    DECLARE v_debit decimal(18,2);
    DECLARE v_kredit decimal(18,2);
    DECLARE v_jenis varchar(50);
    DECLARE v_kategori varchar(80);
    DECLARE v_shift varchar(30);
    DECLARE v_payment_count int DEFAULT 0;
    DECLARE v_payment_uid char(36);
    DECLARE v_payment_ref varchar(120);
    DECLARE v_payment_bank varchar(50);
    DECLARE v_payment_amount decimal(18,2);
    DECLARE v_payment_date datetime;
    DECLARE v_payment_admin varchar(50);
    DECLARE v_payment_shift varchar(30);
    DECLARE v_payment_mode varchar(30);
    DECLARE v_payment_deleted int;
    DECLARE v_payment_note text;
    DECLARE v_payment_parent varchar(50);
    DECLARE cur_ledger CURSOR FOR
        SELECT `sync_uid`,`id_bank`,`debit`,`kredit`,`jenis`,`kategori`,`id_shift`
        FROM `tblbank_ledger`
        WHERE `source_type`=NEW.`source_type_asal`
          AND `source_id`=NEW.`source_id_asal`
          AND `posting_role`<>'REVERSAL'
        ORDER BY `id_ledger`;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done=1;

    CALL `sp_sync_capture_source_transaction`(
        'LEDGER_REVERSAL',NEW.`id_pembalik`,NEW.`sync_uid`,NEW.`tanggal`
    );

    IF COALESCE(@DISABLE_TRIGGER,0)=0 THEN
        SELECT COUNT(*) INTO v_count
        FROM `tblbank_ledger`
        WHERE `source_type`=NEW.`source_type_asal`
          AND `source_id`=NEW.`source_id_asal`
          AND `posting_role`<>'REVERSAL';
        IF v_count=0 AND COALESCE(@SYNC_APPLY_REMOTE,0)=1
            AND NEW.source_type_asal IN ('RECEIVABLE_PAYMENT','PAYABLE_PAYMENT') THEN
            IF NEW.source_type_asal='RECEIVABLE_PAYMENT' THEN
                SELECT COUNT(*) INTO v_payment_count
                FROM tblpiutangpenjualan_log
                WHERE COALESCE(NULLIF(TRIM(request_id),''),no_ref)=NEW.source_id_asal;
                IF v_payment_count=1 THEN
                    SELECT sync_uid,no_ref,id_bank,jumlah,tanggal,id_admin,id_shift,mode_pembayaran,hapus,keterangan,id_piutang
                    INTO v_payment_uid,v_payment_ref,v_payment_bank,v_payment_amount,v_payment_date,
                         v_payment_admin,v_payment_shift,v_payment_mode,v_payment_deleted,v_payment_note,v_payment_parent
                    FROM tblpiutangpenjualan_log
                    WHERE COALESCE(NULLIF(TRIM(request_id),''),no_ref)=NEW.source_id_asal;
                END IF;
            ELSE
                SELECT COUNT(*) INTO v_payment_count FROM tblpiutangpembelian_log
                WHERE id_pembayaran=NEW.source_id_asal;
                IF v_payment_count=1 THEN
                    SELECT sync_uid,id_pembayaran,id_bank,jumlah,tanggal,id_admin,id_shift,mode_pembayaran,hapus,keterangan,id_piutang
                    INTO v_payment_uid,v_payment_ref,v_payment_bank,v_payment_amount,v_payment_date,
                         v_payment_admin,v_payment_shift,v_payment_mode,v_payment_deleted,v_payment_note,v_payment_parent
                    FROM tblpiutangpembelian_log WHERE id_pembayaran=NEW.source_id_asal;
                END IF;
            END IF;
            IF v_payment_count<>1 OR COALESCE(v_payment_deleted,0)<>1
                OR COALESCE(v_payment_amount,0)<=0
                OR COALESCE(v_payment_uid,'') NOT REGEXP '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$'
                OR v_payment_date IS NULL
                OR COALESCE(v_payment_bank,'') IN ('','SYSTEM','RETUR')
                OR COALESCE(v_payment_ref,'') IN ('','-')
                OR UPPER(COALESCE(v_payment_ref,'')) LIKE '%-DP%'
                OR UPPER(COALESCE(v_payment_mode,'')) IN ('DP','DP_PO','SYSTEM','RETUR') THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pemulihan pembalik ditolak: sumber pembayaran batal tidak valid atau ambigu.';
            END IF;
            SELECT COUNT(*) INTO v_payment_count FROM tbldata_bank WHERE id_bank=v_payment_bank;
            IF v_payment_count<>1 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pemulihan pembalik ditolak: akun asal tidak tersedia atau ambigu.';
            END IF;
            IF NEW.source_type_asal='RECEIVABLE_PAYMENT' THEN
                SELECT COUNT(*) INTO v_payment_count FROM tblpiutangpenjualan WHERE id_piutang=v_payment_parent;
            ELSE
                SELECT COUNT(*) INTO v_payment_count FROM tblpiutangpembelian WHERE id_piutang=v_payment_parent;
            END IF;
            IF v_payment_count<>1 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pemulihan pembalik ditolak: induk pembayaran tidak tersedia atau ambigu.';
            END IF;
            CALL sp_post_saldo_v2(
                NEW.source_type_asal,NEW.source_id_asal,v_payment_uid,v_payment_ref,v_payment_bank,'MAIN',
                IF(NEW.source_type_asal='RECEIVABLE_PAYMENT',v_payment_amount,0),
                IF(NEW.source_type_asal='PAYABLE_PAYMENT',v_payment_amount,0),
                v_payment_date,v_payment_admin,
                IF(NEW.source_type_asal='RECEIVABLE_PAYMENT','PIUTANG','HUTANG_PEMBELIAN'),
                IF(NEW.source_type_asal='RECEIVABLE_PAYMENT','Pembayaran Piutang','Pembayaran Hutang Supplier'),
                IF(NEW.source_type_asal='RECEIVABLE_PAYMENT',
                    COALESCE(NULLIF(v_payment_note,''),CONCAT('Pembayaran piutang ',v_payment_parent)),
                    CONCAT('Pembayaran hutang supplier ',v_payment_parent)),NULL,v_payment_shift
            );
            SELECT COUNT(*) INTO v_count FROM tblbank_ledger
            WHERE source_type=NEW.source_type_asal AND source_id=NEW.source_id_asal AND posting_role<>'REVERSAL';
        END IF;

        IF v_count=0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT='Jurnal pembalik ditolak: ledger sumber tidak ditemukan.';
        END IF;

        OPEN cur_ledger;
        read_loop: LOOP
            FETCH cur_ledger INTO v_uid,v_bank,v_debit,v_kredit,v_jenis,v_kategori,v_shift;
            IF v_done=1 THEN
                LEAVE read_loop;
            END IF;
            CALL `sp_post_saldo_v2`(
                'REVERSAL',CONCAT(NEW.`id_pembalik`,':',LEFT(v_uid,36)),NEW.`sync_uid`,
                NEW.`id_pembalik`,v_bank,'REVERSAL',
                v_kredit,v_debit,NEW.`tanggal`,NEW.`id_admin`,
                LEFT(CONCAT('BATAL_',COALESCE(v_jenis,'TRANSAKSI')),50),
                v_kategori,NEW.`alasan`,v_uid,v_shift
            );
        END LOOP;
        CLOSE cur_ledger;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkaryawan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_karyawan_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_karyawan_bi` BEFORE INSERT ON `tblkaryawan` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkaryawan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_karyawan_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_karyawan_bu` BEFORE UPDATE ON `tblkaryawan` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkaryawan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_karyawan_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_karyawan_ai` AFTER INSERT ON `tblkaryawan` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MASTER_KARYAWAN',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkaryawan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_karyawan_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_karyawan_au` AFTER UPDATE ON `tblkaryawan` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MASTER_KARYAWAN',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_kategori
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kas_kategori_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_kas_kategori_bi` BEFORE INSERT ON `tblkas_kategori` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_kategori
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kas_kategori_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_kas_kategori_bu` BEFORE UPDATE ON `tblkas_kategori` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_kategori
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kas_kategori_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_kas_kategori_ai` AFTER INSERT ON `tblkas_kategori` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MASTER_KATEGORI_KAS',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_kategori
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kas_kategori_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_kas_kategori_au` AFTER UPDATE ON `tblkas_kategori` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MASTER_KATEGORI_KAS',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_mutasi
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kas_mutasi_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_kas_mutasi_bi` BEFORE INSERT ON `tblkas_mutasi` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_mutasi
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kas_mutasi_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_kas_mutasi_bu` BEFORE UPDATE ON `tblkas_mutasi` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_mutasi
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kas_mutasi_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_kas_mutasi_ai` AFTER INSERT ON `tblkas_mutasi` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblkas_shift SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_shift=NEW.id_shift; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_mutasi
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kas_mutasi_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_kas_mutasi_au` AFTER UPDATE ON `tblkas_mutasi` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblkas_shift SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_shift=NEW.id_shift; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_shift
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_shift_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_shift_bi` BEFORE INSERT ON `tblkas_shift` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_shift
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_shift_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_shift_bu` BEFORE UPDATE ON `tblkas_shift` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_shift
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_shift_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_shift_ai` AFTER INSERT ON `tblkas_shift` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('CASH_SHIFT',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkas_shift
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_shift_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_shift_au` AFTER UPDATE ON `tblkas_shift` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('CASH_SHIFT',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkategori
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kategori_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_kategori_bi` BEFORE INSERT ON `tblkategori` FOR EACH ROW BEGIN
    IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF;
    SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1);
    SET NEW.updated_at=CURRENT_TIMESTAMP(6);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkategori
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kategori_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_kategori_bu` BEFORE UPDATE ON `tblkategori` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT (NEW.kategori <=> OLD.kategori) OR NOT (NEW.hapus <=> OLD.hapus)) THEN
        SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,0),OLD.row_version+1);
        SET NEW.updated_at=CURRENT_TIMESTAMP(6);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkategori
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kategori_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_kategori_ai` AFTER INSERT ON `tblkategori` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN
        INSERT INTO tblsync_capture_queue(capture_uuid,aggregate_type,aggregate_id,operation,entity_version,source_hint,status_capture,captured_at)
        VALUES(LOWER(UUID()),'MASTER_KATEGORI',NEW.sync_uid,IF(COALESCE(NEW.hapus,0)=1,'DELETE','UPSERT'),NEW.row_version,COALESCE(NULLIF(@SYNC_SOURCE_DEVICE_ID,''),'LOCAL'),'PENDING',CURRENT_TIMESTAMP(6))
        ON DUPLICATE KEY UPDATE capture_uuid=VALUES(capture_uuid),operation=VALUES(operation),entity_version=VALUES(entity_version),source_hint=VALUES(source_hint),status_capture='PENDING',attempt_count=0,next_retry_at=NULL,last_error_code=NULL,last_error_message=NULL,captured_at=VALUES(captured_at),processed_at=NULL;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkategori
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kategori_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_kategori_au` AFTER UPDATE ON `tblkategori` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT (NEW.kategori <=> OLD.kategori) OR NOT (NEW.hapus <=> OLD.hapus)) THEN
        INSERT INTO tblsync_capture_queue(capture_uuid,aggregate_type,aggregate_id,operation,entity_version,source_hint,status_capture,captured_at)
        VALUES(LOWER(UUID()),'MASTER_KATEGORI',NEW.sync_uid,IF(COALESCE(NEW.hapus,0)=1,'DELETE','UPSERT'),NEW.row_version,COALESCE(NULLIF(@SYNC_SOURCE_DEVICE_ID,''),'LOCAL'),'PENDING',CURRENT_TIMESTAMP(6))
        ON DUPLICATE KEY UPDATE capture_uuid=VALUES(capture_uuid),operation=VALUES(operation),entity_version=VALUES(entity_version),source_hint=VALUES(source_hint),status_capture='PENDING',attempt_count=0,next_retry_at=NULL,last_error_code=NULL,last_error_message=NULL,captured_at=VALUES(captured_at),processed_at=NULL;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkomponen_gaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_komponen_gaji_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_komponen_gaji_bi` BEFORE INSERT ON `tblkomponen_gaji` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkomponen_gaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_komponen_gaji_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_komponen_gaji_bu` BEFORE UPDATE ON `tblkomponen_gaji` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkomponen_gaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_komponen_gaji_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_komponen_gaji_ai` AFTER INSERT ON `tblkomponen_gaji` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MASTER_KOMPONEN_GAJI',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblkomponen_gaji
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_komponen_gaji_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_komponen_gaji_au` AFTER UPDATE ON `tblkomponen_gaji` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MASTER_KOMPONEN_GAJI',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbllegacy_stock_opening
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_legacy_stock_opening_bu`;
delimiter ;;
CREATE TRIGGER `trg_legacy_stock_opening_bu` BEFORE UPDATE ON `tbllegacy_stock_opening` FOR EACH ROW BEGIN
    IF OLD.`status`='FINAL' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Stok awal final bersifat immutable.';
    END IF;
    IF NEW.`status` NOT IN ('DRAFT','FINAL') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Status stok awal tidak valid.';
    END IF;
    IF OLD.`status`='DRAFT' AND NEW.`status`='FINAL' THEN
        SET NEW.`finalized_at`=COALESCE(NEW.`finalized_at`,CURRENT_TIMESTAMP(6));
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbllegacy_stock_opening
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_legacy_stock_opening_bd`;
delimiter ;;
CREATE TRIGGER `trg_legacy_stock_opening_bd` BEFORE DELETE ON `tbllegacy_stock_opening` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_MAINTENANCE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Stok awal migrasi tidak dapat dihapus.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbllegacy_stock_opening
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_legacy_stock_opening_au`;
delimiter ;;
CREATE TRIGGER `trg_legacy_stock_opening_au` AFTER UPDATE ON `tbllegacy_stock_opening` FOR EACH ROW BEGIN
    IF OLD.`status`='DRAFT' AND NEW.`status`='FINAL' THEN
        CALL `sp_finalize_legacy_stock_opening`(NEW.`id_opening`);
        CALL `sp_sync_capture_transaction`(
            'TRANSACTION_LEGACY_STOCK_OPENING',
            NEW.`id_opening`,
            'FINAL',
            NEW.`tanggal`
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbllegacy_stock_opening_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_legacy_stock_opening_detail_bi`;
delimiter ;;
CREATE TRIGGER `trg_legacy_stock_opening_detail_bi` BEFORE INSERT ON `tbllegacy_stock_opening_detail` FOR EACH ROW BEGIN
    DECLARE v_status VARCHAR(10);
    SELECT `status` INTO v_status
    FROM `tbllegacy_stock_opening`
    WHERE `id_opening`=NEW.`id_opening`
    LIMIT 1;
    IF COALESCE(v_status,'')<>'DRAFT' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Detail stok awal hanya dapat ditambahkan saat DRAFT.';
    END IF;
    IF COALESCE(NEW.`qty`,0)<=0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Qty stok awal wajib lebih dari nol.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbllegacy_stock_opening_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_legacy_stock_opening_detail_bu`;
delimiter ;;
CREATE TRIGGER `trg_legacy_stock_opening_detail_bu` BEFORE UPDATE ON `tbllegacy_stock_opening_detail` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT='Detail stok awal bersifat immutable.';
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbllegacy_stock_opening_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_legacy_stock_opening_detail_bd`;
delimiter ;;
CREATE TRIGGER `trg_legacy_stock_opening_detail_bd` BEFORE DELETE ON `tbllegacy_stock_opening_detail` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_MAINTENANCE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Detail stok awal final tidak dapat dihapus.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmember_poin_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_point_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_point_bi` BEFORE INSERT ON `tblmember_poin_log` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmember_poin_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_point_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_point_bu` BEFORE UPDATE ON `tblmember_poin_log` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmember_poin_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_point_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_point_ai` AFTER INSERT ON `tblmember_poin_log` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MEMBER_POINT',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmember_poin_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_member_point_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_member_point_au` AFTER UPDATE ON `tblmember_poin_log` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MEMBER_POINT',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmember_setting
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kelas_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_kelas_bi` BEFORE INSERT ON `tblmember_setting` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1); SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmember_setting
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kelas_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_kelas_bu` BEFORE UPDATE ON `tblmember_setting` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.nama_kelas<=>OLD.nama_kelas) OR NOT(NEW.keterangan<=>OLD.keterangan) OR NOT(NEW.diskon_global_persen<=>OLD.diskon_global_persen) OR NOT(NEW.min_belanja<=>OLD.min_belanja) OR NOT(NEW.hapus<=>OLD.hapus)) THEN SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmember_setting
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kelas_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_kelas_ai` AFTER INSERT ON `tblmember_setting` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MASTER_KELAS_MEMBER',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmember_setting
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_kelas_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_kelas_au` AFTER UPDATE ON `tblmember_setting` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MASTER_KELAS_MEMBER',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmerek
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_merek_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_merek_bi` BEFORE INSERT ON `tblmerek` FOR EACH ROW BEGIN
    IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF;
    SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1);
    SET NEW.updated_at=CURRENT_TIMESTAMP(6);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmerek
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_merek_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_merek_bu` BEFORE UPDATE ON `tblmerek` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT (NEW.merek <=> OLD.merek) OR NOT (NEW.hapus <=> OLD.hapus)) THEN
        SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,0),OLD.row_version+1);
        SET NEW.updated_at=CURRENT_TIMESTAMP(6);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmerek
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_merek_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_merek_ai` AFTER INSERT ON `tblmerek` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN
        INSERT INTO tblsync_capture_queue(capture_uuid,aggregate_type,aggregate_id,operation,entity_version,source_hint,status_capture,captured_at)
        VALUES(LOWER(UUID()),'MASTER_MEREK',NEW.sync_uid,IF(COALESCE(NEW.hapus,0)=1,'DELETE','UPSERT'),NEW.row_version,COALESCE(NULLIF(@SYNC_SOURCE_DEVICE_ID,''),'LOCAL'),'PENDING',CURRENT_TIMESTAMP(6))
        ON DUPLICATE KEY UPDATE capture_uuid=VALUES(capture_uuid),operation=VALUES(operation),entity_version=VALUES(entity_version),source_hint=VALUES(source_hint),status_capture='PENDING',attempt_count=0,next_retry_at=NULL,last_error_code=NULL,last_error_message=NULL,captured_at=VALUES(captured_at),processed_at=NULL;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblmerek
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_merek_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_merek_au` AFTER UPDATE ON `tblmerek` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT (NEW.merek <=> OLD.merek) OR NOT (NEW.hapus <=> OLD.hapus)) THEN
        INSERT INTO tblsync_capture_queue(capture_uuid,aggregate_type,aggregate_id,operation,entity_version,source_hint,status_capture,captured_at)
        VALUES(LOWER(UUID()),'MASTER_MEREK',NEW.sync_uid,IF(COALESCE(NEW.hapus,0)=1,'DELETE','UPSERT'),NEW.row_version,COALESCE(NULLIF(@SYNC_SOURCE_DEVICE_ID,''),'LOCAL'),'PENDING',CURRENT_TIMESTAMP(6))
        ON DUPLICATE KEY UPDATE capture_uuid=VALUES(capture_uuid),operation=VALUES(operation),entity_version=VALUES(entity_version),source_hint=VALUES(source_hint),status_capture='PENDING',attempt_count=0,next_retry_at=NULL,last_error_code=NULL,last_error_message=NULL,captured_at=VALUES(captured_at),processed_at=NULL;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpengaturan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_pengaturan_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_pengaturan_bi` BEFORE INSERT ON `tblpengaturan` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpengaturan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_pengaturan_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_pengaturan_bu` BEFORE UPDATE ON `tblpengaturan` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpengaturan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_pengaturan_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_pengaturan_ai` AFTER INSERT ON `tblpengaturan` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0
       AND NOT (LEFT(LOWER(NEW.kode),6)='nomor_' AND RIGHT(LOWER(NEW.kode),7)='_prefix')
    THEN
        CALL sp_sync_capture_catalog('SETTING_TOKO',NEW.sync_uid,NEW.hapus,NEW.row_version);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpengaturan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_pengaturan_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_pengaturan_au` AFTER UPDATE ON `tblpengaturan` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0
       AND NEW.row_version<>OLD.row_version
       AND NOT (LEFT(LOWER(NEW.kode),6)='nomor_' AND RIGHT(LOWER(NEW.kode),7)='_prefix')
    THEN
        CALL sp_sync_capture_catalog('SETTING_TOKO',NEW.sync_uid,NEW.hapus,NEW.row_version);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpengaturan_transaksi
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_pengaturan_trx_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_pengaturan_trx_bi` BEFORE INSERT ON `tblpengaturan_transaksi` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpengaturan_transaksi
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_pengaturan_trx_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_pengaturan_trx_bu` BEFORE UPDATE ON `tblpengaturan_transaksi` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpengaturan_transaksi
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_pengaturan_trx_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_pengaturan_trx_ai` AFTER INSERT ON `tblpengaturan_transaksi` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('SETTING_TRANSAKSI',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpengaturan_transaksi
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_pengaturan_trx_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_pengaturan_trx_au` AFTER UPDATE ON `tblpengaturan_transaksi` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('SETTING_TRANSAKSI',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpermission
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_permission_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_permission_bi` BEFORE INSERT ON `tblpermission` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpermission
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_permission_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_permission_bu` BEFORE UPDATE ON `tblpermission` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpermission
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_permission_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_permission_ai` AFTER INSERT ON `tblpermission` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('SECURITY_PERMISSION',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpermission
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_permission_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_permission_au` AFTER UPDATE ON `tblpermission` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('SECURITY_PERMISSION',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_payable_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_payable_bi` BEFORE INSERT ON `tblpiutangpembelian` FOR EACH ROW BEGIN
    IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF;
    SET NEW.row_version=1;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_payable_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_payable_ai` AFTER INSERT ON `tblpiutangpembelian` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND COALESCE(NEW.hapus,0)=0 THEN CALL sp_sync_capture_catalog('PAYABLE_OPEN',NEW.sync_uid,0,1); END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpembelian_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_payable_payment_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_payable_payment_bi` BEFORE INSERT ON `tblpiutangpembelian_log` FOR EACH ROW BEGIN
    IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF;
    SET NEW.row_version=1;
    SET NEW.updated_at=CURRENT_TIMESTAMP(6);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpembelian_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_payable_payment_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_payable_payment_bu` BEFORE UPDATE ON `tblpiutangpembelian_log` FOR EACH ROW BEGIN
    SET NEW.sync_uid=OLD.sync_uid;
    SET NEW.row_version=OLD.row_version+1;
    SET NEW.updated_at=CURRENT_TIMESTAMP(6);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpembelian_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_payable_payment_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_payable_payment_ai` AFTER INSERT ON `tblpiutangpembelian_log` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0
       AND COALESCE(NEW.id_bank,'') NOT IN ('','RETUR','SYSTEM')
       AND COALESCE(NEW.jumlah,0)>0
       AND COALESCE(NEW.hapus,0)=0
    THEN
        CALL sp_sync_capture_catalog('PAYMENT_PAYABLE',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpembelian_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_hutang_pembelian_log_insert`;
delimiter ;;
CREATE TRIGGER `trg_hutang_pembelian_log_insert` AFTER INSERT ON `tblpiutangpembelian_log` FOR EACH ROW BEGIN
    DECLARE v_total DECIMAL(18,2) DEFAULT 0.00;
    IF @DISABLE_TRIGGER IS NULL OR @DISABLE_TRIGGER=0 THEN
        IF COALESCE(NEW.hapus,0)=0 AND COALESCE(NEW.jumlah,0)>0
           AND COALESCE(NEW.id_bank,'')<>'RETUR'
        THEN
            UPDATE `tblpiutangpembelian`
            SET `dibayar`=LEAST(`total`,`dibayar`+NEW.jumlah),
                `terakhir_bayar`=NEW.tanggal,`sync`=1
            WHERE `id_piutang`=NEW.id_piutang;
            SELECT `total` INTO v_total FROM `tblpiutangpembelian`
            WHERE `id_piutang`=NEW.id_piutang LIMIT 1;
            UPDATE `tblpiutangpembelian`
            SET `status`=IF(`dibayar`>=v_total,'Lunas','Belum'),`sync`=1
            WHERE `id_piutang`=NEW.id_piutang;
            IF UPPER(COALESCE(NEW.mode_pembayaran,''))<>'DP_PO'
               AND COALESCE(NEW.id_pembayaran,'') NOT LIKE '%-DP-PO-%'
               AND COALESCE(NEW.id_bank,'')<>''
            THEN
                CALL `sp_post_saldo_v2`(
                    'PAYABLE_PAYMENT',NEW.id_pembayaran,NEW.sync_uid,NEW.id_pembayaran,
                    NEW.id_bank,'MAIN',0,NEW.jumlah,NEW.tanggal,NEW.id_admin,
                    'HUTANG_PEMBELIAN','Pembayaran Hutang Supplier',
                    CONCAT('Pembayaran hutang supplier ',NEW.id_piutang),NULL,NEW.id_shift
                );
            END IF;
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpembelian_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_payable_payment_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_payable_payment_au` AFTER UPDATE ON `tblpiutangpembelian_log` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0
       AND COALESCE(NEW.id_bank,'') NOT IN ('','RETUR','SYSTEM')
       AND COALESCE(NEW.jumlah,0)>0
       AND NEW.row_version<>OLD.row_version
    THEN
        CALL sp_sync_capture_catalog('PAYMENT_PAYABLE',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpenjualan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_receivable_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_receivable_bi` BEFORE INSERT ON `tblpiutangpenjualan` FOR EACH ROW BEGIN
    IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF;
    SET NEW.row_version=1;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpenjualan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_receivable_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_receivable_ai` AFTER INSERT ON `tblpiutangpenjualan` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND COALESCE(NEW.hapus,0)=0 THEN CALL sp_sync_capture_catalog('RECEIVABLE_OPEN',NEW.sync_uid,0,1); END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpenjualan_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_receivable_payment_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_receivable_payment_bi` BEFORE INSERT ON `tblpiutangpenjualan_log` FOR EACH ROW BEGIN
    IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF;
    SET NEW.row_version=1;
    SET NEW.updated_at=CURRENT_TIMESTAMP(6);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpenjualan_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_receivable_payment_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_receivable_payment_bu` BEFORE UPDATE ON `tblpiutangpenjualan_log` FOR EACH ROW BEGIN
    SET NEW.sync_uid=OLD.sync_uid;
    SET NEW.row_version=OLD.row_version+1;
    SET NEW.updated_at=CURRENT_TIMESTAMP(6);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpenjualan_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_receivable_payment_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_receivable_payment_ai` AFTER INSERT ON `tblpiutangpenjualan_log` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0
       AND COALESCE(NEW.id_bank,'') NOT IN ('','RETUR','SYSTEM')
       AND COALESCE(NEW.jumlah,0)>0
       AND COALESCE(NEW.hapus,0)=0
    THEN
        CALL sp_sync_capture_catalog('PAYMENT_RECEIVABLE',NEW.sync_uid,0,NEW.row_version);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpenjualan_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_update_piutang`;
delimiter ;;
CREATE TRIGGER `trg_update_piutang` AFTER INSERT ON `tblpiutangpenjualan_log` FOR EACH ROW BEGIN
    DECLARE v_source_id VARCHAR(120);
    DECLARE v_role VARCHAR(30);
    IF @DISABLE_TRIGGER IS NULL OR @DISABLE_TRIGGER=0 THEN
        IF COALESCE(NEW.hapus,0)=0 AND COALESCE(NEW.jumlah,0)>0 THEN
            IF COALESCE(NULLIF(TRIM(NEW.no_ref),''),'-')='-' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pembayaran piutang ditolak: nomor referensi wajib diisi.';
            END IF;
            IF COALESCE(NULLIF(TRIM(NEW.id_bank),''),'SYSTEM') IN ('SYSTEM','RETUR') THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pembayaran piutang ditolak: akun kas/bank wajib diisi.';
            END IF;
            SET v_source_id=COALESCE(NULLIF(TRIM(NEW.request_id),''),NEW.no_ref);
            SET v_role=IF(UPPER(NEW.no_ref) LIKE '%-DP','DP','MAIN');
            UPDATE `tblpiutangpenjualan`
            SET `status`=IF(LEAST(`total`,`dibayar`+NEW.jumlah)>=`total`,'Lunas','Belum'),
                `dibayar`=LEAST(`total`,`dibayar`+NEW.jumlah),
                `terakhir_bayar`=NEW.tanggal,`sync`=1
            WHERE `id_piutang`=NEW.id_piutang;
            CALL `sp_post_saldo_v2`(
                'RECEIVABLE_PAYMENT',v_source_id,NEW.sync_uid,NEW.no_ref,NEW.id_bank,v_role,
                NEW.jumlah,0,COALESCE(NEW.tanggal,NOW()),NEW.id_admin,'PIUTANG',
                'Pembayaran Piutang',COALESCE(NULLIF(NEW.keterangan,''),CONCAT('Pembayaran piutang ',NEW.id_piutang)),
                NULL,NEW.id_shift
            );
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpiutangpenjualan_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_receivable_payment_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_receivable_payment_au` AFTER UPDATE ON `tblpiutangpenjualan_log` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0
       AND COALESCE(NEW.id_bank,'') NOT IN ('','RETUR','SYSTEM')
       AND COALESCE(NEW.jumlah,0)>0
       AND NEW.row_version<>OLD.row_version
    THEN
        CALL sp_sync_capture_catalog('PAYMENT_RECEIVABLE',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_bi` BEFORE INSERT ON `tblpurchase_order` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_bu` BEFORE UPDATE ON `tblpurchase_order` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_ai` AFTER INSERT ON `tblpurchase_order` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('PURCHASE_ORDER',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_au` AFTER UPDATE ON `tblpurchase_order` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('PURCHASE_ORDER',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_dp
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_dp_capture_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_dp_capture_ai` AFTER INSERT ON `tblpurchase_order_dp` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblpurchase_order SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_po=NEW.id_po; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_dp
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_po_dp_saldo_insert`;
delimiter ;;
CREATE TRIGGER `trg_po_dp_saldo_insert` AFTER INSERT ON `tblpurchase_order_dp` FOR EACH ROW BEGIN
    IF (@DISABLE_TRIGGER IS NULL OR @DISABLE_TRIGGER=0)
       AND COALESCE(NEW.hapus,0)=0 AND COALESCE(NEW.nominal,0)>0
    THEN
        CALL `sp_post_saldo_v2`(
            'PURCHASE_ORDER_DP',NEW.id_dp,NULL,NEW.id_dp,NEW.id_bank,'DP',
            0,NEW.nominal,COALESCE(NEW.tanggal,NOW()),NEW.id_admin,'DP_PO',
            'Purchase Order',COALESCE(NULLIF(NEW.keterangan,''),CONCAT('DP PO ',NEW.id_po)),
            NULL,NEW.id_shift
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_dp
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_dp_capture_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_dp_capture_au` AFTER UPDATE ON `tblpurchase_order_dp` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblpurchase_order SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_po=NEW.id_po; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_item
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_item_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_item_ai` AFTER INSERT ON `tblpurchase_order_item` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblpurchase_order SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_po=NEW.id_po; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_item
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_item_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_item_au` AFTER UPDATE ON `tblpurchase_order_item` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblpurchase_order SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_po=NEW.id_po; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_po_terima_stock_guard`;
delimiter ;;
CREATE TRIGGER `trg_po_terima_stock_guard` BEFORE UPDATE ON `tblpurchase_order_terima` FOR EACH ROW BEGIN
    IF COALESCE(@DISABLE_TRIGGER,0)=0 AND EXISTS (
        SELECT 1 FROM tblstok_posting_log
        WHERE ref_tabel='PURCHASE_ORDER_RECEIPT' AND no_referensi=OLD.id_terima
    ) AND (
        NOT (NEW.id_terima<=>OLD.id_terima)
        OR NOT (NEW.id_po<=>OLD.id_po)
        OR NOT (NEW.masuk_ke<=>OLD.masuk_ke)
        OR NOT (NEW.hapus<=>OLD.hapus)
        OR NEW.status NOT IN ('DITERIMA','FINAL')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lokasi dan histori penerimaan PO terposting tidak boleh diubah.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_po_terima_stock_delete_guard`;
delimiter ;;
CREATE TRIGGER `trg_po_terima_stock_delete_guard` BEFORE DELETE ON `tblpurchase_order_terima` FOR EACH ROW BEGIN
    IF COALESCE(@DISABLE_TRIGGER,0)=0 AND EXISTS (
        SELECT 1 FROM tblstok_posting_log
        WHERE ref_tabel='PURCHASE_ORDER_RECEIPT' AND no_referensi=OLD.id_terima
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Header penerimaan PO terposting tidak boleh dihapus.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_terima_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_terima_ai` AFTER INSERT ON `tblpurchase_order_terima` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblpurchase_order SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_po=NEW.id_po; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_terima_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_terima_au` AFTER UPDATE ON `tblpurchase_order_terima` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblpurchase_order SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_po=NEW.id_po; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_po_terima_stock_update`;
delimiter ;;
CREATE TRIGGER `trg_po_terima_stock_update` AFTER UPDATE ON `tblpurchase_order_terima` FOR EACH ROW BEGIN
    DECLARE v_done tinyint DEFAULT 0;
    DECLARE v_detail bigint unsigned;
    DECLARE receipt_lines CURSOR FOR
        SELECT id_terima_detail FROM tblpurchase_order_terima_detail
        WHERE id_terima=NEW.id_terima AND hapus=0 ORDER BY id_terima_detail;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done=1;
    IF COALESCE(@DISABLE_TRIGGER,0)=0 AND NEW.hapus=0 AND NEW.status IN ('DITERIMA','FINAL') THEN
        OPEN receipt_lines;
        receipt_loop: LOOP
            FETCH receipt_lines INTO v_detail;
            IF v_done=1 THEN LEAVE receipt_loop; END IF;
            CALL sp_post_po_receipt_line_v2(v_detail);
        END LOOP;
        CLOSE receipt_lines;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_terima_detail_capture_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_terima_detail_capture_bi` BEFORE INSERT ON `tblpurchase_order_terima_detail` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_terima_detail_capture_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_terima_detail_capture_bu` BEFORE UPDATE ON `tblpurchase_order_terima_detail` FOR EACH ROW BEGIN SET NEW.sync_uid=OLD.sync_uid; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_po_terima_detail_stock_guard`;
delimiter ;;
CREATE TRIGGER `trg_po_terima_detail_stock_guard` BEFORE UPDATE ON `tblpurchase_order_terima_detail` FOR EACH ROW BEGIN
    IF COALESCE(@DISABLE_TRIGGER,0)=0 AND EXISTS (
        SELECT 1 FROM tblstok_posting_log
        WHERE ref_tabel='PURCHASE_ORDER_RECEIPT' AND ref_id=CONCAT(OLD.id_terima_detail)
    ) AND (
        NOT (NEW.id_terima_detail<=>OLD.id_terima_detail)
        OR NOT (NEW.id_terima<=>OLD.id_terima)
        OR NOT (NEW.id_po<=>OLD.id_po)
        OR NOT (NEW.id_po_item<=>OLD.id_po_item)
        OR NOT (NEW.id_produk<=>OLD.id_produk)
        OR NOT (NEW.qty_terima<=>OLD.qty_terima)
        OR NOT (NEW.isi<=>OLD.isi)
        OR NOT (NEW.hapus<=>OLD.hapus)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Penerimaan PO terposting tidak boleh diubah; gunakan transaksi koreksi.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_po_terima_detail_stock_delete_guard`;
delimiter ;;
CREATE TRIGGER `trg_po_terima_detail_stock_delete_guard` BEFORE DELETE ON `tblpurchase_order_terima_detail` FOR EACH ROW BEGIN
    IF COALESCE(@DISABLE_TRIGGER,0)=0 AND EXISTS (
        SELECT 1 FROM tblstok_posting_log
        WHERE ref_tabel='PURCHASE_ORDER_RECEIPT' AND ref_id=CONCAT(OLD.id_terima_detail)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histori penerimaan PO terposting tidak boleh dihapus.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_terima_detail_capture_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_terima_detail_capture_ai` AFTER INSERT ON `tblpurchase_order_terima_detail` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblpurchase_order SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_po=NEW.id_po; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_po_terima_detail_stock_insert`;
delimiter ;;
CREATE TRIGGER `trg_po_terima_detail_stock_insert` AFTER INSERT ON `tblpurchase_order_terima_detail` FOR EACH ROW BEGIN
    CALL sp_post_po_receipt_line_v2(NEW.id_terima_detail);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_po_terima_detail_capture_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_po_terima_detail_capture_au` AFTER UPDATE ON `tblpurchase_order_terima_detail` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblpurchase_order SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_po=NEW.id_po; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblpurchase_order_terima_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_po_terima_detail_stock_update`;
delimiter ;;
CREATE TRIGGER `trg_po_terima_detail_stock_update` AFTER UPDATE ON `tblpurchase_order_terima_detail` FOR EACH ROW BEGIN
    CALL sp_post_po_receipt_line_v2(NEW.id_terima_detail);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrak
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rak_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_rak_bi` BEFORE INSERT ON `tblrak` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1); SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrak
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rak_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_rak_bu` BEFORE UPDATE ON `tblrak` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.nama_rak<=>OLD.nama_rak) OR NOT(NEW.keterangan<=>OLD.keterangan) OR NOT(NEW.hapus<=>OLD.hapus)) THEN SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrak
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rak_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_rak_ai` AFTER INSERT ON `tblrak` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('MASTER_RAK',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrak
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rak_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_rak_au` AFTER UPDATE ON `tblrak` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('MASTER_RAK',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrak_sub
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_subrak_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_subrak_bi` BEFORE INSERT ON `tblrak_sub` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrak_sub
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_subrak_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_subrak_ai` AFTER INSERT ON `tblrak_sub` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblrak SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_rak=NEW.id_rak; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrak_sub
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_subrak_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_subrak_au` AFTER UPDATE ON `tblrak_sub` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT(NEW.nama_sub<=>OLD.nama_sub) OR NOT(NEW.keterangan<=>OLD.keterangan) OR NOT(NEW.hapus<=>OLD.hapus) OR NOT(NEW.id_rak<=>OLD.id_rak)) THEN UPDATE tblrak SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_rak=NEW.id_rak; IF NOT(NEW.id_rak<=>OLD.id_rak) THEN UPDATE tblrak SET row_version=row_version+1,sync=1,updated_at=CURRENT_TIMESTAMP(6) WHERE id_rak=OLD.id_rak; END IF; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrencanapembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rencana_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_rencana_bi` BEFORE INSERT ON `tblrencanapembelian` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrencanapembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rencana_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_rencana_bu` BEFORE UPDATE ON `tblrencanapembelian` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrencanapembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rencana_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_rencana_ai` AFTER INSERT ON `tblrencanapembelian` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('PURCHASE_PLAN',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrencanapembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rencana_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_rencana_au` AFTER UPDATE ON `tblrencanapembelian` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('PURCHASE_PLAN',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrencanapembelian_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rencana_detail_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_rencana_detail_bi` BEFORE INSERT ON `tblrencanapembelian_detail` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrencanapembelian_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rencana_detail_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_rencana_detail_bu` BEFORE UPDATE ON `tblrencanapembelian_detail` FOR EACH ROW BEGIN SET NEW.sync_uid=OLD.sync_uid; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrencanapembelian_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rencana_detail_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_rencana_detail_ai` AFTER INSERT ON `tblrencanapembelian_detail` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblrencanapembelian SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_rencana=NEW.id_rencana; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrencanapembelian_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_rencana_detail_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_rencana_detail_au` AFTER UPDATE ON `tblrencanapembelian_detail` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblrencanapembelian SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_rencana=NEW.id_rencana; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_purchase_return_capture_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_purchase_return_capture_ai` AFTER INSERT ON `tblreturpembelian` FOR EACH ROW BEGIN
    IF UPPER(COALESCE(NEW.status,'')) IN ('FINAL','SELESAI','SUKSES') AND COALESCE(NEW.hapus,0)=0 THEN
        CALL sp_sync_capture_transaction('TRANSACTION_PURCHASE_RETURN',NEW.id_retur,'FINAL',COALESCE(NEW.tanggal,CURRENT_TIMESTAMP(6)));
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_retur_pembelian_saldo_ai`;
delimiter ;;
CREATE TRIGGER `trg_retur_pembelian_saldo_ai` AFTER INSERT ON `tblreturpembelian` FOR EACH ROW BEGIN
    IF COALESCE(@DISABLE_TRIGGER,0)=0
       AND UPPER(COALESCE(NEW.`status`,''))='FINAL'
       AND COALESCE(NEW.`hapus`,0)=0
       AND COALESCE(NEW.`dana_kembali`,0)>0 THEN
        CALL `sp_post_saldo_v2`(
            'PURCHASE_RETURN',NEW.`id_retur`,NULL,NEW.`id_retur`,NEW.`id_bank`,'MAIN',
            NEW.`dana_kembali`,0,NEW.`tanggal`,NEW.`id_admin`,
            'RETUR_PEMBELIAN','Retur Pembelian',
            CONCAT('Dana kembali retur pembelian ',NEW.`id_retur`),
            NULL,NEW.`id_shift`
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_purchase_return_capture_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_purchase_return_capture_au` AFTER UPDATE ON `tblreturpembelian` FOR EACH ROW BEGIN
    IF UPPER(COALESCE(NEW.status,'')) IN ('FINAL','SELESAI','SUKSES') AND COALESCE(NEW.hapus,0)=0
       AND (UPPER(COALESCE(OLD.status,'')) NOT IN ('FINAL','SELESAI','SUKSES') OR COALESCE(OLD.hapus,0)<>0) THEN
        CALL sp_sync_capture_transaction('TRANSACTION_PURCHASE_RETURN',NEW.id_retur,'FINAL',COALESCE(NEW.tanggal,CURRENT_TIMESTAMP(6)));
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_retur_pembelian_saldo_au`;
delimiter ;;
CREATE TRIGGER `trg_retur_pembelian_saldo_au` AFTER UPDATE ON `tblreturpembelian` FOR EACH ROW BEGIN
    IF COALESCE(@DISABLE_TRIGGER,0)=0
       AND UPPER(COALESCE(OLD.`status`,''))<>'FINAL'
       AND UPPER(COALESCE(NEW.`status`,''))='FINAL'
       AND COALESCE(NEW.`hapus`,0)=0
       AND COALESCE(NEW.`dana_kembali`,0)>0 THEN
        CALL `sp_post_saldo_v2`(
            'PURCHASE_RETURN',NEW.`id_retur`,NULL,NEW.`id_retur`,NEW.`id_bank`,'MAIN',
            NEW.`dana_kembali`,0,NEW.`tanggal`,NEW.`id_admin`,
            'RETUR_PEMBELIAN','Retur Pembelian',
            CONCAT('Dana kembali retur pembelian ',NEW.`id_retur`),
            NULL,NEW.`id_shift`
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpembelian_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_retur_pembelian_detail_stock_insert`;
delimiter ;;
CREATE TRIGGER `trg_retur_pembelian_detail_stock_insert` AFTER INSERT ON `tblreturpembelian_detail` FOR EACH ROW BEGIN
    DECLARE v_tanggal datetime;
    DECLARE v_admin varchar(50);
    DECLARE v_source_uid char(36);

    IF COALESCE(@DISABLE_TRIGGER,0)=0 THEN
        SELECT tanggal,id_admin
        INTO v_tanggal,v_admin
        FROM tblreturpembelian
        WHERE id_retur=NEW.id_retur
        LIMIT 1;
        SET v_source_uid=(
            SELECT transaction_uuid
            FROM tblsync_transaction_identity
            WHERE aggregate_type='TRANSACTION_PURCHASE_RETURN'
              AND business_id=NEW.id_retur
            LIMIT 1
        );

        IF NEW.hapus=0 AND NEW.status='FINAL' AND COALESCE(NEW.qty_dasar,0)>0 THEN
            CALL sp_post_stok_v2(
                'PURCHASE_RETURN',CONCAT(NEW.id_detail),v_source_uid,NEW.id_retur,
                NEW.id_produk,NEW.id_lokasi,'MAIN',0,NEW.qty_dasar,
                COALESCE(v_tanggal,NOW()),COALESCE(NULLIF(v_admin,''),'SYSTEM'),
                CONCAT('Retur Pembelian ',COALESCE(NEW.id_retur,'-'))
            );
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpenjualan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_sale_return_capture_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_sale_return_capture_ai` AFTER INSERT ON `tblreturpenjualan` FOR EACH ROW BEGIN
    IF UPPER(COALESCE(NEW.status,'')) IN ('FINAL','SELESAI','SUKSES') AND COALESCE(NEW.hapus,0)=0 THEN
        CALL sp_sync_capture_transaction('TRANSACTION_SALE_RETURN',NEW.id_retur,'FINAL',COALESCE(NEW.tanggal,CURRENT_TIMESTAMP(6)));
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpenjualan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_retur_penjualan_saldo_ai`;
delimiter ;;
CREATE TRIGGER `trg_retur_penjualan_saldo_ai` AFTER INSERT ON `tblreturpenjualan` FOR EACH ROW BEGIN
    IF COALESCE(@DISABLE_TRIGGER,0)=0
       AND UPPER(COALESCE(NEW.`status`,''))='FINAL'
       AND COALESCE(NEW.`hapus`,0)=0
       AND COALESCE(NEW.`dana_kembali`,0)>0 THEN
        CALL `sp_post_saldo_v2`(
            'SALE_RETURN',NEW.`id_retur`,NULL,NEW.`id_retur`,NEW.`id_bank`,'MAIN',
            0,NEW.`dana_kembali`,NEW.`tanggal`,NEW.`id_admin`,
            'RETUR_PENJUALAN','Retur Penjualan',
            CONCAT('Pengembalian dana retur penjualan ',NEW.`id_retur`),
            NULL,NEW.`id_shift`
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpenjualan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_sale_return_capture_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_sale_return_capture_au` AFTER UPDATE ON `tblreturpenjualan` FOR EACH ROW BEGIN
    IF UPPER(COALESCE(NEW.status,'')) IN ('FINAL','SELESAI','SUKSES') AND COALESCE(NEW.hapus,0)=0
       AND (UPPER(COALESCE(OLD.status,'')) NOT IN ('FINAL','SELESAI','SUKSES') OR COALESCE(OLD.hapus,0)<>0) THEN
        CALL sp_sync_capture_transaction('TRANSACTION_SALE_RETURN',NEW.id_retur,'FINAL',COALESCE(NEW.tanggal,CURRENT_TIMESTAMP(6)));
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpenjualan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_retur_penjualan_saldo_au`;
delimiter ;;
CREATE TRIGGER `trg_retur_penjualan_saldo_au` AFTER UPDATE ON `tblreturpenjualan` FOR EACH ROW BEGIN
    IF COALESCE(@DISABLE_TRIGGER,0)=0
       AND UPPER(COALESCE(OLD.`status`,''))<>'FINAL'
       AND UPPER(COALESCE(NEW.`status`,''))='FINAL'
       AND COALESCE(NEW.`hapus`,0)=0
       AND COALESCE(NEW.`dana_kembali`,0)>0 THEN
        CALL `sp_post_saldo_v2`(
            'SALE_RETURN',NEW.`id_retur`,NULL,NEW.`id_retur`,NEW.`id_bank`,'MAIN',
            0,NEW.`dana_kembali`,NEW.`tanggal`,NEW.`id_admin`,
            'RETUR_PENJUALAN','Retur Penjualan',
            CONCAT('Pengembalian dana retur penjualan ',NEW.`id_retur`),
            NULL,NEW.`id_shift`
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblreturpenjualan_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_retur_penjualan_detail_stock_insert`;
delimiter ;;
CREATE TRIGGER `trg_retur_penjualan_detail_stock_insert` AFTER INSERT ON `tblreturpenjualan_detail` FOR EACH ROW BEGIN
    DECLARE v_tanggal datetime;
    DECLARE v_admin varchar(50);
    DECLARE v_source_uid char(36);

    IF COALESCE(@DISABLE_TRIGGER,0)=0 THEN
        SELECT tanggal,id_admin
        INTO v_tanggal,v_admin
        FROM tblreturpenjualan
        WHERE id_retur=NEW.id_retur
        LIMIT 1;
        SET v_source_uid=(
            SELECT transaction_uuid
            FROM tblsync_transaction_identity
            WHERE aggregate_type='TRANSACTION_SALE_RETURN'
              AND business_id=NEW.id_retur
            LIMIT 1
        );

        IF NEW.status='FINAL'
           AND NEW.aksi_stok='KEMBALI_STOK'
           AND COALESCE(NEW.qty_dasar,0)>0
        THEN
            CALL sp_post_stok_v2(
                'SALE_RETURN',CONCAT(NEW.id_detail),v_source_uid,NEW.id_retur,
                NEW.id_produk,NEW.id_lokasi,'MAIN',NEW.qty_dasar,0,
                COALESCE(v_tanggal,NOW()),COALESCE(NULLIF(v_admin,''),'SYSTEM'),
                CONCAT('Retur Penjualan ',COALESCE(NEW.id_retur,'-'))
            );
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrole
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_role_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_role_bi` BEFORE INSERT ON `tblrole` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrole
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_role_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_role_bu` BEFORE UPDATE ON `tblrole` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrole
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_role_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_role_ai` AFTER INSERT ON `tblrole` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('SECURITY_ROLE',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrole
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_role_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_role_au` AFTER UPDATE ON `tblrole` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('SECURITY_ROLE',NEW.sync_uid,NEW.hapus,NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrole_permission
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_role_permission_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_role_permission_ai` AFTER INSERT ON `tblrole_permission` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblrole SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_role=NEW.id_role; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblrole_permission
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_role_permission_ad`;
delimiter ;;
CREATE TRIGGER `trg_sync_role_permission_ad` AFTER DELETE ON `tblrole_permission` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN UPDATE tblrole SET updated_at=CURRENT_TIMESTAMP(6),sync=1 WHERE id_role=OLD.id_role; END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblsatuan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_satuan_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_satuan_bi` BEFORE INSERT ON `tblsatuan` FOR EACH ROW BEGIN
    IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF;
    SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,1),1);
    SET NEW.updated_at=CURRENT_TIMESTAMP(6);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblsatuan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_satuan_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_satuan_bu` BEFORE UPDATE ON `tblsatuan` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT (NEW.satuan <=> OLD.satuan) OR NOT (NEW.hapus <=> OLD.hapus)) THEN
        SET NEW.row_version=GREATEST(COALESCE(NEW.row_version,0),OLD.row_version+1);
        SET NEW.updated_at=CURRENT_TIMESTAMP(6);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblsatuan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_satuan_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_satuan_ai` AFTER INSERT ON `tblsatuan` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN
        INSERT INTO tblsync_capture_queue(capture_uuid,aggregate_type,aggregate_id,operation,entity_version,source_hint,status_capture,captured_at)
        VALUES(LOWER(UUID()),'MASTER_SATUAN',NEW.sync_uid,IF(COALESCE(NEW.hapus,0)=1,'DELETE','UPSERT'),NEW.row_version,COALESCE(NULLIF(@SYNC_SOURCE_DEVICE_ID,''),'LOCAL'),'PENDING',CURRENT_TIMESTAMP(6))
        ON DUPLICATE KEY UPDATE capture_uuid=VALUES(capture_uuid),operation=VALUES(operation),entity_version=VALUES(entity_version),source_hint=VALUES(source_hint),status_capture='PENDING',attempt_count=0,next_retry_at=NULL,last_error_code=NULL,last_error_message=NULL,captured_at=VALUES(captured_at),processed_at=NULL;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblsatuan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_satuan_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_satuan_au` AFTER UPDATE ON `tblsatuan` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND (NOT (NEW.satuan <=> OLD.satuan) OR NOT (NEW.hapus <=> OLD.hapus)) THEN
        INSERT INTO tblsync_capture_queue(capture_uuid,aggregate_type,aggregate_id,operation,entity_version,source_hint,status_capture,captured_at)
        VALUES(LOWER(UUID()),'MASTER_SATUAN',NEW.sync_uid,IF(COALESCE(NEW.hapus,0)=1,'DELETE','UPSERT'),NEW.row_version,COALESCE(NULLIF(@SYNC_SOURCE_DEVICE_ID,''),'LOCAL'),'PENDING',CURRENT_TIMESTAMP(6))
        ON DUPLICATE KEY UPDATE capture_uuid=VALUES(capture_uuid),operation=VALUES(operation),entity_version=VALUES(entity_version),source_hint=VALUES(source_hint),status_capture='PENDING',attempt_count=0,next_retry_at=NULL,last_error_code=NULL,last_error_message=NULL,captured_at=VALUES(captured_at),processed_at=NULL;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblstok
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_guard_bi`;
delimiter ;;
CREATE TRIGGER `trg_stok_guard_bi` BEFORE INSERT ON `tblstok` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_CANONICAL_WRITE,0)<>1
       AND COALESCE(@STOCK_MAINTENANCE,0)<>1
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='tblstok hanya boleh diisi oleh sp_post_stok_v2.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblstok
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_guard_bu`;
delimiter ;;
CREATE TRIGGER `trg_stok_guard_bu` BEFORE UPDATE ON `tblstok` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_MAINTENANCE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='tblstok immutable; koreksi melalui transaksi stok pembalik.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblstok
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_guard_bd`;
delimiter ;;
CREATE TRIGGER `trg_stok_guard_bd` BEFORE DELETE ON `tblstok` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_MAINTENANCE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='tblstok immutable dan tidak boleh dihapus langsung.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblstok_opname
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_opname_capture_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_opname_capture_au` AFTER UPDATE ON `tblstok_opname` FOR EACH ROW BEGIN
    IF OLD.status<>1 AND NEW.status=1 THEN
        CALL sp_sync_capture_transaction(
            'TRANSACTION_STOCK_OPNAME',NEW.id_opname,'FINAL',COALESCE(NEW.tanggal,CURRENT_TIMESTAMP(6))
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblstok_opname
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_opname_posting_update`;
delimiter ;;
CREATE TRIGGER `trg_stok_opname_posting_update` AFTER UPDATE ON `tblstok_opname` FOR EACH ROW BEGIN
    DECLARE v_source_uid char(36);

    IF COALESCE(@DISABLE_TRIGGER,0)=0
       AND OLD.status<>1
       AND NEW.status=1
       AND COALESCE(NEW.selisih,0)<>0
    THEN
        SET v_source_uid=(
            SELECT transaction_uuid
            FROM tblsync_transaction_identity
            WHERE aggregate_type='TRANSACTION_STOCK_OPNAME'
              AND business_id=NEW.id_opname
            LIMIT 1
        );

        CALL sp_post_stok_v2(
            'STOCK_OPNAME',NEW.id_opname,v_source_uid,NEW.id_opname,
            NEW.id_produk,NEW.id_gudang,'MAIN',
            IF(NEW.selisih>0,NEW.selisih,0),
            IF(NEW.selisih<0,ABS(NEW.selisih),0),
            COALESCE(NEW.tanggal,NOW()),
            COALESCE(NULLIF(NEW.id_admin,''),'SYSTEM'),
            CONCAT('Stok Opname ',NEW.id_opname,
                IF(COALESCE(NEW.keterangan,'')<>'',CONCAT(' - ',NEW.keterangan),''))
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblstok_posting_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_posting_guard_bi`;
delimiter ;;
CREATE TRIGGER `trg_stok_posting_guard_bi` BEFORE INSERT ON `tblstok_posting_log` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_CANONICAL_WRITE,0)<>1
       AND COALESCE(@STOCK_MAINTENANCE,0)<>1
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting log stok hanya boleh diisi oleh sp_post_stok_v2.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblstok_posting_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_posting_guard_bu`;
delimiter ;;
CREATE TRIGGER `trg_stok_posting_guard_bu` BEFORE UPDATE ON `tblstok_posting_log` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_MAINTENANCE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting log stok immutable.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tblstok_posting_log
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_stok_posting_guard_bd`;
delimiter ;;
CREATE TRIGGER `trg_stok_posting_guard_bd` BEFORE DELETE ON `tblstok_posting_log` FOR EACH ROW BEGIN
    IF COALESCE(@STOCK_MAINTENANCE,0)<>1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Posting log stok tidak boleh dihapus langsung.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksi_kas
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_transaksi_kas_bi`;
delimiter ;;
CREATE TRIGGER `trg_transaksi_kas_bi` BEFORE INSERT ON `tbltransaksi_kas` FOR EACH ROW BEGIN
    SET NEW.`sync_uid`=LOWER(COALESCE(NULLIF(TRIM(NEW.`sync_uid`),''),UUID()));
    SET NEW.`jenis`=UPPER(TRIM(NEW.`jenis`));
    SET NEW.`status`='FINAL';
    SET NEW.`hapus`=0;
    SET NEW.`row_version`=GREATEST(COALESCE(NEW.`row_version`,1),1);
    SET NEW.`sync`=IF(COALESCE(@SYNC_APPLY_REMOTE,0)=1,0,1);
    SET NEW.`kategori`=NULLIF(TRIM(NEW.`kategori`),'');
    SET NEW.`id_bank_asal`=NULLIF(UPPER(TRIM(NEW.`id_bank_asal`)),'');
    SET NEW.`id_bank_tujuan`=NULLIF(UPPER(TRIM(NEW.`id_bank_tujuan`)),'');

    IF NEW.`nominal`<=0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Transaksi kas ditolak: nominal wajib lebih dari nol.';
    END IF;
    IF NEW.`jenis` IN ('MASUK','KELUAR') AND NEW.`id_bank_asal` IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Transaksi kas ditolak: akun kas/bank wajib diisi.';
    END IF;
    IF NEW.`jenis`='TRANSFER'
       AND (NEW.`id_bank_asal` IS NULL OR NEW.`id_bank_tujuan` IS NULL
            OR NEW.`id_bank_asal`=NEW.`id_bank_tujuan`) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Transfer kas ditolak: akun asal dan tujuan tidak valid.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksi_kas
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_transaksi_kas_bu`;
delimiter ;;
CREATE TRIGGER `trg_transaksi_kas_bu` BEFORE UPDATE ON `tbltransaksi_kas` FOR EACH ROW BEGIN
    IF COALESCE(@SYNC_METADATA_ONLY,0)=0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT='Transaksi kas final tidak boleh diubah. Gunakan jurnal pembalik.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksi_kas
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_transaksi_kas_bd`;
delimiter ;;
CREATE TRIGGER `trg_transaksi_kas_bd` BEFORE DELETE ON `tbltransaksi_kas` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT='Transaksi kas final tidak boleh dihapus. Gunakan jurnal pembalik.';
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksi_kas
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_transaksi_kas_ai`;
delimiter ;;
CREATE TRIGGER `trg_transaksi_kas_ai` AFTER INSERT ON `tbltransaksi_kas` FOR EACH ROW BEGIN
    CALL `sp_sync_capture_source_transaction`(
        'TRANSACTION_CASH',NEW.`id_transaksi_kas`,NEW.`sync_uid`,NEW.`tanggal`
    );

    IF COALESCE(@DISABLE_TRIGGER,0)=0 THEN
        IF NEW.`jenis`='MASUK' THEN
            CALL `sp_post_saldo_v2`(
                'CASH_TRANSACTION',NEW.`id_transaksi_kas`,NEW.`sync_uid`,
                NEW.`id_transaksi_kas`,NEW.`id_bank_asal`,'MAIN',
                NEW.`nominal`,0,NEW.`tanggal`,NEW.`id_admin`,'MASUK',
                COALESCE(NEW.`kategori`,'Kas Masuk'),NEW.`keterangan`,
                NULL,NEW.`id_shift`
            );
        ELSEIF NEW.`jenis`='KELUAR' THEN
            CALL `sp_post_saldo_v2`(
                'CASH_TRANSACTION',NEW.`id_transaksi_kas`,NEW.`sync_uid`,
                NEW.`id_transaksi_kas`,NEW.`id_bank_asal`,'MAIN',
                0,NEW.`nominal`,NEW.`tanggal`,NEW.`id_admin`,'KELUAR',
                COALESCE(NEW.`kategori`,'Kas Keluar'),NEW.`keterangan`,
                NULL,NEW.`id_shift`
            );
        ELSE
            CALL `sp_post_saldo_v2`(
                'CASH_TRANSACTION',NEW.`id_transaksi_kas`,NEW.`sync_uid`,
                NEW.`id_transaksi_kas`,NEW.`id_bank_asal`,'TRANSFER_OUT',
                0,NEW.`nominal`,NEW.`tanggal`,NEW.`id_admin`,'TRANSFER',
                COALESCE(NEW.`kategori`,'Transfer Bank'),
                CONCAT('Transfer ke ',NEW.`id_bank_tujuan`,' - ',NEW.`keterangan`),
                NULL,NEW.`id_shift`
            );
            CALL `sp_post_saldo_v2`(
                'CASH_TRANSACTION',NEW.`id_transaksi_kas`,NEW.`sync_uid`,
                NEW.`id_transaksi_kas`,NEW.`id_bank_tujuan`,'TRANSFER_IN',
                NEW.`nominal`,0,NEW.`tanggal`,NEW.`id_admin`,'TRANSFER',
                COALESCE(NEW.`kategori`,'Transfer Bank'),
                CONCAT('Transfer dari ',NEW.`id_bank_asal`,' - ',NEW.`keterangan`),
                NULL,NEW.`id_shift`
            );
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksipembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_purchase_capture_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_purchase_capture_ai` AFTER INSERT ON `tbltransaksipembelian` FOR EACH ROW BEGIN
    CALL sp_sync_capture_transaction('TRANSACTION_PURCHASE',NEW.id_pembelian,'POST',NEW.tanggal);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksipembelian
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_pembelian_payment_insert`;
delimiter ;;
CREATE TRIGGER `trg_pembelian_payment_insert` AFTER INSERT ON `tbltransaksipembelian` FOR EACH ROW BEGIN
    IF (@DISABLE_TRIGGER IS NULL OR @DISABLE_TRIGGER=0)
       AND COALESCE(NEW.hapus,0)=0
       AND UPPER(COALESCE(NEW.mode_pembayaran,'')) IN ('TUNAI','TRANSFER')
       AND COALESCE(NEW.total,0)>0 AND COALESCE(NEW.id_bank,'')<>''
    THEN
        CALL `sp_post_saldo_v2`(
            'PURCHASE',NEW.id_pembelian,NULL,NEW.id_pembelian,NEW.id_bank,'MAIN',
            0,NEW.total,NEW.tanggal,NEW.id_admin,'PEMBELIAN','Pembelian',
            CONCAT('Pembelian ',NEW.id_pembelian),NULL,NEW.id_shift
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksipembelian_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_pembelian_detail_location_bi`;
delimiter ;;
CREATE TRIGGER `trg_pembelian_detail_location_bi` BEFORE INSERT ON `tbltransaksipembelian_detail` FOR EACH ROW BEGIN
    DECLARE v_lokasi varchar(30);
    SELECT masuk_ke INTO v_lokasi
    FROM tbltransaksipembelian
    WHERE id_pembelian=NEW.id_pembelian
    LIMIT 1;
    IF COALESCE(NULLIF(v_lokasi,''),'')='' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Gudang tujuan header pembelian wajib tersedia.';
    END IF;
    SET NEW.masuk_ke=v_lokasi;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksipembelian_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_pembelian_detail_location_bu`;
delimiter ;;
CREATE TRIGGER `trg_pembelian_detail_location_bu` BEFORE UPDATE ON `tbltransaksipembelian_detail` FOR EACH ROW BEGIN
    DECLARE v_lokasi varchar(30);
    SELECT masuk_ke INTO v_lokasi
    FROM tbltransaksipembelian
    WHERE id_pembelian=NEW.id_pembelian
    LIMIT 1;
    IF COALESCE(NULLIF(v_lokasi,''),'')='' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Gudang tujuan header pembelian wajib tersedia.';
    END IF;
    SET NEW.masuk_ke=v_lokasi;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksipembelian_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_pembelian_detail_stock_insert`;
delimiter ;;
CREATE TRIGGER `trg_pembelian_detail_stock_insert` AFTER INSERT ON `tbltransaksipembelian_detail` FOR EACH ROW BEGIN
    DECLARE v_tanggal datetime;
    DECLARE v_admin varchar(50);
    DECLARE v_lokasi varchar(50);
    DECLARE v_id_po varchar(30);
    DECLARE v_source_uid char(36);

    IF COALESCE(@DISABLE_TRIGGER,0)=0 THEN
        SELECT tanggal,id_admin,masuk_ke,id_po
        INTO v_tanggal,v_admin,v_lokasi,v_id_po
        FROM tbltransaksipembelian
        WHERE id_pembelian=NEW.id_pembelian
        LIMIT 1;
        SET v_source_uid=(
            SELECT transaction_uuid
            FROM tblsync_transaction_identity
            WHERE aggregate_type='TRANSACTION_PURCHASE'
              AND business_id=NEW.id_pembelian
            LIMIT 1
        );

        IF NEW.hapus=0
           AND COALESCE(v_id_po,'')=''
           AND COALESCE(NEW.jumlah,0)>0
           AND COALESCE(NEW.isi,0)>0
        THEN
            CALL sp_post_stok_v2(
                'PURCHASE',CONCAT(NEW.id_detail),v_source_uid,NEW.id_pembelian,
                NEW.id_produk,v_lokasi,'MAIN',NEW.jumlah*NEW.isi,0,
                COALESCE(v_tanggal,NOW()),
                COALESCE(NULLIF(NEW.id_admin,''),NULLIF(v_admin,''),'SYSTEM'),
                CONCAT('Pembelian ',COALESCE(NEW.id_pembelian,'-'))
            );
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksipenjualan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_penjualan_saldo_insert`;
delimiter ;;
CREATE TRIGGER `trg_penjualan_saldo_insert` AFTER INSERT ON `tbltransaksipenjualan` FOR EACH ROW BEGIN
    IF (@DISABLE_TRIGGER IS NULL OR @DISABLE_TRIGGER=0)
       AND COALESCE(NEW.source_type,'NORMAL')='NORMAL'
       AND UPPER(COALESCE(NEW.mode_pembayaran,''))<>'PIUTANG'
       AND COALESCE(NEW.grand_total,0)>0
       AND COALESCE(NEW.id_bank,'')<>''
    THEN
        CALL `sp_post_saldo_v2`(
            'SALE',NEW.id_transaksi,NULL,NEW.id_transaksi,NEW.id_bank,'MAIN',
            NEW.grand_total,0,NEW.tanggal,NEW.id_admin,'PENJUALAN','Penjualan',
            CONCAT('Penjualan ',NEW.id_transaksi),NULL,NEW.id_shift
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksipenjualan
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_sale_capture_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_sale_capture_ai` AFTER INSERT ON `tbltransaksipenjualan` FOR EACH ROW BEGIN
    IF COALESCE(NEW.source_type,'NORMAL')='NORMAL' THEN
        CALL `sp_sync_capture_transaction`(
            'TRANSACTION_SALE',NEW.id_transaksi,'POST',NEW.tanggal
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransaksipenjualan_detail
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_potong_stok_otomatis_insert`;
delimiter ;;
CREATE TRIGGER `trg_potong_stok_otomatis_insert` AFTER INSERT ON `tbltransaksipenjualan_detail` FOR EACH ROW BEGIN
    DECLARE v_tanggal DATETIME;
    DECLARE v_admin VARCHAR(50);
    DECLARE v_lokasi VARCHAR(50);
    DECLARE v_source_uid CHAR(36);
    DECLARE v_source_type VARCHAR(20) DEFAULT 'NORMAL';

    SELECT tanggal,id_admin,COALESCE(source_type,'NORMAL')
    INTO v_tanggal,v_admin,v_source_type
    FROM tbltransaksipenjualan
    WHERE id_transaksi=NEW.id_transaksi
    LIMIT 1;

    IF COALESCE(@DISABLE_TRIGGER,0)=0
       AND v_source_type='NORMAL'
       AND NEW.terjual=1
       AND COALESCE(NEW.jumlah,0)>0
       AND COALESCE(NEW.isi_satuan,0)>0
    THEN
        SET v_lokasi=COALESCE(
            NULLIF(NEW.id_lokasi,''),
            (SELECT nilai FROM tblpengaturan
             WHERE kode='id_lokasi_utama' AND hapus=0 LIMIT 1),
            'TOKO'
        );
        SET v_source_uid=(
            SELECT transaction_uuid
            FROM tblsync_transaction_identity
            WHERE aggregate_type='TRANSACTION_SALE'
              AND business_id=NEW.id_transaksi
            LIMIT 1
        );

        CALL `sp_post_stok_v2`(
            'SALE',CONCAT(NEW.id_detail),v_source_uid,NEW.id_transaksi,
            NEW.id_produk,v_lokasi,'MAIN',0,NEW.jumlah*NEW.isi_satuan,
            COALESCE(v_tanggal,NOW()),COALESCE(NULLIF(v_admin,''),'SYSTEM'),
            CONCAT('Penjualan ',COALESCE(NEW.id_transaksi,'-'))
        );
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransferbarang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_transfer_capture_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_transfer_capture_ai` AFTER INSERT ON `tbltransferbarang` FOR EACH ROW BEGIN
    CALL sp_sync_capture_transaction(
        'TRANSACTION_TRANSFER_STOCK',NEW.id_transfer,'POST',COALESCE(NEW.tanggal,CURRENT_TIMESTAMP(6))
    );
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbltransferbarang
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_transfer_barang_stock_insert`;
delimiter ;;
CREATE TRIGGER `trg_transfer_barang_stock_insert` AFTER INSERT ON `tbltransferbarang` FOR EACH ROW BEGIN
    DECLARE v_source_uid char(36);

    IF COALESCE(@DISABLE_TRIGGER,0)=0 AND COALESCE(NEW.jumlah,0)>0 THEN
        SET v_source_uid=(
            SELECT transaction_uuid
            FROM tblsync_transaction_identity
            WHERE aggregate_type='TRANSACTION_TRANSFER_STOCK'
              AND business_id=NEW.id_transfer
            LIMIT 1
        );

        IF BINARY NEW.jenis_dari<>BINARY 'LAINNYA' THEN
            CALL sp_post_stok_v2(
                'STOCK_TRANSFER',NEW.id_transfer,v_source_uid,NEW.id_transfer,
                NEW.id_produk,NEW.dari,'TRANSFER_OUT',0,NEW.jumlah,
                COALESCE(NEW.tanggal,NOW()),
                COALESCE(NULLIF(NEW.id_admin,''),'SYSTEM'),
                CONCAT('Transfer ',NEW.id_transfer,' ke ',NEW.ke)
            );
        END IF;
        IF BINARY NEW.jenis_ke<>BINARY 'LAINNYA' THEN
            CALL sp_post_stok_v2(
                'STOCK_TRANSFER',NEW.id_transfer,v_source_uid,NEW.id_transfer,
                NEW.id_produk,NEW.ke,'TRANSFER_IN',NEW.jumlah,0,
                COALESCE(NEW.tanggal,NOW()),
                COALESCE(NULLIF(NEW.id_admin,''),'SYSTEM'),
                CONCAT('Transfer ',NEW.id_transfer,' dari ',NEW.dari)
            );
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbluser
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_user_bi`;
delimiter ;;
CREATE TRIGGER `trg_sync_user_bi` BEFORE INSERT ON `tbluser` FOR EACH ROW BEGIN IF NEW.sync_uid IS NULL OR NEW.sync_uid='' THEN SET NEW.sync_uid=LOWER(UUID()); END IF; IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.row_version=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbluser
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_user_bu`;
delimiter ;;
CREATE TRIGGER `trg_sync_user_bu` BEFORE UPDATE ON `tbluser` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN SET NEW.sync_uid=OLD.sync_uid; SET NEW.row_version=OLD.row_version+1; SET NEW.sync=1; SET NEW.updated_at=CURRENT_TIMESTAMP(6); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbluser
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_user_ai`;
delimiter ;;
CREATE TRIGGER `trg_sync_user_ai` AFTER INSERT ON `tbluser` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 THEN CALL sp_sync_capture_catalog('SECURITY_USER',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table tbluser
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_sync_user_au`;
delimiter ;;
CREATE TRIGGER `trg_sync_user_au` AFTER UPDATE ON `tbluser` FOR EACH ROW BEGIN IF COALESCE(@SYNC_APPLY_REMOTE,0)=0 AND NEW.row_version<>OLD.row_version THEN CALL sp_sync_capture_catalog('SECURITY_USER',NEW.sync_uid,COALESCE(NEW.hapus,0),NEW.row_version); END IF; END
;;
delimiter ;

SET FOREIGN_KEY_CHECKS = 1;
