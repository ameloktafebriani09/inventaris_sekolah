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

$query = mysqli_query($conn, "
    SELECT
        pg.id_pengembalian,
        p.id_peminjaman,
        u.nama,
        pg.tanggal_pengembalian,
        pg.kondisi_barang,
        pg.catatan
    FROM pengembalian pg
    JOIN peminjaman p
        ON pg.id_peminjaman = p.id_peminjaman
    JOIN pengguna u
        ON p.id_pengguna = u.id_pengguna
    ORDER BY pg.id_pengembalian DESC
");

if (!$query) {
    die("Gagal mengambil laporan: " . mysqli_error($conn));
}

function aman($nilai) {
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

function kelasKondisi($kondisi) {
    $kondisi = strtolower(trim((string) $kondisi));

    if (strpos($kondisi, 'baik') !== false) {
        return 'kondisi-baik';
    }

    if (
        strpos($kondisi, 'rusak berat') !== false ||
        strpos($kondisi, 'rusak parah') !== false
    ) {
        return 'kondisi-rusak-berat';
    }

    if (strpos($kondisi, 'rusak') !== false) {
        return 'kondisi-rusak';
    }

    return 'kondisi-lain';
}

$totalData = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Pengembalian | SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .laporan-card {
            overflow: hidden;
            margin-bottom: 24px;
        }

        .laporan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .laporan-header h3 {
            margin: 0;
            color: var(--text);
            font-size: 18px;
        }

        .total-data {
            padding: 7px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface-2);
            color: var(--text-muted);
            font-size: 13px;
        }

        .aksi-laporan {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 18px 24px;
        }

        .tabel-laporan {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        .tabel-laporan th {
            padding: 14px 16px;
            background: var(--surface-2);
            color: var(--text-muted);
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            border-bottom: 1px solid var(--border);
        }

        .tabel-laporan td {
            padding: 15px 16px;
            color: var(--text);
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        .tabel-laporan tbody tr:last-child td {
            border-bottom: none;
        }

        .tabel-laporan tbody tr:hover {
            background: var(--surface-2);
        }

        .nomor-data {
            color: var(--text-muted) !important;
        }

        .id-data {
            color: #6ee7b7 !important;
            font-weight: 700;
        }

        .catatan-data {
            min-width: 150px;
            max-width: 260px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
        }

        .kondisi-baik {
            color: #6ee7b7;
            background: rgba(16, 185, 129, .12);
        }

        .kondisi-rusak {
            color: #fcd34d;
            background: rgba(245, 158, 11, .12);
        }

        .kondisi-rusak-berat {
            color: #fca5a5;
            background: rgba(239, 68, 68, .12);
        }

        .kondisi-lain {
            color: var(--text-muted);
            background: var(--surface-2);
        }

        .kosong {
            padding: 40px 20px !important;
            text-align: center;
            color: var(--text-muted) !important;
            white-space: normal;
        }

        .kosong-icon {
            display: block;
            font-size: 30px;
            margin-bottom: 10px;
        }

        .info-laporan {
            margin: 0 24px 22px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            .laporan-header {
                padding: 18px;
            }

            .aksi-laporan {
                padding: 16px 18px;
            }

            .aksi-laporan .btn {
                flex: 1;
                text-align: center;
                justify-content: center;
            }

            .info-laporan {
                margin: 0 18px 18px;
            }
        }

        @media print {
            @page {
                size: landscape;
                margin: 12mm;
            }

            body {
                background: #fff !important;
                color: #000 !important;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .aksi-laporan,
            .sidebar,
            .navbar,
            .info-laporan {
                display: none !important;
            }

            .page-header {
                margin-bottom: 15px !important;
            }

            .page-header h1,
            .page-header p,
            .laporan-header h3,
            .total-data {
                color: #000 !important;
            }

            .laporan-card,
            .card {
                box-shadow: none !important;
                border: none !important;
                background: #fff !important;
            }

            .laporan-header {
                padding: 10px 0 !important;
                border-bottom: 1px solid #000 !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            .tabel-laporan {
                width: 100% !important;
                white-space: normal !important;
            }

            .tabel-laporan th,
            .tabel-laporan td {
                padding: 7px !important;
                color: #000 !important;
                border: 1px solid #777 !important;
                background: #fff !important;
                white-space: normal !important;
            }

            .status-badge {
                color: #000 !important;
                background: #fff !important;
                border: 1px solid #777 !important;
            }

            .catatan-data {
                min-width: 0;
                max-width: none;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <span class="eyebrow">LAPORAN INVENTARIS</span>
            <h1>Laporan Pengembalian Barang</h1>
            <p>Inventaris Barang Sekolah - SMK Negeri 2 Padang</p>
        </div>
    </div>

    <div class="card laporan-card">

        <div class="laporan-header">
            <h3>Daftar Pengembalian</h3>

            <span class="total-data">
                Total data: <strong><?= $totalData ?></strong>
            </span>
        </div>

        <div class="aksi-laporan">
            <a href="index.php" class="btn btn-secondary">
                Kembali ke Laporan
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-primary"
            >
                Cetak Laporan
            </button>
        </div>

        <div class="table-responsive">
            <table class="tabel-laporan">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Pengembalian</th>
                        <th>ID Peminjaman</th>
                        <th>Nama Peminjam</th>
                        <th>Tanggal Pengembalian</th>
                        <th>Kondisi Barang</th>
                        <th>Catatan</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($totalData > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($data = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td class="nomor-data">
                                    <?= $no++ ?>
                                </td>

                                <td class="id-data">
                                    #<?= (int) $data['id_pengembalian'] ?>
                                </td>

                                <td class="id-data">
                                    #<?= (int) $data['id_peminjaman'] ?>
                                </td>

                                <td>
                                    <?= aman($data['nama']) ?>
                                </td>

                                <td>
                                    <?= aman($data['tanggal_pengembalian']) ?>
                                </td>

                                <td>
                                    <span class="status-badge <?= kelasKondisi($data['kondisi_barang']) ?>">
                                        <?= aman($data['kondisi_barang']) ?>
                                    </span>
                                </td>

                                <td class="catatan-data">
                                    <?= aman(
                                        trim((string) $data['catatan']) !== ''
                                            ? $data['catatan']
                                            : '-'
                                    ) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="kosong">
                                <span class="kosong-icon">📋</span>
                                Belum ada data pengembalian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="info-laporan">
            <strong>Informasi:</strong>
            Laporan ini menampilkan data pengembalian barang yang tercatat
            di sistem, termasuk nama peminjam, kondisi barang, dan catatan.
            Gunakan tombol <strong>Cetak Laporan</strong> untuk mencetak
            atau menyimpan laporan sebagai PDF.
        </div>

    </div>

</div>

</body>
</html>