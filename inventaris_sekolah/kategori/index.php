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
    "SELECT * FROM kategori ORDER BY id_kategori DESC"
);

if (!$query) {
    die("Gagal mengambil data kategori.");
}

function e($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

$status = $_GET['status'] ?? '';
$pesan = '';
$jenis_pesan = 'error';

if ($status === 'berhasil') {
    $pesan = "Kategori berhasil ditambahkan.";
    $jenis_pesan = 'success';
} elseif ($status === 'terubah') {
    $pesan = "Kategori berhasil diubah.";
    $jenis_pesan = 'success';
} elseif ($status === 'terhapus') {
    $pesan = "Kategori berhasil dihapus.";
    $jenis_pesan = 'success';
} elseif ($status === 'dipakai') {
    $pesan = "Kategori tidak bisa dihapus karena masih digunakan oleh barang.";
} elseif ($status === 'gagal') {
    $pesan = "Proses gagal atau kategori tidak ditemukan.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Kategori | Inventaris SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .kategori-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .kategori-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding: 22px 24px;
            border-bottom: 1px solid var(--border);
        }

        .kategori-card-header h3 {
            margin: 0 0 6px;
            color: var(--text);
            font-size: 18px;
        }

        .kategori-card-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .kategori-toolbar {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
        }

        .kategori-toolbar .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 10px 15px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 13px;
        }

        .kategori-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .kategori-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        .kategori-table th {
            padding: 15px 20px;
            color: var(--text-muted);
            background: var(--surface-2);
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .kategori-table td {
            padding: 16px 20px;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            vertical-align: middle;
        }

        .kategori-table tbody tr:last-child td {
            border-bottom: none;
        }

        .kategori-table tbody tr:hover {
            background: rgba(16, 185, 129, 0.04);
        }

        .kategori-number {
            color: var(--text-muted);
            width: 55px;
        }

        .kategori-name {
            font-weight: 600;
        }

        .kategori-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .kategori-actions form {
            margin: 0;
        }

        .kategori-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 8px 12px;
            border: none;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            font-size: 12px;
        }

        .kategori-empty {
            padding: 45px 20px !important;
            text-align: center;
            color: var(--text-muted) !important;
        }

        .kategori-empty-icon {
            display: block;
            margin-bottom: 10px;
            font-size: 30px;
        }

        .kategori-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 17px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-size: 14px;
        }

        .kategori-alert.success {
            color: #a7f3d0;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .kategori-alert.error {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 600px) {
            .kategori-card-header {
                align-items: stretch;
                flex-direction: column;
                padding: 18px;
            }

            .kategori-card-header .btn {
                width: 100%;
                box-sizing: border-box;
                justify-content: center;
            }

            .kategori-toolbar {
                padding: 14px 18px;
            }

            .kategori-toolbar .btn {
                width: 100%;
                box-sizing: border-box;
            }

            .kategori-table th,
            .kategori-table td {
                padding: 13px 14px;
            }
        }
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <span class="eyebrow">DATA MASTER</span>
            <h1>Data Kategori Barang</h1>
            <p>Kelola kategori inventaris SMK Negeri 2 Padang.</p>
        </div>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="kategori-alert <?= e($jenis_pesan) ?>">
            <span><?= $jenis_pesan === 'success' ? '✓' : '!' ?></span>
            <div><?= e($pesan) ?></div>
        </div>
    <?php endif; ?>

    <div class="kategori-card">

        <div class="kategori-card-header">
            <div>
                <h3>Daftar Kategori</h3>
                <p>
                    Total <?= mysqli_num_rows($query) ?> kategori terdaftar
                </p>
            </div>

            <a href="tambah.php" class="btn btn-primary">
                + Tambah Kategori
            </a>
        </div>

        <div class="kategori-toolbar">
            <a href="../admin/index.php" class="btn btn-secondary">
                ← Kembali ke Dashboard
            </a>
        </div>

        <div class="kategori-table-wrapper">
            <table class="kategori-table">
                <thead>
                    <tr>
                        <th class="kategori-number">No.</th>
                        <th>Nama Kategori</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($data = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td class="kategori-number">
                                    <?= $no++ ?>
                                </td>

                                <td class="kategori-name">
                                    <?= e($data['nama_kategori']) ?>
                                </td>

                                <td>
                                    <div class="kategori-actions">
                                        <a
                                            href="edit.php?id=<?= (int) $data['id_kategori'] ?>"
                                            class="btn btn-warning"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="hapus.php"
                                            onsubmit="return confirm('Yakin ingin menghapus kategori ini?')"
                                        >
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $data['id_kategori'] ?>"
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
                            <td colspan="3" class="kategori-empty">
                                <span class="kategori-empty-icon">▤</span>
                                Belum ada data kategori.<br>
                                Silakan klik tombol Tambah Kategori.
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