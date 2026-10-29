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


function aman($nilai)
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

$id = (int) $_SESSION['id_pengguna'];

$stmt = mysqli_prepare($conn, "
    SELECT nama, username, role, tanggal_dibuat
    FROM pengguna
    WHERE id_pengguna = ?
");

if (!$stmt) {
    error_log("Gagal menyiapkan query profil: " . mysqli_error($conn));
    http_response_code(500);
    exit("Terjadi kesalahan saat mengambil profil.");
}

mysqli_stmt_bind_param($stmt, "i", $id);

if (!mysqli_stmt_execute($stmt)) {
    error_log("Gagal mengambil profil: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    http_response_code(500);
    exit("Terjadi kesalahan saat mengambil profil.");
}

$result = mysqli_stmt_get_result($stmt);
$profil = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$profil) {
    session_unset();
    session_destroy();

    header("Location: ../auth/login.php");
    exit;
}

$daftarRole = [
    'pengelola_sarpras' => 'Pengelola Sarana Prasarana',
    'teknisi' => 'Teknisi',
    'guru' => 'Guru',
    'kepala_sekolah' => 'Kepala Sekolah'
];

$roleTampil = $daftarRole[$profil['role']] ?? $profil['role'];
$inisial = strtoupper(substr($profil['nama'], 0, 1));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profil Pengelola | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 7px;
        }

        .page-header p {
            margin: 0;
            color: var(--text-muted);
        }

        .profil-card {
            max-width: 850px;
            padding: 25px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .profil-header {
            display: flex;
            align-items: center;
            gap: 17px;
            padding-bottom: 23px;
            margin-bottom: 22px;
            border-bottom: 1px solid var(--border);
        }

        .profil-avatar {
            width: 66px;
            height: 66px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 18px;
            background: var(--emerald-soft);
            color: #6ee7b7;
            font-size: 27px;
            font-weight: 700;
        }

        .profil-header h2 {
            margin: 0 0 7px;
            color: var(--text);
            font-size: 20px;
        }

        .profil-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .profil-info {
            display: grid;
            gap: 13px;
        }

        .profil-baris {
            display: grid;
            grid-template-columns: minmax(150px, 0.8fr) minmax(0, 1.5fr);
            gap: 15px;
            padding: 17px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 11px;
        }

        .profil-label {
            color: var(--text-muted);
            font-size: 13px;
        }

        .profil-nilai {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .profil-role {
            display: inline-block;
            padding: 6px 11px;
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 20px;
            background: rgba(16, 185, 129, 0.12);
            color: #6ee7b7;
            font-size: 12px;
        }

        .profil-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .profil-note {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 10px;
            background: rgba(16, 185, 129, 0.07);
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            .profil-card {
                padding: 17px;
            }

            .profil-baris {
                grid-template-columns: 1fr;
                gap: 7px;
            }

            .profil-actions {
                flex-direction: column;
            }

            .profil-actions .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <header class="page-header">
        <div>
            <p class="eyebrow">MANAJEMEN AKUN</p>
            <h1>Profil Pengelola</h1>
            <p>Informasi akun pengelola inventaris sekolah.</p>
        </div>

        <a href="index.php" class="btn btn-secondary">
            ← Dashboard
        </a>
    </header>

    <section class="profil-card">

        <div class="profil-header">
            <div class="profil-avatar">
                <?= aman($inisial); ?>
            </div>

            <div>
                <h2><?= aman($profil['nama']); ?></h2>
                <p>SMK Negeri 2 Padang</p>
            </div>
        </div>

        <div class="profil-info">

            <div class="profil-baris">
                <span class="profil-label">Nama Lengkap</span>
                <span class="profil-nilai">
                    <?= aman($profil['nama']); ?>
                </span>
            </div>

            <div class="profil-baris">
                <span class="profil-label">Username</span>
                <span class="profil-nilai">
                    <?= aman($profil['username']); ?>
                </span>
            </div>

            <div class="profil-baris">
                <span class="profil-label">Hak Akses</span>
                <span class="profil-nilai">
                    <span class="profil-role">
                        <?= aman($roleTampil); ?>
                    </span>
                </span>
            </div>

            <div class="profil-baris">
                <span class="profil-label">Tanggal Akun Dibuat</span>
                <span class="profil-nilai">
                    <?= aman($profil['tanggal_dibuat'] ?? '-'); ?>
                </span>
            </div>

        </div>

        <div class="profil-actions">
            <a href="edit_pengguna.php?id=<?= $id; ?>" class="btn btn-primary">
                Edit Profil
            </a>

            <a href="ubah_password.php" class="btn btn-secondary">
                Ubah Password
            </a>
        </div>

        <div class="profil-note">
            <strong>Informasi akun</strong><br>
            Jaga kerahasiaan password akunmu. Hak akses pengelola digunakan
            untuk mengelola data inventaris SMK Negeri 2 Padang.
        </div>

    </section>

    <footer class="page-footer">
        Sistem Inventaris Barang · SMK Negeri 2 Padang
        &copy; <?= date('Y'); ?>
    </footer>

</div>

</body>
</html>