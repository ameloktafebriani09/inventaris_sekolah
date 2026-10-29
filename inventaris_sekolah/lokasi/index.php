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

$query = mysqli_query(
    $conn,
    "SELECT * FROM lokasi ORDER BY id_lokasi DESC"
);

if (!$query) {
    die("Gagal mengambil data lokasi: " . mysqli_error($conn));
}

function aman($nilai) {
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

$pesan = "";
$jenisPesan = "success";

if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'berhasil':
            $pesan = "Lokasi berhasil ditambahkan.";
            break;

        case 'terubah':
            $pesan = "Lokasi berhasil diubah.";
            break;

        case 'terhapus':
            $pesan = "Lokasi berhasil dihapus.";
            break;

        case 'dipakai':
            $pesan = "Lokasi tidak bisa dihapus karena masih digunakan oleh barang.";
            $jenisPesan = "danger";
            break;

        case 'gagal':
            $pesan = "Proses gagal atau lokasi tidak ditemukan.";
            $jenisPesan = "danger";
            break;

        case 'tidak_ditemukan':
            $pesan = "Data lokasi yang ingin diedit tidak ditemukan.";
            $jenisPesan = "danger";
            break;
    }
}

$totalData = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Lokasi Barang | SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .lokasi-card {
            overflow: hidden;
            margin-bottom: 24px;
        }

        .lokasi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .lokasi-header h3 {
            margin: 0 0 5px;
            color: var(--text);
            font-size: 18px;
        }

        .lokasi-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .lokasi-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px 24px;
        }

        .lokasi-total {
            color: var(--text-muted);
            font-size: 13px;
        }

        .lokasi-total strong {
            color: #6ee7b7;
            font-size: 16px;
        }

        .lokasi-alert {
            margin: 16px 24px 0;
            padding: 13px 16px;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.6;
        }

        .lokasi-alert-success {
            color: #6ee7b7;
            background: rgba(16, 185, 129, .12);
            border: 1px solid rgba(16, 185, 129, .3);
        }

        .lokasi-alert-danger {
            color: #fca5a5;
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .3);
        }

        .lokasi-table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        .lokasi-table th {
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

        .lokasi-table td {
            padding: 15px 16px;
            color: var(--text);
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .lokasi-table tbody tr:last-child td {
            border-bottom: none;
        }

        .lokasi-table tbody tr:hover {
            background: var(--surface-2);
        }

        .lokasi-nomor {
            color: var(--text-muted) !important;
            width: 55px;
        }

        .lokasi-nama {
            font-weight: 600;
            color: #6ee7b7 !important;
        }

        .lokasi-keterangan {
            color: var(--text-muted) !important;
            white-space: normal;
            min-width: 160px;
            max-width: 300px;
            overflow-wrap: anywhere;
        }

        .lokasi-aksi {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .lokasi-aksi .btn {
            padding: 8px 12px;
            font-size: 12px;
        }

        .lokasi-aksi form {
            margin: 0;
        }

        .lokasi-kosong {
            padding: 42px 20px !important;
            text-align: center;
            white-space: normal;
            color: var(--text-muted) !important;
        }

        .lokasi-kosong-icon {
            display: block;
            font-size: 30px;
            margin-bottom: 10px;
        }

        @media (max-width: 600px) {
            .lokasi-header {
                padding: 18px;
            }

            .lokasi-toolbar {
                padding: 16px 18px;
            }

            .lokasi-alert {
                margin-left: 18px;
                margin-right: 18px;
            }

            .lokasi-header > a {
                width: 100%;
                box-sizing: border-box;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <span class="eyebrow">MANAJEMEN INVENTARIS</span>
            <h1>Data Lokasi Barang</h1>
            <p>Kelola lokasi penyimpanan barang SMK Negeri 2 Padang.</p>
        </div>
    </div>

    <div class="card lokasi-card">

        <div class="lokasi-header">
            <div>
                <h3>Daftar Lokasi</h3>
                <p>Informasi tempat penyimpanan inventaris sekolah.</p>
            </div>

            <a href="tambah.php" class="btn btn-primary">
                + Tambah Lokasi
            </a>
        </div>

        <?php if ($pesan !== ''): ?>
            <div class="lokasi-alert lokasi-alert-<?= aman($jenisPesan) ?>">
                <?= aman($pesan) ?>
            </div>
        <?php endif; ?>

        <div class="lokasi-toolbar">
            <div class="lokasi-total">
                Total lokasi:
                <strong><?= $totalData ?></strong>
            </div>

            <a href="../admin/index.php" class="btn btn-secondary">
                Kembali ke Dashboard
            </a>
        </div>

        <div class="table-responsive">
            <table class="lokasi-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Lokasi</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($totalData > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($data = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td class="lokasi-nomor">
                                    <?= $no++ ?>
                                </td>

                                <td class="lokasi-nama">
                                    <?= aman($data['nama_lokasi']) ?>
                                </td>

                                <td class="lokasi-keterangan">
                                    <?php
                                    $keterangan = trim(
                                        (string) ($data['keterangan'] ?? '')
                                    );
                                    echo $keterangan !== ''
                                        ? aman($keterangan)
                                        : '-';
                                    ?>
                                </td>

                                <td>
                                    <div class="lokasi-aksi">
                                        <a
                                            href="edit.php?id=<?= (int) $data['id_lokasi'] ?>"
                                            class="btn btn-primary"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="hapus.php"
                                            onsubmit="return confirm('Yakin ingin menghapus lokasi ini?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="id_lokasi"
                                                value="<?= (int) $data['id_lokasi'] ?>"
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
                            <td colspan="4" class="lokasi-kosong">
                                <span class="lokasi-kosong-icon">📍</span>
                                Belum ada data lokasi.<br>
                                Klik <strong>Tambah Lokasi</strong> untuk
                                menambahkan lokasi penyimpanan barang.
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