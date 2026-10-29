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
    !isset($_POST['id_lokasi']) ||
    !ctype_digit((string) $_POST['id_lokasi']) ||
    (int) $_POST['id_lokasi'] <= 0
) {
    header("Location: index.php?status=gagal");
    exit;
}

$id = (int) $_POST['id_lokasi'];

$cek = mysqli_prepare(
    $conn,
    "SELECT id_barang
     FROM barang
     WHERE id_lokasi = ?
     LIMIT 1"
);

if (!$cek) {
    header("Location: index.php?status=gagal");
    exit;
}

mysqli_stmt_bind_param($cek, "i", $id);

if (!mysqli_stmt_execute($cek)) {
    mysqli_stmt_close($cek);
    header("Location: index.php?status=gagal");
    exit;
}

mysqli_stmt_store_result($cek);

if (mysqli_stmt_num_rows($cek) > 0) {
    mysqli_stmt_close($cek);
    header("Location: index.php?status=dipakai");
    exit;
}

mysqli_stmt_close($cek);

$hapus = mysqli_prepare(
    $conn,
    "DELETE FROM lokasi
     WHERE id_lokasi = ?"
);

if (!$hapus) {
    header("Location: index.php?status=gagal");
    exit;
}

mysqli_stmt_bind_param($hapus, "i", $id);

if (!mysqli_stmt_execute($hapus)) {
    mysqli_stmt_close($hapus);
    header("Location: index.php?status=gagal");
    exit;
}

$berhasil = mysqli_stmt_affected_rows($hapus) > 0;

mysqli_stmt_close($hapus);

if ($berhasil) {
    header("Location: index.php?status=terhapus");
    exit;
}

header("Location: index.php?status=gagal");
exit;
?>
