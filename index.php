<?php
require_once 'config.php';

// Ambil semua booth untuk tombol filter & modal
$stmtBooths = $pdo->query("SELECT * FROM booths ORDER BY nama_booth ASC");
$booths = $stmtBooths->fetchAll();

// Ambil produk beserta booth dan relasi toppingnya
$stmtProducts = $pdo->query("
    SELECT p.id, p.booth_id, p.nama_produk, p.harga_dasar, b.nama_booth
    FROM products p
    JOIN booths b ON p.booth_id = b.id
    ORDER BY b.nama_booth ASC, p.nama_produk ASC
");
$rawProducts = $stmtProducts->fetchAll();

// Ambil seluruh topping
$stmtToppings = $pdo->query("SELECT * FROM product_toppings ORDER BY harga_ekstra ASC");
$rawToppings = $stmtToppings->fetchAll();

// Kelompokkan topping ke dalam array produk
$productsMap = [];
foreach ($rawProducts as $p) {
    $productsMap[$p['id']] = [
        'id'          => (int)$p['id'],
        'booth_id'    => (int)$p['booth_id'],
        'nama_booth'  => $p['nama_booth'],
        'nama_produk' => $p['nama_produk'],
        'harga_dasar' => (int)$p['harga_dasar'],
        'toppings'    => []
    ];
}

foreach ($rawToppings as $t) {
    if (isset($productsMap[$t['product_id']])) {
        $productsMap[$t['product_id']]['toppings'][] = [
            'id'           => (int)$t['id'],
            'nama_topping' => $t['nama_topping'],
            'harga_ekstra' => (int)$t['harga_ekstra']
        ];
    }
}
$productsList = array_values($productsMap);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Sentral Bazar Al Umanaa</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="navbar">
        <div class="brand">
            <h2>POS Bazar Al Umanaa</h2>
            <span class="stand-badge">Kasir Sentral</span>
        </div>
        <nav style="display: flex; gap: 8px;">
            <a href="riwayat.php" class="btn-secondary">Riwayat & Rekap</a>
        </nav>
    </header>

    <!-- Notifikasi Feedback CRUD -->
    <?php if (isset($_GET['msg'])): ?>
        <div style="max-width: 1400px; margin: 15px auto -5px; padding: 0 20px;">
            <div class="alert <?= ($_GET['status'] ?? '') === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?= htmlspecialchars($_GET['msg']); ?>
            </div>
        </div>
    <?php endif; ?>

    <main class="pos-container">
        <!-- Area Katalog Produk & Filter Booth -->
        <section class="catalog-section">
            <div class="catalog-header">
                <h3>Pilih Menu</h3>
                <div class="catalog-actions">
                    <button type="button" class="btn-action-primary" onclick="openBoothModal()">⚙ Kelola Booth</button>
                    <button type="button" class="btn-action-primary" onclick="openAddProductModal()">+ Tambah Menu</button>
                </div>
            </div>

            <!-- Filter Booth -->
            <div class="booth-filter-bar">
                <button type="button" class="filter-chip active" onclick="filterByBooth(0, this)">Semua Booth</button>
                <?php foreach ($booths as $b): ?>
                    <button type="button" class="filter-chip" onclick="filterByBooth(<?= $b['id']; ?>, this)">
                        <?= htmlspecialchars($b['nama_booth']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Grid Menu Produk -->
            <div class="product-grid" id="productGridContainer">
                <?php if (empty($productsList)): ?>
                    <p style="grid-column: 1 / -1; text-align: center; color: #637381; padding: 30px 0;">
                        Belum ada menu produk. Klik <strong>+ Tambah Menu</strong> untuk menambahkan.
                    </p>
                <?php else: ?>
                    <?php foreach ($productsList as $p): ?>
                        <div class="product-card" 
                             data-booth-id="<?= $p['booth_id']; ?>" 
                             onclick="handleProductClick(<?= $p['id']; ?>)">
                            <div class="product-card-top">
                                <span class="booth-tag"><?= htmlspecialchars($p['nama_booth']); ?></span>
                                <button type="button" class="btn-card-edit" title="Edit Produk & Topping" onclick="openEditProductModal(<?= $p['id']; ?>, event)">✎</button>
                            </div>
                            <div class="product-name"><?= htmlspecialchars($p['nama_produk']); ?></div>
                            <div class="product-price">Rp <?= number_format($p['harga_dasar'], 0, ',', '.'); ?></div>
                            <?php if (!empty($p['toppings'])): ?>
                                <span class="topping-badge">+ <?= count($p['toppings']); ?> Topping</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Area Keranjang & Transaksi Kasir -->
        <section class="checkout-section">
            <div class="section-title">
                <h3>Detail Pesanan</h3>
            </div>

            <!-- Pilihan Cepat Kategori Pelanggan -->
            <div class="form-group">
                <label>Kategori Pelanggan:</label>
                <div class="customer-type-buttons">
                    <button type="button" class="btn-customer active" data-type="Santri" onclick="selectCustomerType('Santri')">
                        Santri
                    </button>
                    <button type="button" class="btn-customer" data-type="Asatidz" onclick="selectCustomerType('Asatidz')">
                        Asatidz
                    </button>
                    <button type="button" class="btn-customer" data-type="Wali Santri / Tamu" onclick="selectCustomerType('Wali Santri / Tamu')">
                        Wali Santri / Tamu
                    </button>
                </div>
            </div>

            <!-- Form Input Nama Pelanggan -->
            <div class="form-group" id="customerNameGroup">
                <label for="namaPelanggan" id="namaPelangganLabel">Nama Santri:</label>
                <input type="text" id="namaPelanggan" placeholder="Ketik nama santri..." autocomplete="off">
            </div>

            <div class="cart-wrapper">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Item & Booth</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="cartTableBody">
                        <tr id="emptyCartRow">
                            <td colspan="4" class="text-center">Belum ada item dipilih</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="total-box">
                <span>Total Tagihan:</span>
                <span id="grandTotalText">Rp 0</span>
            </div>

            <!-- Metode Pembayaran -->
            <div class="payment-selection">
                <label>Metode Pembayaran (Pencatatan):</label>
                <div class="payment-buttons">
                    <button type="button" class="btn-payment active" data-method="TKI" onclick="selectPaymentMethod('TKI')">
                        Tap TKI (NFC)
                    </button>
                    <button type="button" class="btn-payment" data-method="QRIS" onclick="selectPaymentMethod('QRIS')">
                        QRIS
                    </button>
                    <button type="button" class="btn-payment" data-method="TUNAI" onclick="selectPaymentMethod('TUNAI')">
                        Tunai
                    </button>
                </div>
            </div>

            <!-- Area Khusus Pembayaran Tunai (Kalkulator Kembalian Otomatis) -->
            <div class="cash-section" id="cashSection" style="display: none;">
                <div class="form-group" style="margin-bottom: 8px;">
                    <label for="uangDiterima">Uang Diterima dari Pelanggan (Rp):</label>
                    <input type="number" id="uangDiterima" placeholder="Ketik nominal uang..." min="0" step="1000" oninput="calculateChange()">
                </div>
                
                <!-- Tombol Nominal Cepat -->
                <div class="quick-cash-buttons">
                    <button type="button" class="btn-quick-cash" onclick="setExactCash()">Uang Pas</button>
                    <button type="button" class="btn-quick-cash" onclick="setQuickCash(10000)">10k</button>
                    <button type="button" class="btn-quick-cash" onclick="setQuickCash(20000)">20k</button>
                    <button type="button" class="btn-quick-cash" onclick="setQuickCash(50000)">50k</button>
                    <button type="button" class="btn-quick-cash" onclick="setQuickCash(100000)">100k</button>
                </div>

                <!-- Tampilan Kembalian / Status Uang Kurang -->
                <div class="change-box neutral" id="changeBox">
                    <span id="changeBoxLabel">Uang Kembalian:</span>
                    <strong id="changeAmountText">Rp 0</strong>
                </div>
            </div>

            <button type="button" class="btn-checkout" id="btnSubmitOrder" onclick="submitTransaction()">
                Simpan Transaksi
            </button>
        </section>
    </main>

    <!-- 1. Modal Pemilihan Topping Kasir -->
    <div class="modal-overlay" id="toppingModal">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <span class="booth-tag" id="modalBoothName">Booth</span>
                    <h3 id="modalProductName">Nama Produk</h3>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeToppingModal()">✕</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.85rem; color: #637381; margin-bottom: 12px;">Pilih varian atau topping tambahan:</p>
                <div id="toppingOptionsContainer" class="topping-list"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeToppingModal()">Batal</button>
                <button type="button" class="btn-checkout" style="padding: 10px 18px;" onclick="confirmAddToCartWithTopping()">
                    Tambahkan ke Pesanan
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Modal CRUD Booth / UMKM -->
    <div class="modal-overlay" id="boothModal">
        <div class="modal-card" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Kelola Booth / Stand UMKM</h3>
                <button type="button" class="btn-close-modal" onclick="closeBoothModal()">✕</button>
            </div>
            <div class="modal-body">
                <form action="crud_action.php" method="POST" style="margin-bottom: 20px;">
                    <input type="hidden" name="action" value="add_booth">
                    <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 6px;">Tambah Booth Baru:</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" name="nama_booth" placeholder="Nama booth (cth: Martabak Mas Bro)" required style="flex: 1; padding: 8px 12px; border: 1px solid #c4cdd5; border-radius: 6px;">
                        <button type="submit" class="btn-action-primary" style="padding: 8px 14px;">+ Tambah</button>
                    </div>
                </form>

                <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 8px;">Daftar Booth Aktif:</label>
                <div class="cart-wrapper" style="max-height: 220px;">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Nama Booth</th>
                                <th style="text-align: center; width: 80px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booths as $b): ?>
                                <tr>
                                    <td>
                                        <form action="crud_action.php" method="POST" style="display: flex; gap: 6px;">
                                            <input type="hidden" name="action" value="edit_booth">
                                            <input type="hidden" name="booth_id" value="<?= $b['id']; ?>">
                                            <input type="text" name="nama_booth" value="<?= htmlspecialchars($b['nama_booth']); ?>" required style="padding: 5px 8px; font-size: 0.85rem; width: 100%; border: 1px solid #dfe3e8; border-radius: 4px;">
                                            <button type="submit" class="btn-action btn-edit" style="border: none; cursor: pointer;">Simpan</button>
                                        </form>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="crud_action.php?action=delete_booth&booth_id=<?= $b['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Hapus booth <?= htmlspecialchars(addslashes($b['nama_booth'])); ?>? Seluruh produk di booth ini juga akan terhapus!')">Hapus</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeBoothModal()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- 3. Modal Form Tambah / Edit Produk & Topping -->
    <div class="modal-overlay" id="productModal">
        <div class="modal-card" style="max-width: 520px;">
            <div class="modal-header">
                <h3 id="productModalTitle">Tambah Menu Baru</h3>
                <button type="button" class="btn-close-modal" onclick="closeProductModal()">✕</button>
            </div>
            <form action="crud_action.php" method="POST" id="productForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="product_id" id="prodFormId" value="0">

                    <div class="form-group">
                        <label for="prodFormBooth">Milik Booth / Stand:</label>
                        <select id="prodFormBooth" name="booth_id" required style="width: 100%; padding: 10px; border: 1px solid #c4cdd5; border-radius: 6px; font-size: 0.95rem;">
                            <option value="">-- Pilih Booth --</option>
                            <?php foreach ($booths as $b): ?>
                                <option value="<?= $b['id']; ?>"><?= htmlspecialchars($b['nama_booth']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prodFormName">Nama Menu / Produk:</label>
                        <input type="text" id="prodFormName" name="nama_produk" placeholder="cth: Nasi Goreng Jawara" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="prodFormPrice">Harga Dasar (Rp):</label>
                        <input type="number" id="prodFormPrice" name="harga_dasar" min="500" step="500" placeholder="cth: 15000" required>
                    </div>

                    <div style="margin-top: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="font-size: 0.85rem; font-weight: 600;">Varian Topping Tambahan (Opsional):</label>
                            <button type="button" class="btn-action btn-edit" style="border: none; cursor: pointer;" onclick="addToppingRow()">+ Tambah Topping</button>
                        </div>
                        <div id="toppingRowsContainer" style="max-height: 180px; overflow-y: auto; padding: 4px;"></div>
                    </div>
                </div>

                <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                    <a href="#" id="btnDeleteProduct" class="btn-action btn-delete" style="display: none; padding: 8px 12px;" onclick="return confirm('Hapus produk ini secara permanen?')">Hapus Menu Ini</a>
                    <div style="display: flex; gap: 8px; margin-left: auto;">
                        <button type="button" class="btn-secondary" onclick="closeProductModal()">Batal</button>
                        <button type="submit" class="btn-checkout" style="padding: 10px 18px;">Simpan Menu</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Sinkronisasi PHP ke JavaScript -->
    <script>
        window.BOOTHS = <?= json_encode($booths, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.PRODUCTS = <?= json_encode($productsList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>