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
        p.id_peminjaman,
        u.nama,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        p.status
    FROM peminjaman p
    JOIN pengguna u ON p.id_pengguna = u.id_pengguna
    ORDER BY p.id_peminjaman DESC
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

function kelasStatus($status) {
    $status = strtolower(trim((string) $status));

    if (in_array($status, ['menunggu', 'pending'])) {
        return 'status-menunggu';
    }

    if (in_array($status, ['disetujui', 'dipinjam'])) {
        return 'status-disetujui';
    }

    if (in_array($status, ['ditolak'])) {
        return 'status-ditolak';
    }

    if (in_array($status, ['dikembalikan', 'selesai'])) {
        return 'status-selesai';
    }

    return 'status-lain';
}

$totalData = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Peminjaman | SMK Negeri 2 Padang</title>

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
        }

        .tabel-laporan tbody tr:last-child td {
            border-bottom: none;
        }

        .tabel-laporan tbody tr:hover {
            background: var(--surface-2);
        }

        .nomor-data {
            color: var(--text-muted) !important;
            width: 55px;
        }

        .id-data {
            color: #6ee7b7 !important;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-menunggu {
            color: #fcd34d;
            background: rgba(245, 158, 11, .12);
        }

        .status-disetujui {
            color: #6ee7b7;
            background: rgba(16, 185, 129, .12);
        }

        .status-ditolak {
            color: #fca5a5;
            background: rgba(239, 68, 68, .12);
        }

        .status-selesai {
            color: #93c5fd;
            background: rgba(59, 130, 246, .12);
        }

        .status-lain {
            color: var(--text-muted);
            background: var(--surface-2);
        }

        .kosong {
            padding: 40px 20px !important;
            text-align: center;
            color: var(--text-muted) !important;
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
                margin: 15mm;
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
                padding: 8px !important;
                color: #000 !important;
                border: 1px solid #777 !important;
                background: #fff !important;
            }

            .status-badge {
                color: #000 !important;
                background: #fff !important;
                border: 1px solid #777 !important;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <span class="eyebrow">LAPORAN INVENTARIS</span>
            <h1>Laporan Peminjaman Barang</h1>
            <p>Inventaris Barang Sekolah - SMK Negeri 2 Padang</p>
        </div>
    </div>

    <div class="card laporan-card">

        <div class="laporan-header">
            <h3>Daftar Peminjaman</h3>

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
                        <th>ID Peminjaman</th>
                        <th>Nama Peminjam</th>
                        <th>Tanggal Pinjam</th>
                        <th>Tanggal Kembali</th>
                        <th>Status</th>
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
                                    #<?= (int) $data['id_peminjaman'] ?>
                                </td>

                                <td>
                                    <?= aman($data['nama']) ?>
                                </td>

                                <td>
                                    <?= aman($data['tanggal_pinjam']) ?>
                                </td>

                                <td>
                                    <?= aman($data['tanggal_kembali']) ?>
                                </td>

                                <td>
                                    <span class="status-badge <?= kelasStatus($data['status']) ?>">
                                        <?= aman($data['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="kosong">
                                <span class="kosong-icon">📋</span>
                                Belum ada data peminjaman.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="info-laporan">
            <strong>Informasi:</strong>
            Laporan ini menampilkan seluruh data peminjaman barang yang
            tercatat di sistem. Gunakan tombol <strong>Cetak Laporan</strong>
            untuk mencetak atau menyimpan laporan sebagai PDF.
        </div>

    </div>

</div>

</body>
</html>