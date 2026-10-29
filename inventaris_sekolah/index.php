
<?php
session_start();

// Jika belum login, masuk ke halaman login
if (!isset($_SESSION['id_pengguna'], $_SESSION['role'])) {
    header("Location: auth/login.php");
    exit;
}

// Arahkan pengelola sarana prasarana
if ($_SESSION['role'] === 'pengelola_sarpras') {
    header("Location: admin/index.php");
    exit;
}

// Arahkan guru, teknisi, dan kepala sekolah
if (in_array(
    $_SESSION['role'],
    ['guru', 'teknisi', 'kepala_sekolah'],
    true
)) {
    header("Location: pengguna/index.php");
    exit;
}

// Jika role tidak dikenali
session_unset();
session_destroy();

header("Location: auth/login.php");
exit;
?>
