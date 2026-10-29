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

function e($nilai)
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

// Ambil kategori dan lokasi.
$kategori = mysqli_query(
    $conn,
    "SELECT id_kategori, nama_kategori
     FROM kategori
     ORDER BY nama_kategori"
);

if (!$kategori) {
    die("Gagal mengambil data kategori.");
}

$daftarKategori = mysqli_fetch_all($kategori, MYSQLI_ASSOC);

$lokasi = mysqli_query(
    $conn,
    "SELECT id_lokasi, nama_lokasi
     FROM lokasi
     ORDER BY nama_lokasi"
);

if (!$lokasi) {
    die("Gagal mengambil data lokasi.");
}

$daftarLokasi = mysqli_fetch_all($lokasi, MYSQLI_ASSOC);

// Nilai awal formulir.
$kode = trim($_POST['kode_inventaris'] ?? '');
$nama = trim($_POST['nama_barang'] ?? '');
$id_kategori = $_POST['id_kategori'] ?? '';
$id_lokasi = $_POST['id_lokasi'] ?? '';
$jumlah = $_POST['jumlah'] ?? '1';
$kondisi = $_POST['kondisi'] ?? 'Baik';

// Proses penyimpanan.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $kondisiValid = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    $kategoriValid = filter_var($id_kategori, FILTER_VALIDATE_INT);
    $lokasiValid = filter_var($id_lokasi, FILTER_VALIDATE_INT);
    $jumlahValid = filter_var($jumlah, FILTER_VALIDATE_INT);

    if (
        $kode === '' ||
        $nama === '' ||
        strlen($kode) > 50 ||
        strlen($nama) > 150 ||
        $kategoriValid === false ||
        $kategoriValid === null ||
        $kategoriValid < 1 ||
        $lokasiValid === false ||
        $lokasiValid === null ||
        $lokasiValid < 1 ||
        $jumlahValid === false ||
        $jumlahValid === null ||
        $jumlahValid < 1 ||
        !in_array($kondisi, $kondisiValid, true)
    ) {
        $pesan = "Mohon isi semua data dengan benar. Jumlah barang minimal 1 unit.";
    } else {
        $id_kategori = (int) $kategoriValid;
        $id_lokasi = (int) $lokasiValid;
        $jumlah = (int) $jumlahValid;

        mysqli_begin_transaction($conn);

        try {
            // Pastikan kategori tersedia.
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_kategori
                 FROM kategori
                 WHERE id_kategori = ?"
            );

            if (!$stmt) {
                throw new Exception("Gagal memeriksa kategori.");
            }

            mysqli_stmt_bind_param($stmt, "i", $id_kategori);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            $kategoriAda = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            if (!$kategoriAda) {
                throw new Exception("Kategori yang dipilih tidak tersedia.");
            }

            // Pastikan lokasi tersedia.
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_lokasi
                 FROM lokasi
                 WHERE id_lokasi = ?"
            );

            if (!$stmt) {
                throw new Exception("Gagal memeriksa lokasi.");
            }

            mysqli_stmt_bind_param($stmt, "i", $id_lokasi);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            $lokasiAda = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            if (!$lokasiAda) {
                throw new Exception("Lokasi yang dipilih tidak tersedia.");
            }

            // Periksa kode inventaris.
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_barang
                 FROM barang
                 WHERE kode_inventaris = ?"
            );

            if (!$stmt) {
                throw new Exception("Gagal memeriksa kode inventaris.");
            }

            mysqli_stmt_bind_param($stmt, "s", $kode);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            $kodeAda = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            if ($kodeAda) {
                throw new Exception("Kode inventaris sudah digunakan.");
            }

            // Tentukan jumlah setiap kondisi berdasarkan pilihan.
            $jumlahBaik = 0;
            $jumlahRusakRingan = 0;
            $jumlahRusakBerat = 0;

            if ($kondisi === 'Baik') {
                $jumlahBaik = $jumlah;
            } elseif ($kondisi === 'Rusak Ringan') {
                $jumlahRusakRingan = $jumlah;
            } else {
                $jumlahRusakBerat = $jumlah;
            }

            // Simpan jumlah total dan rincian kondisi sekaligus.
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO barang (
                    kode_inventaris,
                    nama_barang,
                    id_kategori,
                    id_lokasi,
                    jumlah,
                    kondisi,
                    jumlah_baik,
                    jumlah_rusak_ringan,
                    jumlah_rusak_berat
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$stmt) {
                throw new Exception("Gagal menyiapkan penyimpanan barang.");
            }

            mysqli_stmt_bind_param(
                $stmt,
                "ssiiisiii",
                $kode,
                $nama,
                $id_kategori,
                $id_lokasi,
                $jumlah,
                $kondisi,
                $jumlahBaik,
                $jumlahRusakRingan,
                $jumlahRusakBerat
            );

            if (!mysqli_stmt_execute($stmt)) {
                $kodeDuplikat = mysqli_stmt_errno($stmt) === 1062;
                mysqli_stmt_close($stmt);

                if ($kodeDuplikat) {
                    throw new Exception("Kode inventaris sudah digunakan.");
                }

                throw new Exception("Gagal menyimpan barang.");
            }

            mysqli_stmt_close($stmt);

            mysqli_commit($conn);

            header("Location: index.php?status=berhasil");
            exit;

        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            $pesan = $ex->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Barang | Inventaris SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .tambah-barang-wrapper {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }

        .tambah-barang-card {
            width: 100%;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .tambah-card-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
        }

        .tambah-card-header h2 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: 20px;
        }

        .tambah-card-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .tambah-card-body {
            padding: 28px;
        }

        .tambah-alert {
            margin-bottom: 22px;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
        }

        .tambah-alert.error {
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .tambah-alert.info {
            color: #fcd34d;
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.25);
        }

        .tambah-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px 24px;
        }

        .tambah-form-group {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .tambah-form-group label {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .tambah-form-group input,
        .tambah-form-group select {
            width: 100%;
            min-width: 0;
            min-height: 45px;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-2);
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            box-sizing: border-box;
        }

        .tambah-form-group select option {
            background: var(--surface);
            color: var(--text);
        }

        .tambah-form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }

        .tambah-form-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 43px;
            text-decoration: none;
        }

        @media (max-width: 650px) {
            .tambah-card-header,
            .tambah-card-body {
                padding: 20px;
            }

            .tambah-form-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 19px;
            }

            .tambah-form-actions {
                flex-direction: column;
            }

            .tambah-form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<div class="main-content">

    <div class="page-header">
        <div>
            <div class="eyebrow">MANAJEMEN INVENTARIS</div>
            <h1>Tambah Barang Inventaris</h1>
            <p>SMK Negeri 2 Padang</p>
        </div>
    </div>

    <div class="tambah-barang-wrapper">
        <div class="tambah-barang-card">

            <div class="tambah-card-header">
                <h2>Formulir Barang Baru</h2>
                <p>Lengkapi informasi barang yang akan dicatat.</p>
            </div>

            <div class="tambah-card-body">

                <?php if ($pesan !== ''): ?>
                    <div class="tambah-alert error">
                        <?= e($pesan); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($daftarKategori) || empty($daftarLokasi)): ?>
                    <div class="tambah-alert info">
                        Tambahkan data kategori dan lokasi terlebih dahulu
                        sebelum memasukkan barang.
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="tambah-form-grid">

                        <div class="tambah-form-group">
                            <label for="kode_inventaris">Kode Inventaris</label>
                            <input
                                type="text"
                                id="kode_inventaris"
                                name="kode_inventaris"
                                maxlength="50"
                                value="<?= e($kode); ?>"
                                placeholder="Contoh: INV-001"
                                required
                            >
                        </div>

                        <div class="tambah-form-group">
                            <label for="nama_barang">Nama Barang</label>
                            <input
                                type="text"
                                id="nama_barang"
                                name="nama_barang"
                                maxlength="150"
                                value="<?= e($nama); ?>"
                                placeholder="Masukkan nama barang"
                                required
                            >
                        </div>

                        <div class="tambah-form-group">
                            <label for="id_kategori">Kategori Barang</label>
                            <select id="id_kategori" name="id_kategori" required>
                                <option value="">-- Pilih Kategori --</option>

                                <?php foreach ($daftarKategori as $k): ?>
                                    <option
                                        value="<?= (int) $k['id_kategori']; ?>"
                                        <?= (string) $id_kategori === (string) $k['id_kategori'] ? 'selected' : ''; ?>
                                    >
                                        <?= e($k['nama_kategori']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="tambah-form-group">
                            <label for="id_lokasi">Lokasi Barang</label>
                            <select id="id_lokasi" name="id_lokasi" required>
                                <option value="">-- Pilih Lokasi --</option>

                                <?php foreach ($daftarLokasi as $l): ?>
                                    <option
                                        value="<?= (int) $l['id_lokasi']; ?>"
                                        <?= (string) $id_lokasi === (string) $l['id_lokasi'] ? 'selected' : ''; ?>
                                    >
                                        <?= e($l['nama_lokasi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="tambah-form-group">
                            <label for="jumlah">Jumlah Barang</label>
                            <input
                                type="number"
                                id="jumlah"
                                name="jumlah"
                                min="1"
                                value="<?= e($jumlah); ?>"
                                required
                            >
                        </div>

                        <div class="tambah-form-group">
                            <label for="kondisi">Kondisi Barang</label>
                            <select id="kondisi" name="kondisi" required>
                                <?php foreach (
                                    ['Baik', 'Rusak Ringan', 'Rusak Berat']
                                    as $pilihan
                                ): ?>
                                    <option
                                        value="<?= e($pilihan); ?>"
                                        <?= $kondisi === $pilihan ? 'selected' : ''; ?>
                                    >
                                        <?= e($pilihan); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="tambah-form-actions">
                        <button
                            type="submit"
                            name="simpan"
                            class="btn btn-primary"
                            <?= empty($daftarKategori) || empty($daftarLokasi) ? 'disabled' : ''; ?>
                        >
                            Simpan Barang
                        </button>

                        <a href="index.php" class="btn btn-secondary">
                            Batal
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>

</div>
</body>
</html>