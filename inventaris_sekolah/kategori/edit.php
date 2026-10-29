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
$nama_lama = "";

$stmt = mysqli_prepare(
    $conn,
    "SELECT nama_kategori
     FROM kategori
     WHERE id_kategori = ?"
);

if (!$stmt) {
    die("Gagal menyiapkan query kategori.");
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $nama_lama);

if (!mysqli_stmt_fetch($stmt)) {
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $nama_kategori = trim($_POST['nama_kategori'] ?? "");

    if ($nama_kategori === "") {
        $pesan = "Nama kategori wajib diisi.";
    } elseif (strlen($nama_kategori) > 100) {
        $pesan = "Nama kategori maksimal 100 karakter.";
        $nama_lama = $nama_kategori;
    } else {
        $nama_lama = $nama_kategori;

        $cek = mysqli_prepare(
            $conn,
            "SELECT id_kategori
             FROM kategori
             WHERE nama_kategori = ?
             AND id_kategori != ?"
        );

        if (!$cek) {
            $pesan = "Gagal memeriksa nama kategori.";
        } else {
            mysqli_stmt_bind_param($cek, "si", $nama_kategori, $id);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            $duplikat = mysqli_stmt_num_rows($cek) > 0;
            mysqli_stmt_close($cek);

            if ($duplikat) {
                $pesan = "Nama kategori sudah digunakan.";
            } else {
                $update = mysqli_prepare(
                    $conn,
                    "UPDATE kategori
                     SET nama_kategori = ?
                     WHERE id_kategori = ?"
                );

                if (!$update) {
                    $pesan = "Gagal menyiapkan perubahan kategori.";
                } else {
                    mysqli_stmt_bind_param($update, "si", $nama_kategori, $id);
                    $berhasil = mysqli_stmt_execute($update);
                    mysqli_stmt_close($update);

                    if ($berhasil) {
                        header("Location: index.php?status=berhasil");
                        exit;
                    } else {
                        $pesan = "Gagal mengubah kategori.";
                    }
                }
            }
        }
    }
}

function aman($teks) {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Kategori | Inventaris SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .edit-kategori-card {
            max-width: 680px;
            margin: 0 auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .edit-kategori-header {
            padding: 22px 26px;
            border-bottom: 1px solid var(--border);
        }

        .edit-kategori-header h3 {
            margin: 0 0 7px;
            color: var(--text);
            font-size: 19px;
        }

        .edit-kategori-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .edit-kategori-body {
            padding: 26px;
        }

        .edit-kategori-group {
            display: flex;
            flex-direction: column;
            gap: 9px;
            margin-bottom: 24px;
        }

        .edit-kategori-group label {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .edit-kategori-group input {
            box-sizing: border-box;
            width: 100%;
            min-height: 46px;
            padding: 12px 14px;
            color: var(--text);
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 9px;
            outline: none;
            font: inherit;
        }

        .edit-kategori-group input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.13);
        }

        .edit-kategori-help {
            color: var(--text-muted);
            font-size: 12px;
        }

        .edit-kategori-alert {
            padding: 13px 15px;
            margin-bottom: 22px;
            color: #fecaca;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 9px;
            font-size: 14px;
        }

        .edit-kategori-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .edit-kategori-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 18px;
            border-radius: 9px;
            text-decoration: none;
            cursor: pointer;
            font: inherit;
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .edit-kategori-body {
                padding: 20px;
            }

            .edit-kategori-header {
                padding: 20px;
            }

            .edit-kategori-actions {
                flex-direction: column;
            }

            .edit-kategori-actions .btn {
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <span class="eyebrow">DATA MASTER</span>
            <h1>Edit Kategori Barang</h1>
            <p>Perbarui informasi kategori inventaris SMK Negeri 2 Padang.</p>
        </div>
    </div>

    <div class="edit-kategori-card">

        <div class="edit-kategori-header">
            <h3>Form Edit Kategori</h3>
            <p>Ubah nama kategori pada kolom di bawah ini.</p>
        </div>

        <div class="edit-kategori-body">

            <?php if ($pesan !== ""): ?>
                <div class="edit-kategori-alert">
                    <?= aman($pesan) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="edit-kategori-group">
                    <label for="nama_kategori">Nama Kategori</label>

                    <input
                        type="text"
                        id="nama_kategori"
                        name="nama_kategori"
                        maxlength="100"
                        value="<?= aman($nama_lama) ?>"
                        placeholder="Contoh: Peralatan Elektronik"
                        autocomplete="off"
                        required
                    >

                    <span class="edit-kategori-help">
                        Nama kategori maksimal 100 karakter.
                    </span>
                </div>

                <div class="edit-kategori-actions">
                    <button
                        type="submit"
                        name="simpan"
                        class="btn btn-primary"
                    >
                        Simpan Perubahan
                    </button>

                    <a href="index.php" class="btn btn-secondary">
                        Kembali
                    </a>
                </div>

            </form>

        </div>
    </div>

</div>

</body>
</html>