<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Hanya menerima request POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'Data pesanan kosong']);
    exit;
}

$namaPelanggan = trim($input['nama_pelanggan'] ?? 'Umum');
$metodeBayar   = $input['metode_bayar'];
$totalBayar    = (int)$input['total_bayar'];
$items         = $input['items'];

// Generate Nomor Invoice Kasir Utama: BZR-[TIMESTAMP]-[RANDOM 4 KARAKTER]
$invoiceCode = 'BZR-' . date('dHis') . '-' . strtoupper(substr(uniqid(), -4));

try {
    $pdo->beginTransaction();

    // 1. Simpan Header Transaksi Kasir Utama
    $stmtSale = $pdo->prepare("
        INSERT INTO sales_logs (invoice_code, nama_pelanggan, metode_bayar, total_bayar)
        VALUES (?, ?, ?, ?)
    ");
    $stmtSale->execute([$invoiceCode, $namaPelanggan, $metodeBayar, $totalBayar]);
    $saleId = $pdo->lastInsertId();

    // 2. Simpan Seluruh Detail Item dengan Booth & Topping Terpilih
    $stmtItem = $pdo->prepare("
        INSERT INTO sales_items (
            sale_id, product_id, booth_id, nama_booth, 
            nama_barang, nama_topping, harga_satuan, qty, subtotal
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $stmtItem->execute([
            $saleId,
            (int)$item['productId'],
            (int)$item['boothId'],
            $item['boothName'],
            $item['productName'],
            $item['toppingName'] ?? null,
            (int)$item['harga_satuan'],
            (int)$item['qty'],
            (int)$item['subtotal']
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'invoice' => $invoiceCode,
        'message' => 'Transaksi berhasil disimpan'
    ]);
} catch (\Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}