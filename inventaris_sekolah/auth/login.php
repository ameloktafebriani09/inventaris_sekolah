
<?php
session_start();
require_once "../koneksi.php";

$error = "";

// Jika sudah login, arahkan sesuai role
if (isset($_SESSION['id_pengguna'], $_SESSION['role'])) {
    $role = $_SESSION['role'];

    if ($role === 'pengelola_sarpras') {
        header("Location: ../admin/index.php");
        exit;
    } elseif (in_array(
        $role,
        ['teknisi', 'guru', 'kepala_sekolah'],
        true
    )) {
        header("Location: ../pengguna/index.php");
        exit;
    } else {
        // Role tidak dikenal
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username dan password wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_pengguna, nama, username, password, role
             FROM pengguna
             WHERE username = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $error = "Terjadi kesalahan saat memproses login.";
        } else {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $akun = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (
                $akun &&
                password_verify($password, $akun['password'])
            ) {
                $roleDiizinkan = [
                    'pengelola_sarpras',
                    'teknisi',
                    'guru',
                    'kepala_sekolah'
                ];

                if (!in_array($akun['role'], $roleDiizinkan, true)) {
                    $error = "Role akun tidak dikenali.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION['id_pengguna'] = (int) $akun['id_pengguna'];
                    $_SESSION['nama'] = $akun['nama'];
                    $_SESSION['username'] = $akun['username'];
                    $_SESSION['role'] = $akun['role'];

                    if ($akun['role'] === 'pengelola_sarpras') {
                        header("Location: ../admin/index.php");
                        exit;
                    }

                    header("Location: ../pengguna/index.php");
                    exit;
                }
            } else {
                $error = "Username atau password salah.";
            }
        }
    }
}

function aman($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
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

    <title>Login | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body.login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: var(--bg);
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 32px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .login-logo {
            width: 68px;
            height: 68px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border-radius: 18px;
            background: var(--emerald-soft);
            color: var(--emerald);
            font-size: 32px;
        }

        .login-box h1 {
            margin: 0;
            color: var(--text);
            text-align: center;
            font-size: 24px;
        }

        .login-description {
            margin: 12px 0 25px;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.7;
            font-size: 14px;
        }

        .login-box .form-group {
            margin-bottom: 18px;
        }

        .login-box label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-weight: 600;
        }

        .login-box input {
            width: 100%;
        }

        .login-box .btn {
            width: 100%;
            justify-content: center;
            margin-top: 8px;
        }

        .login-error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #7f3030;
            border-radius: 8px;
            background: #321b1b;
            color: #fecaca;
            font-size: 14px;
        }

        .login-footer {
            margin-top: 25px;
            color: var(--text-muted);
            text-align: center;
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 24px 20px;
            }
        }
    </style>
</head>

<body class="login-page">

<div class="login-box">

    <div class="login-logo">📦</div>

    <h1>Login Inventaris</h1>

    <div class="login-description">
        Sistem Inventaris Barang Sekolah<br>
        <strong>SMK Negeri 2 Padang</strong>
    </div>

    <?php if ($error !== ''): ?>
        <div class="login-error">
            <?= aman($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="<?= aman($_POST['username'] ?? '') ?>"
                autocomplete="username"
                placeholder="Masukkan username"
                required
                autofocus
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                placeholder="Masukkan password"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Login
        </button>
    </form>

    <div class="login-footer">
        &copy; <?= date('Y') ?> SMK Negeri 2 Padang
    </div>

</div>

</body>
</html>
