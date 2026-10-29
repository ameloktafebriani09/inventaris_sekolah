
<?php
session_start();
require_once "../koneksi.php";

/* =========================================
   1. PEMERIKSAAN LOGIN DAN HAK AKSES
========================================= */
if (!isset($_SESSION['id_pengguna'], $_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

$role = $_SESSION['role'];

// Admin tidak boleh membuka halaman pengguna.
if ($role === 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Kepala Sekolah dilarang mengajukan peminjaman.
if ($role === 'kepala_sekolah') {
    echo "<script>
        alert('Akses ditolak! Kepala Sekolah tidak diperbolehkan mengajukan peminjaman.');
        window.location='index.php';
    </script>";
    exit;
}

// Hanya guru dan teknisi yang boleh mengajukan.
if (!in_array($role, ['guru', 'teknisi'], true)) {
    header("Location: ../auth/login.php");
    exit;
}

$id_pengguna = (int) $_SESSION['id_pengguna'];

$error = "";
$berhasil = "";

function aman($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

function tanggalValid($tanggal)
{
    if (!is_string($tanggal) || $tanggal === '') {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

    return $date !== false &&
           $date->format('Y-m-d') === $tanggal;
}

/* =========================================
   2. PROSES PENGAJUAN PEMINJAMAN
========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_barang = filter_var(
        $_POST['id_barang'] ?? '',
        FILTER_VALIDATE_INT
    );

    $jumlah = filter_var(
        $_POST['jumlah'] ?? '',
        FILTER_VALIDATE_INT
    );

    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_kembali = $_POST['tanggal_kembali'] ?? '';

    if (
        $id_barang === false ||
        $id_barang === null ||
        $id_barang < 1 ||
        $jumlah === false ||
        $jumlah === null ||
        $jumlah < 1 ||
        !tanggalValid($tanggal_pinjam) ||
        !tanggalValid($tanggal_kembali)
    ) {
        $error = "Data peminjaman tidak valid.";
    } elseif ($tanggal_pinjam < date('Y-m-d')) {
        $error = "Tanggal pinjam tidak boleh sebelum hari ini.";
    } elseif ($tanggal_kembali < $tanggal_pinjam) {
        $error = "Tanggal kembali tidak boleh sebelum tanggal pinjam.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            /*
             * Periksa barang dan stok terbaru.
             * FOR UPDATE mencegah pemeriksaan stok bersamaan
             * saat transaksi lain sedang mengubah data barang.
             */
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_barang, nama_barang, jumlah, jumlah_baik
                 FROM barang
                 WHERE id_barang = ?
                 FOR UPDATE"
            );

            if (!$stmt) {
                throw new Exception(
                    "Gagal menyiapkan pemeriksaan barang."
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_barang
            );

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                throw new Exception(
                    "Gagal memeriksa stok barang."
                );
            }

            $result = mysqli_stmt_get_result($stmt);
            $barang = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$barang) {
                throw new Exception(
                    "Barang tidak ditemukan."
                );
            }

            $stokBaik = (int) $barang['jumlah_baik'];
            $stokTotal = (int) $barang['jumlah'];

            if ($stokBaik < 0 || $stokTotal < 0) {
                throw new Exception(
                    "Data stok barang tidak valid."
                );
            }

            if ($stokBaik > $stokTotal) {
                throw new Exception(
                    "Data stok barang tidak konsisten. Hubungi pengelola sarpras."
                );
            }

            if ($stokBaik < $jumlah) {
                throw new Exception(
                    "Stok baik barang '" .
                    $barang['nama_barang'] .
                    "' tidak mencukupi. Stok baik tersedia: " .
                    $stokBaik . " unit."
                );
            }

            /*
             * Simpan pengajuan.
             * Status awal adalah Menunggu.
             */
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO peminjaman
                 (id_pengguna, tanggal_pinjam, tanggal_kembali, status)
                 VALUES (?, ?, ?, 'Menunggu')"
            );

            if (!$stmt) {
                throw new Exception(
                    "Gagal menyiapkan data peminjaman."
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "iss",
                $id_pengguna,
                $tanggal_pinjam,
                $tanggal_kembali
            );

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                throw new Exception(
                    "Gagal menyimpan pengajuan peminjaman."
                );
            }

            $id_peminjaman = mysqli_insert_id($conn);

            mysqli_stmt_close($stmt);

            /*
             * Simpan detail barang yang diajukan.
             */
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO detail_peminjaman
                 (id_peminjaman, id_barang, jumlah)
                 VALUES (?, ?, ?)"
            );

            if (!$stmt) {
                throw new Exception(
                    "Gagal menyiapkan detail peminjaman."
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "iii",
                $id_peminjaman,
                $id_barang,
                $jumlah
            );

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                throw new Exception(
                    "Gagal menyimpan detail peminjaman."
                );
            }

            mysqli_stmt_close($stmt);

            // Selesaikan transaksi jika semuanya berhasil.
            mysqli_commit($conn);

            $berhasil =
                "Pengajuan berhasil dikirim dan menunggu persetujuan pengelola sarpras.";

            // Kosongkan formulir setelah berhasil.
            $_POST = [];

        } catch (Throwable $e) {
            mysqli_rollback($conn);

            $error = $e->getMessage();
        }
    }
}

/* =========================================
   3. AMBIL DAFTAR BARANG
========================================= */
$daftar_barang = mysqli_query(
    $conn,
    "SELECT id_barang, kode_inventaris, nama_barang, jumlah_baik
     FROM barang
     WHERE jumlah_baik > 0
     ORDER BY nama_barang ASC"
);

if (!$daftar_barang) {
    die("Gagal mengambil daftar barang: " .
        mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Ajukan Peminjaman | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 27px;
        }

        .page-header p {
            margin: 5px 0 0;
            color: var(--text-muted);
        }

        .form-card {
            max-width: 850px;
        }

        .form-intro {
            margin: 0 0 24px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 25px;
        }

        .catatan {
            margin-top: 22px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-2);
            color: var(--text-muted);
            font-size: 12px;
        }

        .catatan strong {
            color: #6ee7b7;
        }

        .alert {
            overflow-wrap: anywhere;
        }

        @media (max-width: 600px) {
            .page-header h1 {
                font-size: 23px;
            }

            .form-card {
                padding: 17px;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<div class="main-content">

    <div class="page-header">
        <div>
            <p class="eyebrow">INVENTARIS SEKOLAH</p>

            <h1>Ajukan Peminjaman Barang</h1>

            <p>SMK Negeri 2 Padang</p>
        </div>

        <a href="index.php" class="btn btn-secondary">
            Kembali ke Dashboard
        </a>
    </div>

    <div class="form-card">

        <p class="form-intro">
            Silakan isi formulir berikut untuk mengajukan
            peminjaman barang inventaris sekolah.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error">
                <?= aman($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($berhasil !== ''): ?>
            <div class="alert alert-success">
                <?= aman($berhasil); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="id_barang">Pilih Barang</label>

                <select
                    name="id_barang"
                    id="id_barang"
                    class="form-control"
                    required
                >
                    <option value="">-- Pilih Barang --</option>

                    <?php while (
                        $barang = mysqli_fetch_assoc($daftar_barang)
                    ): ?>
                        <option
                            value="<?= (int) $barang['id_barang']; ?>"
                            <?= (
                                isset($_POST['id_barang']) &&
                                (string) $_POST['id_barang'] ===
                                (string) $barang['id_barang']
                            ) ? 'selected' : ''; ?>
                        >
                            <?= aman($barang['kode_inventaris']); ?>
                            -
                            <?= aman($barang['nama_barang']); ?>
                            (Stok baik:
                            <?= (int) $barang['jumlah_baik']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="jumlah">Jumlah yang Dipinjam</label>

                <input
                    type="number"
                    name="jumlah"
                    id="jumlah"
                    class="form-control"
                    min="1"
                    value="<?= aman($_POST['jumlah'] ?? '1'); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="tanggal_pinjam">Tanggal Pinjam</label>

                <input
                    type="date"
                    name="tanggal_pinjam"
                    id="tanggal_pinjam"
                    class="form-control"
                    min="<?= date('Y-m-d'); ?>"
                    value="<?= aman($_POST['tanggal_pinjam'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="tanggal_kembali">Tanggal Kembali</label>

                <input
                    type="date"
                    name="tanggal_kembali"
                    id="tanggal_kembali"
                    class="form-control"
                    min="<?= date('Y-m-d'); ?>"
                    value="<?= aman($_POST['tanggal_kembali'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Kirim Pengajuan
                </button>

                <a href="index.php" class="btn btn-secondary">
                    Batal
                </a>
            </div>

        </form>

        <div class="catatan">
            <strong>Catatan:</strong>
            Pengajuan berstatus Menunggu sampai disetujui pengelola
            sarana prasarana. Stok tidak dikurangi ketika pengajuan
            dibuat; pengurangan stok perlu dilakukan saat pengajuan
            disetujui.
        </div>

    </div>
</div>

<script>
const tanggalPinjam =
    document.getElementById('tanggal_pinjam');

const tanggalKembali =
    document.getElementById('tanggal_kembali');

const tanggalHariIni = '<?= date('Y-m-d'); ?>';

tanggalPinjam.addEventListener('change', function () {
    tanggalKembali.min = this.value || tanggalHariIni;

    if (
        tanggalKembali.value &&
        tanggalKembali.value < tanggalKembali.min
    ) {
        tanggalKembali.value = tanggalKembali.min;
    }
});
</script>

</body>
</html>
