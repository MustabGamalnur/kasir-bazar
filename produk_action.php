<?php
require_once 'config.php';

$action = $_REQUEST['action'] ?? '';
$stand_id = 1; // Stand default

// 1. CREATE: Tambah Produk Baru
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $harga       = (int)($_POST['harga'] ?? 0);

    if (!empty($nama_produk) && $harga > 0) {
        $stmt = $pdo->prepare("INSERT INTO products (stand_id, nama_produk, harga) VALUES (?, ?, ?)");
        $stmt->execute([$stand_id, $nama_produk, $harga]);
        header("Location: produk.php?status=success&msg=Produk berhasil ditambahkan");
        exit;
    } else {
        header("Location: produk.php?status=error&msg=Nama dan harga produk wajib diisi dengan benar");
        exit;
    }
}

// 2. UPDATE: Edit Data Produk
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $harga       = (int)($_POST['harga'] ?? 0);

    if ($id > 0 && !empty($nama_produk) && $harga > 0) {
        $stmt = $pdo->prepare("UPDATE products SET nama_produk = ?, harga = ? WHERE id = ? AND stand_id = ?");
        $stmt->execute([$nama_produk, $harga, $id, $stand_id]);
        header("Location: produk.php?status=success&msg=Produk berhasil diperbarui");
        exit;
    } else {
        header("Location: produk.php?status=error&msg=Gagal memperbarui data produk");
        exit;
    }
}

// 3. DELETE: Hapus Produk
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND stand_id = ?");
        $stmt->execute([$id, $stand_id]);
        header("Location: produk.php?status=success&msg=Produk berhasil dihapus");
        exit;
    } else {
        header("Location: produk.php?status=error&msg=ID produk tidak valid");
        exit;
    }
}

// Redirect fallback jika akses tanpa aksi valid
header("Location: produk.php");
exit;