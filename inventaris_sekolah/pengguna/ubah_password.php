
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
$error = "";
$berhasil = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    if (
        $password_lama === '' ||
        $password_baru === '' ||
        $konfirmasi === ''
    ) {
        $error = "Semua kolom wajib diisi.";
    } elseif (strlen($password_baru) < 8) {
        $error = "Password baru minimal 8 karakter.";
    } elseif ($password_baru !== $konfirmasi) {
        $error = "Konfirmasi password baru tidak cocok.";
    } elseif ($password_lama === $password_baru) {
        $error = "Password baru harus berbeda dari password lama.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT password FROM pengguna
             WHERE id_pengguna = ? LIMIT 1"
        );

        if (!$stmt) {
            $error = "Terjadi kesalahan saat memeriksa password.";
        } else {
            mysqli_stmt_bind_param($stmt, "i", $id_pengguna);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $akun = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (
                !$akun ||
                !password_verify($password_lama, $akun['password'])
            ) {
                $error = "Password lama yang Anda masukkan salah.";
            } else {
                $hash = password_hash(
                    $password_baru,
                    PASSWORD_DEFAULT
                );

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE pengguna
                     SET password = ?
                     WHERE id_pengguna = ?"
                );

                if (!$stmt) {
                    $error = "Password gagal diubah. Silakan coba lagi.";
                } else {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "si",
                        $hash,
                        $id_pengguna
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $berhasil = "Password berhasil diubah.";
                    } else {
                        $error = "Password gagal diubah. Silakan coba lagi.";
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

function aman($teks)
{
    return htmlspecialchars(
        (string) $teks,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Password | Inventaris SMK Negeri 2 Padang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .password-container {
            max-width: 620px;
            margin: 0 auto;
        }

        .password-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
        }

        .password-icon {
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            font-size: 25px;
            margin-bottom: 18px;
        }

        .password-card h2 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: 22px;
        }

        .password-description {
            margin: 0 0 24px;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .password-card .form-group {
            margin-bottom: 20px;
        }

        .password-card label {
            display: block;
            margin-bottom: 9px;
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .password-card .form-control {
            width: 100%;
        }

        .password-card .form-control:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
        }

        .pesan {
            padding: 13px 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
        }

        .pesan.error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .pesan.berhasil {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .password-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 26px;
        }

        .password-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .password-note {
            margin-top: 22px;
            padding: 14px;
            border-radius: 10px;
            background: var(--surface-2);
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            .password-card {
                padding: 20px;
            }

            .password-actions {
                flex-direction: column;
            }

            .password-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="main-content">
    <div class="page-header">
        <div>
            <div class="eyebrow">PENGATURAN AKUN</div>
            <h1>Ubah Password</h1>
            <p>Perbarui password untuk menjaga keamanan akun Anda.</p>
        </div>
    </div>

    <div class="password-container">
        <div class="password-card">

            <div class="password-icon">🔐</div>

            <h2>Ganti Password Akun</h2>
            <p class="password-description">
                Masukkan password lama, lalu buat password baru
                yang mudah Anda ingat dan sulit ditebak.
            </p>

            <?php if ($error !== ''): ?>
                <div class="pesan error">
                    <?= aman($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($berhasil !== ''): ?>
                <div class="pesan berhasil">
                    <?= aman($berhasil); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="password_lama">Password Lama</label>
                    <input
                        type="password"
                        class="form-control"
                        id="password_lama"
                        name="password_lama"
                        placeholder="Masukkan password lama"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password_baru">Password Baru</label>
                    <input
                        type="password"
                        class="form-control"
                        id="password_baru"
                        name="password_baru"
                        placeholder="Minimal 8 karakter"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="konfirmasi">Konfirmasi Password Baru</label>
                    <input
                        type="password"
                        class="form-control"
                        id="konfirmasi"
                        name="konfirmasi"
                        placeholder="Masukkan ulang password baru"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="password-actions">
                    <button type="submit" class="btn btn-primary">
                        Simpan Password
                    </button>

                    <a href="index.php" class="btn btn-secondary">
                        Kembali ke Dashboard
                    </a>
                </div>
            </form>

            <div class="password-note">
                <strong>Tips keamanan:</strong>
                gunakan password minimal 8 karakter dan jangan
                membagikannya kepada orang lain.
            </div>

        </div>
    </div>
</div>

</body>
</html>
