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
    JOIN pengguna u
        ON p.id_pengguna = u.id_pengguna
    ORDER BY p.id_peminjaman DESC
");

if (!$query) {
    die("Gagal mengambil data peminjaman: " . mysqli_error($conn));
}

function e($nilai) {
    return htmlspecialchars(
        (string) ($nilai ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

function kelasStatus($status) {
    switch ($status) {
        case 'Menunggu':
            return 'status-menunggu';
        case 'Disetujui':
            return 'status-disetujui';
        case 'Ditolak':
            return 'status-ditolak';
        case 'Dikembalikan':
            return 'status-dikembalikan';
        default:
            return 'status-default';
    }
}

/*
|--------------------------------------------------------------------------
| Menentukan status waktu secara otomatis
|--------------------------------------------------------------------------
| Status ini hanya untuk tampilan, tidak mengubah database.
*/
function statusWaktu($status, $tanggalKembali) {
    if ($status === 'Dikembalikan') {
        return [
            'label' => 'Sudah Dikembalikan',
            'kelas' => 'status-dikembalikan',
            'hari'  => 0
        ];
    }

    if ($status !== 'Disetujui') {
        return [
            'label' => '-',
            'kelas' => 'status-default',
            'hari'  => 0
        ];
    }

    $hariIni = new DateTime(date('Y-m-d'));
    $batasKembali = new DateTime($tanggalKembali);

    // Selisih positif berarti tanggal kembali sudah lewat.
    $selisih = (int) $batasKembali
        ->diff($hariIni)
        ->format('%r%a');

    if ($selisih > 0) {
        return [
            'label' => 'Terlambat',
            'kelas' => 'status-ditolak',
            'hari'  => $selisih
        ];
    }

    if ($selisih === 0) {
        return [
            'label' => 'Jatuh Tempo',
            'kelas' => 'status-menunggu',
            'hari'  => 0
        ];
    }

    return [
        'label' => 'Belum Jatuh Tempo',
        'kelas' => 'status-disetujui',
        'hari'  => 0
    ];
}

$status_pesan = [
    'berhasil' => 'Peminjaman berhasil ditambahkan.',
    'terubah'  => 'Status peminjaman berhasil diperbarui.',
    'terhapus' => 'Data peminjaman berhasil dihapus.',
    'gagal'    => 'Proses gagal dilakukan. Periksa data peminjaman.',
];

$status_pesan_key = $_GET['status'] ?? '';
$total_peminjaman = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Peminjaman | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .peminjaman-wrapper {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .peminjaman-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding: 20px 22px;
            background: var(--surface, #102b24);
            border: 1px solid var(--border, #285044);
            border-radius: 12px;
        }

        .peminjaman-summary-label {
            color: var(--text-muted, #a5bcb1);
            font-size: 13px;
            margin-bottom: 6px;
        }

        .peminjaman-summary-number {
            color: var(--text, #f0f7f3);
            font-size: 26px;
            font-weight: 700;
        }

        .peminjaman-card {
            overflow: hidden;
            background: var(--surface, #102b24);
            border: 1px solid var(--border, #285044);
            border-radius: 14px;
        }

        .peminjaman-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 20px 22px;
            border-bottom: 1px solid var(--border, #285044);
            background: var(--surface-2, #17382e);
        }

        .peminjaman-card-header h3 {
            margin: 0;
            color: var(--text, #f0f7f3);
            font-size: 17px;
        }

        .peminjaman-card-body {
            padding: 22px;
        }

        .peminjaman-alert {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.6;
        }

        .peminjaman-alert-success {
            color: #86efac;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .peminjaman-alert-danger {
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .peminjaman-actions-top {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .peminjaman-table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border, #285044);
            border-radius: 10px;
        }

        .peminjaman-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        .peminjaman-table th,
        .peminjaman-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border, #285044);
        }

        .peminjaman-table th {
            color: var(--text-muted, #a5bcb1);
            background: var(--surface-2, #17382e);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }

        .peminjaman-table td {
            color: var(--text, #f0f7f3);
            font-size: 13px;
            vertical-align: middle;
        }

        .peminjaman-table tbody tr:last-child td {
            border-bottom: none;
        }

        .peminjaman-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.025);
        }

        .peminjaman-number {
            color: #6ee7b7;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-menunggu {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.15);
        }

        .status-disetujui {
            color: #86efac;
            background: rgba(34, 197, 94, 0.15);
        }

        .status-ditolak {
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.15);
        }

        .status-dikembalikan {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.15);
        }

        .status-default {
            color: #cbd5e1;
            background: rgba(148, 163, 184, 0.15);
        }

        .peminjaman-action-buttons {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 7px;
        }

        .peminjaman-action-buttons .btn {
            font-size: 12px;
            padding: 8px 11px;
            white-space: nowrap;
        }

        .peminjaman-delete-form {
            display: inline-flex;
            margin: 0;
        }

        .peminjaman-empty {
            padding: 35px 18px !important;
            text-align: center !important;
            color: var(--text-muted, #a5bcb1) !important;
        }

        .peminjaman-empty-icon {
            display: block;
            margin-bottom: 8px;
            font-size: 25px;
        }

        @media (max-width: 600px) {
            .peminjaman-card-body {
                padding: 15px;
            }

            .peminjaman-card-header {
                padding: 16px;
            }

            .peminjaman-actions-top {
                flex-direction: column;
                align-items: stretch;
            }

            .peminjaman-actions-top .btn {
                width: 100%;
                text-align: center;
            }

            .peminjaman-summary {
                padding: 17px;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Data Peminjaman Barang</h1>
            <p style="color: var(--text-muted, #a5bcb1); margin-top: 6px;">
                Inventaris Barang Sekolah - SMK Negeri 2 Padang
            </p>
        </div>
    </div>

    <div class="peminjaman-wrapper">

        <div class="peminjaman-summary">
            <div>
                <div class="peminjaman-summary-label">
                    Total Data Peminjaman
                </div>

                <div class="peminjaman-summary-number">
                    <?= $total_peminjaman ?>
                </div>
            </div>

            <div>
                <span class="status-badge status-default">
                    Data Peminjaman
                </span>
            </div>
        </div>

        <section class="peminjaman-card">

            <div class="peminjaman-card-header">
                <h3>Daftar Peminjaman</h3>
            </div>

            <div class="peminjaman-card-body">

                <?php if (isset($status_pesan[$status_pesan_key])): ?>
                    <div class="peminjaman-alert <?= $status_pesan_key === 'gagal' ? 'peminjaman-alert-danger' : 'peminjaman-alert-success' ?>">
                        <?= e($status_pesan[$status_pesan_key]) ?>
                    </div>
                <?php endif; ?>

                <div class="peminjaman-actions-top">
                    <a href="../admin/index.php" class="btn btn-secondary">
                        Kembali ke Dashboard
                    </a>

                    <a href="tambah.php" class="btn btn-primary">
                        + Tambah Peminjaman
                    </a>
                </div>

                <div class="peminjaman-table-wrapper">
                    <table class="peminjaman-table">

                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID Peminjaman</th>
                                <th>Nama Pengguna</th>
                                <th>Tanggal Pinjam</th>
                                <th>Tanggal Kembali</th>
                                <th>Status Peminjaman</th>
                                <th>Status Waktu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($total_peminjaman > 0): ?>
                                <?php $no = 1; ?>

                                <?php while ($data = mysqli_fetch_assoc($query)): ?>
                                    <?php
                                    $waktu = statusWaktu(
                                        $data['status'],
                                        $data['tanggal_kembali']
                                    );
                                    ?>

                                    <tr>
                                        <td><?= $no++ ?></td>

                                        <td class="peminjaman-number">
                                            #<?= (int) $data['id_peminjaman'] ?>
                                        </td>

                                        <td><?= e($data['nama']) ?></td>

                                        <td><?= e($data['tanggal_pinjam']) ?></td>

                                        <td><?= e($data['tanggal_kembali']) ?></td>

                                        <td>
                                            <span class="status-badge <?= e(kelasStatus($data['status'])) ?>">
                                                <?= e($data['status']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= e($waktu['kelas']) ?>">
                                                <?= e($waktu['label']) ?>
                                            </span>

                                            <?php if ($waktu['hari'] > 0): ?>
                                                <br>
                                                <small style="color:#fca5a5;">
                                                    Terlambat <?= (int) $waktu['hari'] ?> hari
                                                </small>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="peminjaman-action-buttons">

                                                <a
                                                    href="detail.php?id=<?= (int) $data['id_peminjaman'] ?>"
                                                    class="btn btn-primary"
                                                >
                                                    Detail
                                                </a>

                                                <?php if (
                                                    !in_array(
                                                        $data['status'],
                                                        ['Ditolak', 'Dikembalikan'],
                                                        true
                                                    )
                                                ): ?>
                                                    <a
                                                        href="edit.php?id=<?= (int) $data['id_peminjaman'] ?>"
                                                        class="btn btn-secondary"
                                                    >
                                                        Edit Status
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (
                                                    in_array(
                                                        $data['status'],
                                                        ['Menunggu', 'Ditolak'],
                                                        true
                                                    )
                                                ): ?>
                                                    <form
                                                        action="hapus.php"
                                                        method="POST"
                                                        class="peminjaman-delete-form"
                                                        onsubmit="return confirm('Yakin ingin menghapus peminjaman ini?');"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="id_peminjaman"
                                                            value="<?= (int) $data['id_peminjaman'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger"
                                                        >
                                                            Hapus
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="peminjaman-empty">
                                        <span class="peminjaman-empty-icon">📋</span>
                                        Belum ada data peminjaman.
                                    </td>
                                </tr>
                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>

            </div>
        </section>

    </div>

</div>

</body>
</html>
