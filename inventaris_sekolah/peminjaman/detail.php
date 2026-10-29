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
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

function kelasStatus($status)
{
    switch ($status) {
        case 'Disetujui':
            return 'status-disetujui';

        case 'Menunggu':
            return 'status-menunggu';

        case 'Ditolak':
            return 'status-ditolak';

        case 'Dikembalikan':
            return 'status-dikembalikan';

        default:
            return 'status-default';
    }
}

function statusWaktu($status, $tanggalKembali)
{
    if ($status === 'Dikembalikan') {
        return [
            'label' => 'Sudah Dikembalikan',
            'kelas' => 'status-dikembalikan'
        ];
    }

    if ($status !== 'Disetujui') {
        return [
            'label' => 'Belum Aktif',
            'kelas' => 'status-default'
        ];
    }

    try {
        $hariIni = new DateTimeImmutable(date('Y-m-d'));
        $batas = new DateTimeImmutable($tanggalKembali);
    } catch (Exception $e) {
        return [
            'label' => 'Tanggal Tidak Valid',
            'kelas' => 'status-default'
        ];
    }

    if ($batas < $hariIni) {
        $terlambat = $batas->diff($hariIni)->days;

        return [
            'label' => 'Terlambat ' . $terlambat . ' hari',
            'kelas' => 'status-ditolak'
        ];
    }

    if ($batas == $hariIni) {
        return [
            'label' => 'Jatuh Tempo Hari Ini',
            'kelas' => 'status-menunggu'
        ];
    }

    return [
        'label' => 'Belum Jatuh Tempo',
        'kelas' => 'status-disetujui'
    ];
}

if (
    !isset($_GET['id']) ||
    !ctype_digit((string) $_GET['id']) ||
    (int) $_GET['id'] <= 0
) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.id_peminjaman,
        u.nama,
        u.username,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        p.status
     FROM peminjaman p
     JOIN pengguna u
        ON p.id_pengguna = u.id_pengguna
     WHERE p.id_peminjaman = ?"
);

if (!$stmt) {
    die("Gagal menyiapkan data peminjaman.");
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result(
    $stmt,
    $id_peminjaman,
    $nama_pengguna,
    $username,
    $tanggal_pinjam,
    $tanggal_kembali,
    $status
);

if (!mysqli_stmt_fetch($stmt)) {
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

mysqli_stmt_close($stmt);

// Ambil barang yang tercatat dalam detail peminjaman.
$detail = mysqli_prepare(
    $conn,
    "SELECT
        b.kode_inventaris,
        b.nama_barang,
        d.jumlah,
        b.kondisi
     FROM detail_peminjaman d
     JOIN barang b
        ON d.id_barang = b.id_barang
     WHERE d.id_peminjaman = ?
     ORDER BY b.nama_barang ASC"
);

if (!$detail) {
    die("Gagal menyiapkan detail barang.");
}

mysqli_stmt_bind_param($detail, "i", $id);
mysqli_stmt_execute($detail);
mysqli_stmt_bind_result(
    $detail,
    $kode_barang,
    $nama_barang,
    $jumlah,
    $kondisi
);

$data_barang = [];
$total_barang = 0;

while (mysqli_stmt_fetch($detail)) {
    $data_barang[] = [
        'kode' => $kode_barang,
        'nama' => $nama_barang,
        'jumlah' => (int) $jumlah,
        'kondisi' => $kondisi
    ];

    $total_barang += (int) $jumlah;
}

mysqli_stmt_close($detail);

$waktu = statusWaktu($status, $tanggal_kembali);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Peminjaman | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .detail-wrapper {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .detail-card {
            background: var(--surface, #102b24);
            border: 1px solid var(--border, #285044);
            border-radius: 14px;
            overflow: hidden;
        }

        .detail-card-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border, #285044);
            background: var(--surface-2, #17382e);
        }

        .detail-card-header h3 {
            margin: 0;
            color: var(--text, #f0f7f3);
            font-size: 17px;
        }

        .detail-card-body {
            padding: 22px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .detail-item {
            padding: 16px;
            background: var(--surface-2, #17382e);
            border: 1px solid var(--border, #285044);
            border-radius: 10px;
            min-width: 0;
        }

        .detail-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted, #a5bcb1);
            font-size: 13px;
        }

        .detail-value {
            color: var(--text, #f0f7f3);
            font-size: 15px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-disetujui,
        .status-dikembalikan {
            color: #86efac;
            background: rgba(34, 197, 94, 0.15);
        }

        .status-menunggu {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.15);
        }

        .status-ditolak {
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.15);
        }

        .status-default {
            color: #cbd5e1;
            background: rgba(148, 163, 184, 0.15);
        }

        .detail-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .detail-table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
        }

        .detail-table th,
        .detail-table td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid var(--border, #285044);
        }

        .detail-table th {
            color: var(--text-muted, #a5bcb1);
            background: var(--surface-2, #17382e);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .detail-table td {
            color: var(--text, #f0f7f3);
            font-size: 14px;
        }

        .detail-table tbody tr:last-child td {
            border-bottom: none;
        }

        .detail-empty {
            padding: 30px 18px !important;
            text-align: center !important;
            color: var(--text-muted, #a5bcb1) !important;
        }

        .detail-footer {
            display: flex;
            justify-content: flex-end;
            padding: 18px 22px;
            border-top: 1px solid var(--border, #285044);
        }

        .page-description {
            margin-top: 6px;
            color: var(--text-muted, #a5bcb1);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        @media (max-width: 650px) {
            .detail-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .detail-card-body {
                padding: 15px;
            }

            .detail-card-header,
            .detail-footer {
                padding: 15px;
            }

            .detail-footer .btn {
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
            <h1>Detail Peminjaman Barang</h1>
            <p class="page-description">
                Inventaris Barang Sekolah - SMK Negeri 2 Padang
            </p>
        </div>
    </div>

    <div class="detail-wrapper">

        <section class="detail-card">
            <div class="detail-card-header">
                <h3>Informasi Peminjaman</h3>
            </div>

            <div class="detail-card-body">
                <div class="detail-grid">

                    <div class="detail-item">
                        <span class="detail-label">Nomor Peminjaman</span>
                        <div class="detail-value">
                            #<?= (int) $id_peminjaman ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Nama Peminjam</span>
                        <div class="detail-value">
                            <?= aman($nama_pengguna) ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Username</span>
                        <div class="detail-value">
                            <?= aman($username) ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Tanggal Pinjam</span>
                        <div class="detail-value">
                            <?= aman($tanggal_pinjam) ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Batas Tanggal Kembali</span>
                        <div class="detail-value">
                            <?= aman($tanggal_kembali) ?>
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Status Peminjaman</span>
                        <div class="detail-value">
                            <span class="status-badge <?= aman(kelasStatus($status)) ?>">
                                <?= aman($status) ?>
                            </span>
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Status Waktu</span>
                        <div class="detail-value">
                            <span class="status-badge <?= aman($waktu['kelas']) ?>">
                                <?= aman($waktu['label']) ?>
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <section class="detail-card">
            <div class="detail-card-header">
                <h3>Barang yang Dipinjam</h3>
            </div>

            <div class="detail-card-body">
                <div class="summary-grid">
                    <div class="detail-item">
                        <span class="detail-label">Jenis Barang</span>
                        <div class="detail-value">
                            <?= count($data_barang) ?> jenis
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Total Jumlah Barang</span>
                        <div class="detail-value">
                            <?= $total_barang ?> unit
                        </div>
                    </div>
                </div>
            </div>

            <div class="detail-table-wrapper">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Kode Inventaris</th>
                            <th>Nama Barang</th>
                            <th>Jumlah</th>
                            <th>Kondisi Saat Ini</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($data_barang)): ?>
                            <?php foreach ($data_barang as $i => $barang): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= aman($barang['kode']) ?></td>
                                    <td><?= aman($barang['nama']) ?></td>
                                    <td><?= (int) $barang['jumlah'] ?> unit</td>
                                    <td><?= aman($barang['kondisi']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="detail-empty">
                                    Belum ada detail barang untuk peminjaman ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="detail-footer">
                <a href="index.php" class="btn btn-secondary">
                    Kembali ke Peminjaman
                </a>
            </div>
        </section>

    </div>
</div>

</body>
</html>