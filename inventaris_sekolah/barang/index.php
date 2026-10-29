<?php
session_start();
require_once "../koneksi.php";

// Hak akses Pengelola Sarana Prasarana
if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'pengelola_sarpras'
) {
    header("Location: ../auth/login.php");
    exit;
}

// Ambil data barang beserta kategori dan lokasi.
$query = mysqli_query($conn, "
    SELECT
        b.id_barang,
        b.kode_inventaris,
        b.nama_barang,
        b.jumlah,
        b.jumlah_baik,
        b.jumlah_rusak_ringan,
        b.jumlah_rusak_berat,
        k.nama_kategori,
        l.nama_lokasi
    FROM barang b
    JOIN kategori k ON b.id_kategori = k.id_kategori
    JOIN lokasi l ON b.id_lokasi = l.id_lokasi
    ORDER BY b.id_barang DESC
");

if (!$query) {
    die("Gagal mengambil data barang: " . mysqli_error($conn));
}

function e($nilai)
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

// Pesan status.
$status = $_GET['status'] ?? '';
$pesanStatus = '';

switch ($status) {
    case 'terubah':
        $pesanStatus = 'Data barang berhasil diperbarui.';
        break;
    case 'terhapus':
        $pesanStatus = 'Data barang berhasil dihapus.';
        break;
    case 'ada_riwayat':
        $pesanStatus = 'Barang tidak dapat dihapus karena memiliki riwayat perubahan kondisi.';
        break;
    case 'ada_peminjaman':
        $pesanStatus = 'Barang tidak dapat dihapus karena tercatat dalam detail peminjaman.';
        break;
    case 'berhasil':
        $pesanStatus = 'Data barang berhasil ditambahkan.';
        break;
    case 'gagal':
        $pesanStatus = 'Proses gagal. Silakan periksa kembali data barang.';
        break;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Barang | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .rincian-stok {
            min-width: 135px;
            line-height: 1.9;
            font-size: 12px;
        }

        .rincian-stok div {
            white-space: nowrap;
        }

        .stok-baik {
            color: #16a34a;
        }

        .stok-ringan {
            color: #d97706;
        }

        .stok-berat {
            color: #dc2626;
        }

        .jumlah-total {
            font-weight: 700;
            font-size: 15px;
        }

        @media (max-width: 768px) {
            .page-header {
                gap: 12px;
            }

            .page-header > div:last-child {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
        }
    </style>
</head>

<body>
<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Data Barang Inventaris</h1>
            <p>Inventaris Barang SMK Negeri 2 Padang</p>
        </div>

        <div>
            <a href="../admin/index.php" class="btn btn-secondary">
                Kembali ke Dashboard
            </a>

            <a href="tambah.php" class="btn btn-primary">
                + Tambah Barang
            </a>
        </div>
    </div>

    <?php if ($pesanStatus !== ''): ?>
        <div class="alert <?= in_array($status, ['gagal', 'ada_riwayat', 'ada_peminjaman'], true) ? 'alert-danger' : 'alert-success' ?>">
            <?= e($pesanStatus) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div>
                <h2>Daftar Barang</h2>
                <p>
                    Data barang, lokasi, jumlah total, dan rincian kondisi barang.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Inventaris</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                        <th>Jumlah Total</th>
                        <th>Rincian Kondisi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($barang = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td><?= $no++ ?></td>

                                <td>
                                    <?= e($barang['kode_inventaris']) ?>
                                </td>

                                <td>
                                    <?= e($barang['nama_barang']) ?>
                                </td>

                                <td>
                                    <?= e($barang['nama_kategori']) ?>
                                </td>

                                <td>
                                    <?= e($barang['nama_lokasi']) ?>
                                </td>

                                <td class="jumlah-total">
                                    <?= (int) $barang['jumlah'] ?> unit
                                </td>

                                <td>
                                    <div class="rincian-stok">
                                        <div class="stok-baik">
                                            Baik:
                                            <?= (int) $barang['jumlah_baik'] ?> unit
                                        </div>

                                        <div class="stok-ringan">
                                            Rusak ringan:
                                            <?= (int) $barang['jumlah_rusak_ringan'] ?> unit
                                        </div>

                                        <div class="stok-berat">
                                            Rusak berat:
                                            <?= (int) $barang['jumlah_rusak_berat'] ?> unit
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="aksi">
                                        <a
                                            href="edit.php?id=<?= (int) $barang['id_barang'] ?>"
                                            class="btn btn-warning"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            action="hapus.php"
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus barang ini?')"
                                        >
                                            <input
                                                type="hidden"
                                                name="id_barang"
                                                value="<?= (int) $barang['id_barang'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-danger"
                                            >
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">
                                Belum ada data barang. Silakan klik
                                Tambah Barang untuk memulai.
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
