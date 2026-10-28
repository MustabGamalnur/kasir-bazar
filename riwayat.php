<?php
require_once 'config.php';

// Ambil daftar booth untuk filter
$stmtBooths = $pdo->query("SELECT * FROM booths ORDER BY nama_booth ASC");
$booths = $stmtBooths->fetchAll();

// 1. Parameter Filter & Pencarian
$q        = trim($_GET['q'] ?? '');
$metode   = trim($_GET['metode'] ?? 'ALL');
$boothId  = (int)($_GET['booth_id'] ?? 0);

// Dapatkan nama booth terpilih
$selectedBoothName = 'Semua Booth / UMKM';
if ($boothId > 0) {
    foreach ($booths as $b) {
        if ($b['id'] == $boothId) {
            $selectedBoothName = $b['nama_booth'];
            break;
        }
    }
}

// 2. Ambil Total Omzet Keseluruhan Kasir Utama (Global)
$stmtTotal = $pdo->query("SELECT COALESCE(SUM(total_bayar), 0) AS grand_omzet, COUNT(*) AS grand_transaksi FROM sales_logs");
$totalData = $stmtTotal->fetch();

// 3. Ambil Rekap Pendapatan per Metode Bayar (Global)
$stmtSummary = $pdo->query("
    SELECT metode_bayar, COUNT(*) as total_transaksi, COALESCE(SUM(total_bayar), 0) as total_nominal
    FROM sales_logs
    GROUP BY metode_bayar
");
$methodSummaries = $stmtSummary->fetchAll();

// 4. Ambil Rekap Hak Pencairan per Booth
$stmtBoothSummary = $pdo->query("
    SELECT booth_id, nama_booth, COALESCE(SUM(subtotal), 0) as omzet_booth, COALESCE(SUM(qty), 0) as total_item_terjual
    FROM sales_items
    GROUP BY booth_id, nama_booth
    ORDER BY omzet_booth DESC
");
$boothSummaries = $stmtBoothSummary->fetchAll();

// 5. Query Tabel Rekap Produk Terjual (Mengikuti Filter Booth)
$queryProdSum = "
    SELECT 
        nama_booth,
        nama_barang,
        COALESCE(nama_topping, '-') AS varian_topping,
        harga_satuan,
        SUM(qty) AS total_terjual,
        SUM(subtotal) AS total_omzet
    FROM sales_items
";
$paramsProdSum = [];

if ($boothId > 0) {
    $queryProdSum .= " WHERE booth_id = ?";
    $paramsProdSum[] = $boothId;
}

$queryProdSum .= " GROUP BY nama_booth, nama_barang, varian_topping, harga_satuan ORDER BY nama_booth ASC, total_omzet DESC";
$stmtProdSum = $pdo->prepare($queryProdSum);
$stmtProdSum->execute($paramsProdSum);
$productSummaries = $stmtProdSum->fetchAll();

// 6. Query Transaksi dengan Pencarian Multi-Kolom (Invoice, Pelanggan, Produk, Booth)
$whereConditions = [];
$paramsLogs = [];

// Pencarian Multi-Kolom (Revisi Poin 1)
if (!empty($q)) {
    $whereConditions[] = "(
        s.invoice_code LIKE ? 
        OR s.nama_pelanggan LIKE ? 
        OR s.id IN (
            SELECT sale_id FROM sales_items 
            WHERE nama_barang LIKE ? OR nama_booth LIKE ?
        )
    )";
    $paramsLogs[] = "%$q%";
    $paramsLogs[] = "%$q%";
    $paramsLogs[] = "%$q%";
    $paramsLogs[] = "%$q%";
}

// Filter Metode Bayar
if (!empty($metode) && $metode !== 'ALL') {
    $whereConditions[] = "s.metode_bayar = ?";
    $paramsLogs[] = $metode;
}

// Filter Booth Terpilih (Revisi Poin 2)
if ($boothId > 0) {
    $whereConditions[] = "s.id IN (SELECT sale_id FROM sales_items WHERE booth_id = ?)";
    $paramsLogs[] = $boothId;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

$queryLogs = "
    SELECT s.id, s.invoice_code, s.nama_pelanggan, s.metode_bayar, s.total_bayar, s.waktu_transaksi,
           GROUP_CONCAT(
               CONCAT(
                   i.nama_barang, 
                   IF(i.nama_topping IS NOT NULL AND i.nama_topping != '', CONCAT(' (+', i.nama_topping, ')'), ''),
                   ' [', i.nama_booth, '] (x', i.qty, ')'
               ) SEPARATOR '<br>'
           ) AS rincian_item
    FROM sales_logs s
    LEFT JOIN sales_items i ON s.id = i.sale_id
    $whereClause
    GROUP BY s.id, s.invoice_code, s.nama_pelanggan, s.metode_bayar, s.total_bayar, s.waktu_transaksi
    ORDER BY s.waktu_transaksi DESC
    LIMIT 100
";

$stmtLogs = $pdo->prepare($queryLogs);
$stmtLogs->execute($paramsLogs);
$logs = $stmtLogs->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap & Riwayat Kasir Sentral</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .badge-TKI { background: #e8f5e9; color: #2e7d32; }
        .badge-QRIS { background: #e3f2fd; color: #1565c0; }
        .badge-TUNAI { background: #fff3e0; color: #ef6c00; }
        
        .rekap-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .rekap-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #dfe3e8;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            cursor: pointer;
            transition: 0.15s;
        }
        .rekap-card:hover {
            transform: translateY(-2px);
            border-color: #2e7d32;
        }
        .rekap-title {
            font-size: 0.82rem;
            color: #637381;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .rekap-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #212b36;
        }

        .filter-panel {
            background: #ffffff;
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }

        .filter-inputs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .export-box {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .active-filter-alert {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            color: #0d47a1;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.88rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="brand">
            <h2>Riwayat & Rekonsiliasi Kasir</h2>
            <span class="stand-badge">Kasir Sentral</span>
        </div>
        <a href="index.php" class="btn-secondary">Kembali ke Kasir</a>
    </header>

    <div style="max-width: 1350px; margin: 20px auto; padding: 0 20px;">
        <!-- Notifikasi Status Aksi -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert <?= ($_GET['status'] ?? '') === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?= htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <!-- Ringkasan Arus Kas Masuk Kasir Utama -->
        <h3 style="margin-bottom: 12px;">Arus Kas Masuk (Kasir Utama)</h3>
        <div class="rekap-grid">
            <div class="rekap-card" style="border-left: 4px solid #1b5e20; background: #fbfdfb;" onclick="window.location.href='riwayat.php'">
                <div class="rekap-title">TOTAL OMZET BAZAR (<?= (int)$totalData['grand_transaksi']; ?> Trx)</div>
                <div class="rekap-value" style="color: #1b5e20;">Rp <?= number_format($totalData['grand_omzet'], 0, ',', '.'); ?></div>
            </div>

            <?php if (empty($methodSummaries)): ?>
                <div class="rekap-card">
                    <div class="rekap-title">Belum ada transaksi</div>
                    <div class="rekap-value">Rp 0</div>
                </div>
            <?php else: ?>
                <?php foreach ($methodSummaries as $ms): ?>
                    <div class="rekap-card">
                        <div class="rekap-title">Metode <?= htmlspecialchars($ms['metode_bayar']); ?> (<?= $ms['total_transaksi']; ?> Trx)</div>
                        <div class="rekap-value">Rp <?= number_format($ms['total_nominal'], 0, ',', '.'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Ringkasan Pencairan Tiap Booth -->
        <h3 style="margin-bottom: 12px;">Rekap Hak Pencairan Booth / UMKM</h3>
        <div class="rekap-grid" style="margin-bottom: 24px;">
            <?php if (empty($boothSummaries)): ?>
                <div class="rekap-card" style="border-left: 4px solid #dfe3e8;">
                    <div class="rekap-title">Belum ada menu terjual</div>
                    <div class="rekap-value">Rp 0</div>
                </div>
            <?php else: ?>
                <?php foreach ($boothSummaries as $bs): ?>
                    <div class="rekap-card" style="border-left: 4px solid <?= $boothId == $bs['booth_id'] ? '#0d47a1' : '#2e7d32'; ?>; <?= $boothId == $bs['booth_id'] ? 'background: #e3f2fd;' : ''; ?>" 
                         onclick="changeBoothFilter(<?= $bs['booth_id']; ?>)">
                        <div class="rekap-title"><?= htmlspecialchars($bs['nama_booth']); ?> (<?= $bs['total_item_terjual']; ?> porsi)</div>
                        <div class="rekap-value" style="color: <?= $boothId == $bs['booth_id'] ? '#0d47a1' : '#2e7d32'; ?>;">
                            Rp <?= number_format($bs['omzet_booth'], 0, ',', '.'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Panel Filter & Pencarian Universal -->
        <div class="filter-panel">
            <form action="riwayat.php" method="GET" class="filter-inputs" id="filterForm">
                <input type="hidden" name="booth_id" value="<?= $boothId; ?>">
                
                <!-- Pencarian Multi-Kolom: Invoice, Pelanggan, Menu, Booth (Revisi Poin 1) -->
                <input type="text" name="q" value="<?= htmlspecialchars($q); ?>" 
                       placeholder="Cari invoice, pelanggan, produk, booth..." 
                       style="padding: 8px 12px; border: 1px solid #c4cdd5; border-radius: 6px; font-size: 0.88rem; width: 280px;" autocomplete="off">
                
                <select name="metode" style="padding: 8px 10px; border: 1px solid #c4cdd5; border-radius: 6px; font-size: 0.88rem;">
                    <option value="ALL" <?= $metode === 'ALL' ? 'selected' : ''; ?>>Semua Metode</option>
                    <option value="TUNAI" <?= $metode === 'TUNAI' ? 'selected' : ''; ?>>Tunai</option>
                    <option value="QRIS" <?= $metode === 'QRIS' ? 'selected' : ''; ?>>QRIS</option>
                    <option value="TKI" <?= $metode === 'TKI' ? 'selected' : ''; ?>>TKI</option>
                </select>

                <button type="submit" class="btn-action-primary" style="padding: 8px 14px;">Cari</button>
                <?php if (!empty($q) || $metode !== 'ALL' || $boothId > 0): ?>
                    <a href="riwayat.php" class="btn-secondary" style="padding: 7px 12px;">Reset Filter</a>
                <?php endif; ?>
            </form>

            <!-- Dropdown Rekap Booth Interaktif (Revisi Poin 2) -->
            <div class="export-box">
                <label style="font-size: 0.82rem; font-weight: 600; color: #637381;">Rekap Produk:</label>
                <select id="exportBoothSelect" onchange="changeBoothFilter(this.value)" style="padding: 8px 10px; border: 1px solid #c4cdd5; border-radius: 6px; font-size: 0.88rem; font-weight: 600;">
                    <option value="0" <?= $boothId === 0 ? 'selected' : ''; ?>>-- Semua Booth / UMKM --</option>
                    <?php foreach ($booths as $b): ?>
                        <option value="<?= $b['id']; ?>" <?= $boothId == $b['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($b['nama_booth']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn-action-primary" onclick="exportData('excel')" style="background: #107c41;">
                    📊 Download Excel
                </button>
                <button type="button" class="btn-secondary" onclick="exportData('print')">
                    🖨 Cetak / PDF
                </button>
            </div>
        </div>

        <!-- Banner Informasi Filter Aktif -->
        <?php if ($boothId > 0): ?>
            <div class="active-filter-alert">
                <span>Menampilkan rekapitulasi penjualan khusus booth: <strong><?= htmlspecialchars($selectedBoothName); ?></strong></span>
                <a href="riwayat.php" style="color: #0d47a1; font-weight: bold; text-decoration: underline; font-size: 0.82rem;">Tampilkan Semua Booth</a>
            </div>
        <?php endif; ?>

        <!-- TABEL 1: Rekapitulasi Produk & Topping Terjual Sesuai Booth (Revisi Poin 2) -->
        <div class="checkout-section" style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3>Rekapitulasi Menu Terjual: <?= htmlspecialchars($selectedBoothName); ?></h3>
                <span style="font-size: 0.85rem; color: #637381; font-weight: 600;">Total Varian: <?= count($productSummaries); ?> Menu</span>
            </div>
            
            <div class="cart-wrapper" style="max-height: 380px;">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="text-center">No</th>
                            <?php if ($boothId === 0): ?><th style="width: 160px;">Booth</th><?php endif; ?>
                            <th>Nama Produk / Menu</th>
                            <th style="width: 180px;">Topping / Varian</th>
                            <th style="width: 130px;">Harga Satuan</th>
                            <th style="width: 110px;" class="text-center">Qty Terjual</th>
                            <th style="width: 150px;">Total Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productSummaries)): ?>
                            <tr>
                                <td colspan="<?= $boothId === 0 ? '7' : '6'; ?>" class="text-center" style="padding: 20px;">
                                    Belum ada menu yang terjual untuk booth ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $noProd = 1;
                            $sumPorsi = 0;
                            $sumOmzet = 0;
                            foreach ($productSummaries as $row): 
                                $sumPorsi += $row['total_terjual'];
                                $sumOmzet += $row['total_omzet'];
                            ?>
                                <tr>
                                    <td class="text-center"><?= $noProd++; ?></td>
                                    <?php if ($boothId === 0): ?>
                                        <td><span class="booth-tag"><?= htmlspecialchars($row['nama_booth']); ?></span></td>
                                    <?php endif; ?>
                                    <td><strong><?= htmlspecialchars($row['nama_barang']); ?></strong></td>
                                    <td><?= htmlspecialchars($row['varian_topping']); ?></td>
                                    <td>Rp <?= number_format($row['harga_satuan'], 0, ',', '.'); ?></td>
                                    <td class="text-center"><strong><?= $row['total_terjual']; ?></strong> porsi</td>
                                    <td><strong>Rp <?= number_format($row['total_omzet'], 0, ',', '.'); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="background: #f1f8e9; font-weight: bold;">
                                <td colspan="<?= $boothId === 0 ? '5' : '4'; ?>" style="text-align: right;">TOTAL:</td>
                                <td class="text-center"><?= $sumPorsi; ?> porsi</td>
                                <td style="color: #1b5e20;">Rp <?= number_format($sumOmzet, 0, ',', '.'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABEL 2: Catatan Transaksi Terkini (Filtered) -->
        <div class="checkout-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3>Catatan Transaksi Terkini <?= $boothId > 0 ? "($selectedBoothName)" : ''; ?></h3>
                <span style="font-size: 0.85rem; color: #637381; font-weight: 600;">Ditemukan: <?= count($logs); ?> Transaksi</span>
            </div>
            
            <div class="cart-wrapper" style="max-height: 480px;">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th style="width: 75px;">Waktu</th>
                            <th style="width: 160px;">Invoice</th>
                            <th style="width: 170px;">Pelanggan</th>
                            <th>Rincian Menu, Topping & Booth</th>
                            <th style="width: 85px;">Metode</th>
                            <th style="width: 110px;">Total</th>
                            <th style="width: 120px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" class="text-center" style="padding: 24px;">Tidak ada transaksi yang cocok dengan kriteria filter saat ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <td><?= date('H:i:s', strtotime($row['waktu_transaksi'])); ?></td>
                                    <td><strong><?= htmlspecialchars($row['invoice_code']); ?></strong></td>
                                    <td><?= htmlspecialchars($row['nama_pelanggan']); ?></td>
                                    <td style="line-height: 1.5; font-size: 0.85rem;"><?= $row['rincian_item'] ?? '-'; ?></td>
                                    <td><span class="badge badge-<?= $row['metode_bayar']; ?>"><?= $row['metode_bayar']; ?></span></td>
                                    <td><strong>Rp <?= number_format($row['total_bayar'], 0, ',', '.'); ?></strong></td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <button type="button" class="btn-action btn-edit" style="border: none; cursor: pointer;" 
                                                onclick="openEditTrxModal(<?= $row['id']; ?>, '<?= addslashes(htmlspecialchars($row['invoice_code'])); ?>', '<?= addslashes(htmlspecialchars($row['nama_pelanggan'])); ?>', '<?= $row['metode_bayar']; ?>')">
                                            Edit
                                        </button>
                                        <a href="riwayat_action.php?action=delete_trx&id=<?= $row['id']; ?>" 
                                           class="btn-action btn-delete" 
                                           onclick="return confirm('Batalkan transaksi <?= htmlspecialchars(addslashes($row['invoice_code'])); ?>?\nTransaksi ini akan dihapus dan omzet disesuaikan!')">
                                            Batal
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Koreksi Data Transaksi -->
    <div class="modal-overlay" id="editTrxModal">
        <div class="modal-card" style="max-width: 440px;">
            <div class="modal-header">
                <h3>Koreksi Transaksi</h3>
                <button type="button" class="btn-close-modal" onclick="closeEditTrxModal()">✕</button>
            </div>
            <form action="riwayat_action.php" method="POST">
                <input type="hidden" name="action" value="edit_trx">
                <input type="hidden" name="id" id="editTrxId" value="0">

                <div class="modal-body">
                    <div class="form-group">
                        <label>Nomor Invoice:</label>
                        <input type="text" id="editTrxInvoice" readonly style="background: #f4f6f8; font-weight: bold; color: #454f5b;">
                    </div>

                    <div class="form-group">
                        <label for="editNamaPelanggan">Nama Pelanggan / Keterangan:</label>
                        <input type="text" name="nama_pelanggan" id="editNamaPelanggan" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="editMetodeBayar">Metode Pembayaran:</label>
                        <select name="metode_bayar" id="editMetodeBayar" required style="width: 100%; padding: 10px; border: 1px solid #c4cdd5; border-radius: 6px;">
                            <option value="TUNAI">Tunai</option>
                            <option value="QRIS">QRIS</option>
                            <option value="TKI">Tap TKI (NFC)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditTrxModal()">Tutup</button>
                    <button type="submit" class="btn-checkout" style="padding: 10px 18px;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fungsi pindah filter booth secara instan saat dropdown dipilih atau kartu booth diklik
        function changeBoothFilter(boothId) {
            const currentQ = "<?= urlencode($q); ?>";
            const currentMetode = "<?= urlencode($metode); ?>";
            window.location.href = `riwayat.php?booth_id=${boothId}&q=${currentQ}&metode=${currentMetode}`;
        }

        function openEditTrxModal(id, invoice, nama, metode) {
            document.getElementById('editTrxId').value = id;
            document.getElementById('editTrxInvoice').value = invoice;
            document.getElementById('editNamaPelanggan').value = nama;
            document.getElementById('editMetodeBayar').value = metode;
            document.getElementById('editTrxModal').classList.add('open');
        }

        function closeEditTrxModal() {
            document.getElementById('editTrxModal').classList.remove('open');
        }

        function exportData(format) {
            const boothId = document.getElementById('exportBoothSelect').value;
            const url = `export.php?booth_id=${boothId}&format=${format}`;
            if (format === 'print') {
                window.open(url, '_blank', 'width=900,height=700');
            } else {
                window.location.href = url;
            }
        }
    </script>
</body>
</html>