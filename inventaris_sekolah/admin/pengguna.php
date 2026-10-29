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

function labelRole($role)
{
    $daftarRole = [
        'pengelola_sarpras' => 'Pengelola Sarpras',
        'teknisi' => 'Teknisi',
        'guru' => 'Guru',
        'kepala_sekolah' => 'Kepala Sekolah'
    ];

    return $daftarRole[$role] ?? $role;
}

$query = mysqli_query(
    $conn,
    "SELECT id_pengguna, nama, username, role, tanggal_dibuat
     FROM pengguna
     ORDER BY id_pengguna DESC"
);

if (!$query) {
    error_log("Gagal mengambil data pengguna: " . mysqli_error($conn));
    http_response_code(500);
    exit("Terjadi kesalahan saat mengambil data pengguna. Silakan periksa database.");
}

$totalPengguna = mysqli_num_rows($query);

$status = $_GET['status'] ?? '';

$pesanStatus = [
    'berhasil' => [
        'kelas' => 'alert-success',
        'pesan' => 'Data pengguna berhasil diproses.'
    ],
    'gagal' => [
        'kelas' => 'alert-danger',
        'pesan' => 'Data pengguna gagal diproses.'
    ],
    'hapus_berhasil' => [
        'kelas' => 'alert-success',
        'pesan' => 'Data pengguna berhasil dihapus.'
    ],
    'pengguna_memiliki_peminjaman' => [
        'kelas' => 'alert-danger',
        'pesan' => 'Pengguna tidak dapat dihapus karena memiliki riwayat peminjaman.'
    ],
    'gagal_hapus_diri' => [
        'kelas' => 'alert-danger',
        'pesan' => 'Anda tidak dapat menghapus akun yang sedang digunakan.'
    ],
    'gagal_hapus' => [
        'kelas' => 'alert-danger',
        'pesan' => 'Data pengguna gagal dihapus.'
    ]
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Pengguna | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .user-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .user-summary-card {
            flex: 1;
            min-width: 180px;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface);
        }

        .user-summary-card span {
            display: block;
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 8px;
        }

        .user-summary-card strong {
            display: block;
            color: var(--text);
            font-size: 27px;
        }

        .user-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin: 18px 0;
        }

        .user-search {
            width: 100%;
            max-width: 340px;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface-2);
            color: var(--text);
            font: inherit;
            outline: none;
        }

        .user-search:focus {
            border-color: #10b981;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 20px;
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .user-name {
            font-weight: 600;
            color: var(--text);
        }

        .user-username {
            color: var(--text-muted);
        }

        .aksi {
            white-space: nowrap;
        }

        .aksi form {
            display: inline-block;
            margin: 0;
        }

        .empty-state {
            padding: 30px 16px;
            text-align: center;
            color: var(--text-muted);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table-responsive table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
        }

        .table-responsive th,
        .table-responsive td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .table-responsive th {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-responsive tbody tr:hover {
            background: var(--surface-2);
        }

        .btn-warning {
            background: #b7791f;
            color: #fff;
        }

        .btn-warning:hover {
            filter: brightness(1.1);
        }

        .alert {
            padding: 13px 16px;
            border-radius: 9px;
            margin: 16px 0;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 600px) {
            .user-search {
                max-width: none;
            }

            .user-toolbar {
                align-items: stretch;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <header class="page-header">
        <div>
            <p class="eyebrow">MANAJEMEN AKUN</p>
            <h1>Data Pengguna</h1>
            <p>Kelola akun pengguna Inventaris Barang Sekolah.</p>
        </div>

        <a href="tambah_pengguna.php" class="btn btn-primary">
            + Tambah Pengguna
        </a>
    </header>

    <section class="user-summary">
        <div class="user-summary-card">
            <span>Total Pengguna Terdaftar</span>
            <strong><?= $totalPengguna; ?></strong>
        </div>
    </section>

    <section class="content-card">

        <div class="section-heading">
            <div>
                <h2>Daftar Pengguna</h2>
                <p>SMK Negeri 2 Padang</p>
            </div>

            <a href="index.php" class="btn btn-secondary">
                ← Dashboard
            </a>
        </div>

        <?php if (isset($pesanStatus[$status])): ?>
            <div class="alert <?= aman($pesanStatus[$status]['kelas']); ?>">
                <?= aman($pesanStatus[$status]['pesan']); ?>
            </div>
        <?php endif; ?>

        <div class="user-toolbar">
            <input
                type="search"
                id="cariPengguna"
                class="user-search"
                placeholder="Cari nama, username, atau role..."
                aria-label="Cari data pengguna"
            >

            <span style="color: var(--text-muted); font-size: 13px;">
                Total: <?= $totalPengguna; ?> pengguna
            </span>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody id="tabelPengguna">
                <?php if ($totalPengguna > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($data = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td><?= $no++; ?></td>

                            <td>
                                <span class="user-name">
                                    <?= aman($data['nama']); ?>
                                </span>
                            </td>

                            <td>
                                <span class="user-username">
                                    <?= aman($data['username']); ?>
                                </span>
                            </td>

                            <td>
                                <span class="role-badge">
                                    <?= aman(labelRole($data['role'])); ?>
                                </span>
                            </td>

                            <td>
                                <?= aman($data['tanggal_dibuat'] ?? '-'); ?>
                            </td>

                            <td class="aksi">
                                <a
                                    href="edit_pengguna.php?id=<?= (int) $data['id_pengguna']; ?>"
                                    class="btn btn-warning"
                                >
                                    Edit
                                </a>

                                <?php if (
                                    (int) $data['id_pengguna'] !==
                                    (int) $_SESSION['id_pengguna']
                                ): ?>
                                    <form
                                        method="POST"
                                        action="hapus_pengguna.php"
                                        onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $data['id_pengguna']; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                        >
                                            Hapus
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <tr id="barisTidakDitemukan" style="display: none;">
                        <td colspan="6" class="empty-state">
                            Pengguna yang dicari tidak ditemukan.
                        </td>
                    </tr>

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            Belum ada data pengguna.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

    <footer class="page-footer">
        Sistem Inventaris Barang · SMK Negeri 2 Padang
        &copy; <?= date('Y'); ?>
    </footer>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputCari = document.getElementById('cariPengguna');
    const tabel = document.getElementById('tabelPengguna');
    const barisTidakDitemukan = document.getElementById('barisTidakDitemukan');

    if (!inputCari || !tabel) {
        return;
    }

    inputCari.addEventListener('input', function () {
        const kataKunci = this.value.toLowerCase().trim();
        const baris = tabel.querySelectorAll('tr:not(#barisTidakDitemukan)');
        let jumlahCocok = 0;

        baris.forEach(function (tr) {
            const teks = tr.textContent.toLowerCase();
            const cocok = teks.includes(kataKunci);

            tr.style.display = cocok ? '' : 'none';

            if (cocok) {
                jumlahCocok++;
            }
        });

        if (barisTidakDitemukan) {
            barisTidakDitemukan.style.display =
                jumlahCocok === 0 ? '' : 'none';
        }
    });
});
</script>

</body>
</html>