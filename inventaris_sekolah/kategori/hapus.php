<?php
session_start();
require_once "../koneksi.php";

if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'pengelola_sarpras'
) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (
    !isset($_POST['id']) ||
    !ctype_digit((string) $_POST['id']) ||
    (int) $_POST['id'] < 1
) {
    header("Location: index.php?status=gagal");
    exit;
}

$id = (int) $_POST['id'];

$cek = mysqli_prepare(
    $conn,
    "SELECT id_barang
     FROM barang
     WHERE id_kategori = ?
     LIMIT 1"
);

if (!$cek) {
    header("Location: index.php?status=gagal");
    exit;
}

mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
mysqli_stmt_store_result($cek);

$kategori_dipakai = mysqli_stmt_num_rows($cek) > 0;

mysqli_stmt_close($cek);

if ($kategori_dipakai) {
    header("Location: index.php?status=dipakai");
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "DELETE FROM kategori
     WHERE id_kategori = ?"
);

if (!$stmt) {
    header("Location: index.php?status=gagal");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id);

$berhasil = mysqli_stmt_execute($stmt);
$jumlah_terhapus = mysqli_stmt_affected_rows($stmt);

mysqli_stmt_close($stmt);

if ($berhasil && $jumlah_terhapus > 0) {
    header("Location: index.php?status=terhapus");
    exit;
}

header("Location: index.php?status=gagal");
exit;
?>
