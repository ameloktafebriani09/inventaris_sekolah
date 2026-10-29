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

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];
$error = "";


$stmt = mysqli_prepare(
    $conn,
    "SELECT id_lokasi, nama_lokasi FROM lokasi WHERE id_lokasi = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$lokasi = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$lokasi) {
    header("Location: index.php?status=tidak_ditemukan");
    exit;
}


function aman($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lokasi = trim($_POST['nama_lokasi'] ?? '');

    if ($nama_lokasi === '') {
        $error = "Nama lokasi wajib diisi.";
    } elseif (strlen($nama_lokasi) > 100) {
        $error = "Nama lokasi maksimal 100 karakter.";
    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_lokasi
             FROM lokasi
             WHERE nama_lokasi = ? AND id_lokasi != ?"
        );

        mysqli_stmt_bind_param($stmt, "si", $nama_lokasi, $id);
        mysqli_stmt_execute($stmt);

        $cek = mysqli_stmt_get_result($stmt);
        $duplikat = mysqli_num_rows($cek) > 0;

        mysqli_stmt_close($stmt);

        if ($duplikat) {
            $error = "Nama lokasi tersebut sudah digunakan.";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE lokasi SET nama_lokasi = ? WHERE id_lokasi = ?"
            );

            mysqli_stmt_bind_param($stmt, "si", $nama_lokasi, $id);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: index.php?status=berhasil");
                exit;
            } else {
                $error = "Gagal memperbarui lokasi.";
                mysqli_stmt_close($stmt);
            }
        }
    }

    $lokasi['nama_lokasi'] = $nama_lokasi;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Lokasi | SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .edit-lokasi-card {
            max-width: 650px;
            margin: 0 auto;
            padding: 26px;
        }

        .edit-lokasi-heading {
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }

        .edit-lokasi-heading h2 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: 20px;
        }

        .edit-lokasi-heading p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .edit-lokasi-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .edit-lokasi-group {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .edit-lokasi-group label {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .edit-lokasi-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            outline: none;
            background: var(--surface-2);
            color: var(--text);
            font: inherit;
            transition: border-color .2s, box-shadow .2s;
        }

        .edit-lokasi-group input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, .12);
        }

        .edit-lokasi-group small {
            color: var(--text-muted);
            font-size: 12px;
        }

        .edit-lokasi-error {
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid rgba(239, 68, 68, .3);
            border-radius: 9px;
            background: rgba(239, 68, 68, .1);
            color: #fca5a5;
            font-size: 14px;
        }

        .edit-lokasi-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 8px;
        }

        @media (max-width: 600px) {
            .edit-lokasi-card {
                padding: 20px;
            }

            .edit-lokasi-actions {
                flex-direction: column;
            }

            .edit-lokasi-actions .btn {
                width: 100%;
                box-sizing: border-box;
                justify-content: center;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <span class="eyebrow">DATA INVENTARIS</span>
            <h1>Edit Lokasi</h1>
            <p>Perbarui informasi lokasi barang SMK Negeri 2 Padang.</p>
        </div>
    </div>

    <div class="card edit-lokasi-card">

        <div class="edit-lokasi-heading">
            <h2>Form Edit Lokasi</h2>
            <p>Ubah nama lokasi, lalu simpan perubahan.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="edit-lokasi-error">
                <?= aman($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="edit-lokasi-form">

            <div class="edit-lokasi-group">
                <label for="id_lokasi">ID Lokasi</label>
                <input
                    type="text"
                    id="id_lokasi"
                    value="<?= (int) $lokasi['id_lokasi'] ?>"
                    disabled
                >
                <small>ID lokasi tidak dapat diubah.</small>
            </div>

            <div class="edit-lokasi-group">
                <label for="nama_lokasi">Nama Lokasi</label>
                <input
                    type="text"
                    name="nama_lokasi"
                    id="nama_lokasi"
                    maxlength="100"
                    placeholder="Contoh: Ruang Laboratorium"
                    value="<?= aman($lokasi['nama_lokasi']) ?>"
                    required
                    autofocus
                >
            </div>

            <div class="edit-lokasi-actions">
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

</body>
</html>