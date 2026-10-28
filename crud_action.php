<?php
require_once 'config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ==========================================
// 1. CRUD BOOTH / UMKM
// ==========================================

// Tambah Booth Baru
if ($action === 'add_booth' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_booth = trim($_POST['nama_booth'] ?? '');

    if (!empty($nama_booth)) {
        $stmt = $pdo->prepare("INSERT INTO booths (nama_booth) VALUES (?)");
        $stmt->execute([$nama_booth]);
        header("Location: index.php?status=success&msg=Booth+berhasil+ditambahkan");
        exit;
    }
    header("Location: index.php?status=error&msg=Nama+booth+tidak+boleh+kosong");
    exit;
}

// Edit Nama Booth
if ($action === 'edit_booth' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $booth_id   = (int)($_POST['booth_id'] ?? 0);
    $nama_booth = trim($_POST['nama_booth'] ?? '');

    if ($booth_id > 0 && !empty($nama_booth)) {
        $stmt = $pdo->prepare("UPDATE booths SET nama_booth = ? WHERE id = ?");
        $stmt->execute([$nama_booth, $booth_id]);
        header("Location: index.php?status=success&msg=Booth+berhasil+diperbarui");
        exit;
    }
    header("Location: index.php?status=error&msg=Gagal+memperbarui+nama+booth");
    exit;
}

// Hapus Booth
if ($action === 'delete_booth') {
    $booth_id = (int)($_GET['booth_id'] ?? 0);

    if ($booth_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM booths WHERE id = ?");
        $stmt->execute([$booth_id]);
        header("Location: index.php?status=success&msg=Booth+dan+seluruh+menunya+berhasil+dihapus");
        exit;
    }
    header("Location: index.php?status=error&msg=ID+booth+tidak+valid");
    exit;
}

// ==========================================
// 2. CRUD PRODUK & TOPPING
// ==========================================

// Simpan Produk (Tambah Baru atau Edit)
if ($action === 'save_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId   = (int)($_POST['product_id'] ?? 0);
    $boothId     = (int)($_POST['booth_id'] ?? 0);
    $namaProduk  = trim($_POST['nama_produk'] ?? '');
    $hargaDasar  = (int)($_POST['harga_dasar'] ?? 0);

    $toppingNames  = $_POST['topping_names'] ?? [];
    $toppingPrices = $_POST['topping_prices'] ?? [];

    if ($boothId <= 0 || empty($namaProduk) || $hargaDasar <= 0) {
        header("Location: index.php?status=error&msg=Data+produk+tidak+lengkap+atau+harga+tidak+valid");
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($productId > 0) {
            // Mode UPDATE Produk
            $stmt = $pdo->prepare("UPDATE products SET booth_id = ?, nama_produk = ?, harga_dasar = ? WHERE id = ?");
            $stmt->execute([$boothId, $namaProduk, $hargaDasar, $productId]);

            // Hapus topping lama untuk digantikan daftar topping terkini
            $stmtDelTop = $pdo->prepare("DELETE FROM product_toppings WHERE product_id = ?");
            $stmtDelTop->execute([$productId]);
        } else {
            // Mode CREATE Produk
            $stmt = $pdo->prepare("INSERT INTO products (booth_id, nama_produk, harga_dasar) VALUES (?, ?, ?)");
            $stmt->execute([$boothId, $namaProduk, $hargaDasar]);
            $productId = (int)$pdo->lastInsertId();
        }

        // Masukkan varian topping jika diisi
        if (!empty($toppingNames)) {
            $stmtTop = $pdo->prepare("INSERT INTO product_toppings (product_id, nama_topping, harga_ekstra) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($toppingNames); $i++) {
                $topName = trim($toppingNames[$i]);
                $topPrice = (int)($toppingPrices[$i] ?? 0);
                if (!empty($topName)) {
                    $stmtTop->execute([$productId, $topName, $topPrice]);
                }
            }
        }

        $pdo->commit();
        header("Location: index.php?status=success&msg=Menu+produk+berhasil+disimpan");
        exit;
    } catch (\Exception $e) {
        $pdo->rollBack();
        header("Location: index.php?status=error&msg=Database+Error:+" . urlencode($e->getMessage()));
        exit;
    }
}

// Hapus Produk
if ($action === 'delete_product') {
    $productId = (int)($_GET['id'] ?? 0);

    if ($productId > 0) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        header("Location: index.php?status=success&msg=Produk+berhasil+dihapus");
        exit;
    }
    header("Location: index.php?status=error&msg=ID+produk+tidak+valid");
    exit;
}

header("Location: index.php");
exit;