
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
    "SELECT nama, username, role, tanggal_dibuat
     FROM pengguna
     WHERE id_pengguna = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Terjadi kesalahan saat mengambil data profil.");
}

mysqli_stmt_bind_param($stmt, "i", $id_pengguna);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$profil = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$profil) {
    session_unset();
    session_destroy();

    header("Location: ../auth/login.php");
    exit;
}

function aman($teks) {
    return htmlspecialchars(
        (string) ($teks ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

$namaRole = ucwords(
    str_replace('_', ' ', $profil['role'])
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profil Pengguna</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 8px;
        }

        .page-header p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .profil-card {
            max-width: 720px;
            margin: 0 auto;
            padding: 28px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .profil-identitas {
            display: flex;
            align-items: center;
            gap: 18px;
            padding-bottom: 24px;
            margin-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .profil-icon {
            flex-shrink: 0;
            width: 76px;
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: rgba(22, 134, 106, 0.18);
            border: 1px solid rgba(22, 134, 106, 0.35);
            color: #39bd91;
            font-size: 34px;
        }

        .profil-identitas h2 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: 21px;
            overflow-wrap: anywhere;
        }

        .profil-identitas p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        .role-badge {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 11px;
            border-radius: 20px;
            background: rgba(22, 134, 106, 0.15);
            color: #39bd91;
            font-size: 12px;
            font-weight: 700;
        }

        .profil-detail {
            margin-top: 12px;
        }

        .baris {
            display: grid;
            grid-template-columns: minmax(150px, 0.8fr) minmax(0, 1.2fr);
            gap: 18px;
            padding: 19px 0;
            border-bottom: 1px solid var(--border);
        }

        .label {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .nilai {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .profil-aksi {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 25px;
        }

        @media (max-width: 600px) {
            .profil-card {
                padding: 20px;
            }

            .profil-identitas {
                align-items: flex-start;
                gap: 14px;
            }

            .profil-icon {
                width: 60px;
                height: 60px;
                font-size: 27px;
                border-radius: 14px;
            }

            .profil-identitas h2 {
                font-size: 18px;
            }

            .baris {
                grid-template-columns: 1fr;
                gap: 6px;
                padding: 15px 0;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <span class="eyebrow">PENGATURAN AKUN</span>
        <h1>Profil Pengguna</h1>
        <p>
            Informasi akun Anda pada Sistem Inventaris Barang
            SMK Negeri 2 Padang.
        </p>
    </div>

    <div class="profil-card">

        <div class="profil-identitas">

            <div class="profil-icon">♙</div>

            <div>
                <h2><?= aman($profil['nama']); ?></h2>

                <p>
                    @<?= aman($profil['username']); ?>
                </p>

                <span class="role-badge">
                    <?= aman($namaRole); ?>
                </span>
            </div>

        </div>

        <div class="profil-detail">

            <div class="baris">
                <span class="label">Nama Lengkap</span>
                <div class="nilai">
                    <?= aman($profil['nama']); ?>
                </div>
            </div>

            <div class="baris">
                <span class="label">Username</span>
                <div class="nilai">
                    <?= aman($profil['username']); ?>
                </div>
            </div>

            <div class="baris">
                <span class="label">Jabatan / Role</span>
                <div class="nilai">
                    <?= aman($namaRole); ?>
                </div>
            </div>

            <div class="baris">
                <span class="label">Tanggal Pembuatan Akun</span>
                <div class="nilai">
                    <?= aman($profil['tanggal_dibuat']); ?>
                </div>
            </div>

        </div>

        <div class="profil-aksi">
            <a href="index.php" class="btn btn-secondary">
                Kembali ke Dashboard
            </a>

            <a href="ubah_password.php" class="btn btn-primary">
                Ubah Password
            </a>
        </div>

    </div>

</div>

</body>
</html>
