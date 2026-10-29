<?php
session_start();
require_once "../koneksi.php";

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== "pengelola_sarpras"
) {
    header("Location: ../auth/login.php");
    exit;
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function kelasStatus($status)
{
    switch ($status) {
        case 'Menunggu':
            return 'warning';
        case 'Disetujui':
            return 'success';
        case 'Ditolak':
            return 'danger';
        case 'Dikembalikan':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function ambilPeminjaman($conn, $id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT p.*, u.nama AS nama_pengguna
         FROM peminjaman p
         JOIN pengguna u ON p.id_pengguna = u.id_pengguna
         WHERE p.id_peminjaman = ?"
    );

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $data;
}

$id = $_GET['id'] ?? '';

if (!ctype_digit((string)$id) || (int)$id <= 0) {
    header("Location: index.php");
    exit;
}

$id = (int)$id;
$pesan = '';
$jenisPesan = 'danger';

$peminjaman = ambilPeminjaman($conn, $id);

if (!$peminjaman) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statusBaru = $_POST['status'] ?? '';
    $statusValid = ['Menunggu', 'Disetujui', 'Ditolak'];

    if (!in_array($statusBaru, $statusValid, true)) {
        $pesan = "Status yang dipilih tidak valid.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            // Kunci peminjaman agar tidak diproses bersamaan.
            $stmt = mysqli_prepare(
                $conn,
                "SELECT status
                 FROM peminjaman
                 WHERE id_peminjaman = ?
                 FOR UPDATE"
            );

            if (!$stmt) {
                throw new Exception("Gagal memeriksa status peminjaman.");
            }

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $dataLama = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$dataLama) {
                throw new Exception("Data peminjaman tidak ditemukan.");
            }

            $statusLama = $dataLama['status'];

            if (in_array($statusLama, ['Ditolak', 'Dikembalikan'], true)) {
                throw new Exception(
                    "Peminjaman berstatus {$statusLama} tidak dapat diubah."
                );
            }

            if ($statusLama === $statusBaru) {
                throw new Exception("Status tidak mengalami perubahan.");
            }

            if (
                $statusLama === 'Disetujui' &&
                $statusBaru === 'Menunggu'
            ) {
                throw new Exception(
                    "Peminjaman yang sudah disetujui tidak dapat diubah kembali menjadi Menunggu."
                );
            }

            // Ambil semua detail barang.
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_barang, jumlah
                 FROM detail_peminjaman
                 WHERE id_peminjaman = ?
                 ORDER BY id_barang
                 FOR UPDATE"
            );

            if (!$stmt) {
                throw new Exception("Gagal mengambil detail peminjaman.");
            }

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $detailBarang = [];

            while ($row = mysqli_fetch_assoc($result)) {
                $detailBarang[] = $row;
            }

            mysqli_stmt_close($stmt);

            if (!$detailBarang) {
                throw new Exception(
                    "Detail barang peminjaman tidak ditemukan."
                );
            }

            foreach ($detailBarang as $detail) {
                if ((int)$detail['id_barang'] <= 0 || (int)$detail['jumlah'] <= 0) {
                    throw new Exception(
                        "Data jumlah atau ID barang pada peminjaman tidak valid."
                    );
                }
            }

            /*
             * MENUNGGU -> DISETUJUI
             * Kurangi jumlah total dan jumlah barang baik.
             */
            if (
                $statusLama === 'Menunggu' &&
                $statusBaru === 'Disetujui'
            ) {
                foreach ($detailBarang as $detail) {
                    $idBarang = (int)$detail['id_barang'];
                    $jumlahPinjam = (int)$detail['jumlah'];

                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT nama_barang, jumlah, jumlah_baik,
                                jumlah_rusak_ringan, jumlah_rusak_berat
                         FROM barang
                         WHERE id_barang = ?
                         FOR UPDATE"
                    );

                    if (!$stmt) {
                        throw new Exception("Gagal memeriksa stok barang.");
                    }

                    mysqli_stmt_bind_param($stmt, "i", $idBarang);
                    mysqli_stmt_execute($stmt);

                    $resultBarang = mysqli_stmt_get_result($stmt);
                    $barang = mysqli_fetch_assoc($resultBarang);

                    mysqli_stmt_close($stmt);

                    if (!$barang) {
                        throw new Exception(
                            "Barang dengan ID {$idBarang} tidak ditemukan."
                        );
                    }

                    $stokTotal = (int)$barang['jumlah'];
                    $stokBaik = (int)$barang['jumlah_baik'];
                    $stokRingan = (int)$barang['jumlah_rusak_ringan'];
                    $stokBerat = (int)$barang['jumlah_rusak_berat'];

                    if (
                        $stokTotal < 0 ||
                        $stokBaik < 0 ||
                        $stokRingan < 0 ||
                        $stokBerat < 0 ||
                        $stokTotal !== ($stokBaik + $stokRingan + $stokBerat)
                    ) {
                        throw new Exception(
                            "Rincian stok barang '{$barang['nama_barang']}' tidak konsisten. Periksa data barang terlebih dahulu."
                        );
                    }

                    if ($stokBaik < $jumlahPinjam) {
                        throw new Exception(
                            "Stok baik barang '{$barang['nama_barang']}' tidak mencukupi. Stok baik tersedia: {$stokBaik} unit."
                        );
                    }

                    $jumlahBaru = $stokTotal - $jumlahPinjam;
                    $baikBaru = $stokBaik - $jumlahPinjam;

                    // Kondisi ringkasan berdasarkan kondisi stok yang tersisa.
                    if ($stokBerat > 0) {
                        $kondisiBaru = 'Rusak Berat';
                    } elseif ($stokRingan > 0) {
                        $kondisiBaru = 'Rusak Ringan';
                    } else {
                        $kondisiBaru = 'Baik';
                    }

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE barang
                         SET jumlah = ?,
                             jumlah_baik = ?,
                             kondisi = ?
                         WHERE id_barang = ?
                           AND jumlah = ?
                           AND jumlah_baik = ?"
                    );

                    if (!$stmt) {
                        throw new Exception("Gagal menyiapkan perubahan stok.");
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iisiii",
                        $jumlahBaru,
                        $baikBaru,
                        $kondisiBaru,
                        $idBarang,
                        $stokTotal,
                        $stokBaik
                    );

                    mysqli_stmt_execute($stmt);
                    $affected = mysqli_stmt_affected_rows($stmt);
                    mysqli_stmt_close($stmt);

                    if ($affected !== 1) {
                        throw new Exception(
                            "Gagal memperbarui stok barang '{$barang['nama_barang']}'."
                        );
                    }
                }
            }

            /*
             * DISETUJUI -> DITOLAK
             * Kembalikan stok yang sebelumnya dikurangi saat persetujuan.
             * Stok tersebut kembali sebagai barang baik karena barang belum
             * menjalani proses pengembalian.
             */
            if (
                $statusLama === 'Disetujui' &&
                $statusBaru === 'Ditolak'
            ) {
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id_pengembalian
                     FROM pengembalian
                     WHERE id_peminjaman = ?
                     LIMIT 1"
                );

                if (!$stmt) {
                    throw new Exception(
                        "Gagal memeriksa data pengembalian."
                    );
                }

                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                $resultKembali = mysqli_stmt_get_result($stmt);
                $dataKembali = mysqli_fetch_assoc($resultKembali);

                mysqli_stmt_close($stmt);

                if ($dataKembali) {
                    throw new Exception(
                        "Peminjaman sudah memiliki catatan pengembalian dan tidak dapat ditolak."
                    );
                }

                foreach ($detailBarang as $detail) {
                    $idBarang = (int)$detail['id_barang'];
                    $jumlahPinjam = (int)$detail['jumlah'];

                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT nama_barang, jumlah, jumlah_baik,
                                jumlah_rusak_ringan, jumlah_rusak_berat
                         FROM barang
                         WHERE id_barang = ?
                         FOR UPDATE"
                    );

                    if (!$stmt) {
                        throw new Exception("Gagal memeriksa stok barang.");
                    }

                    mysqli_stmt_bind_param($stmt, "i", $idBarang);
                    mysqli_stmt_execute($stmt);

                    $resultBarang = mysqli_stmt_get_result($stmt);
                    $barang = mysqli_fetch_assoc($resultBarang);

                    mysqli_stmt_close($stmt);

                    if (!$barang) {
                        throw new Exception(
                            "Barang dengan ID {$idBarang} tidak ditemukan."
                        );
                    }

                    $stokTotal = (int)$barang['jumlah'];
                    $stokBaik = (int)$barang['jumlah_baik'];
                    $stokRingan = (int)$barang['jumlah_rusak_ringan'];
                    $stokBerat = (int)$barang['jumlah_rusak_berat'];

                    if (
                        $stokTotal < 0 ||
                        $stokBaik < 0 ||
                        $stokRingan < 0 ||
                        $stokBerat < 0 ||
                        $stokTotal !== ($stokBaik + $stokRingan + $stokBerat)
                    ) {
                        throw new Exception(
                            "Rincian stok barang '{$barang['nama_barang']}' tidak konsisten."
                        );
                    }

                    $jumlahBaru = $stokTotal + $jumlahPinjam;
                    $baikBaru = $stokBaik + $jumlahPinjam;

                    if (
                        $jumlahBaru > 2147483647 ||
                        $baikBaru > 2147483647
                    ) {
                        throw new Exception("Jumlah stok terlalu besar.");
                    }

                    if ($stokBerat > 0) {
                        $kondisiBaru = 'Rusak Berat';
                    } elseif ($stokRingan > 0) {
                        $kondisiBaru = 'Rusak Ringan';
                    } else {
                        $kondisiBaru = 'Baik';
                    }

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE barang
                         SET jumlah = ?,
                             jumlah_baik = ?,
                             kondisi = ?
                         WHERE id_barang = ?"
                    );

                    if (!$stmt) {
                        throw new Exception("Gagal menyiapkan pengembalian stok.");
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iisi",
                        $jumlahBaru,
                        $baikBaru,
                        $kondisiBaru,
                        $idBarang
                    );

                    mysqli_stmt_execute($stmt);
                    $affected = mysqli_stmt_affected_rows($stmt);
                    mysqli_stmt_close($stmt);

                    if ($affected !== 1) {
                        throw new Exception(
                            "Gagal mengembalikan stok barang '{$barang['nama_barang']}'."
                        );
                    }
                }
            }

            // Simpan status terbaru.
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE peminjaman
                 SET status = ?
                 WHERE id_peminjaman = ?
                   AND status = ?"
            );

            if (!$stmt) {
                throw new Exception("Gagal menyiapkan perubahan status.");
            }

            mysqli_stmt_bind_param(
                $stmt,
                "sis",
                $statusBaru,
                $id,
                $statusLama
            );

            mysqli_stmt_execute($stmt);
            $affectedStatus = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affectedStatus !== 1) {
                throw new Exception(
                    "Gagal menyimpan perubahan status peminjaman."
                );
            }

            mysqli_commit($conn);

            header("Location: index.php?pesan=status_berhasil");
            exit;

        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            error_log("Edit peminjaman gagal: " . $ex->getMessage());
            $pesan = $ex->getMessage();
        }
    }

    $peminjaman = ambilPeminjaman($conn, $id);

    if (!$peminjaman) {
        header("Location: index.php");
        exit;
    }
}

$statusSekarang = $peminjaman['status'];

$bolehEdit = in_array(
    $statusSekarang,
    ['Menunggu', 'Disetujui'],
    true
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Status Peminjaman - Inventaris SMKN 2 Padang</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <div class="mb-4">
                <a href="index.php" class="btn btn-outline-secondary">
                    &larr; Kembali
                </a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">

                    <h3 class="mb-1">Edit Status Peminjaman</h3>

                    <p class="text-muted mb-4">
                        Inventaris Barang SMK Negeri 2 Padang
                    </p>

                    <?php if ($pesan !== ''): ?>
                        <div class="alert alert-<?= e($jenisPesan) ?>">
                            <?= e($pesan) ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <div class="mb-2">
                            <strong>ID Peminjaman:</strong>
                            <?= e($peminjaman['id_peminjaman']) ?>
                        </div>

                        <div class="mb-2">
                            <strong>Nama Peminjam:</strong>
                            <?= e($peminjaman['nama_pengguna']) ?>
                        </div>

                        <div class="mb-2">
                            <strong>Tanggal Pinjam:</strong>
                            <?= e($peminjaman['tanggal_pinjam']) ?>
                        </div>

                        <div class="mb-2">
                            <strong>Tanggal Kembali:</strong>
                            <?= e($peminjaman['tanggal_kembali']) ?>
                        </div>

                        <div class="mb-2">
                            <strong>Status Saat Ini:</strong>
                            <span class="badge bg-<?= e(kelasStatus($statusSekarang)) ?>">
                                <?= e($statusSekarang) ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($bolehEdit): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    Pilih Status Baru
                                </label>

                                <select
                                    name="status"
                                    id="status"
                                    class="form-select"
                                    required
                                >
                                    <option value="">
                                        -- Pilih Status --
                                    </option>

                                    <option
                                        value="Menunggu"
                                        <?= $statusSekarang === 'Menunggu' ? 'selected' : '' ?>
                                    >
                                        Menunggu
                                    </option>

                                    <option
                                        value="Disetujui"
                                        <?= $statusSekarang === 'Disetujui' ? 'selected' : '' ?>
                                    >
                                        Disetujui
                                    </option>

                                    <option value="Ditolak">
                                        Ditolak
                                    </option>
                                </select>
                            </div>

                            <div class="alert alert-info">
                                <strong>Informasi:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>
                                        Persetujuan mengurangi stok total dan stok baik.
                                    </li>
                                    <li>
                                        Barang hanya dapat disetujui jika jumlah baik mencukupi.
                                    </li>
                                    <li>
                                        Penolakan setelah persetujuan mengembalikan stok yang sebelumnya dikurangi.
                                    </li>
                                    <li>
                                        Status Ditolak dan Dikembalikan tidak dapat diedit lagi.
                                    </li>
                                </ul>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    Simpan Perubahan
                                </button>

                                <a href="index.php" class="btn btn-secondary">
                                    Batal
                                </a>
                            </div>
                        </form>

                    <?php else: ?>
                        <div class="alert alert-warning">
                            Status peminjaman ini tidak dapat diedit lagi.
                        </div>

                        <a href="index.php" class="btn btn-secondary">
                            Kembali
                        </a>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
