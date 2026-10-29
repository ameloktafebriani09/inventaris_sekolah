<?php
session_start();
require_once "../koneksi.php";

// Pastikan pengguna sudah login dan memiliki role Pengelola Sarpras
if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'pengelola_sarpras'
) {
    header("Location: ../auth/login.php");
    exit;
}

// Ambil riwayat perubahan kondisi barang
$query = mysqli_query($conn, "
    SELECT
        r.id_riwayat,
        b.kode_inventaris,
        b.nama_barang,
        u.nama AS nama_pengguna,
        r.kondisi_sebelumnya,
        r.kondisi_baru,
        r.keterangan,
        r.waktu_perubahan
    FROM riwayat_barang r
    JOIN barang b ON r.id_barang = b.id_barang
    JOIN pengguna u ON r.id_pengguna = u.id_pengguna
    ORDER BY r.waktu_perubahan DESC
");

// Periksa apakah query berhasil
if (!$query) {
    error_log('Query riwayat barang gagal: ' . mysqli_error($conn));
    die("Gagal mengambil data riwayat barang. Periksa tabel dan kolom database.");
}

// Amankan teks sebelum ditampilkan ke halaman
function aman($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

$total_riwayat = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Barang | Inventaris SMK Negeri 2 Padang</title>

    <!-- CSS utama -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .riwayat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .riwayat-header {
            padding: 22px 24px;
            border-bottom: 1px solid var(--border);
        }

        .riwayat-header h2 {
            margin: 0 0 7px;
            color: var(--text);
            font-size: 19px;
        }

        .riwayat-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .riwayat-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 18px 24px;
        }

        .riwayat-total {
            color: var(--text-muted);
            font-size: 13px;
        }

        .riwayat-total strong {
            color: var(--text);
        }

        .tabel-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .tabel-riwayat {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
        }

        .tabel-riwayat th {
            padding: 14px 16px;
            background: var(--surface-2);
            color: var(--text-muted);
            text-align: left;
            font-size: 12px;
            white-space: nowrap;
            border-bottom: 1px solid var(--border);
        }

        .tabel-riwayat td {
            padding: 15px 16px;
            color: var(--text);
            font-size: 13px;
            line-height: 1.6;
            vertical-align: top;
            border-bottom: 1px solid var(--border);
        }

        .tabel-riwayat tbody tr:last-child td {
            border-bottom: none;
        }

        .tabel-riwayat tbody tr:hover {
            background: rgba(16, 185, 129, 0.04);
        }

        .badge-kode {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 7px;
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-kondisi {
            display: inline-block;
            padding: 5px 9px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: var(--surface-2);
            color: var(--text);
            white-space: nowrap;
        }

        .waktu {
            min-width: 150px;
            white-space: nowrap;
        }

        .kosong {
            padding: 45px 20px !important;
            text-align: center;
            color: var(--text-muted) !important;
        }

        .aksi {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .riwayat-header,
            .riwayat-info {
                padding: 16px;
            }

            .aksi {
                justify-content: flex-start;
            }

            .page-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <!-- Header halaman -->
    <div class="page-header">
        <div>
            <div class="eyebrow">MONITORING INVENTARIS</div>
            <h1>Riwayat Barang</h1>
            <p>
                Catatan perubahan kondisi barang inventaris
                SMK Negeri 2 Padang.
            </p>
        </div>
    </div>

    <!-- Tombol kembali -->
    <div class="aksi">
        <a href="../index.php" class="btn btn-secondary">
            &larr; Kembali ke Dashboard
        </a>
    </div>

    <!-- Kartu riwayat -->
    <div class="riwayat-card">

        <div class="riwayat-header">
            <h2>Daftar Riwayat Perubahan</h2>
            <p>
                Informasi barang, kondisi sebelumnya, kondisi terbaru,
                pengguna yang melakukan perubahan, dan waktu perubahan.
            </p>
        </div>

        <!-- Jumlah riwayat -->
        <div class="riwayat-info">
            <div class="riwayat-total">
                Total catatan:
                <strong><?= $total_riwayat; ?> riwayat</strong>
            </div>
        </div>

        <!-- Tabel riwayat -->
        <div class="tabel-wrapper">
            <table class="tabel-riwayat">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Inventaris</th>
                        <th>Nama Barang</th>
                        <th>Diubah Oleh</th>
                        <th>Kondisi Sebelumnya</th>
                        <th>Kondisi Baru</th>
                        <th>Keterangan</th>
                        <th>Waktu Perubahan</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($total_riwayat > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($data = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td><?= $no++; ?></td>

                                <td>
                                    <span class="badge-kode">
                                        <?= aman($data['kode_inventaris']); ?>
                                    </span>
                                </td>

                                <td>
                                    <strong>
                                        <?= aman($data['nama_barang']); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= aman($data['nama_pengguna']); ?>
                                </td>

                                <td>
                                    <span class="badge-kondisi">
                                        <?= aman(
                                            $data['kondisi_sebelumnya'] ?? '-'
                                        ); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge-kondisi">
                                        <?= aman(
                                            $data['kondisi_baru'] ?? '-'
                                        ); ?>
                                    </span>
                                </td>

                                <td>
                                    <?= aman(
                                        !empty($data['keterangan'])
                                            ? $data['keterangan']
                                            : '-'
                                    ); ?>
                                </td>

                                <td class="waktu">
                                    <?= aman($data['waktu_perubahan']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="kosong">
                                Belum ada riwayat perubahan barang.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

</body>
</html>

<?php
mysqli_free_result($query);
?>
