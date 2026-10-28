<?php
require_once 'config.php';

$boothId = (int)($_GET['booth_id'] ?? 0);
$format  = $_GET['format'] ?? 'excel'; // 'excel' atau 'print'

// Ambil info Booth
$boothName = 'Semua Booth / UMKM';
if ($boothId > 0) {
    $stmtB = $pdo->prepare("SELECT nama_booth FROM booths WHERE id = ?");
    $stmtB->execute([$boothId]);
    $boothData = $stmtB->fetch();
    if ($boothData) {
        $boothName = $boothData['nama_booth'];
    }
}

// Ambil Rincian Penjualan Produk per Booth
$query = "
    SELECT 
        nama_booth,
        nama_barang,
        COALESCE(nama_topping, '-') AS varian_topping,
        harga_satuan,
        SUM(qty) AS total_terjual,
        SUM(subtotal) AS total_omzet
    FROM sales_items
";
$params = [];

if ($boothId > 0) {
    $query .= " WHERE booth_id = ?";
    $params[] = $boothId;
}

$query .= " GROUP BY nama_booth, nama_barang, varian_topping, harga_satuan ORDER BY nama_booth ASC, total_omzet DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Header jika memilih unduh Excel
if ($format === 'excel') {
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $boothName);
    $filename = "Rekap_Penjualan_" . $safeName . "_" . date('Ymd_His') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Penjualan - <?= htmlspecialchars($boothName); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #333;
            padding: 20px;
        }
        .header-title {
            text-align: center;
            margin-bottom: 20px;
        }
        .header-title h2 { margin: 0 0 5px; }
        .header-title p { margin: 0; color: #666; font-size: 13px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table, th, td {
            border: 1px solid #999;
        }
        th {
            background-color: #f2f2f2;
            padding: 8px;
            text-align: left;
        }
        td {
            padding: 7px 8px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .total-row {
            font-weight: bold;
            background-color: #eafeea;
        }
        .sign-area {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .sign-box {
            text-align: center;
            width: 200px;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <?php if ($format === 'print'): ?>
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 8px 16px; background: #2e7d32; color: #fff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">
                🖨 Cetak Dokumen / Simpan PDF
            </button>
            <button onclick="window.close()" style="padding: 8px 16px; background: #666; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px;">
                Tutup
            </button>
        </div>
    <?php endif; ?>

    <div class="header-title">
        <h2>REKAPITULASI PENJUALAN PRODUK BAZAR AL UMANAA</h2>
        <p>Tenant / Booth: <strong><?= htmlspecialchars($boothName); ?></strong></p>
        <p>Waktu Cetak: <?= date('d-m-Y H:i:s'); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;" class="text-center">No</th>
                <?php if ($boothId === 0): ?><th>Booth</th><?php endif; ?>
                <th>Nama Produk / Menu</th>
                <th>Topping / Varian</th>
                <th class="text-right">Harga Satuan</th>
                <th class="text-center" style="width: 90px;">Qty Terjual</th>
                <th class="text-right">Subtotal Omzet</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalPorsi = 0;
            $grandTotal = 0;
            $no = 1;
            ?>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="<?= $boothId === 0 ? '7' : '6'; ?>" class="text-center" style="padding: 20px;">Belum ada produk yang terjual untuk booth ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $row): 
                    $totalPorsi += $row['total_terjual'];
                    $grandTotal += $row['total_omzet'];
                ?>
                    <tr>
                        <td class="text-center"><?= $no++; ?></td>
                        <?php if ($boothId === 0): ?><td><?= htmlspecialchars($row['nama_booth']); ?></td><?php endif; ?>
                        <td><strong><?= htmlspecialchars($row['nama_barang']); ?></strong></td>
                        <td><?= htmlspecialchars($row['varian_topping']); ?></td>
                        <td class="text-right">Rp <?= number_format($row['harga_satuan'], 0, ',', '.'); ?></td>
                        <td class="text-center"><?= $row['total_terjual']; ?></td>
                        <td class="text-right">Rp <?= number_format($row['total_omzet'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="<?= $boothId === 0 ? '5' : '4'; ?>" class="text-right">TOTAL KESELURUHAN:</td>
                    <td class="text-center"><?= $totalPorsi; ?> porsi</td>
                    <td class="text-right">Rp <?= number_format($grandTotal, 0, ',', '.'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="sign-area">
        <div class="sign-box">
            <p>Penanggung Jawab Booth,</p>
            <br><br><br>
            <p>( _______________________ )</p>
        </div>
        <div class="sign-box">
            <p>Bendahara / Kasir Sentral,</p>
            <br><br><br>
            <p>( _______________________ )</p>
        </div>
    </div>
</body>
</html>