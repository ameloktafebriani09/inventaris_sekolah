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
$nama_kategori = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $nama_kategori = trim($_POST['nama_kategori'] ?? "");

    if ($nama_kategori === "") {
        $pesan = "Nama kategori wajib diisi.";
    } elseif (strlen($nama_kategori) > 100) {
        $pesan = "Nama kategori maksimal 100 karakter.";
    } else {
        $cek = mysqli_prepare(
            $conn,
            "SELECT id_kategori
             FROM kategori
             WHERE nama_kategori = ?
             LIMIT 1"
        );

        if (!$cek) {
            $pesan = "Gagal memeriksa kategori.";
        } else {
            mysqli_stmt_bind_param($cek, "s", $nama_kategori);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            $duplikat = mysqli_stmt_num_rows($cek) > 0;
            mysqli_stmt_close($cek);

            if ($duplikat) {
                $pesan = "Kategori tersebut sudah ada.";
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO kategori (nama_kategori)
                     VALUES (?)"
                );

                if (!$stmt) {
                    $pesan = "Gagal menyiapkan penyimpanan kategori.";
                } else {
                    mysqli_stmt_bind_param($stmt, "s", $nama_kategori);

                    $berhasil = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    if ($berhasil) {
                        header("Location: index.php?status=berhasil");
                        exit;
                    } else {
                        $pesan = "Gagal menyimpan kategori.";
                    }
                }
            }
        }
    }
}

function e($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Kategori | Inventaris SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .tambah-kategori-card {
            max-width: 680px;
            margin: 0 auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .tambah-kategori-header {
            padding: 22px 26px;
            border-bottom: 1px solid var(--border);
        }

        .tambah-kategori-header h3 {
            margin: 0 0 7px;
            color: var(--text);
            font-size: 19px;
        }

        .tambah-kategori-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .tambah-kategori-body {
            padding: 26px;
        }

        .tambah-kategori-group {
            display: flex;
            flex-direction: column;
            gap: 9px;
            margin-bottom: 24px;
        }

        .tambah-kategori-group label {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .tambah-kategori-group input {
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

        .tambah-kategori-group input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.13);
        }

        .tambah-kategori-help {
            color: var(--text-muted);
            font-size: 12px;
        }

        .tambah-kategori-alert {
            padding: 13px 15px;
            margin-bottom: 22px;
            color: #fecaca;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 9px;
            font-size: 14px;
        }

        .tambah-kategori-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .tambah-kategori-actions .btn {
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
            .tambah-kategori-header,
            .tambah-kategori-body {
                padding: 20px;
            }

            .tambah-kategori-actions {
                flex-direction: column;
            }

            .tambah-kategori-actions .btn {
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
            <h1>Tambah Kategori Barang</h1>
            <p>Tambahkan kategori baru untuk inventaris SMK Negeri 2 Padang.</p>
        </div>
    </div>

    <div class="tambah-kategori-card">

        <div class="tambah-kategori-header">
            <h3>Form Tambah Kategori</h3>
            <p>Isi informasi kategori yang ingin ditambahkan.</p>
        </div>

        <div class="tambah-kategori-body">

            <?php if ($pesan !== ""): ?>
                <div class="tambah-kategori-alert">
                    <?= e($pesan) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="tambah-kategori-group">
                    <label for="nama_kategori">Nama Kategori</label>

                    <input
                        type="text"
                        id="nama_kategori"
                        name="nama_kategori"
                        maxlength="100"
                        placeholder="Contoh: Elektronik"
                        value="<?= e($nama_kategori) ?>"
                        autocomplete="off"
                        required
                    >

                    <span class="tambah-kategori-help">
                        Masukkan nama kategori yang jelas, maksimal 100 karakter.
                    </span>
                </div>

                <div class="tambah-kategori-actions">
                    <button
                        type="submit"
                        name="simpan"
                        class="btn btn-primary"
                    >
                        Simpan Kategori
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