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

function aman($nilai)
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

$namaPengelola = $_SESSION['nama'] ?? 'Pengelola Sarana Prasarana';

$sqlStatistik = "
    SELECT
        (SELECT COUNT(*) FROM barang) AS total_barang,
        (SELECT COALESCE(SUM(jumlah), 0) FROM barang) AS total_stok,
        (SELECT COUNT(*) FROM kategori) AS total_kategori,
        (SELECT COUNT(*) FROM lokasi) AS total_lokasi,
        (
            SELECT COUNT(*)
            FROM peminjaman
            WHERE status = 'Menunggu'
        ) AS total_menunggu
";

$queryStatistik = mysqli_query($conn, $sqlStatistik);

if (!$queryStatistik) {
    error_log("Dashboard inventaris: " . mysqli_error($conn));
    http_response_code(500);
    exit("Terjadi kesalahan saat mengambil statistik dashboard. Silakan periksa konfigurasi database.");
}

$statistik = mysqli_fetch_assoc($queryStatistik);

$totalBarang   = (int) $statistik['total_barang'];
$totalStok     = (int) $statistik['total_stok'];
$totalKategori = (int) $statistik['total_kategori'];
$totalLokasi   = (int) $statistik['total_lokasi'];
$totalMenunggu = (int) $statistik['total_menunggu'];

$inisial = strtoupper(substr($namaPengelola, 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Inventaris | SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="app-layout">

    <aside class="sidebar">

        <div class="brand">
            <div class="brand-icon">IS</div>
            <div>
                <h2>Inventaris</h2>
                <p>SMK Negeri 2 Padang</p>
            </div>
        </div>

        <div class="sidebar-label">MENU UTAMA</div>

        <nav class="sidebar-nav">
            <a href="index.php" class="nav-link active">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a href="pengguna.php" class="nav-link">
                <span class="nav-icon">♙</span>
                Data Pengguna
            </a>

            <a href="../barang/index.php" class="nav-link">
                <span class="nav-icon">▤</span>
                Data Barang
            </a>

            <a href="../kategori/index.php" class="nav-link">
                <span class="nav-icon">▧</span>
                Data Kategori
            </a>

            <a href="../lokasi/index.php" class="nav-link">
                <span class="nav-icon">⌖</span>
                Data Lokasi
            </a>

            <a href="../peminjaman/index.php" class="nav-link">
                <span class="nav-icon">⇄</span>
                Data Peminjaman
            </a>

            <a href="../pengembalian/index.php" class="nav-link">
                <span class="nav-icon">↩</span>
                Data Pengembalian
            </a>

            <a href="../riwayat/index.php" class="nav-link">
                <span class="nav-icon">◷</span>
                Riwayat Barang
            </a>

            <a href="../laporan/index.php" class="nav-link">
                <span class="nav-icon">▥</span>
                Laporan Inventaris
            </a>

            <!-- MENU BARU: LAPORAN KERUSAKAN -->
            <a href="../laporan/barang_rusak.php" class="nav-link">
                <span class="nav-icon">⚠</span>
                Laporan Kerusakan
            </a>
        </nav>

        <div class="sidebar-label">AKUN</div>

        <nav class="sidebar-nav">
            <a href="profil.php" class="nav-link">
                <span class="nav-icon">♙</span>
                Profil Pengelola
            </a>

            <a href="ubah_password.php" class="nav-link">
                <span class="nav-icon">⚿</span>
                Ubah Password
            </a>

            <a
                href="../auth/logout.php"
                class="nav-link logout-link"
                onclick="return confirm('Yakin ingin keluar dari akun?')"
            >
                <span class="nav-icon">⇥</span>
                Logout
            </a>
        </nav>

        <div class="sidebar-footer">
            <span class="status-dot"></span>
            Sistem Inventaris Aktif
        </div>

    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <p class="eyebrow">SISTEM INFORMASI SEKOLAH</p>
                <h1>Dashboard</h1>
            </div>

            <div class="user-info">
                <div class="avatar">
                    <?= aman($inisial); ?>
                </div>

                <div>
                    <strong><?= aman($namaPengelola); ?></strong>
                    <span>Pengelola Sarana Prasarana</span>
                </div>
            </div>
        </header>

        <section class="welcome-card">
            <div>
                <p class="eyebrow">SELAMAT DATANG</p>

                <h2>Halo, <?= aman($namaPengelola); ?>!</h2>

                <p>
                    Pantau dan kelola inventaris barang
                    SMK Negeri 2 Padang melalui satu dashboard.
                </p>
            </div>

            <div class="welcome-decoration">IS</div>
        </section>

        <section class="section">

            <div class="section-heading">
                <div>
                    <h2>Ringkasan Inventaris</h2>
                    <p>Informasi terbaru dari database inventaris.</p>
                </div>
            </div>

            <div class="stats-grid">

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Total Jenis Barang</span>
                        <span class="stat-icon">▤</span>
                    </div>

                    <h3><?= $totalBarang; ?></h3>
                    <p>Jenis barang terdaftar</p>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Total Stok</span>
                        <span class="stat-icon">▦</span>
                    </div>

                    <h3><?= $totalStok; ?></h3>
                    <p>Jumlah seluruh barang</p>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Kategori</span>
                        <span class="stat-icon">▧</span>
                    </div>

                    <h3><?= $totalKategori; ?></h3>
                    <p>Kategori barang terdaftar</p>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span>Lokasi</span>
                        <span class="stat-icon">⌖</span>
                    </div>

                    <h3><?= $totalLokasi; ?></h3>
                    <p>Lokasi penyimpanan</p>
                </article>

                <article class="stat-card stat-warning">
                    <div class="stat-top">
                        <span>Peminjaman Menunggu</span>
                        <span class="stat-icon">◷</span>
                    </div>

                    <h3><?= $totalMenunggu; ?></h3>
                    <p>Menunggu persetujuan</p>
                </article>

            </div>
        </section>

        <section class="section panel">

            <div class="section-heading">
                <div>
                    <h2>Akses Cepat</h2>
                    <p>Pilih menu untuk mengelola data inventaris.</p>
                </div>
            </div>

            <div class="quick-menu">

                <a href="../barang/index.php" class="quick-link">
                    <span class="quick-icon">▤</span>
                    <span>
                        <strong>Data Barang</strong>
                        <small>Kelola inventaris</small>
                    </span>
                    <span class="arrow">→</span>
                </a>

                <a href="../kategori/index.php" class="quick-link">
                    <span class="quick-icon">▧</span>
                    <span>
                        <strong>Data Kategori</strong>
                        <small>Kelola kategori</small>
                    </span>
                    <span class="arrow">→</span>
                </a>

                <a href="../lokasi/index.php" class="quick-link">
                    <span class="quick-icon">⌖</span>
                    <span>
                        <strong>Data Lokasi</strong>
                        <small>Kelola lokasi barang</small>
                    </span>
                    <span class="arrow">→</span>
                </a>

                <a href="pengguna.php" class="quick-link">
                    <span class="quick-icon">♙</span>
                    <span>
                        <strong>Data Pengguna</strong>
                        <small>Kelola akun pengguna</small>
                    </span>
                    <span class="arrow">→</span>
                </a>

                <a href="../peminjaman/index.php" class="quick-link">
                    <span class="quick-icon">⇄</span>
                    <span>
                        <strong>Peminjaman</strong>
                        <small>Kelola pengajuan</small>
                    </span>
                    <span class="arrow">→</span>
                </a>

                <a href="../pengembalian/index.php" class="quick-link">
                    <span class="quick-icon">↩</span>
                    <span>
                        <strong>Pengembalian</strong>
                        <small>Kelola pengembalian</small>
                    </span>
                    <span class="arrow">→</span>
                </a>

                <!-- MENU BARU: AKSES CEPAT LAPORAN KERUSAKAN -->
                <a href="../laporan/barang_rusak.php" class="quick-link">
                    <span class="quick-icon">⚠</span>
                    <span>
                        <strong>Laporan Kerusakan</strong>
                        <small>Kirim dan pantau laporan kerusakan barang</small>
                    </span>
                    <span class="arrow">→</span>
                </a>

            </div>
        </section>

        <footer class="page-footer">
            Sistem Inventaris Barang · SMK Negeri 2 Padang
            &copy; <?= date('Y'); ?>
        </footer>

    </main>
</div>

</body>
</html>