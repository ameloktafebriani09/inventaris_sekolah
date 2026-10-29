<?php
session_start();
require_once "../koneksi.php";

// Cek login dan hak akses
if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] === 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

$role = $_SESSION['role'];

// Role yang diizinkan mencetak daftar barang
$bisaCetak = in_array(
    $role,
    ['pengelola_sarpras', 'kepala_sekolah', 'teknisi'],
    true
);

// Ambil data barang inventaris
$query = mysqli_query(
    $conn,
    "SELECT
        b.id_barang,
        b.kode_inventaris,
        b.nama_barang,
        k.nama_kategori,
        l.nama_lokasi,
        b.jumlah,
        b.jumlah_baik,
        b.jumlah_rusak_ringan,
        b.jumlah_rusak_berat,
        b.kondisi
     FROM barang b
     LEFT JOIN kategori k
        ON b.id_kategori = k.id_kategori
     LEFT JOIN lokasi l
        ON b.id_lokasi = l.id_lokasi
     ORDER BY b.nama_barang ASC"
);

if (!$query) {
    die("Gagal mengambil data barang: " . mysqli_error($conn));
}

// Fungsi mengamankan output
function aman($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
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

    <title>Data Barang Inventaris</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
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
            line-height: 1.6;
        }

        .aksi {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .aksi button {
            font-family: inherit;
            cursor: pointer;
        }

        .table-card {
            width: 100%;
        }

        .tabel-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .tabel-barang {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .tabel-barang th,
        .tabel-barang td {
            padding: 12px;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .tabel-barang th {
            font-size: 12px;
        }

        .tabel-barang td {
            font-size: 13px;
        }

        .tabel-barang td.nama-barang {
            white-space: normal;
            min-width: 160px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .tersedia {
            background: rgba(52, 211, 153, 0.14);
            color: #6ee7b7;
            border: 1px solid rgba(52, 211, 153, 0.25);
        }

        .tidak-tersedia {
            background: rgba(248, 113, 113, 0.13);
            color: #fca5a5;
            border: 1px solid rgba(248, 113, 113, 0.25);
        }

        .kosong {
            padding: 25px;
            text-align: center;
            color: var(--text-muted);
        }

        @media (max-width: 600px) {
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
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <p class="eyebrow">INVENTARIS SEKOLAH</p>

            <h1>Data Barang Inventaris</h1>

            <p>
                Daftar inventaris barang SMK Negeri 2 Padang,
                termasuk kategori, lokasi, jumlah, kondisi,
                dan status peminjaman.
            </p>
        </div>

        <div class="aksi">

            <?php if ($bisaCetak): ?>

                <a
                    href="cetak_daftar_barang.php"
                    class="btn btn-primary"
                >
                    Cetak Daftar Barang
                </a>

            <?php else: ?>

                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="peringatanCetak()"
                >
                    Cetak Daftar Barang
                </button>

            <?php endif; ?>

            <a href="index.php" class="btn btn-secondary">
                Kembali ke Dashboard
            </a>

        </div>
    </div>

    <div class="table-card">
        <div class="tabel-wrapper">

            <table class="tabel-barang">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Inventaris</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                        <th>Jumlah</th>
                        <th>Kondisi</th>
                        <th>Status Peminjaman</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($query) > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($barang = mysqli_fetch_assoc($query)): ?>

                            <?php
                            $jumlahBaik = (int) $barang['jumlah_baik'];
                            $bisaDipinjam = $jumlahBaik > 0;
                            ?>

                            <tr>
                                <td><?= $no++; ?></td>

                                <td>
                                    <?= aman($barang['kode_inventaris']); ?>
                                </td>

                                <td class="nama-barang">
                                    <?= aman($barang['nama_barang']); ?>
                                </td>

                                <td>
                                    <?= aman($barang['nama_kategori'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= aman($barang['nama_lokasi'] ?? '-'); ?>
                                </td>

                                <td>
                                    <?= (int) $barang['jumlah']; ?>
                                </td>

                                <td>
                                    <?= aman($barang['kondisi']); ?>
                                </td>

                                <td>
                                    <?php if ($bisaDipinjam): ?>

                                        <span class="status tersedia">
                                            Bisa Dipinjam
                                            (<?= $jumlahBaik; ?> unit baik)
                                        </span>

                                    <?php else: ?>

                                        <span class="status tidak-tersedia">
                                            Tidak Bisa Dipinjam
                                        </span>

                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="kosong">
                                Belum ada data barang inventaris.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>

        </div>
    </div>

</div>

<script>
function peringatanCetak() {
    alert(
        "Fitur cetak daftar barang hanya dapat digunakan oleh " +
        "Kepala Sekolah, Pengelola Sarpras, dan Teknisi."
    );
}
</script>

</body>
</html>

<?php
mysqli_free_result($query);
?>
