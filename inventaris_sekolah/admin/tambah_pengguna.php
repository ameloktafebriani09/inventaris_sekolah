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
$jenis_pesan = "";

$nama = "";
$username = "";
$role = "teknisi";

$daftar_role = [
    'pengelola_sarpras',
    'teknisi',
    'guru',
    'kepala_sekolah'
];

$label_role = [
    'pengelola_sarpras' => 'Pengelola Sarana Prasarana',
    'teknisi' => 'Teknisi',
    'guru' => 'Guru',
    'kepala_sekolah' => 'Kepala Sekolah'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($nama === '' || $username === '' || $password === '') {
        $pesan = "Semua kolom wajib diisi.";
        $jenis_pesan = "danger";
    } elseif (strlen($nama) > 100) {
        $pesan = "Nama maksimal 100 karakter.";
        $jenis_pesan = "danger";
    } elseif (strlen($username) > 50) {
        $pesan = "Username maksimal 50 karakter.";
        $jenis_pesan = "danger";
    } elseif (strlen($password) < 8) {
        $pesan = "Password minimal 8 karakter.";
        $jenis_pesan = "danger";
    } elseif (!in_array($role, $daftar_role, true)) {
        $pesan = "Peran pengguna tidak valid.";
        $jenis_pesan = "danger";
    } else {

        $cek = mysqli_prepare(
            $conn,
            "SELECT id_pengguna
             FROM pengguna
             WHERE username = ?
             LIMIT 1"
        );

        if (!$cek) {
            error_log("Gagal menyiapkan pemeriksaan username: " . mysqli_error($conn));
            $pesan = "Terjadi kesalahan saat memeriksa username.";
            $jenis_pesan = "danger";
        } else {
            mysqli_stmt_bind_param($cek, "s", $username);

            if (!mysqli_stmt_execute($cek)) {
                error_log("Gagal memeriksa username: " . mysqli_stmt_error($cek));
                $pesan = "Terjadi kesalahan saat memeriksa username.";
                $jenis_pesan = "danger";
                mysqli_stmt_close($cek);
            } else {
                mysqli_stmt_store_result($cek);

                if (mysqli_stmt_num_rows($cek) > 0) {
                    $pesan = "Username sudah digunakan. Silakan pilih username lain.";
                    $jenis_pesan = "danger";
                    mysqli_stmt_close($cek);
                } else {
                    mysqli_stmt_close($cek);

                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    if ($hash === false) {
                        $pesan = "Password gagal diproses. Silakan coba lagi.";
                        $jenis_pesan = "danger";
                    } else {
                        $stmt = mysqli_prepare(
                            $conn,
                            "INSERT INTO pengguna
                                (nama, username, password, role)
                             VALUES (?, ?, ?, ?)"
                        );

                        if (!$stmt) {
                            error_log("Gagal menyiapkan insert pengguna: " . mysqli_error($conn));
                            $pesan = "Terjadi kesalahan saat menyiapkan penyimpanan.";
                            $jenis_pesan = "danger";
                        } else {
                            mysqli_stmt_bind_param(
                                $stmt,
                                "ssss",
                                $nama,
                                $username,
                                $hash,
                                $role
                            );

                            if (mysqli_stmt_execute($stmt)) {
                                mysqli_stmt_close($stmt);

                                header("Location: pengguna.php?status=berhasil");
                                exit;
                            }

                            $kodeError = mysqli_stmt_errno($stmt);
                            error_log("Gagal menyimpan pengguna. Kode: " . $kodeError);

                            if ($kodeError === 1062) {
                                $pesan = "Username sudah digunakan. Silakan pilih username lain.";
                            } else {
                                $pesan = "Data pengguna gagal disimpan. Silakan coba lagi.";
                            }

                            $jenis_pesan = "danger";
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

    <title>Tambah Pengguna | Inventaris Sekolah</title>

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

        .form-card {
            max-width: 760px;
            padding: 25px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .form-card-header {
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }

        .form-card-header h2 {
            margin: 0 0 7px;
            color: var(--text);
            font-size: 19px;
        }

        .form-card-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
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

        .form-group input:focus,
        .form-group select:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-group select option {
            background: var(--surface);
            color: var(--text);
        }

        .form-group small {
            display: block;
            margin-top: 8px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .alert {
            padding: 13px 16px;
            margin-bottom: 20px;
            border-radius: 9px;
            font-size: 13px;
            line-height: 1.6;
        }

        .alert-danger {
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .form-note {
            margin-top: 20px;
            padding: 14px 16px;
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 10px;
            background: rgba(16, 185, 129, 0.07);
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            .form-card {
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
            <p class="eyebrow">MANAJEMEN AKUN</p>
            <h1>Tambah Pengguna</h1>
            <p>Tambahkan akun untuk pengguna inventaris sekolah.</p>
        </div>

        <a href="pengguna.php" class="btn btn-secondary">
            ← Kembali
        </a>
    </header>

    <section class="form-card">

        <div class="form-card-header">
            <h2>Formulir Pengguna</h2>
            <p>SMK Negeri 2 Padang</p>
        </div>

        <?php if ($pesan !== ''): ?>
            <div
                class="alert alert-<?= aman($jenis_pesan); ?>"
                role="alert"
            >
                <?= aman($pesan); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="nama">Nama Lengkap</label>

                <input
                    type="text"
                    id="nama"
                    name="nama"
                    value="<?= aman($nama); ?>"
                    placeholder="Masukkan nama lengkap"
                    maxlength="100"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="form-group">
                <label for="username">Username</label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= aman($username); ?>"
                    placeholder="Masukkan username"
                    maxlength="50"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Minimal 8 karakter"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <small>
                    Password minimal 8 karakter dan disimpan dalam bentuk hash,
                    bukan sebagai teks biasa.
                </small>
            </div>

            <div class="form-group">
                <label for="role">Peran Pengguna</label>

                <select id="role" name="role" required>
                    <?php foreach ($daftar_role as $pilihan_role): ?>
                        <option
                            value="<?= aman($pilihan_role); ?>"
                            <?= $role === $pilihan_role ? 'selected' : ''; ?>
                        >
                            <?= aman($label_role[$pilihan_role]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <small>
                    Pilih peran sesuai tugas pengguna di sekolah.
                </small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Simpan Pengguna
                </button>

                <a href="pengguna.php" class="btn btn-secondary">
                    Batal
                </a>
            </div>

        </form>

        <div class="form-note">
            <strong>Informasi:</strong>
            Username harus unik. Setiap akun akan memperoleh hak akses
            berdasarkan peran yang dipilih.
        </div>

    </section>

    <footer class="page-footer">
        Sistem Inventaris Barang · SMK Negeri 2 Padang
        &copy; <?= date('Y'); ?>
    </footer>

</div>

</body>
</html>