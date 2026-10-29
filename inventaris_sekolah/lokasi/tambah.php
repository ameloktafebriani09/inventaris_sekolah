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

$error = "";
$nama_lokasi = "";
$keterangan = "";

function aman($nilai) {
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lokasi = trim($_POST['nama_lokasi'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($nama_lokasi === '') {
        $error = "Nama lokasi wajib diisi.";
    } elseif (strlen($nama_lokasi) > 100) {
        $error = "Nama lokasi maksimal 100 karakter.";
    } elseif (strlen($keterangan) > 255) {
        $error = "Keterangan maksimal 255 karakter.";
    } else {
        
        $cek = mysqli_prepare(
            $conn,
            "SELECT id_lokasi
             FROM lokasi
             WHERE nama_lokasi = ?
             LIMIT 1"
        );

        if (!$cek) {
            $error = "Gagal memeriksa data lokasi.";
        } else {
            mysqli_stmt_bind_param($cek, "s", $nama_lokasi);

            if (!mysqli_stmt_execute($cek)) {
                $error = "Gagal memeriksa data lokasi.";
                mysqli_stmt_close($cek);
            } else {
                mysqli_stmt_store_result($cek);
                $duplikat = mysqli_stmt_num_rows($cek) > 0;
                mysqli_stmt_close($cek);

                if ($duplikat) {
                    $error = "Nama lokasi tersebut sudah terdaftar.";
                } else {
                    
                    $simpan = mysqli_prepare(
                        $conn,
                        "INSERT INTO lokasi (nama_lokasi, keterangan)
                         VALUES (?, ?)"
                    );

                    if (!$simpan) {
                        $error = "Gagal menyiapkan penyimpanan data.";
                    } else {
                        mysqli_stmt_bind_param(
                            $simpan,
                            "ss",
                            $nama_lokasi,
                            $keterangan
                        );

                        if (mysqli_stmt_execute($simpan)) {
                            mysqli_stmt_close($simpan);

                            header("Location: index.php?status=berhasil");
                            exit;
                        }

                        $error = "Lokasi gagal ditambahkan. Silakan coba lagi.";
                        mysqli_stmt_close($simpan);
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Lokasi | SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .tambah-lokasi-card {
            max-width: 700px;
            margin: 0 auto 24px;
            padding: 26px;
        }

        .tambah-lokasi-heading {
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }

        .tambah-lokasi-heading h2 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: 20px;
        }

        .tambah-lokasi-heading p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .tambah-lokasi-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .tambah-lokasi-group {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .tambah-lokasi-group label {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .tambah-lokasi-group input,
        .tambah-lokasi-group textarea {
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

        .tambah-lokasi-group textarea {
            min-height: 110px;
            resize: vertical;
        }

        .tambah-lokasi-group input:focus,
        .tambah-lokasi-group textarea:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, .12);
        }

        .tambah-lokasi-group small {
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .tambah-lokasi-error {
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid rgba(239, 68, 68, .3);
            border-radius: 9px;
            background: rgba(239, 68, 68, .1);
            color: #fca5a5;
            font-size: 14px;
            line-height: 1.6;
        }

        .tambah-lokasi-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 8px;
        }

        @media (max-width: 600px) {
            .tambah-lokasi-card {
                padding: 20px;
            }

            .tambah-lokasi-actions {
                flex-direction: column;
            }

            .tambah-lokasi-actions .btn {
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
            <span class="eyebrow">MANAJEMEN INVENTARIS</span>
            <h1>Tambah Lokasi</h1>
            <p>Tambahkan lokasi penyimpanan barang sekolah.</p>
        </div>
    </div>

    <div class="card tambah-lokasi-card">

        <div class="tambah-lokasi-heading">
            <h2>Form Tambah Lokasi</h2>
            <p>
                Isi nama lokasi dan keterangan untuk mencatat tempat
                penyimpanan inventaris SMK Negeri 2 Padang.
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="tambah-lokasi-error">
                <?= aman($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="tambah-lokasi-form">

            <div class="tambah-lokasi-group">
                <label for="nama_lokasi">Nama Lokasi</label>

                <input
                    type="text"
                    name="nama_lokasi"
                    id="nama_lokasi"
                    maxlength="100"
                    placeholder="Contoh: Ruang Laboratorium"
                    value="<?= aman($nama_lokasi) ?>"
                    required
                    autofocus
                >

                <small>
                    Masukkan nama tempat penyimpanan barang.
                </small>
            </div>

            <div class="tambah-lokasi-group">
                <label for="keterangan">Keterangan</label>

                <textarea
                    name="keterangan"
                    id="keterangan"
                    maxlength="255"
                    placeholder="Contoh: Tempat penyimpanan komputer dan peralatan praktik"
                ><?= aman($keterangan) ?></textarea>

                <small>
                    Keterangan boleh dikosongkan jika tidak diperlukan.
                </small>
            </div>

            <div class="tambah-lokasi-actions">
                <button type="submit" class="btn btn-primary">
                    Simpan Lokasi
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