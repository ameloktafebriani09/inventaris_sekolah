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

$id = (int) $_SESSION['id_pengguna'];

$pesan = "";
$jenisPesan = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passwordLama = $_POST['password_lama'] ?? '';
    $passwordBaru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    if (
        $passwordLama === '' ||
        $passwordBaru === '' ||
        $konfirmasi === ''
    ) {
        $pesan = "Semua kolom wajib diisi.";
        $jenisPesan = "error";
    } elseif (strlen($passwordBaru) < 8) {
        $pesan = "Password baru minimal 8 karakter.";
        $jenisPesan = "error";
    } elseif ($passwordBaru !== $konfirmasi) {
        $pesan = "Konfirmasi password baru tidak cocok.";
        $jenisPesan = "error";
    } elseif ($passwordLama === $passwordBaru) {
        $pesan = "Password baru harus berbeda dari password lama.";
        $jenisPesan = "error";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT password
             FROM pengguna
             WHERE id_pengguna = ?
             LIMIT 1"
        );

        if (!$stmt) {
            error_log("Gagal menyiapkan query password: " . mysqli_error($conn));
            $pesan = "Terjadi kesalahan saat memeriksa akun.";
            $jenisPesan = "error";
        } else {
            mysqli_stmt_bind_param($stmt, "i", $id);

            if (!mysqli_stmt_execute($stmt)) {
                error_log("Gagal mengambil password: " . mysqli_stmt_error($stmt));
                $pesan = "Terjadi kesalahan saat memeriksa akun.";
                $jenisPesan = "error";
                mysqli_stmt_close($stmt);
            } else {
                $result = mysqli_stmt_get_result($stmt);
                $akun = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if (
                    !$akun ||
                    !password_verify($passwordLama, $akun['password'])
                ) {
                    $pesan = "Password lama yang dimasukkan salah.";
                    $jenisPesan = "error";
                } else {

                    $hash = password_hash(
                        $passwordBaru,
                        PASSWORD_DEFAULT
                    );

                    if ($hash === false) {
                        $pesan = "Password baru gagal diproses.";
                        $jenisPesan = "error";
                    } else {

                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE pengguna
                             SET password = ?
                             WHERE id_pengguna = ?"
                        );

                        if (!$stmt) {
                            error_log("Gagal menyiapkan update password: " . mysqli_error($conn));
                            $pesan = "Terjadi kesalahan saat menyiapkan perubahan password.";
                            $jenisPesan = "error";
                        } else {
                            mysqli_stmt_bind_param(
                                $stmt,
                                "si",
                                $hash,
                                $id
                            );

                            if (mysqli_stmt_execute($stmt)) {
                                $pesan = "Password berhasil diubah.";
                                $jenisPesan = "sukses";
                            } else {
                                error_log("Gagal memperbarui password: " . mysqli_stmt_error($stmt));
                                $pesan = "Password gagal diubah. Silakan coba lagi.";
                                $jenisPesan = "error";
                            }

                            mysqli_stmt_close($stmt);
                        }
                    }
                }
            }
        }
    }
}

function aman($nilai)
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ubah Password | Inventaris Sekolah</title>

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

        .password-card {
            max-width: 700px;
            padding: 25px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .password-card-header {
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }

        .password-card-header h2 {
            margin: 0 0 7px;
            color: var(--text);
            font-size: 19px;
        }

        .password-card-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .password-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            margin-bottom: 16px;
            border-radius: 13px;
            background: var(--emerald-soft);
            color: #6ee7b7;
            font-size: 23px;
        }

        .form-group {
            margin-bottom: 21px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-2);
            color: var(--text);
            font: inherit;
            font-size: 14px;
            outline: none;
        }

        .form-group input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 8px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .password-hint {
            margin: 18px 0 0;
            padding: 14px 16px;
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 10px;
            background: rgba(16, 185, 129, 0.07);
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .password-hint strong {
            color: #6ee7b7;
        }

        .alert {
            padding: 13px 16px;
            margin-bottom: 22px;
            border-radius: 9px;
            font-size: 13px;
            line-height: 1.6;
        }

        .alert-success {
            color: #6ee7b7;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 600px) {
            .password-card {
                padding: 17px;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
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
            <p class="eyebrow">KEAMANAN AKUN</p>
            <h1>Ubah Password</h1>
            <p>Perbarui password akun pengelola dengan aman.</p>
        </div>

        <a href="index.php" class="btn btn-secondary">
            ← Dashboard
        </a>
    </header>

    <section class="password-card">

        <div class="password-card-header">
            <div class="password-icon">⚿</div>
            <h2>Formulir Password</h2>
            <p>SMK Negeri 2 Padang</p>
        </div>

        <?php if ($pesan !== ''): ?>
            <div
                class="alert <?= $jenisPesan === 'sukses'
                    ? 'alert-success'
                    : 'alert-danger'; ?>"
                role="alert"
            >
                <?= aman($pesan); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="password_lama">Password Lama</label>

                <input
                    type="password"
                    id="password_lama"
                    name="password_lama"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password_baru">Password Baru</label>

                <input
                    type="password"
                    id="password_baru"
                    name="password_baru"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <small>Password baru harus terdiri dari minimal 8 karakter.</small>
            </div>

            <div class="form-group">
                <label for="konfirmasi">Konfirmasi Password Baru</label>

                <input
                    type="password"
                    id="konfirmasi"
                    name="konfirmasi"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <small>Masukkan kembali password baru yang sama.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Simpan Password
                </button>

                <a href="profil.php" class="btn btn-secondary">
                    Batal
                </a>
            </div>

        </form>

        <div class="password-hint">
            <strong>Tips keamanan</strong><br>
            Gunakan password yang sulit ditebak dan jangan membagikannya
            kepada orang lain. Password akan disimpan dalam bentuk hash.
        </div>

    </section>

    <footer class="page-footer">
        Sistem Inventaris Barang · SMK Negeri 2 Padang
        &copy; <?= date('Y'); ?>
    </footer>

</div>

</body>
</html>