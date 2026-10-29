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

if (
    !isset($_GET['id']) ||
    !ctype_digit((string) $_GET['id']) ||
    (int) $_GET['id'] < 1
) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];
$pesan = "";

function aman($nilai)
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

function ambilBarang($conn, $id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM barang WHERE id_barang = ?"
    );

    if (!$stmt) {
        throw new Exception("Gagal mengambil data barang.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $data;
}

$barang = ambilBarang($conn, $id);

if (!$barang) {
    header("Location: index.php");
    exit;
}

$kode = $barang['kode_inventaris'];
$nama = $barang['nama_barang'];
$kategori = (string) $barang['id_kategori'];
$lokasi = (string) $barang['id_lokasi'];

$jumlahBaik = (string) $barang['jumlah_baik'];
$jumlahRusakRingan = (string) $barang['jumlah_rusak_ringan'];
$jumlahRusakBerat = (string) $barang['jumlah_rusak_berat'];

$keterangan = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = trim($_POST['kode_inventaris'] ?? '');
    $nama = trim($_POST['nama_barang'] ?? '');
    $kategori = (string) ($_POST['id_kategori'] ?? '');
    $lokasi = (string) ($_POST['id_lokasi'] ?? '');

    $jumlahBaik = (string) ($_POST['jumlah_baik'] ?? '');
    $jumlahRusakRingan = (string) ($_POST['jumlah_rusak_ringan'] ?? '');
    $jumlahRusakBerat = (string) ($_POST['jumlah_rusak_berat'] ?? '');

    $keterangan = trim($_POST['keterangan'] ?? '');

    if (
        $kode === '' ||
        strlen($kode) > 50 ||
        $nama === '' ||
        strlen($nama) > 150 ||
        !ctype_digit($kategori) ||
        (int) $kategori < 1 ||
        !ctype_digit($lokasi) ||
        (int) $lokasi < 1 ||
        !ctype_digit($jumlahBaik) ||
        !ctype_digit($jumlahRusakRingan) ||
        !ctype_digit($jumlahRusakBerat)
    ) {
        $pesan = "Periksa kembali data yang kamu masukkan.";
    } else {
        $kategori = (int) $kategori;
        $lokasi = (int) $lokasi;
        $jumlahBaik = (int) $jumlahBaik;
        $jumlahRusakRingan = (int) $jumlahRusakRingan;
        $jumlahRusakBerat = (int) $jumlahRusakBerat;

        $jumlah = $jumlahBaik + $jumlahRusakRingan + $jumlahRusakBerat;

        if ($jumlah > 2147483647) {
            $pesan = "Jumlah barang terlalu besar.";
        } else {
            mysqli_begin_transaction($conn);

            try {
                // Kunci dan ambil data terbaru agar stok tidak tertimpa
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT kondisi
                     FROM barang
                     WHERE id_barang = ?
                     FOR UPDATE"
                );

                if (!$stmt) {
                    throw new Exception("Gagal memeriksa data barang.");
                }

                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $hasil = mysqli_stmt_get_result($stmt);
                $dataLama = mysqli_fetch_assoc($hasil);
                mysqli_stmt_close($stmt);

                if (!$dataLama) {
                    throw new Exception("Data barang tidak ditemukan.");
                }

                // Periksa kategori
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id_kategori FROM kategori WHERE id_kategori = ?"
                );

                if (!$stmt) {
                    throw new Exception("Gagal memeriksa kategori.");
                }

                mysqli_stmt_bind_param($stmt, "i", $kategori);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                $kategoriAda = mysqli_stmt_num_rows($stmt) > 0;
                mysqli_stmt_close($stmt);

                // Periksa lokasi
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id_lokasi FROM lokasi WHERE id_lokasi = ?"
                );

                if (!$stmt) {
                    throw new Exception("Gagal memeriksa lokasi.");
                }

                mysqli_stmt_bind_param($stmt, "i", $lokasi);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                $lokasiAda = mysqli_stmt_num_rows($stmt) > 0;
                mysqli_stmt_close($stmt);

                if (!$kategoriAda || !$lokasiAda) {
                    throw new Exception(
                        "Kategori atau lokasi yang dipilih tidak tersedia."
                    );
                }

                // Tentukan kondisi ringkasan berdasarkan stok yang tersedia
                if ($jumlahRusakBerat > 0) {
                    $kondisi = 'Rusak Berat';
                } elseif ($jumlahRusakRingan > 0) {
                    $kondisi = 'Rusak Ringan';
                } elseif ($jumlahBaik > 0) {
                    $kondisi = 'Baik';
                } else {
                    $kondisi = $dataLama['kondisi'];
                }

                // Perbarui data dan rincian stok
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE barang
                     SET kode_inventaris = ?,
                         nama_barang = ?,
                         id_kategori = ?,
                         id_lokasi = ?,
                         jumlah = ?,
                         kondisi = ?,
                         jumlah_baik = ?,
                         jumlah_rusak_ringan = ?,
                         jumlah_rusak_berat = ?
                     WHERE id_barang = ?"
                );

                if (!$stmt) {
                    throw new Exception("Gagal menyiapkan perubahan barang.");
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssiiisiiii",
                    $kode,
                    $nama,
                    $kategori,
                    $lokasi,
                    $jumlah,
                    $kondisi,
                    $jumlahBaik,
                    $jumlahRusakRingan,
                    $jumlahRusakBerat,
                    $id
                );

                if (!mysqli_stmt_execute($stmt)) {
                    $errorSql = mysqli_stmt_errno($stmt);
                    mysqli_stmt_close($stmt);

                    if ($errorSql === 1062) {
                        throw new Exception(
                            "Kode inventaris sudah digunakan barang lain."
                        );
                    }

                    throw new Exception("Gagal memperbarui data barang.");
                }

                mysqli_stmt_close($stmt);

                // Catat perubahan kondisi ke riwayat
                if ($dataLama['kondisi'] !== $kondisi) {
                    $idPengguna = (int) $_SESSION['id_pengguna'];
                    $kondisiLama = $dataLama['kondisi'];

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO riwayat_barang
                        (
                            id_barang,
                            id_pengguna,
                            kondisi_sebelumnya,
                            kondisi_baru,
                            keterangan
                        )
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    if (!$stmt) {
                        throw new Exception(
                            "Gagal menyiapkan riwayat perubahan kondisi."
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iisss",
                        $id,
                        $idPengguna,
                        $kondisiLama,
                        $kondisi,
                        $keterangan
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        throw new Exception(
                            "Gagal menyimpan riwayat perubahan kondisi."
                        );
                    }

                    mysqli_stmt_close($stmt);
                }

                mysqli_commit($conn);

                header("Location: index.php?status=terubah");
                exit;

            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $pesan = $e->getMessage();
            }
        }
    }
}

// Ambil daftar kategori
$kategori_query = mysqli_query(
    $conn,
    "SELECT id_kategori, nama_kategori
     FROM kategori
     ORDER BY nama_kategori"
);

if (!$kategori_query) {
    die("Gagal mengambil kategori.");
}

$daftarKategori = [];

while ($k = mysqli_fetch_assoc($kategori_query)) {
    $daftarKategori[] = $k;
}

// Ambil daftar lokasi
$lokasi_query = mysqli_query(
    $conn,
    "SELECT id_lokasi, nama_lokasi
     FROM lokasi
     ORDER BY nama_lokasi"
);

if (!$lokasi_query) {
    die("Gagal mengambil lokasi.");
}

$daftarLokasi = [];

while ($l = mysqli_fetch_assoc($lokasi_query)) {
    $daftarLokasi[] = $l;
}

$jumlahTampil =
    (int) $jumlahBaik +
    (int) $jumlahRusakRingan +
    (int) $jumlahRusakBerat;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Barang | Inventaris SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .edit-barang-wrapper {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }

        .edit-barang-card {
            width: 100%;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .edit-card-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
        }

        .edit-card-header h2 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: 20px;
        }

        .edit-card-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .edit-card-body {
            padding: 28px;
        }

        .edit-alert {
            margin-bottom: 24px;
            padding: 14px 16px;
            border: 1px solid rgba(239, 68, 68, .3);
            border-radius: 10px;
            background: rgba(239, 68, 68, .12);
            color: #fca5a5;
            font-size: 14px;
            line-height: 1.6;
        }

        .edit-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px 24px;
        }

        .edit-form-group {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .edit-form-group.full-width {
            grid-column: 1 / -1;
        }

        .edit-form-group label {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .edit-form-group input,
        .edit-form-group select,
        .edit-form-group textarea {
            width: 100%;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-2);
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            box-sizing: border-box;
        }

        .edit-form-group input,
        .edit-form-group select {
            min-height: 45px;
        }

        .edit-form-group textarea {
            min-height: 110px;
            resize: vertical;
        }

        .edit-form-group small {
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .edit-form-group input:focus,
        .edit-form-group select:focus,
        .edit-form-group textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, .12);
        }

        .edit-form-group select option {
            background: var(--surface);
            color: var(--text);
        }

        .jumlah-total {
            font-weight: 700;
        }

        .edit-form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }

        .edit-form-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 43px;
            text-decoration: none;
        }

        @media (max-width: 650px) {
            .edit-card-header,
            .edit-card-body {
                padding: 20px;
            }

            .edit-form-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 19px;
            }

            .edit-form-group.full-width {
                grid-column: auto;
            }

            .edit-form-actions {
                flex-direction: column;
            }

            .edit-form-actions .btn {
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
            <h1>Edit Data Barang</h1>
            <p>Perbarui informasi inventaris SMK Negeri 2 Padang.</p>
        </div>
    </div>

    <div class="edit-barang-wrapper">
        <div class="edit-barang-card">

            <div class="edit-card-header">
                <h2>Formulir Edit Barang</h2>
                <p>
                    Sesuaikan jumlah barang berdasarkan kondisi sebenarnya.
                </p>
            </div>

            <div class="edit-card-body">

                <?php if ($pesan !== ''): ?>
                    <div class="edit-alert">
                        <?= aman($pesan); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="edit-form-grid">

                        <div class="edit-form-group">
                            <label for="kode_inventaris">Kode Inventaris</label>
                            <input
                                type="text"
                                id="kode_inventaris"
                                name="kode_inventaris"
                                maxlength="50"
                                value="<?= aman($kode); ?>"
                                required
                            >
                        </div>

                        <div class="edit-form-group">
                            <label for="nama_barang">Nama Barang</label>
                            <input
                                type="text"
                                id="nama_barang"
                                name="nama_barang"
                                maxlength="150"
                                value="<?= aman($nama); ?>"
                                required
                            >
                        </div>

                        <div class="edit-form-group">
                            <label for="id_kategori">Kategori</label>
                            <select id="id_kategori" name="id_kategori" required>
                                <option value="">-- Pilih Kategori --</option>

                                <?php foreach ($daftarKategori as $k): ?>
                                    <option
                                        value="<?= (int) $k['id_kategori']; ?>"
                                        <?= (string) $k['id_kategori'] === (string) $kategori
                                            ? 'selected' : ''; ?>
                                    >
                                        <?= aman($k['nama_kategori']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="edit-form-group">
                            <label for="id_lokasi">Lokasi</label>
                            <select id="id_lokasi" name="id_lokasi" required>
                                <option value="">-- Pilih Lokasi --</option>

                                <?php foreach ($daftarLokasi as $l): ?>
                                    <option
                                        value="<?= (int) $l['id_lokasi']; ?>"
                                        <?= (string) $l['id_lokasi'] === (string) $lokasi
                                            ? 'selected' : ''; ?>
                                    >
                                        <?= aman($l['nama_lokasi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="edit-form-group">
                            <label for="jumlah_baik">Jumlah Kondisi Baik</label>
                            <input
                                type="number"
                                id="jumlah_baik"
                                name="jumlah_baik"
                                min="0"
                                max="2147483647"
                                value="<?= aman($jumlahBaik); ?>"
                                required
                            >
                        </div>

                        <div class="edit-form-group">
                            <label for="jumlah_rusak_ringan">
                                Jumlah Rusak Ringan
                            </label>
                            <input
                                type="number"
                                id="jumlah_rusak_ringan"
                                name="jumlah_rusak_ringan"
                                min="0"
                                max="2147483647"
                                value="<?= aman($jumlahRusakRingan); ?>"
                                required
                            >
                        </div>

                        <div class="edit-form-group">
                            <label for="jumlah_rusak_berat">
                                Jumlah Rusak Berat
                            </label>
                            <input
                                type="number"
                                id="jumlah_rusak_berat"
                                name="jumlah_rusak_berat"
                                min="0"
                                max="2147483647"
                                value="<?= aman($jumlahRusakBerat); ?>"
                                required
                            >
                        </div>

                        <div class="edit-form-group">
                            <label for="jumlah_total">Total Jumlah Barang</label>
                            <input
                                type="number"
                                id="jumlah_total"
                                class="jumlah-total"
                                value="<?= $jumlahTampil; ?>"
                                readonly
                            >
                            <small>
                                Total dihitung otomatis dari ketiga kondisi.
                            </small>
                        </div>

                        <div class="edit-form-group full-width">
                            <label for="keterangan">
                                Keterangan Perubahan Kondisi
                            </label>

                            <textarea
                                id="keterangan"
                                name="keterangan"
                                rows="4"
                                placeholder="Jelaskan perubahan kondisi jika ada."
                            ><?= aman($keterangan); ?></textarea>

                            <small>
                                Keterangan disimpan ke riwayat jika kondisi
                                ringkasan barang berubah.
                            </small>
                        </div>

                    </div>

                    <div class="edit-form-actions">
                        <button type="submit" class="btn btn-primary">
                            Simpan Perubahan
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

<script>
const inputBaik = document.getElementById('jumlah_baik');
const inputRingan = document.getElementById('jumlah_rusak_ringan');
const inputBerat = document.getElementById('jumlah_rusak_berat');
const inputTotal = document.getElementById('jumlah_total');

function hitungTotal() {
    const baik = Math.max(0, Number(inputBaik.value) || 0);
    const ringan = Math.max(0, Number(inputRingan.value) || 0);
    const berat = Math.max(0, Number(inputBerat.value) || 0);

    inputTotal.value = baik + ringan + berat;
}

inputBaik.addEventListener('input', hitungTotal);
inputRingan.addEventListener('input', hitungTotal);
inputBerat.addEventListener('input', hitungTotal);
</script>

</body>
</html>
