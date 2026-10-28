<?php
require_once 'config.php';

$stand_id = 1;

// Mode Edit: Cek apakah ada parameter ?edit=ID di URL
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM products WHERE id = ? AND stand_id = ?");
    $stmtEdit->execute([$edit_id, $stand_id]);
    $edit_data = $stmtEdit->fetch();
}

// READ: Ambil seluruh daftar produk stand ini
$stmt = $pdo->prepare("SELECT * FROM products WHERE stand_id = ? ORDER BY id DESC");
$stmt->execute([$stand_id]);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu Produk - Stand Bazar</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="navbar">
        <div class="brand">
            <h2>POS Bazar Al Umanaa</h2>
            <span class="stand-badge">Manajemen Menu</span>
        </div>
        <nav style="display: flex; gap: 8px;">
            <a href="index.php" class="btn-secondary">Kasir</a>
            <a href="riwayat.php" class="btn-secondary">Riwayat</a>
        </nav>
    </header>

    <div style="max-width: 1200px; margin: 20px auto; padding: 0 20px;">
        <!-- Notifikasi Feedback -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert <?= ($_GET['status'] ?? '') === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?= htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <div class="crud-layout">
            <!-- Kolom Form: Tambah / Edit -->
            <div class="crud-card">
                <h3><?= $edit_data ? 'Edit Menu Produk' : 'Tambah Menu Baru'; ?></h3>
                <hr style="margin: 12px 0; border: 0; border-top: 1px solid #dfe3e8;">

                <form action="produk_action.php" method="POST">
                    <input type="hidden" name="action" value="<?= $edit_data ? 'update' : 'create'; ?>">
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="id" value="<?= $edit_data['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nama_produk">Nama Produk / Makanan:</label>
                        <input type="text" id="nama_produk" name="nama_produk" 
                               value="<?= htmlspecialchars($edit_data['nama_produk'] ?? ''); ?>" 
                               placeholder="cth: Risol Mayo" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="harga">Harga (Rp):</label>
                        <input type="number" id="harga" name="harga" min="500" step="500"
                               value="<?= htmlspecialchars($edit_data['harga'] ?? ''); ?>" 
                               placeholder="cth: 5000" required>
                    </div>

                    <div style="display: flex; gap: 8px; margin-top: 16px;">
                        <button type="submit" class="btn-checkout" style="padding: 10px;">
                            <?= $edit_data ? 'Perbarui Menu' : 'Simpan Produk'; ?>
                        </button>
                        <?php if ($edit_data): ?>
                            <a href="produk.php" class="btn-secondary" style="display: flex; align-items: center;">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Kolom Tabel: Daftar Produk -->
            <div class="crud-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3>Daftar Menu Saat Ini</h3>
                    <span style="font-size: 0.85rem; color: #637381; font-weight: 600;">Total: <?= count($products); ?> Menu</span>
                </div>

                <div class="cart-wrapper" style="max-height: 480px;">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Produk</th>
                                <th>Harga</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">Belum ada menu yang didaftarkan.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($products as $row): ?>
                                    <tr>
                                        <td style="width: 40px;"><?= $no++; ?></td>
                                        <td><strong><?= htmlspecialchars($row['nama_produk']); ?></strong></td>
                                        <td>Rp <?= number_format($row['harga'], 0, ',', '.'); ?></td>
                                        <td style="text-align: center; width: 140px;">
                                            <a href="produk.php?edit=<?= $row['id']; ?>" class="btn-action btn-edit">Edit</a>
                                            <a href="produk_action.php?action=delete&id=<?= $row['id']; ?>" 
                                               class="btn-action btn-delete" 
                                               onclick="return confirm('Yakin ingin menghapus menu <?= htmlspecialchars(addslashes($row['nama_produk'])); ?>?')">Hapus</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>