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

$pesan = "";

function e($nilai) {
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

function ambilPengguna($conn) {
    return mysqli_query(
        $conn,
        "SELECT id_pengguna, nama
         FROM pengguna
         WHERE role != 'pengelola_sarpras'
         ORDER BY nama"
    );
}

function ambilBarang($conn) {
    return mysqli_query(
        $conn,
        "SELECT id_barang, kode_inventaris, nama_barang, jumlah
         FROM barang
         WHERE jumlah > 0
         ORDER BY nama_barang"
    );
}

$pengguna = ambilPengguna($conn);
$barang = ambilBarang($conn);

if (!$pengguna || !$barang) {
    die("Gagal mengambil data dari database.");
}

$id_pengguna_form = $_POST['id_pengguna'] ?? '';
$id_barang_form = $_POST['id_barang'] ?? '';
$jumlah_form = $_POST['jumlah'] ?? '1';
$tanggal_pinjam_form = $_POST['tanggal_pinjam'] ?? date('Y-m-d');
$tanggal_kembali_form = $_POST['tanggal_kembali'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pengguna = filter_var(
        $_POST['id_pengguna'] ?? null,
        FILTER_VALIDATE_INT
    );

    $id_barang = filter_var(
        $_POST['id_barang'] ?? null,
        FILTER_VALIDATE_INT
    );

    $jumlah = filter_var(
        $_POST['jumlah'] ?? null,
        FILTER_VALIDATE_INT
    );

    $tanggal_pinjam = trim($_POST['tanggal_pinjam'] ?? '');
    $tanggal_kembali = trim($_POST['tanggal_kembali'] ?? '');

    $pinjam = DateTime::createFromFormat('!Y-m-d', $tanggal_pinjam);
    $kembali = DateTime::createFromFormat('!Y-m-d', $tanggal_kembali);

    $tanggal_valid =
        $pinjam !== false &&
        $pinjam->format('Y-m-d') === $tanggal_pinjam &&
        $kembali !== false &&
        $kembali->format('Y-m-d') === $tanggal_kembali &&
        $tanggal_kembali >= $tanggal_pinjam;

    if (
        !$id_pengguna ||
        $id_pengguna <= 0 ||
        !$id_barang ||
        $id_barang <= 0 ||
        $jumlah === false ||
        $jumlah === null ||
        $jumlah <= 0 ||
        !$tanggal_valid
    ) {
        $pesan = "Mohon isi semua data dengan benar. Tanggal kembali tidak boleh sebelum tanggal pinjam.";
    } else {

        $cek_pengguna = mysqli_prepare(
            $conn,
            "SELECT id_pengguna
             FROM pengguna
             WHERE id_pengguna = ?
             AND role != 'pengelola_sarpras'"
        );

        if (!$cek_pengguna) {
            $pesan = "Gagal memeriksa data pengguna.";
        } else {
            mysqli_stmt_bind_param($cek_pengguna, "i", $id_pengguna);
            mysqli_stmt_execute($cek_pengguna);
            mysqli_stmt_store_result($cek_pengguna);

            $pengguna_ada = mysqli_stmt_num_rows($cek_pengguna) > 0;
            mysqli_stmt_close($cek_pengguna);

            if (!$pengguna_ada) {
                $pesan = "Pengguna tidak ditemukan atau tidak dapat melakukan peminjaman.";
            } else {
                if (!mysqli_begin_transaction($conn)) {
                    $pesan = "Transaksi database gagal dimulai.";
                } else {
                    try {

                        $cek_barang = mysqli_prepare(
                            $conn,
                            "SELECT jumlah
                             FROM barang
                             WHERE id_barang = ?
                             FOR UPDATE"
                        );

                        if (!$cek_barang) {
                            throw new Exception("Gagal memeriksa stok barang.");
                        }

                        mysqli_stmt_bind_param(
                            $cek_barang,
                            "i",
                            $id_barang
                        );

                        if (!mysqli_stmt_execute($cek_barang)) {
                            mysqli_stmt_close($cek_barang);
                            throw new Exception("Gagal mengambil stok barang.");
                        }

                        mysqli_stmt_bind_result($cek_barang, $stok);

                        if (!mysqli_stmt_fetch($cek_barang)) {
                            mysqli_stmt_close($cek_barang);
                            throw new Exception("Barang tidak ditemukan.");
                        }

                        mysqli_stmt_close($cek_barang);

                        if ($stok <= 0) {
                            throw new Exception("Stok barang sudah habis.");
                        }

                        if ($jumlah > $stok) {
                            throw new Exception(
                                "Jumlah yang dipinjam melebihi stok barang yang tersedia."
                            );
                        }

                        $simpan = mysqli_prepare(
                            $conn,
                            "INSERT INTO peminjaman
                            (
                                id_pengguna,
                                tanggal_pinjam,
                                tanggal_kembali,
                                status
                            )
                            VALUES (?, ?, ?, 'Menunggu')"
                        );

                        if (!$simpan) {
                            throw new Exception(
                                "Gagal menyiapkan data peminjaman."
                            );
                        }

                        mysqli_stmt_bind_param(
                            $simpan,
                            "iss",
                            $id_pengguna,
                            $tanggal_pinjam,
                            $tanggal_kembali
                        );

                        if (!mysqli_stmt_execute($simpan)) {
                            mysqli_stmt_close($simpan);
                            throw new Exception(
                                "Gagal menyimpan data peminjaman."
                            );
                        }

                        $id_peminjaman = mysqli_insert_id($conn);
                        mysqli_stmt_close($simpan);

                        $detail = mysqli_prepare(
                            $conn,
                            "INSERT INTO detail_peminjaman
                            (
                                id_peminjaman,
                                id_barang,
                                jumlah
                            )
                            VALUES (?, ?, ?)"
                        );

                        if (!$detail) {
                            throw new Exception(
                                "Gagal menyiapkan detail peminjaman."
                            );
                        }

                        mysqli_stmt_bind_param(
                            $detail,
                            "iii",
                            $id_peminjaman,
                            $id_barang,
                            $jumlah
                        );

                        if (!mysqli_stmt_execute($detail)) {
                            mysqli_stmt_close($detail);
                            throw new Exception(
                                "Gagal menyimpan detail peminjaman."
                            );
                        }

                        mysqli_stmt_close($detail);

                        mysqli_commit($conn);

                        header("Location: index.php?status=berhasil");
                        exit;

                    } catch (Throwable $e) {
                        mysqli_rollback($conn);
                        $pesan = $e->getMessage();
                    }
                }
            }
        }
    }
}

$pengguna = ambilPengguna($conn);
$barang = ambilBarang($conn);

if (!$pengguna || !$barang) {
    die("Gagal mengambil data dari database.");
}

$total_pengguna = mysqli_num_rows($pengguna);
$total_barang = mysqli_num_rows($barang);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Peminjaman | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .tambah-peminjaman-wrapper {
            max-width: 900px;
        }

        .tambah-peminjaman-card {
            overflow: hidden;
            background: var(--surface, #102b24);
            border: 1px solid var(--border, #285044);
            border-radius: 14px;
        }

        .tambah-peminjaman-header {
            padding: 20px 24px;
            background: var(--surface-2, #17382e);
            border-bottom: 1px solid var(--border, #285044);
        }

        .tambah-peminjaman-header h3 {
            margin: 0 0 6px;
            color: var(--text, #f0f7f3);
            font-size: 18px;
        }

        .tambah-peminjaman-header p {
            margin: 0;
            color: var(--text-muted, #a5bcb1);
            font-size: 13px;
        }

        .tambah-peminjaman-body {
            padding: 24px;
        }

        .tambah-peminjaman-alert {
            padding: 14px 16px;
            margin-bottom: 22px;
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.6;
        }

        .tambah-peminjaman-info {
            padding: 15px 17px;
            margin-bottom: 24px;
            color: #a7f3d0;
            background: rgba(16, 185, 129, 0.09);
            border: 1px solid rgba(52, 211, 153, 0.25);
            border-radius: 9px;
            font-size: 13px;
            line-height: 1.7;
        }

        .tambah-peminjaman-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px 20px;
        }

        .tambah-peminjaman-group {
            min-width: 0;
        }

        .tambah-peminjaman-group.full-width {
            grid-column: 1 / -1;
        }

        .tambah-peminjaman-group label {
            display: block;
            margin-bottom: 9px;
            color: var(--text, #f0f7f3);
            font-size: 14px;
            font-weight: 600;
        }

        .tambah-peminjaman-group input,
        .tambah-peminjaman-group select {
            display: block;
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            color: var(--text, #f0f7f3);
            background: var(--surface-2, #17382e);
            border: 1px solid var(--border, #285044);
            border-radius: 8px;
            font: inherit;
            box-sizing: border-box;
        }

        .tambah-peminjaman-group select option {
            color: #f0f7f3;
            background: #17382e;
        }

        .tambah-peminjaman-group input:focus,
        .tambah-peminjaman-group select:focus {
            outline: 2px solid rgba(52, 211, 153, 0.3);
            border-color: #34d399;
        }

        .tambah-peminjaman-help {
            margin-top: 8px;
            color: var(--text-muted, #a5bcb1);
            font-size: 12px;
            line-height: 1.6;
        }

        .tambah-peminjaman-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--border, #285044);
        }

        .tambah-peminjaman-actions .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (max-width: 650px) {
            .tambah-peminjaman-header,
            .tambah-peminjaman-body {
                padding: 17px;
            }

            .tambah-peminjaman-grid {
                grid-template-columns: 1fr;
                gap: 19px;
            }

            .tambah-peminjaman-group.full-width {
                grid-column: auto;
            }

            .tambah-peminjaman-actions {
                flex-direction: column;
            }

            .tambah-peminjaman-actions .btn {
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
            <h1>Tambah Peminjaman Barang</h1>
            <p style="color: var(--text-muted, #a5bcb1); margin-top: 6px;">
                Inventaris Barang Sekolah - SMK Negeri 2 Padang
            </p>
        </div>
    </div>

    <div class="tambah-peminjaman-wrapper">

        <section class="tambah-peminjaman-card">

            <div class="tambah-peminjaman-header">
                <h3>Formulir Peminjaman</h3>
                <p>Lengkapi data pengguna, barang, jumlah, dan tanggal peminjaman.</p>
            </div>

            <div class="tambah-peminjaman-body">

                <?php if ($pesan !== ''): ?>
                    <div class="tambah-peminjaman-alert">
                        <?= e($pesan) ?>
                    </div>
                <?php endif; ?>

                <?php if ($total_pengguna === 0 || $total_barang === 0): ?>
                    <div class="tambah-peminjaman-alert">
                        <?php if ($total_pengguna === 0): ?>
                            Belum ada pengguna yang dapat melakukan peminjaman.
                        <?php endif; ?>

                        <?php if ($total_barang === 0): ?>
                            Tidak ada barang dengan stok tersedia. Tambahkan stok barang terlebih dahulu.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="tambah-peminjaman-info">
                    <strong>Informasi:</strong>
                    Peminjaman baru berstatus Menunggu. Stok barang baru dikurangi
                    setelah permohonan disetujui oleh pengelola sarpras.
                </div>

                <form method="POST">

                    <div class="tambah-peminjaman-grid">

                        <div class="tambah-peminjaman-group full-width">
                            <label for="id_pengguna">Nama Peminjam</label>

                            <select
                                id="id_pengguna"
                                name="id_pengguna"
                                required
                            >
                                <option value="">-- Pilih Pengguna --</option>

                                <?php while ($u = mysqli_fetch_assoc($pengguna)): ?>
                                    <option
                                        value="<?= (int) $u['id_pengguna'] ?>"
                                        <?= (string) $id_pengguna_form === (string) $u['id_pengguna'] ? 'selected' : '' ?>
                                    >
                                        <?= e($u['nama']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="tambah-peminjaman-group full-width">
                            <label for="id_barang">Barang yang Dipinjam</label>

                            <select
                                id="id_barang"
                                name="id_barang"
                                required
                            >
                                <option value="">-- Pilih Barang --</option>

                                <?php while ($b = mysqli_fetch_assoc($barang)): ?>
                                    <option
                                        value="<?= (int) $b['id_barang'] ?>"
                                        <?= (string) $id_barang_form === (string) $b['id_barang'] ? 'selected' : '' ?>
                                    >
                                        <?= e($b['kode_inventaris']) ?>
                                        -
                                        <?= e($b['nama_barang']) ?>
                                        (Stok: <?= (int) $b['jumlah'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>

                            <p class="tambah-peminjaman-help">
                                Hanya barang yang memiliki stok lebih dari nol yang ditampilkan.
                            </p>
                        </div>

                        <div class="tambah-peminjaman-group">
                            <label for="jumlah">Jumlah Dipinjam</label>

                            <input
                                type="number"
                                id="jumlah"
                                name="jumlah"
                                min="1"
                                step="1"
                                required
                                value="<?= e($jumlah_form) ?>"
                            >
                        </div>

                        <div class="tambah-peminjaman-group">
                            <label for="tanggal_pinjam">Tanggal Pinjam</label>

                            <input
                                type="date"
                                id="tanggal_pinjam"
                                name="tanggal_pinjam"
                                required
                                value="<?= e($tanggal_pinjam_form) ?>"
                            >
                        </div>

                        <div class="tambah-peminjaman-group">
                            <label for="tanggal_kembali">Tanggal Kembali</label>

                            <input
                                type="date"
                                id="tanggal_kembali"
                                name="tanggal_kembali"
                                min="<?= e($tanggal_pinjam_form) ?>"
                                required
                                value="<?= e($tanggal_kembali_form) ?>"
                            >

                            <p class="tambah-peminjaman-help">
                                Tanggal kembali harus sama dengan atau setelah tanggal pinjam.
                            </p>
                        </div>

                    </div>

                    <div class="tambah-peminjaman-actions">

                        <button
                            type="submit"
                            name="simpan"
                            class="btn btn-primary"
                            <?= ($total_pengguna === 0 || $total_barang === 0) ? 'disabled' : '' ?>
                        >
                            Simpan Peminjaman
                        </button>

                        <a href="index.php" class="btn btn-secondary">
                            Kembali
                        </a>

                    </div>

                </form>

            </div>
        </section>

    </div>

</div>

<script>
    const tanggalPinjam = document.getElementById('tanggal_pinjam');
    const tanggalKembali = document.getElementById('tanggal_kembali');

    tanggalPinjam.addEventListener('change', function () {
        tanggalKembali.min = this.value;

        if (
            tanggalKembali.value !== '' &&
            tanggalKembali.value < this.value
        ) {
            tanggalKembali.value = this.value;
        }
    });
</script>

</body>
</html>