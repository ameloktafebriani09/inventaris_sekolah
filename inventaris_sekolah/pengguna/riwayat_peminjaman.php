
<?php
session_start();
require_once "../koneksi.php";

// Periksa login dan role pengguna
if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] === 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

$id_pengguna = (int) $_SESSION['id_pengguna'];
$role = $_SESSION['role'];

// Kepala sekolah dan pengelola sarpras melihat semua riwayat.
// Guru dan teknisi hanya melihat riwayat milik sendiri.
if (in_array($role, ['kepala_sekolah', 'pengelola_sarpras'], true)) {

    $sql = "
        SELECT
            p.id_peminjaman,
            p.id_pengguna,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            p.status,
            u.nama AS nama_peminjam,
            GROUP_CONCAT(
                CONCAT(b.nama_barang, ' (', dp.jumlah, ')')
                SEPARATOR ', '
            ) AS daftar_barang
        FROM peminjaman p
        LEFT JOIN pengguna u
            ON p.id_pengguna = u.id_pengguna
        LEFT JOIN detail_peminjaman dp
            ON p.id_peminjaman = dp.id_peminjaman
        LEFT JOIN barang b
            ON dp.id_barang = b.id_barang
        GROUP BY
            p.id_peminjaman,
            p.id_pengguna,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            p.status,
            u.nama
        ORDER BY p.id_peminjaman DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);

} else {

    $sql = "
        SELECT
            p.id_peminjaman,
            p.id_pengguna,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            p.status,
            u.nama AS nama_peminjam,
            GROUP_CONCAT(
                CONCAT(b.nama_barang, ' (', dp.jumlah, ')')
                SEPARATOR ', '
            ) AS daftar_barang
        FROM peminjaman p
        LEFT JOIN pengguna u
            ON p.id_pengguna = u.id_pengguna
        LEFT JOIN detail_peminjaman dp
            ON p.id_peminjaman = dp.id_peminjaman
        LEFT JOIN barang b
            ON dp.id_barang = b.id_barang
        WHERE p.id_pengguna = ?
        GROUP BY
            p.id_peminjaman,
            p.id_pengguna,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            p.status,
            u.nama
        ORDER BY p.id_peminjaman DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_pengguna);
    }
}

if (!$stmt) {
    error_log("Query riwayat peminjaman gagal: " . mysqli_error($conn));
    die("Riwayat peminjaman gagal dimuat. Periksa struktur tabel database.");
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

function aman($teks)
{
    return htmlspecialchars(
        (string) ($teks ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

function kelasStatus($status)
{
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

$lihat_semua = in_array(
    $role,
    ['kepala_sekolah', 'pengelola_sarpras'],
    true
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Peminjaman</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            margin: 0 0 8px;
        }

        .page-header p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .aksi-halaman {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .table-card {
            padding: 22px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 14px;
        }

        .judul-tabel {
            margin: 0 0 18px;
            color: var(--text);
            font-size: 18px;
        }

        .tabel-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .tabel-riwayat {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        .tabel-riwayat th,
        .tabel-riwayat td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
            font-size: 13px;
            line-height: 1.6;
        }

        .tabel-riwayat th {
            background: var(--surface);
            color: var(--text-muted);
            font-weight: 700;
            white-space: nowrap;
        }

        .tabel-riwayat td {
            color: var(--text);
        }

        .tabel-riwayat tbody tr:last-child td {
            border-bottom: none;
        }

        .tabel-riwayat tbody tr:hover {
            background: rgba(22, 134, 106, 0.07);
        }

        .nomor {
            color: var(--text-muted) !important;
        }

        .id-peminjaman {
            font-weight: 700;
            white-space: nowrap;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-menunggu {
            background: rgba(214, 163, 61, 0.15);
            color: #e4b84f;
        }

        .status-disetujui {
            background: rgba(60, 186, 131, 0.15);
            color: #45ca91;
        }

        .status-ditolak {
            background: rgba(220, 75, 75, 0.15);
            color: #f07878;
        }

        .status-dikembalikan {
            background: rgba(106, 157, 232, 0.15);
            color: #8bb8fa;
        }

        .status-default {
            background: rgba(150, 150, 150, 0.15);
            color: var(--text-muted);
        }

        .btn-detail {
            display: inline-block;
            padding: 7px 11px;
            border: 1px solid rgba(22, 134, 106, 0.4);
            border-radius: 7px;
            background: rgba(22, 134, 106, 0.12);
            color: #39bd91;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-detail:hover {
            background: rgba(22, 134, 106, 0.24);
        }

        .kosong {
            padding: 35px 20px;
            text-align: center;
            background: var(--surface);
            border: 1px dashed var(--border);
            border-radius: 10px;
        }

        .kosong-ikon {
            margin-bottom: 12px;
            color: #39bd91;
            font-size: 32px;
        }

        .kosong h3 {
            margin: 0 0 8px;
            color: var(--text);
        }

        .kosong p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .catatan-tabel {
            margin: 15px 0 0;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .table-card {
                padding: 14px;
            }

            .aksi-halaman {
                flex-direction: column;
            }

            .aksi-halaman .btn {
                justify-content: center;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <span class="eyebrow">DATA PEMINJAMAN</span>

        <h1>
            <?= $lihat_semua
                ? 'Seluruh Riwayat Peminjaman'
                : 'Riwayat Peminjaman Saya'; ?>
        </h1>

        <p>
            <?php if ($lihat_semua): ?>
                Lihat riwayat peminjaman seluruh pengguna
                di SMK Negeri 2 Padang.
            <?php else: ?>
                Lihat daftar pengajuan peminjaman barang,
                tanggal peminjaman, dan status pengajuan Anda.
            <?php endif; ?>
        </p>
    </div>

    <div class="aksi-halaman">
        <a href="cetak_riwayat_peminjaman.php" class="btn btn-primary">
            Cetak Riwayat
        </a>

        <a href="index.php" class="btn btn-secondary">
            Kembali ke Dashboard
        </a>
    </div>

    <div class="table-card">

        <h2 class="judul-tabel">Daftar Riwayat Peminjaman</h2>

        <?php if (mysqli_num_rows($result) > 0): ?>

            <div class="tabel-wrapper">
                <table class="tabel-riwayat">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>ID Peminjaman</th>

                            <?php if ($lihat_semua): ?>
                                <th>Nama Peminjam</th>
                            <?php endif; ?>

                            <th>Barang dan Jumlah</th>
                            <th>Tanggal Pinjam</th>
                            <th>Tanggal Kembali</th>
                            <th>Status</th>
                            <th>Detail</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php $no = 1; ?>

                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="nomor">
                                    <?= $no++; ?>
                                </td>

                                <td class="id-peminjaman">
                                    #<?= (int) $row['id_peminjaman']; ?>
                                </td>

                                <?php if ($lihat_semua): ?>
                                    <td>
                                        <?= aman($row['nama_peminjam']); ?>
                                    </td>
                                <?php endif; ?>

                                <td>
                                    <?= aman($row['daftar_barang']); ?>
                                </td>

                                <td>
                                    <?= aman($row['tanggal_pinjam']); ?>
                                </td>

                                <td>
                                    <?= aman($row['tanggal_kembali']); ?>
                                </td>

                                <td>
                                    <span class="status <?= kelasStatus($row['status'] ?? ''); ?>">
                                        <?= aman($row['status']); ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="detail_peminjaman.php?id=<?= (int) $row['id_peminjaman']; ?>"
                                        class="btn-detail"
                                    >
                                        Lihat Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <p class="catatan-tabel">
                Geser tabel ke samping jika dibuka melalui layar HP
                untuk melihat seluruh kolom.
            </p>

        <?php else: ?>

            <div class="kosong">
                <div class="kosong-ikon">◷</div>

                <h3>Belum Ada Riwayat</h3>

                <p>
                    <?= $lihat_semua
                        ? 'Belum ada data peminjaman dari seluruh pengguna.'
                        : 'Anda belum memiliki riwayat peminjaman. Pengajuan yang dibuat akan tampil di halaman ini.'; ?>
                </p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>
