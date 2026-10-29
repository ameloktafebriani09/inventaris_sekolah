
<?php
session_start();
require_once "../koneksi.php";

// Cek login dan hak akses.
if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] === 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

$id_pengguna = (int) $_SESSION['id_pengguna'];
$id_peminjaman = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$role = $_SESSION['role'];

$lihatSemuaData = in_array(
    $role,
    ['kepala_sekolah', 'pengelola_sarpras'],
    true
);

// ID tidak valid tidak perlu diteruskan ke query.
if ($id_peminjaman <= 0) {
    http_response_code(404);
    $data = [];
} else {
    // Kepala Sekolah dan Pengelola Sarpras dapat melihat semua data.
    // Guru dan Teknisi hanya dapat melihat peminjaman sendiri.
    if ($lihatSemuaData) {
        $sql = "SELECT
                    p.id_peminjaman,
                    p.tanggal_pinjam,
                    p.tanggal_kembali,
                    p.status,
                    u.nama AS nama_peminjam,
                    b.kode_inventaris,
                    b.nama_barang,
                    dp.jumlah,
                    k.nama_kategori,
                    l.nama_lokasi
                FROM peminjaman p
                JOIN pengguna u
                    ON p.id_pengguna = u.id_pengguna
                JOIN detail_peminjaman dp
                    ON p.id_peminjaman = dp.id_peminjaman
                JOIN barang b
                    ON dp.id_barang = b.id_barang
                LEFT JOIN kategori k
                    ON b.id_kategori = k.id_kategori
                LEFT JOIN lokasi l
                    ON b.id_lokasi = l.id_lokasi
                WHERE p.id_peminjaman = ?
                ORDER BY b.nama_barang ASC";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_peminjaman
            );
        }
    } else {
        $sql = "SELECT
                    p.id_peminjaman,
                    p.tanggal_pinjam,
                    p.tanggal_kembali,
                    p.status,
                    b.kode_inventaris,
                    b.nama_barang,
                    dp.jumlah,
                    k.nama_kategori,
                    l.nama_lokasi
                FROM peminjaman p
                JOIN detail_peminjaman dp
                    ON p.id_peminjaman = dp.id_peminjaman
                JOIN barang b
                    ON dp.id_barang = b.id_barang
                LEFT JOIN kategori k
                    ON b.id_kategori = k.id_kategori
                LEFT JOIN lokasi l
                    ON b.id_lokasi = l.id_lokasi
                WHERE p.id_peminjaman = ?
                  AND p.id_pengguna = ?
                ORDER BY b.nama_barang ASC";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $id_peminjaman,
                $id_pengguna
            );
        }
    }

    if (!$stmt) {
        error_log("Gagal menyiapkan detail peminjaman: " . mysqli_error($conn));
        die("Terjadi kesalahan saat mengambil detail peminjaman.");
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    if (!$data) {
        http_response_code(404);
    }
}

function aman($nilai)
{
    return htmlspecialchars(
        (string) ($nilai ?? '-'),
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

    <title>Detail Peminjaman | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 26px;
        }

        .page-header p {
            margin: 6px 0 0;
            color: var(--text-muted);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .info-item {
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface-2);
            min-width: 0;
        }

        .info-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 12px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 6px;
            background: rgba(52, 211, 153, 0.14);
            color: #6ee7b7;
            font-size: 12px;
            font-weight: 600;
        }

        .table-card {
            margin-top: 10px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .tabel-detail {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        .tabel-detail th,
        .tabel-detail td {
            padding: 12px;
            text-align: left;
            vertical-align: middle;
        }

        .tabel-detail th {
            font-size: 12px;
        }

        .tabel-detail td {
            font-size: 13px;
        }

        .kosong {
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface-2);
            color: var(--text-muted);
            line-height: 1.7;
        }

        .aksi {
            margin-top: 25px;
        }

        @media (max-width: 600px) {
            .page-header h1 {
                font-size: 22px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .info-item {
                padding: 14px;
            }
        }
    </style>
</head>

<body>
<div class="main-content">

    <div class="page-header">
        <div>
            <p class="eyebrow">INVENTARIS SEKOLAH</p>
            <h1>Detail Peminjaman Barang</h1>
            <p>SMK Negeri 2 Padang</p>
        </div>

        <a href="riwayat_peminjaman.php" class="btn btn-secondary">
            Kembali ke Riwayat
        </a>
    </div>

    <?php if (!$data): ?>

        <div class="kosong">
            <strong>Data peminjaman tidak ditemukan.</strong>
            <br>
            Data mungkin tidak tersedia atau akun Anda tidak memiliki
            akses untuk melihat peminjaman tersebut.

            <div class="aksi">
                <a href="riwayat_peminjaman.php" class="btn btn-primary">
                    Kembali ke Riwayat
                </a>
            </div>
        </div>

    <?php else: ?>

        <div class="content-card">
            <h3>Informasi Peminjaman</h3>

            <div class="info-grid">

                <div class="info-item">
                    <span class="info-label">ID Peminjaman</span>
                    <div class="info-value">
                        #<?= (int) $data[0]['id_peminjaman']; ?>
                    </div>
                </div>

                <?php if ($lihatSemuaData): ?>
                    <div class="info-item">
                        <span class="info-label">Nama Peminjam</span>
                        <div class="info-value">
                            <?= aman($data[0]['nama_peminjam']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="info-item">
                    <span class="info-label">Status Peminjaman</span>
                    <div class="info-value">
                        <span class="status">
                            <?= aman($data[0]['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="info-item">
                    <span class="info-label">Tanggal Pinjam</span>
                    <div class="info-value">
                        <?= aman($data[0]['tanggal_pinjam']); ?>
                    </div>
                </div>

                <div class="info-item">
                    <span class="info-label">Tanggal Kembali</span>
                    <div class="info-value">
                        <?= aman($data[0]['tanggal_kembali']); ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="table-card">
            <h3>Daftar Barang yang Dipinjam</h3>

            <div class="table-wrapper">
                <table class="tabel-detail">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Kode Inventaris</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Lokasi</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($data as $i => $barang): ?>
                            <tr>
                                <td><?= $i + 1; ?></td>

                                <td>
                                    <?= aman($barang['kode_inventaris']); ?>
                                </td>

                                <td>
                                    <?= aman($barang['nama_barang']); ?>
                                </td>

                                <td>
                                    <?= aman($barang['nama_kategori']); ?>
                                </td>

                                <td>
                                    <?= aman($barang['nama_lokasi']); ?>
                                </td>

                                <td>
                                    <?= (int) $barang['jumlah']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="aksi">
            <a href="riwayat_peminjaman.php" class="btn btn-secondary">
                Kembali ke Riwayat
            </a>
        </div>

    <?php endif; ?>

</div>
</body>
</html>
