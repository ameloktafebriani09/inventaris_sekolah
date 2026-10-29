
<?php
session_start();
require_once "../koneksi.php";

if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] === 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

$id_pengguna = (int) $_SESSION['id_pengguna'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        p.status,
        GROUP_CONCAT(
            CONCAT(b.nama_barang, ' (', dp.jumlah, ')')
            SEPARATOR ', '
        ) AS daftar_barang
     FROM peminjaman p
     LEFT JOIN detail_peminjaman dp
        ON p.id_peminjaman = dp.id_peminjaman
     LEFT JOIN barang b
        ON dp.id_barang = b.id_barang
     WHERE p.id_pengguna = ?
     GROUP BY
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        p.status
     ORDER BY p.id_peminjaman DESC"
);

if (!$stmt) {
    die("Terjadi kesalahan saat menyiapkan data notifikasi.");
}

mysqli_stmt_bind_param($stmt, "i", $id_pengguna);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

function aman($teks) {
    return htmlspecialchars(
        (string) ($teks ?? '-'),
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

function pesanStatus($status) {
    switch ($status) {
        case 'Menunggu':
            return 'Pengajuan Anda masih menunggu keputusan pengelola sarpras.';

        case 'Disetujui':
            return 'Pengajuan disetujui. Silakan gunakan barang sesuai ketentuan sekolah.';

        case 'Ditolak':
            return 'Pengajuan tidak disetujui. Silakan hubungi pengelola sarpras untuk mendapatkan penjelasan.';

        case 'Dikembalikan':
            return 'Peminjaman telah tercatat selesai dikembalikan.';

        default:
            return 'Silakan periksa kembali status peminjaman Anda.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Notifikasi Peminjaman</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-header {
            margin-bottom: 26px;
        }

        .page-header h1 {
            margin: 0 0 8px;
        }

        .page-header p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .daftar-notifikasi {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .kartu-notifikasi {
            padding: 22px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-left: 4px solid #16866a;
            border-radius: 12px;
            transition: border-color 0.2s, transform 0.2s;
        }

        .kartu-notifikasi:hover {
            border-left-color: #39bd91;
            transform: translateY(-2px);
        }

        .notifikasi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .notifikasi-header h2 {
            margin: 0;
            font-size: 18px;
            color: var(--text);
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
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

        .info-notifikasi {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 18px;
        }

        .info-item {
            padding: 13px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            min-width: 0;
        }

        .info-label {
            display: block;
            margin-bottom: 7px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .info-value {
            display: block;
            color: var(--text);
            font-size: 14px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .pesan-status {
            margin: 0 0 18px;
            padding: 13px 15px;
            border-radius: 8px;
            background: rgba(22, 134, 106, 0.1);
            border: 1px solid rgba(22, 134, 106, 0.3);
            color: var(--text);
            font-size: 14px;
            line-height: 1.7;
        }

        .aksi-notifikasi {
            display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 10px;
        }

        .kosong {
            padding: 35px 20px;
            text-align: center;
            background: var(--surface-2);
            border: 1px dashed var(--border);
            border-radius: 12px;
        }

        .kosong-ikon {
            margin-bottom: 12px;
            font-size: 32px;
            color: #39bd91;
        }

        .kosong h3 {
            margin: 0 0 8px;
            color: var(--text);
        }

        .kosong p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .tombol-kembali {
            margin-top: 24px;
        }

        @media (max-width: 600px) {
            .kartu-notifikasi {
                padding: 17px;
            }

            .info-notifikasi {
                grid-template-columns: 1fr;
            }

            .notifikasi-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <span class="eyebrow">INFORMASI PENGGUNA</span>
        <h1>Notifikasi Peminjaman</h1>
        <p>
            Pantau perkembangan pengajuan peminjaman barang
            inventaris SMK Negeri 2 Padang.
        </p>
    </div>

    <div class="daftar-notifikasi">

        <?php if (mysqli_num_rows($result) > 0): ?>

            <?php while ($row = mysqli_fetch_assoc($result)): ?>

                <?php
                $status = $row['status'] ?? '';
                ?>

                <article class="kartu-notifikasi">

                    <div class="notifikasi-header">
                        <h2>
                            Peminjaman #<?= (int) $row['id_peminjaman']; ?>
                        </h2>

                        <span class="status <?= kelasStatus($status); ?>">
                            <?= aman($status); ?>
                        </span>
                    </div>

                    <div class="info-notifikasi">

                        <div class="info-item">
                            <span class="info-label">Daftar Barang</span>
                            <span class="info-value">
                                <?= aman($row['daftar_barang']); ?>
                            </span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Tanggal Pinjam</span>
                            <span class="info-value">
                                <?= aman($row['tanggal_pinjam']); ?>
                            </span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Tanggal Kembali</span>
                            <span class="info-value">
                                <?= aman($row['tanggal_kembali']); ?>
                            </span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Status Pengajuan</span>
                            <span class="info-value">
                                <?= aman($status); ?>
                            </span>
                        </div>

                    </div>

                    <p class="pesan-status">
                        <?= aman(pesanStatus($status)); ?>
                    </p>

                    <div class="aksi-notifikasi">
                        <a
                            href="detail_peminjaman.php?id=<?= (int) $row['id_peminjaman']; ?>"
                            class="btn btn-primary"
                        >
                            Lihat Detail
                        </a>
                    </div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="kosong">
                <div class="kosong-ikon">♧</div>
                <h3>Belum Ada Notifikasi</h3>
                <p>
                    Anda belum memiliki pengajuan peminjaman.
                    Pengajuan yang dibuat akan muncul di halaman ini.
                </p>
            </div>

        <?php endif; ?>

    </div>

    <div class="tombol-kembali">
        <a href="index.php" class="btn btn-secondary">
            Kembali ke Dashboard
        </a>
    </div>

</div>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>
