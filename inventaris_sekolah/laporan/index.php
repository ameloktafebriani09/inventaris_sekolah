
<?php
session_start();
require_once "../koneksi.php";

// Pastikan pengguna sudah login
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Hak akses: Pengelola Sarpras, Kepala Sekolah, dan Teknisi
$role = $_SESSION['role'] ?? '';

$role_diizinkan = [
    'pengelola_sarpras',
    'kepala_sekolah',
    'teknisi'
];

if (!in_array($role, $role_diizinkan, true)) {
    header("Location: ../index.php");
    exit;
}

// Mengambil statistik inventaris
$query_statistik = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_jenis,
        COALESCE(SUM(jumlah), 0) AS total_stok,
        COALESCE(SUM(jumlah_baik), 0) AS total_baik,
        COALESCE(SUM(jumlah_rusak_ringan), 0) AS total_rusak_ringan,
        COALESCE(SUM(jumlah_rusak_berat), 0) AS total_rusak_berat
    FROM barang
");

if (!$query_statistik) {
    die("Gagal mengambil statistik inventaris: " . mysqli_error($conn));
}

$statistik = mysqli_fetch_assoc($query_statistik);

// Mengambil daftar barang beserta kategori dan lokasi
$query_barang = mysqli_query($conn, "
    SELECT
        b.id_barang,
        b.kode_inventaris,
        b.nama_barang,
        b.jumlah,
        b.kondisi,
        b.jumlah_baik,
        b.jumlah_rusak_ringan,
        b.jumlah_rusak_berat,
        k.nama_kategori,
        l.nama_lokasi
    FROM barang b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    LEFT JOIN lokasi l ON b.id_lokasi = l.id_lokasi
    ORDER BY b.nama_barang ASC
");

if (!$query_barang) {
    die("Gagal mengambil data barang: " . mysqli_error($conn));
}

// Nama pengguna untuk header
$nama_pengguna = $_SESSION['nama'] ?? 'Pengguna';

// Tujuan tombol kembali sesuai role
$link_dashboard = $role === 'pengelola_sarpras'
    ? '../admin/index.php'
    : '../pengguna/index.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Inventaris | Sistem Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .laporan-container {
            padding: 28px;
            color: #e5e7eb;
        }

        .laporan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 26px;
        }

        .laporan-header h1 {
            margin: 0 0 8px;
            color: #ecfdf5;
            font-size: 28px;
        }

        .laporan-header p {
            margin: 0;
            color: #9ca3af;
        }

        .laporan-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .laporan-btn {
            display: inline-block;
            padding: 11px 16px;
            border: 0;
            border-radius: 9px;
            background: #059669;
            color: white;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .laporan-btn:hover {
            background: #047857;
        }

        .laporan-btn.kembali {
            background: #374151;
        }

        .laporan-statistik {
            display: grid;
            grid-template-columns: repeat(5, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .laporan-card {
            padding: 20px;
            background: #111827;
            border: 1px solid #263445;
            border-radius: 14px;
        }

        .laporan-card .label {
            display: block;
            margin-bottom: 12px;
            color: #9ca3af;
            font-size: 13px;
        }

        .laporan-card .angka {
            color: #34d399;
            font-size: 28px;
            font-weight: 700;
        }

        .laporan-card.ringan .angka {
            color: #fbbf24;
        }

        .laporan-card.berat .angka {
            color: #f87171;
        }

        .laporan-tabel-container {
            overflow-x: auto;
            background: #111827;
            border: 1px solid #263445;
            border-radius: 14px;
            padding: 20px;
        }

        .laporan-tabel-container h2 {
            margin: 0 0 18px;
            color: #ecfdf5;
            font-size: 19px;
        }

        .laporan-tabel {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        .laporan-tabel th,
        .laporan-tabel td {
            padding: 13px 12px;
            border-bottom: 1px solid #263445;
            text-align: left;
            font-size: 13px;
        }

        .laporan-tabel th {
            background: #064e3b;
            color: #ecfdf5;
            white-space: nowrap;
        }

        .laporan-tabel td {
            color: #d1d5db;
        }

        .laporan-tabel tbody tr:hover {
            background: #1f2937;
        }

        .kondisi-baik {
            color: #34d399 !important;
        }

        .kondisi-ringan {
            color: #fbbf24 !important;
        }

        .kondisi-berat {
            color: #f87171 !important;
        }

        .laporan-footer {
            margin-top: 18px;
            color: #9ca3af;
            font-size: 12px;
            text-align: right;
        }

        @media (max-width: 1100px) {
            .laporan-statistik {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .laporan-container {
                padding: 16px;
            }

            .laporan-header h1 {
                font-size: 23px;
            }

            .laporan-statistik {
                grid-template-columns: 1fr;
            }

            .laporan-card .angka {
                font-size: 24px;
            }

            .laporan-actions {
                width: 100%;
            }

            .laporan-btn {
                text-align: center;
            }
        }

        @media print {
            body {
                background: white !important;
                color: black !important;
            }

            .laporan-container {
                padding: 0;
                color: black !important;
            }

            .laporan-header h1,
            .laporan-tabel-container h2,
            .laporan-tabel td {
                color: black !important;
            }

            .laporan-header p,
            .laporan-card .label,
            .laporan-footer {
                color: #444 !important;
            }

            .laporan-actions {
                display: none !important;
            }

            .laporan-statistik {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }

            .laporan-card,
            .laporan-tabel-container {
                background: white !important;
                border: 1px solid #bbb !important;
                padding: 10px;
            }

            .laporan-card .angka,
            .laporan-tabel th,
            .laporan-tabel td {
                color: black !important;
            }

            .laporan-tabel th {
                background: #e5e7eb !important;
            }

            .laporan-tabel th,
            .laporan-tabel td {
                border: 1px solid #bbb;
                padding: 6px;
                font-size: 9px;
            }

            .laporan-tabel {
                min-width: 0;
            }
        }
    </style>
</head>

<body>

<div class="laporan-container">

    <div class="laporan-header">
        <div>
            <h1>Laporan Inventaris</h1>
            <p>
                Sistem Inventaris Barang Sekolah SMK Negeri 2 Padang
                <br>
                Pengguna: <?= htmlspecialchars($nama_pengguna) ?>
            </p>
        </div>

        <div class="laporan-actions">
            <a href="<?= htmlspecialchars($link_dashboard) ?>"
               class="laporan-btn kembali">
                Kembali ke Dashboard
            </a>

            <button type="button"
                    class="laporan-btn"
                    onclick="window.print()">
                Cetak Laporan
            </button>
        </div>
    </div>

    <div class="laporan-statistik">

        <div class="laporan-card">
            <span class="label">Total Jenis Barang</span>
            <div class="angka">
                <?= number_format((int) $statistik['total_jenis'], 0, ',', '.') ?>
            </div>
        </div>

        <div class="laporan-card">
            <span class="label">Total Stok Barang</span>
            <div class="angka">
                <?= number_format((int) $statistik['total_stok'], 0, ',', '.') ?>
            </div>
        </div>

        <div class="laporan-card">
            <span class="label">Barang Kondisi Baik</span>
            <div class="angka">
                <?= number_format((int) $statistik['total_baik'], 0, ',', '.') ?>
            </div>
        </div>

        <div class="laporan-card ringan">
            <span class="label">Rusak Ringan</span>
            <div class="angka">
                <?= number_format((int) $statistik['total_rusak_ringan'], 0, ',', '.') ?>
            </div>
        </div>

        <div class="laporan-card berat">
            <span class="label">Rusak Berat</span>
            <div class="angka">
                <?= number_format((int) $statistik['total_rusak_berat'], 0, ',', '.') ?>
            </div>
        </div>

    </div>

    <div class="laporan-tabel-container">

        <h2>Rincian Data Inventaris Barang</h2>

        <table class="laporan-tabel">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Kode Inventaris</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Lokasi</th>
                    <th>Total Stok</th>
                    <th>Baik</th>
                    <th>Rusak Ringan</th>
                    <th>Rusak Berat</th>
                </tr>
            </thead>

            <tbody>
                <?php if (mysqli_num_rows($query_barang) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($barang = mysqli_fetch_assoc($query_barang)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <?= htmlspecialchars($barang['kode_inventaris'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($barang['nama_barang'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($barang['nama_kategori'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($barang['nama_lokasi'] ?? '-') ?>
                            </td>

                            <td>
                                <?= number_format((int) $barang['jumlah'], 0, ',', '.') ?>
                            </td>

                            <td class="kondisi-baik">
                                <?= number_format((int) $barang['jumlah_baik'], 0, ',', '.') ?>
                            </td>

                            <td class="kondisi-ringan">
                                <?= number_format((int) $barang['jumlah_rusak_ringan'], 0, ',', '.') ?>
                            </td>

                            <td class="kondisi-berat">
                                <?= number_format((int) $barang['jumlah_rusak_berat'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">
                            Belum ada data inventaris barang.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="laporan-footer">
            Dicetak pada: <?= date('d-m-Y H:i') ?>
        </div>

    </div>

</div>

</body>
</html>
