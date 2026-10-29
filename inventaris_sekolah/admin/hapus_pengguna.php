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
    header("Location: pengguna.php");
    exit;
}

if (
    !isset($_POST['id']) ||
    !is_string($_POST['id']) ||
    !ctype_digit($_POST['id']) ||
    (int) $_POST['id'] < 1
) {
    header("Location: pengguna.php?pesan=gagal_hapus");
    exit;
}

$id = (int) $_POST['id'];

if ($id === (int) $_SESSION['id_pengguna']) {
    header("Location: pengguna.php?pesan=gagal_hapus_diri");
    exit;
}

try {

    mysqli_begin_transaction($conn);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_pengguna
         FROM pengguna
         WHERE id_pengguna = ?
         FOR UPDATE"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    $pengguna = mysqli_fetch_assoc($hasil);

    mysqli_stmt_close($stmt);

    if (!$pengguna) {
        mysqli_rollback($conn);
        header("Location: pengguna.php?pesan=gagal_hapus");
        exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_peminjaman
         FROM peminjaman
         WHERE id_pengguna = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    $memiliki_peminjaman = mysqli_stmt_num_rows($stmt) > 0;

    mysqli_stmt_close($stmt);

    if ($memiliki_peminjaman) {
        mysqli_rollback($conn);
        header("Location: pengguna.php?pesan=pengguna_memiliki_peminjaman");
        exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM pengguna
         WHERE id_pengguna = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Gagal menghapus pengguna.");
    }

    $berhasil = mysqli_stmt_affected_rows($stmt) === 1;

    mysqli_stmt_close($stmt);

    if (!$berhasil) {
        throw new Exception("Data pengguna tidak berhasil dihapus.");
    }

    mysqli_commit($conn);

    header("Location: pengguna.php?pesan=hapus_berhasil");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);

    error_log("Hapus pengguna ID {$id}: " . $e->getMessage());

    header("Location: pengguna.php?pesan=gagal_hapus");
    exit;
}
?>