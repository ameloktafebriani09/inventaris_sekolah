
<?php
session_start();
require_once "../koneksi.php";

// Periksa login dan hak akses
if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] === 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

$id_pengguna = (int) $_SESSION['id_pengguna'];
$role = $_SESSION['role'];

// Kepala Sekolah dan Pengelola Sarpras melihat semua data
$lihat_semua = in_array(
    $role,
    ['kepala_sekolah', 'pengelola_sarpras'],
    true
);

// Ambil data riwayat pengembalian
if ($lihat_semua) {
    $sql = "
        SELECT
            pg.id_pengembalian,
            p.id_peminjaman,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            pg.tanggal_pengembalian,
            pg.kondisi_barang,
            pg.catatan,
            u.nama AS nama_peminjam
        FROM pengembalian pg
        JOIN peminjaman p
            ON pg.id_peminjaman = p.id_peminjaman
        JOIN pengguna u
            ON p.id_pengguna = u.id_pengguna
        ORDER BY
            pg.tanggal_pengembalian DESC,
            pg.id_pengembalian DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);
} else {
    $sql = "
        SELECT
            pg.id_pengembalian,
            p.id_peminjaman,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            pg.tanggal_pengembalian,
            pg.kondisi_barang,
            pg.catatan
        FROM pengembalian pg
        JOIN peminjaman p
            ON pg.id_peminjaman = p.id_peminjaman
        WHERE p.id_pengguna = ?
        ORDER BY
            pg.tanggal_pengembalian DESC,
            pg.id_pengembalian DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_pengguna);
    }
}

if (!$stmt) {
    error_log(
        "Query riwayat pengembalian gagal: " . mysqli_error($conn)
    );
    die("Riwayat pengembalian gagal dimuat. Periksa database.");
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Pengembalian | Inventaris SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            margin: 8px 0;
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

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .tabel-pengembalian {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
        }

        .tabel-pengembalian th,
        .tabel-pengembalian td {
            padding: 13px 12px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
            font-size: 13px;
            line-height: 1.6;
        }

        .tabel-pengembalian th {
            background: var(--surface);
            color: var(--text-muted);
            font-weight: 700;
            white-space: nowrap;
        }

        .tabel-pengembalian td {
            color: var(--text);
        }

        .tabel-pengembalian tbody tr:last-child td {
            border-bottom: none;
        }

        .tabel-pengembalian tbody tr:hover {
            background: rgba(22, 134, 106, 0.07);
        }

        .nomor {
            color: var(--text-muted) !important;
        }

        .id-data {
            font-weight: 700;
            white-space: nowrap;
        }

        .kondisi {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: rgba(22, 134, 106, 0.15);
            color: #39bd91;
            font-size: 12px;
            font-weight: 700;
        }

        .catatan {
            min-width: 130px;
            max-width: 250px;
            overflow-wrap: anywhere;
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
            margin: 14px 0 0;
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
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <span class="eyebrow">DATA PENGEMBALIAN</span>

        <h1>
            <?= $lihat_semua
                ? 'Seluruh Riwayat Pengembalian'
                : 'Riwayat Pengembalian Barang'; ?>
        </h1>

        <p>
            <?= $lihat_semua
                ? 'Lihat catatan pengembalian barang dari seluruh pengguna Sistem Inventaris Barang Sekolah SMK Negeri 2 Padang.'
                : 'Lihat catatan pengembalian barang dari peminjaman milik Anda.'; ?>
        </p>
    </div>

    <div class="aksi-halaman">
        <a
            href="cetak_riwayat_pengembalian.php"
            class="btn btn-primary"
        >
            Cetak Riwayat
        </a>

        <a href="index.php" class="btn btn-secondary">
            Kembali ke Dashboard
        </a>
    </div>

    <div class="table-card">

        <h2 class="judul-tabel">
            Daftar Riwayat Pengembalian
        </h2>

        <?php if (mysqli_num_rows($result) > 0): ?>

            <div class="table-wrapper">
                <table class="tabel-pengembalian">
                    <thead>
                        <tr>
                            <th>No.</th>

                            <?php if ($lihat_semua): ?>
                                <th>Nama Peminjam</th>
                            <?php endif; ?>

                            <th>ID Pengembalian</th>
                            <th>ID Peminjaman</th>
                            <th>Tanggal Pinjam</th>
                            <th>Batas Kembali</th>
                            <th>Tanggal Dikembalikan</th>
                            <th>Kondisi Barang</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php $no = 1; ?>

                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="nomor">
                                    <?= $no++; ?>
                                </td>

                                <?php if ($lihat_semua): ?>
                                    <td>
                                        <?= aman($row['nama_peminjam']); ?>
                                    </td>
                                <?php endif; ?>

                                <td class="id-data">
                                    #<?= (int) $row['id_pengembalian']; ?>
                                </td>

                                <td class="id-data">
                                    #<?= (int) $row['id_peminjaman']; ?>
                                </td>

                                <td>
                                    <?= aman($row['tanggal_pinjam']); ?>
                                </td>

                                <td>
                                    <?= aman($row['tanggal_kembali']); ?>
                                </td>

                                <td>
                                    <?= aman($row['tanggal_pengembalian']); ?>
                                </td>

                                <td>
                                    <span class="kondisi">
                                        <?= aman($row['kondisi_barang']); ?>
                                    </span>
                                </td>

                                <td class="catatan">
                                    <?= aman($row['catatan']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <p class="catatan-tabel">
                Jika dibuka melalui HP, geser tabel ke samping
                untuk melihat semua kolom.
            </p>

        <?php else: ?>

            <div class="kosong">
                <div class="kosong-ikon">↩</div>

                <h3>Belum Ada Riwayat Pengembalian</h3>

                <p>
                    <?= $lihat_semua
                        ? 'Belum ada catatan pengembalian barang yang tercatat.'
                        : 'Riwayat akan tampil setelah pengembalian barang Anda tercatat.'; ?>
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
