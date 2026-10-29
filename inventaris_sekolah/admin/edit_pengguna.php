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

function e($nilai)
{
    return htmlspecialchars(
        (string) ($nilai ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

if (
    !isset($_GET['id']) ||
    !is_string($_GET['id']) ||
    !ctype_digit($_GET['id']) ||
    (int) $_GET['id'] < 1
) {
    header("Location: pengguna.php");
    exit;
}

$id = (int) $_GET['id'];
$pesan = "";

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

$stmt = mysqli_prepare(
    $conn,
    "SELECT id_pengguna, nama, username, role
     FROM pengguna
     WHERE id_pengguna = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$data) {
    header("Location: pengguna.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if (
        !is_string($nama) ||
        !is_string($username) ||
        !is_string($role) ||
        !is_string($password)
    ) {
        $pesan = "Data yang dikirim tidak valid.";
    } elseif ($nama === '' || $username === '') {
        $pesan = "Nama lengkap dan username wajib diisi.";
    } elseif (strlen($nama) > 100 || strlen($username) > 50) {
        $pesan = "Nama maksimal 100 karakter dan username maksimal 50 karakter.";
    } elseif (!in_array($role, $daftar_role, true)) {
        $pesan = "Peran pengguna tidak valid.";
    } elseif (
        $id === (int) $_SESSION['id_pengguna'] &&
        $role !== 'pengelola_sarpras'
    ) {
        $pesan = "Peran akun sendiri tidak boleh diubah.";
    } elseif ($password !== '' && strlen($password) < 8) {
        $pesan = "Password baru minimal 8 karakter.";
    } elseif ($password !== '' && strlen($password) > 4096) {
        $pesan = "Password terlalu panjang.";
    } else {

        $cek = mysqli_prepare(
            $conn,
            "SELECT id_pengguna
             FROM pengguna
             WHERE username = ?
               AND id_pengguna != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($cek, "si", $username, $id);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $pesan = "Username sudah digunakan pengguna lain.";
        } else {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $update = mysqli_prepare(
                    $conn,
                    "UPDATE pengguna
                     SET nama = ?,
                         username = ?,
                         password = ?,
                         role = ?
                     WHERE id_pengguna = ?"
                );

                mysqli_stmt_bind_param(
                    $update,
                    "ssssi",
                    $nama,
                    $username,
                    $hash,
                    $role,
                    $id
                );
            } else {
                $update = mysqli_prepare(
                    $conn,
                    "UPDATE pengguna
                     SET nama = ?,
                         username = ?,
                         role = ?
                     WHERE id_pengguna = ?"
                );

                mysqli_stmt_bind_param(
                    $update,
                    "sssi",
                    $nama,
                    $username,
                    $role,
                    $id
                );
            }

            if (mysqli_stmt_execute($update)) {
                mysqli_stmt_close($update);
                mysqli_stmt_close($cek);

                if ($id === (int) $_SESSION['id_pengguna']) {
                    $_SESSION['nama'] = $nama;
                    $_SESSION['username'] = $username;
                }

                header("Location: pengguna.php?status=terubah");
                exit;
            } else {
                error_log(
                    "Gagal memperbarui pengguna ID {$id}: " .
                    mysqli_stmt_error($update)
                );

                $pesan = "Gagal menyimpan perubahan pengguna.";
                mysqli_stmt_close($update);
            }
        }

        mysqli_stmt_close($cek);
    }

    $data['nama'] = is_string($nama) ? $nama : $data['nama'];
    $data['username'] = is_string($username)
        ? $username
        : $data['username'];
    $data['role'] = is_string($role) ? $role : $data['role'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Pengguna | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .edit-pengguna-wrapper {
            max-width: 850px;
            margin: 0 auto;
        }

        .edit-pengguna-card {
            padding: 26px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .edit-pengguna-info {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .edit-pengguna-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.14);
            color: #34d399;
            font-size: 23px;
        }

        .edit-pengguna-info h3 {
            margin: 0 0 5px;
        }

        .edit-pengguna-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .edit-pengguna-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .edit-pengguna-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .edit-pengguna-field.full-width {
            grid-column: 1 / -1;
        }

        .edit-pengguna-field label {
            font-size: 14px;
            font-weight: 600;
        }

        .edit-pengguna-field input,
        .edit-pengguna-field select {
            box-sizing: border-box;
            width: 100%;
            min-width: 0;
            padding: 12px 14px;
            color: var(--text);
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font: inherit;
        }

        .edit-pengguna-field input:focus,
        .edit-pengguna-field select:focus {
            outline: 2px solid rgba(16, 185, 129, 0.3);
            border-color: #10b981;
        }

        .edit-pengguna-field select option {
            color: var(--text);
            background: var(--surface);
        }

        .edit-pengguna-field small {
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .edit-pengguna-alert {
            margin-bottom: 20px;
            padding: 14px 16px;
            color: #fecaca;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 10px;
        }

        .edit-pengguna-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .edit-pengguna-footer {
            margin-top: 24px;
            color: var(--text-muted);
            text-align: center;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            .edit-pengguna-card {
                padding: 18px;
            }

            .edit-pengguna-grid {
                grid-template-columns: 1fr;
            }

            .edit-pengguna-field.full-width {
                grid-column: auto;
            }

            .edit-pengguna-actions {
                flex-direction: column;
            }

            .edit-pengguna-actions .btn {
                width: 100%;
                box-sizing: border-box;
                text-align: center;
            }
        }
    </style>
</head>

<body>
<div class="main-content edit-pengguna-wrapper">

    <div class="page-header">
        <div>
            <p class="eyebrow">MANAJEMEN PENGGUNA</p>
            <h1>Edit Pengguna</h1>
            <p>Perbarui informasi akun pengguna inventaris sekolah.</p>
        </div>

        <a href="pengguna.php" class="btn btn-secondary">
            Kembali
        </a>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="edit-pengguna-alert" role="alert">
            <?= e($pesan); ?>
        </div>
    <?php endif; ?>

    <div class="edit-pengguna-card">

        <div class="edit-pengguna-info">
            <div class="edit-pengguna-avatar">♙</div>

            <div>
                <h3><?= e($data['nama']); ?></h3>
                <p>
                    ID Pengguna: #<?= (int) $data['id_pengguna']; ?>
                    ·
                    <?= e($label_role[$data['role']] ?? $data['role']); ?>
                </p>
            </div>
        </div>

        <form method="POST" action="">
            <div class="edit-pengguna-grid">

                <div class="edit-pengguna-field">
                    <label for="nama">Nama Lengkap</label>

                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        maxlength="100"
                        value="<?= e($data['nama']); ?>"
                        autocomplete="name"
                        required
                    >
                </div>

                <div class="edit-pengguna-field">
                    <label for="username">Username</label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        maxlength="50"
                        value="<?= e($data['username']); ?>"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="edit-pengguna-field full-width">
                    <label for="password">Password Baru</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        maxlength="4096"
                        autocomplete="new-password"
                        placeholder="Kosongkan jika tidak ingin mengubah password"
                    >

                    <small>
                        Password baru minimal 8 karakter.
                        Password lama tetap digunakan jika kolom ini dikosongkan.
                    </small>
                </div>

                <div class="edit-pengguna-field full-width">
                    <label for="role">Peran Pengguna</label>

                    <select id="role" name="role" required>
                        <?php foreach ($label_role as $nilai => $label): ?>
                            <option
                                value="<?= e($nilai); ?>"
                                <?= $data['role'] === $nilai
                                    ? 'selected'
                                    : ''; ?>
                            >
                                <?= e($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($id === (int) $_SESSION['id_pengguna']): ?>
                        <small>
                            Peran akun yang sedang digunakan tidak dapat
                            diubah menjadi peran lain.
                        </small>
                    <?php endif; ?>
                </div>

            </div>

            <div class="edit-pengguna-actions">
                <button
                    type="submit"
                    name="simpan"
                    value="1"
                    class="btn btn-primary"
                >
                    Simpan Perubahan
                </button>

                <a href="pengguna.php" class="btn btn-secondary">
                    Batal
                </a>
            </div>
        </form>
    </div>

    <div class="edit-pengguna-footer">
        Sistem Inventaris Barang SMK Negeri 2 Padang
    </div>

</div>
</body>
</html>