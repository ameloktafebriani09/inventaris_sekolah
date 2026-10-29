<?php
session_start();
require_once "../koneksi.php";

if (
    !isset($_SESSION['id_pengguna']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'pengelola_sarpras'
) {
    header("Location: ../auth/login.php");
    exit;
}

function e($value)
{
    return htmlspecialchars((string) ($value ?? '-'), ENT_QUOTES, 'UTF-8');
}

$sql = "
    SELECT
        pg.id_pengembalian,
        pg.id_peminjaman,
        pg.tanggal_pengembalian,
        pg.kondisi_barang,
        pg.catatan,
        u.nama,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        COALESCE(SUM(dp.jumlah), 0) AS total_barang,
        COALESCE(SUM(dp.jumlah_baik), 0) AS jumlah_baik,
        COALESCE(SUM(dp.jumlah_rusak_ringan), 0) AS jumlah_rusak_ringan,
        COALESCE(SUM(dp.jumlah_rusak_berat), 0) AS jumlah_rusak_berat
    FROM pengembalian pg
    JOIN peminjaman p
        ON pg.id_peminjaman = p.id_peminjaman
    JOIN pengguna u
        ON p.id_pengguna = u.id_pengguna
    LEFT JOIN detail_pengembalian dp
        ON pg.id_pengembalian = dp.id_pengembalian
    GROUP BY
        pg.id_pengembalian,
        pg.id_peminjaman,
        pg.tanggal_pengembalian,
        pg.kondisi_barang,
        pg.catatan,
        u.nama,
        p.tanggal_pinjam,
        p.tanggal_kembali
    ORDER BY pg.id_pengembalian DESC
";

$query = mysqli_query($conn, $sql);

if (!$query) {
    die("Gagal mengambil data pengembalian: " . mysqli_error($conn));
}

$dataPengembalian = [];
$totalPengembalian = 0;
$totalTerlambat = 0;
$totalTepatWaktu = 0;

while ($data = mysqli_fetch_assoc($query)) {
    $batasKembali = new DateTime($data['tanggal_kembali']);
    $tanggalAktual = new DateTime($data['tanggal_pengembalian']);

    $selisihHari = (int) $batasKembali
        ->diff($tanggalAktual)
        ->format('%r%a');

    if ($selisihHari > 0) {
        $data['status_waktu'] = 'Terlambat';
        $data['hari_terlambat'] = $selisihHari;
        $totalTerlambat++;
    } else {
        $data['status_waktu'] = 'Tepat Waktu';
        $data['hari_terlambat'] = 0;
        $totalTepatWaktu++;
    }

    $dataPengembalian[] = $data;
    $totalPengembalian++;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Pengembalian | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .pengembalian-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin: 22px 0;
        }

        .pengembalian-stat {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .pengembalian-stat p {
            margin: 0 0 8px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .pengembalian-stat h3 {
            margin: 0;
            font-size: 27px;
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: rgba(16, 185, 129, 0.13);
            font-size: 22px;
        }

        .pengembalian-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1250px;
        }

        .pengembalian-table th,
        .pengembalian-table td {
            padding: 14px 16px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid var(--border);
        }

        .pengembalian-table th {
            white-space: nowrap;
        }

        .pengembalian-table tbody tr:last-child td {
            border-bottom: none;
        }

        .pengembalian-table tbody tr:hover {
            background: var(--surface-2);
        }

        .kondisi-badge,
        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .kondisi-badge {
            background: rgba(16, 185, 129, 0.13);
            color: #34d399;
        }

        .status-tepat {
            background: rgba(16, 185, 129, 0.13);
            color: #34d399;
        }

        .status-terlambat {
            background: rgba(239, 68, 68, 0.13);
            color: #f87171;
        }

        .jumlah-kondisi {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 135px;
        }

        .jumlah-kondisi span {
            display: block;
            font-size: 13px;
            white-space: nowrap;
        }

        .jumlah-baik {
            color: #34d399;
        }

        .jumlah-ringan {
            color: #fbbf24;
        }

        .jumlah-berat {
            color: #f87171;
        }

        .catatan-cell {
            min-width: 180px;
            max-width: 280px;
            overflow-wrap: anywhere;
        }

        .pengembalian-empty {
            padding: 35px 16px !important;
            text-align: center !important;
            color: var(--text-muted);
        }

        .pengembalian-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        @media (max-width: 900px) {
            .pengembalian-stats {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .pengembalian-stat {
                padding: 18px;
            }

            .pengembalian-table th,
            .pengembalian-table td {
                padding: 12px;
            }

            .pengembalian-actions .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <p class="eyebrow">INVENTARIS SEKOLAH</p>
            <h2>Data Pengembalian Barang</h2>
            <p>
                Kelola dan pantau catatan pengembalian barang
                inventaris SMK Negeri 2 Padang.
            </p>
        </div>
    </div>

    <div class="pengembalian-actions">
        <a href="tambah.php" class="btn btn-primary">
            + Catat Pengembalian
        </a>

        <a href="../admin/index.php" class="btn btn-secondary">
            Kembali ke Dashboard
        </a>
    </div>

    <div class="pengembalian-stats">

        <div class="pengembalian-stat">
            <div>
                <p>Total Pengembalian</p>
                <h3><?= $totalPengembalian; ?></h3>
            </div>
            <div class="stat-icon">↩</div>
        </div>

        <div class="pengembalian-stat">
            <div>
                <p>Tepat Waktu</p>
                <h3><?= $totalTepatWaktu; ?></h3>
            </div>
            <div class="stat-icon">✓</div>
        </div>

        <div class="pengembalian-stat">
            <div>
                <p>Terlambat</p>
                <h3><?= $totalTerlambat; ?></h3>
            </div>
            <div class="stat-icon">!</div>
        </div>

    </div>

    <div class="content-card">
        <div class="table-responsive">

            <table class="pengembalian-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Pengembalian</th>
                        <th>ID Peminjaman</th>
                        <th>Nama Peminjam</th>
                        <th>Tanggal Pinjam</th>
                        <th>Batas Kembali</th>
                        <th>Tanggal Pengembalian</th>
                        <th>Total Barang</th>
                        <th>Rincian Kondisi Barang</th>
                        <th>Status Waktu</th>
                        <th>Keterlambatan</th>
                        <th>Catatan</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($totalPengembalian > 0): ?>
                        <?php $no = 1; ?>

                        <?php foreach ($dataPengembalian as $data): ?>
                            <tr>
                                <td><?= $no++; ?></td>

                                <td>
                                    #<?= (int) $data['id_pengembalian']; ?>
                                </td>

                                <td>
                                    #<?= (int) $data['id_peminjaman']; ?>
                                </td>

                                <td>
                                    <strong><?= e($data['nama']); ?></strong>
                                </td>

                                <td><?= e($data['tanggal_pinjam']); ?></td>

                                <td><?= e($data['tanggal_kembali']); ?></td>

                                <td>
                                    <?= e($data['tanggal_pengembalian']); ?>
                                </td>

                                <td>
                                    <?= (int) $data['total_barang']; ?> unit
                                </td>

                                <td>
                                    <div class="jumlah-kondisi">
                                        <span class="jumlah-baik">
                                            Baik:
                                            <?= (int) $data['jumlah_baik']; ?> unit
                                        </span>

                                        <span class="jumlah-ringan">
                                            Rusak ringan:
                                            <?= (int) $data['jumlah_rusak_ringan']; ?> unit
                                        </span>

                                        <span class="jumlah-berat">
                                            Rusak berat:
                                            <?= (int) $data['jumlah_rusak_berat']; ?> unit
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span class="status-badge <?= 
                                        $data['status_waktu'] === 'Terlambat'
                                            ? 'status-terlambat'
                                            : 'status-tepat'
                                    ?>">
                                        <?= e($data['status_waktu']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($data['hari_terlambat'] > 0): ?>
                                        <?= (int) $data['hari_terlambat']; ?> hari
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>

                                <td class="catatan-cell">
                                    <?= e(
                                        trim((string) ($data['catatan'] ?? '')) !== ''
                                            ? $data['catatan']
                                            : '-'
                                    ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="pengembalian-empty">
                                Belum ada data pengembalian barang.
                                <br><br>
                                Klik tombol <strong>Catat Pengembalian</strong>
                                untuk menambahkan data baru.
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
