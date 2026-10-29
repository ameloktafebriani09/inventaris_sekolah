
<?php
session_start();
require_once "../koneksi.php";

// ==========================================
// 1. LOGIN DAN HAK AKSES
// ==========================================

if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] === 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

$id_pengguna = (int) $_SESSION['id_pengguna'];
$nama = $_SESSION['nama'] ?? 'Pengguna';
$role = $_SESSION['role'];

$roleDiizinkan = [
    'pengelola_sarpras',
    'teknisi',
    'guru',
    'kepala_sekolah'
];

if (!in_array($role, $roleDiizinkan, true)) {
    http_response_code(403);
    exit("Akses ditolak.");
}

$isKepalaSekolah = ($role === 'kepala_sekolah');

$lihatSemuaData = in_array(
    $role,
    ['kepala_sekolah', 'pengelola_sarpras'],
    true
);

$bolehCetakBarang = in_array(
    $role,
    ['kepala_sekolah', 'pengelola_sarpras', 'teknisi'],
    true
);

// ==========================================
// 2. FUNGSI MENGHITUNG DATA
// ==========================================

function jumlahData($conn, $sql, $id_pengguna = null)
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        error_log("Gagal menyiapkan query: " . mysqli_error($conn));
        return 0;
    }

    if ($id_pengguna !== null) {
        mysqli_stmt_bind_param($stmt, "i", $id_pengguna);
    }

    if (!mysqli_stmt_execute($stmt)) {
        error_log("Gagal menjalankan query: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return 0;
    }

    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return (int) ($data['total'] ?? 0);
}

// ==========================================
// 3. RINGKASAN PEMINJAMAN
// ==========================================

if ($lihatSemuaData) {
    $total = jumlahData(
        $conn,
        "SELECT COUNT(*) AS total FROM peminjaman"
    );

    $menunggu = jumlahData(
        $conn,
        "SELECT COUNT(*) AS total
         FROM peminjaman
         WHERE status = 'Menunggu'"
    );

    $disetujui = jumlahData(
        $conn,
        "SELECT COUNT(*) AS total
         FROM peminjaman
         WHERE status = 'Disetujui'"
    );

    $dikembalikan = jumlahData(
        $conn,
        "SELECT COUNT(*) AS total
         FROM peminjaman
         WHERE status = 'Dikembalikan'"
    );
} else {
    $total = jumlahData(
        $conn,
        "SELECT COUNT(*) AS total
         FROM peminjaman
         WHERE id_pengguna = ?",
        $id_pengguna
    );

    $menunggu = jumlahData(
        $conn,
        "SELECT COUNT(*) AS total
         FROM peminjaman
         WHERE id_pengguna = ?
         AND status = 'Menunggu'",
        $id_pengguna
    );

    $disetujui = jumlahData(
        $conn,
        "SELECT COUNT(*) AS total
         FROM peminjaman
         WHERE id_pengguna = ?
         AND status = 'Disetujui'",
        $id_pengguna
    );

    $dikembalikan = jumlahData(
        $conn,
        "SELECT COUNT(*) AS total
         FROM peminjaman
         WHERE id_pengguna = ?
         AND status = 'Dikembalikan'",
        $id_pengguna
    );
}

// ==========================================
// 4. FUNGSI KEAMANAN TEKS
// ==========================================

function aman($teks)
{
    return htmlspecialchars(
        (string) ($teks ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Pengguna | Inventaris SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            margin: 0 0 8px;
        }

        .page-header p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .statistik {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 32px;
        }

        .kartu-statistik {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px;
            min-width: 0;
            transition: transform 0.2s, border-color 0.2s;
        }

        .kartu-statistik:hover {
            transform: translateY(-3px);
            border-color: #16866a;
        }

        .statistik-label {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0 0 14px;
        }

        .angka {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.2;
        }

        .statistik-total {
            border-top: 4px solid #16866a;
        }

        .statistik-menunggu {
            border-top: 4px solid #d6a33d;
        }

        .statistik-disetujui {
            border-top: 4px solid #3cba83;
        }

        .statistik-kembali {
            border-top: 4px solid #6a9de8;
        }

        .bagian-menu {
            margin-top: 10px;
        }

        .judul-bagian {
            margin: 0 0 16px;
            font-size: 20px;
        }

        .menu-pengguna {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .menu-item {
            display: flex;
            flex-direction: column;
            min-height: 145px;
            padding: 22px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 14px;
            text-decoration: none;
            color: var(--text);
            transition: transform 0.2s, border-color 0.2s;
        }

        .menu-item:hover {
            transform: translateY(-3px);
            border-color: #16866a;
        }

        .menu-item strong {
            display: block;
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--text);
        }

        .menu-item span {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .menu-ikon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            margin-bottom: 16px;
            border-radius: 11px;
            background: rgba(22, 134, 106, 0.15);
            color: #39bd91;
            font-size: 21px;
        }

        .bagian-logout {
            margin-top: 28px;
        }

        .btn-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            background: #9f3434;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-logout:hover {
            background: #7f2828;
        }

        .footer-dashboard {
            margin-top: 35px;
            padding: 20px 10px;
            border-top: 1px solid var(--border);
            color: var(--text-muted);
            text-align: center;
            font-size: 13px;
        }

        @media (max-width: 1000px) {
            .statistik,
            .menu-pengguna {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .statistik,
            .menu-pengguna {
                grid-template-columns: 1fr;
            }

            .kartu-statistik {
                padding: 18px;
            }

            .angka {
                font-size: 28px;
            }

            .menu-item {
                min-height: auto;
            }
        }
    </style>
</head>

<body>
<div class="main-content">

    <!-- HEADER DASHBOARD -->
    <div class="page-header">
        <span class="eyebrow">SISTEM INVENTARIS SEKOLAH</span>

        <h1>
            Dashboard
            <?php
            if ($role === 'kepala_sekolah') {
                echo 'Kepala Sekolah';
            } elseif ($role === 'pengelola_sarpras') {
                echo 'Pengelola Sarpras';
            } elseif ($role === 'teknisi') {
                echo 'Teknisi';
            } elseif ($role === 'guru') {
                echo 'Guru';
            } else {
                echo 'Pengguna';
            }
            ?>
        </h1>

        <p>
            Selamat datang, <strong><?= aman($nama); ?></strong>!

            <?php if ($lihatSemuaData): ?>
                Pantau data peminjaman dan laporan inventaris
                SMK Negeri 2 Padang.
            <?php else: ?>
                Kelola pengajuan dan pantau status peminjaman
                barang inventaris sekolah.
            <?php endif; ?>
        </p>
    </div>

    <!-- RINGKASAN PEMINJAMAN -->
    <section>
        <h2 class="judul-bagian">Ringkasan Peminjaman</h2>

        <div class="statistik">

            <div class="kartu-statistik statistik-total">
                <p class="statistik-label">Total Peminjaman</p>
                <div class="angka"><?= $total; ?></div>
            </div>

            <div class="kartu-statistik statistik-menunggu">
                <p class="statistik-label">Menunggu Persetujuan</p>
                <div class="angka"><?= $menunggu; ?></div>
            </div>

            <div class="kartu-statistik statistik-disetujui">
                <p class="statistik-label">Peminjaman Disetujui</p>
                <div class="angka"><?= $disetujui; ?></div>
            </div>

            <div class="kartu-statistik statistik-kembali">
                <p class="statistik-label">Sudah Dikembalikan</p>
                <div class="angka"><?= $dikembalikan; ?></div>
            </div>

        </div>
    </section>

    <!-- MENU PENGGUNA -->
    <section class="bagian-menu">
        <h2 class="judul-bagian">Menu Pengguna</h2>

        <div class="menu-pengguna">

            <!-- DATA BARANG -->
            <a href="daftar_barang.php" class="menu-item">
                <div class="menu-ikon">▤</div>

                <strong>Data Barang</strong>

                <span>
                    Lihat daftar barang, kategori, lokasi, jumlah,
                    dan kondisi barang inventaris sekolah.
                </span>
            </a>

            <!-- CETAK DAFTAR BARANG -->
            <?php if ($bolehCetakBarang): ?>
                <a href="cetak_daftar_barang.php" class="menu-item">
                    <div class="menu-ikon">▧</div>

                    <strong>Cetak Daftar Barang</strong>

                    <span>
                        Cetak daftar inventaris sekolah atau simpan
                        laporan dalam format PDF.
                    </span>
                </a>
            <?php endif; ?>

            <!-- LAPORAN KERUSAKAN -->
            <?php if (in_array(
                $role,
                ['teknisi', 'pengelola_sarpras', 'kepala_sekolah'],
                true
            )): ?>
                <a href="../laporan/barang_rusak.php" class="menu-item">
                    <div class="menu-ikon">⚠</div>

                    <strong>Laporan Kerusakan Barang</strong>

                    <span>
                        <?php if ($role === 'kepala_sekolah'): ?>
                            Lihat seluruh laporan kerusakan barang,
                            pantau status penanganan, dan cetak
                            laporan kerusakan inventaris sekolah.
                        <?php elseif ($role === 'teknisi'): ?>
                            Lihat laporan kerusakan barang dan
                            perbarui status penanganannya.
                        <?php else: ?>
                            Ajukan laporan kerusakan barang dan
                            pantau tindak lanjut dari teknisi.
                        <?php endif; ?>
                    </span>
                </a>
            <?php endif; ?>

            <!-- AJUKAN PEMINJAMAN -->
            <?php if (!$isKepalaSekolah): ?>
                <a href="ajukan_peminjaman.php" class="menu-item">
                    <div class="menu-ikon">＋</div>

                    <strong>Ajukan Peminjaman</strong>

                    <span>
                        Ajukan peminjaman barang inventaris
                        sesuai kebutuhan.
                    </span>
                </a>
            <?php endif; ?>

            <!-- RIWAYAT PEMINJAMAN -->
            <a href="riwayat_peminjaman.php" class="menu-item">
                <div class="menu-ikon">◷</div>

                <strong>Riwayat Peminjaman</strong>

                <span>
                    <?= $lihatSemuaData
                        ? 'Lihat riwayat peminjaman barang dari seluruh pengguna.'
                        : 'Lihat pengajuan yang pernah dibuat beserta statusnya.'; ?>
                </span>
            </a>

            <!-- RIWAYAT PENGEMBALIAN -->
            <a href="riwayat_pengembalian.php" class="menu-item">
                <div class="menu-ikon">↩</div>

                <strong>Riwayat Pengembalian</strong>

                <span>
                    <?= $lihatSemuaData
                        ? 'Lihat catatan pengembalian barang dari seluruh pengguna.'
                        : 'Lihat catatan pengembalian barang milik Anda.'; ?>
                </span>
            </a>

            <!-- NOTIFIKASI -->
            <?php if (!$isKepalaSekolah): ?>
                <a href="notifikasi.php" class="menu-item">
                    <div class="menu-ikon">♧</div>

                    <strong>Notifikasi Peminjaman</strong>

                    <span>
                        Periksa informasi status dan keterangan
                        terbaru dari pengajuan peminjaman.
                    </span>
                </a>
            <?php endif; ?>

            <!-- PROFIL -->
            <a href="profil.php" class="menu-item">
                <div class="menu-ikon">♙</div>

                <strong>Profil Saya</strong>

                <span>
                    Lihat informasi akun dan data profil pengguna.
                </span>
            </a>

            <!-- UBAH PASSWORD -->
            <a href="ubah_password.php" class="menu-item">
                <div class="menu-ikon">⚿</div>

                <strong>Ubah Password</strong>

                <span>
                    Ganti password akun untuk menjaga keamanan
                    akses Anda.
                </span>
            </a>

        </div>
    </section>

    <!-- LOGOUT -->
    <div class="bagian-logout">
        <a
            href="../auth/logout.php"
            class="btn-logout"
            onclick="return confirm('Yakin ingin keluar dari akun?')"
        >
            Logout
        </a>
    </div>

    <!-- FOOTER -->
    <footer class="footer-dashboard">
        Sistem Inventaris Barang Sekolah &copy; <?= date('Y'); ?>
        — SMK Negeri 2 Padang
    </footer>

</div>
</body>
</html>
