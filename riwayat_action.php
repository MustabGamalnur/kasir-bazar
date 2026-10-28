<?php
require_once 'config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. DELETE / BATALKAN TRANSAKSI
if ($action === 'delete_trx') {
    $saleId = (int)($_GET['id'] ?? 0);

    if ($saleId > 0) {
        try {
            $pdo->beginTransaction();

            // Hapus rincian item terlebih dahulu
            $stmtItems = $pdo->prepare("DELETE FROM sales_items WHERE sale_id = ?");
            $stmtItems->execute([$saleId]);

            // Hapus data header transaksi
            $stmtLog = $pdo->prepare("DELETE FROM sales_logs WHERE id = ?");
            $stmtLog->execute([$saleId]);

            $pdo->commit();
            header("Location: riwayat.php?status=success&msg=Transaksi+berhasil+dibatalkan+dan+dihapus");
            exit;
        } catch (\Exception $e) {
            $pdo->rollBack();
            header("Location: riwayat.php?status=error&msg=Gagal+membatalkan+transaksi:+" . urlencode($e->getMessage()));
            exit;
        }
    }
    header("Location: riwayat.php?status=error&msg=ID+transaksi+tidak+valid");
    exit;
}

// 2. UPDATE / KOREKSI DATA TRANSAKSI
if ($action === 'edit_trx' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $saleId        = (int)($_POST['id'] ?? 0);
    $namaPelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $metodeBayar   = $_POST['metode_bayar'] ?? '';

    $allowedMethods = ['TUNAI', 'QRIS', 'TKI'];

    if ($saleId > 0 && !empty($namaPelanggan) && in_array($metodeBayar, $allowedMethods)) {
        try {
            $stmt = $pdo->prepare("UPDATE sales_logs SET nama_pelanggan = ?, metode_bayar = ? WHERE id = ?");
            $stmt->execute([$namaPelanggan, $metodeBayar, $saleId]);

            header("Location: riwayat.php?status=success&msg=Data+transaksi+berhasil+diperbarui");
            exit;
        } catch (\Exception $e) {
            header("Location: riwayat.php?status=error&msg=Gagal+mengubah+transaksi:+" . urlencode($e->getMessage()));
            exit;
        }
    }
    header("Location: riwayat.php?status=error&msg=Data+koreksi+transaksi+tidak+lengkap");
    exit;
}

header("Location: riwayat.php");
exit;