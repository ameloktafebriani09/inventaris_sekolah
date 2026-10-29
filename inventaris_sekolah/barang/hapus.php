<?php
session_start();
require_once "../koneksi.php";

// Periksa hak akses
if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'pengelola_sarpras'
) {
    header("Location: ../auth/login.php");
    exit;
}

// Penghapusan hanya melalui POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Validasi ID barang
if (
    !isset($_POST['id_barang']) ||
    !ctype_digit((string) $_POST['id_barang']) ||
    (int) $_POST['id_barang'] < 1
) {
    header("Location: index.php?status=gagal");
    exit;
}

$id = (int) $_POST['id_barang'];

mysqli_begin_transaction($conn);

try {
    // Pastikan barang tersedia dan kunci data selama proses
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_barang
         FROM barang
         WHERE id_barang = ?
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception("Gagal memeriksa barang.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $barang = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$barang) {
        throw new Exception("tidak_ditemukan");
    }

    // Periksa riwayat perubahan kondisi
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_riwayat
         FROM riwayat_barang
         WHERE id_barang = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception("Gagal memeriksa riwayat barang.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $adaRiwayat = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    if ($adaRiwayat) {
        throw new Exception("ada_riwayat");
    }

    // Periksa detail peminjaman
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_detail
         FROM detail_peminjaman
         WHERE id_barang = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception("Gagal memeriksa data peminjaman.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $adaPeminjaman = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    if ($adaPeminjaman) {
        throw new Exception("ada_peminjaman");
    }

    // Periksa detail pengembalian
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_detail_pengembalian
         FROM detail_pengembalian
         WHERE id_barang = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception("Gagal memeriksa data pengembalian.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $adaPengembalian = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    if ($adaPengembalian) {
        throw new Exception("ada_pengembalian");
    }

    // Hapus barang jika tidak memiliki data terkait
    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM barang
         WHERE id_barang = ?"
    );

    if (!$stmt) {
        throw new Exception("Gagal menyiapkan penghapusan barang.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Gagal menghapus barang.");
    }

    $terhapus = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($terhapus < 1) {
        throw new Exception("tidak_ditemukan");
    }

    mysqli_commit($conn);

    header("Location: index.php?status=terhapus");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);

    $pesan = $e->getMessage();

    if ($pesan === "ada_riwayat") {
        header("Location: index.php?status=ada_riwayat");
    } elseif ($pesan === "ada_peminjaman") {
        header("Location: index.php?status=ada_peminjaman");
    } elseif ($pesan === "ada_pengembalian") {
        header("Location: index.php?status=ada_pengembalian");
    } elseif ($pesan === "tidak_ditemukan") {
        header("Location: index.php?status=gagal");
    } else {
        header("Location: index.php?status=gagal");
    }

    exit;
}
?>
