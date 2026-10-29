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
    !isset($_POST['id_peminjaman']) ||
    !ctype_digit((string) $_POST['id_peminjaman']) ||
    (int) $_POST['id_peminjaman'] <= 0
) {
    header("Location: index.php?status=gagal");
    exit;
}

$id = (int) $_POST['id_peminjaman'];

if (!mysqli_begin_transaction($conn)) {
    header("Location: index.php?status=gagal");
    exit;
}

try {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT status
         FROM peminjaman
         WHERE id_peminjaman = ?
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception("Gagal memeriksa data peminjaman.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Gagal mengambil data peminjaman.");
    }

    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$data) {
        throw new Exception("Data peminjaman tidak ditemukan.");
    }

    if (!in_array($data['status'], ['Menunggu', 'Ditolak'], true)) {
        throw new Exception(
            "Peminjaman yang sudah disetujui atau dikembalikan tidak boleh dihapus."
        );
    }

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM detail_peminjaman
         WHERE id_peminjaman = ?"
    );

    if (!$stmt) {
        throw new Exception("Gagal menyiapkan penghapusan detail.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Gagal menghapus detail peminjaman.");
    }

    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM peminjaman
         WHERE id_peminjaman = ?"
    );

    if (!$stmt) {
        throw new Exception("Gagal menyiapkan penghapusan peminjaman.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Gagal menghapus data peminjaman.");
    }

    if (mysqli_stmt_affected_rows($stmt) !== 1) {
        mysqli_stmt_close($stmt);
        throw new Exception("Data peminjaman gagal dihapus.");
    }

    mysqli_stmt_close($stmt);

    mysqli_commit($conn);

    header("Location: index.php?status=terhapus");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    header("Location: index.php?status=gagal");
    exit;
}
?>