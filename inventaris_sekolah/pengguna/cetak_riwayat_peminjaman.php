
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
$nama = $_SESSION['nama'] ?? 'Pengguna';

// Kepala sekolah dan pengelola sarpras melihat seluruh riwayat.
$lihat_semua = in_array(
    $role,
    ['kepala_sekolah', 'pengelola_sarpras'],
    true
);

if ($lihat_semua) {
    $sql = "
        SELECT
            p.id_peminjaman,
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
    error_log("Gagal menyiapkan cetak riwayat: " . mysqli_error($conn));
    die("Data riwayat peminjaman gagal dimuat. Periksa database.");
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

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

    <title>Cetak Riwayat Peminjaman</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
        }

        .main-content {
            padding: 30px;
        }

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

        .aksi {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .laporan {
            padding: 30px;
            background: #fff;
            color: #222;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
        }

        .judul-laporan {
            text-align: center;
            line-height: 1.5;
        }

        .judul-laporan h2 {
            margin: 0;
            font-size: 21px;
        }

        .judul-laporan h3 {
            margin: 5px 0;
            font-size: 17px;
        }

        .garis-laporan {
            border: 0;
            border-top: 2px solid #222;
            margin: 15px 0 20px;
        }

        .identitas {
            margin: 20px 0;
            font-size: 13px;
        }

        .identitas p {
            margin: 6px 0;
        }

        .tanggal-cetak {
            text-align: right;
            font-size: 12px;
            margin-top: 20px;
        }

        .tabel-laporan {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabel-laporan th,
        .tabel-laporan td {
            border: 1px solid #555;
            padding: 10px;
            text-align: left;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .tabel-laporan th {
            background: #e8eee9;
            color: #222;
        }

        .tengah {
            text-align: center !important;
        }

        .ttd {
            margin-top: 40px;
            text-align: right;
            line-height: 1.7;
            font-size: 13px;
            page-break-inside: avoid;
        }

        .nama-ttd {
            display: inline-block;
            min-width: 180px;
            margin-top: 65px;
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
        }

        @media (max-width: 700px) {
            .main-content {
                padding: 16px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .aksi {
                width: 100%;
            }

            .aksi .btn {
                flex: 1;
                text-align: center;
            }

            .laporan {
                padding: 15px;
                overflow-x: auto;
            }

            .tabel-laporan {
                min-width: 700px;
            }
        }

        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }

            body {
                margin: 0 !important;
                background: #fff !important;
                color: #222 !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }

            .page-header,
            .aksi {
                display: none !important;
            }

            .laporan {
                padding: 0 !important;
                margin: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #222 !important;
            }

            .tabel-laporan {
                width: 100% !important;
                min-width: 0 !important;
                margin-top: 15px;
            }

            .tabel-laporan th,
            .tabel-laporan td {
                padding: 7px;
                font-size: 10px;
                border: 1px solid #555 !important;
            }

            .tabel-laporan th {
                background: #e8eee9 !important;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .judul-laporan,
            .identitas,
            .tanggal-cetak,
            .ttd {
                color: #222 !important;
            }

            thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <p class="eyebrow">INVENTARIS SEKOLAH</p>
            <h1>Cetak Riwayat Peminjaman</h1>
            <p>SMK Negeri 2 Padang</p>
        </div>

        <div class="aksi">
            <button
                type="button"
                class="btn btn-primary"
                onclick="window.print()"
            >
                Cetak / Simpan PDF
            </button>

            <a
                href="riwayat_peminjaman.php"
                class="btn btn-secondary"
            >
                Kembali
            </a>
        </div>
    </div>

    <div class="laporan">

        <div class="judul-laporan">
            <h2>SMK NEGERI 2 PADANG</h2>
            <h3>LAPORAN RIWAYAT PEMINJAMAN BARANG</h3>
        </div>

        <hr class="garis-laporan">

        <div class="identitas">
            <?php if ($lihat_semua): ?>
                <p>
                    <strong>Jenis Laporan:</strong>
                    Seluruh Riwayat Peminjaman
                </p>
            <?php else: ?>
                <p>
                    <strong>Nama Pengguna:</strong>
                    <?= aman($nama); ?>
                </p>
            <?php endif; ?>
        </div>

        <p class="tanggal-cetak">
            Tanggal cetak: <?= date('d-m-Y'); ?>
        </p>

        <table class="tabel-laporan">
            <thead>
                <tr>
                    <th class="tengah">No.</th>
                    <th class="tengah">ID Peminjaman</th>

                    <?php if ($lihat_semua): ?>
                        <th>Nama Peminjam</th>
                    <?php endif; ?>

                    <th>Nama Barang dan Jumlah</th>
                    <th>Tanggal Pinjam</th>
                    <th>Tanggal Kembali</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="tengah">
                                <?= $no++; ?>
                            </td>

                            <td class="tengah">
                                <?= (int) $row['id_peminjaman']; ?>
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
                                <?= aman($row['status']); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td
                            colspan="<?= $lihat_semua ? 7 : 6; ?>"
                            class="tengah"
                        >
                            Belum ada riwayat peminjaman.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="ttd">
            Padang, <?= date('d-m-Y'); ?>
            <br>

            <?php if ($lihat_semua): ?>
                Kepala Sekolah / Pengelola Sarpras
            <?php else: ?>
                Pengguna
            <?php endif; ?>

            <br>

            <span class="nama-ttd">
                <?= aman($nama); ?>
            </span>
        </div>

    </div>

</div>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>
