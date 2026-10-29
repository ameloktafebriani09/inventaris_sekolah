
<?php
session_start();
require_once "../koneksi.php";

// ==========================================
// 1. LOGIN DAN HAK AKSES
// ==========================================

if (!isset($_SESSION['id_pengguna'], $_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_pengguna = (int) $_SESSION['id_pengguna'];
$nama = $_SESSION['nama'] ?? 'Pengguna';
$role = $_SESSION['role'];

$roleDiizinkan = [
    'pengelola_sarpras',
    'teknisi',
    'kepala_sekolah'
];

if (!in_array($role, $roleDiizinkan, true)) {
    http_response_code(403);
    exit("Akses ditolak. Kamu tidak memiliki izin membuka Laporan Kerusakan.");
}

$isSarpras = ($role === 'pengelola_sarpras');
$isTeknisi = ($role === 'teknisi');
$isKepsek = ($role === 'kepala_sekolah');

function aman($nilai)
{
    return htmlspecialchars((string) ($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

function kembaliLaporan($parameter = '')
{
    header(
        "Location: barang_rusak.php" .
        ($parameter !== '' ? '?' . $parameter : '')
    );
    exit;
}

// ==========================================
// 2. PENGAJUAN LAPORAN OLEH SARPRAS
// ==========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['kirim_laporan'])
) {
    if (!$isSarpras) {
        http_response_code(403);
        exit("Hanya Pengelola Sarana Prasarana yang dapat mengajukan laporan.");
    }

    $id_barang = filter_input(INPUT_POST, 'id_barang', FILTER_VALIDATE_INT);
    $jumlah_rusak = filter_input(INPUT_POST, 'jumlah_rusak', FILTER_VALIDATE_INT);
    $jenis = $_POST['jenis_kerusakan'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (
        !$id_barang ||
        !$jumlah_rusak ||
        $jumlah_rusak < 1 ||
        !in_array($jenis, ['Rusak Ringan', 'Rusak Berat'], true)
    ) {
        kembaliLaporan('error=input');
    }

    mysqli_begin_transaction($conn);

    try {
        // Ambil data barang dan kunci baris selama transaksi.
        $stmtBarang = mysqli_prepare(
            $conn,
            "SELECT
                id_barang,
                jumlah,
                jumlah_rusak_ringan,
                jumlah_rusak_berat
             FROM barang
             WHERE id_barang = ?
             FOR UPDATE"
        );

        if (!$stmtBarang) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmtBarang, "i", $id_barang);

        if (!mysqli_stmt_execute($stmtBarang)) {
            throw new Exception(mysqli_stmt_error($stmtBarang));
        }

        $hasilBarang = mysqli_stmt_get_result($stmtBarang);
        $barang = mysqli_fetch_assoc($hasilBarang);
        mysqli_stmt_close($stmtBarang);

        if (!$barang) {
            throw new Exception('barang_tidak_ditemukan');
        }

        $totalStok = (int) $barang['jumlah'];
        $rusakRingan = (int) $barang['jumlah_rusak_ringan'];
        $rusakBerat = (int) $barang['jumlah_rusak_berat'];

        // Jumlah rusak tidak boleh melampaui stok yang belum rusak.
        $stokBelumRusak = $totalStok - $rusakRingan - $rusakBerat;

        if ($jumlah_rusak > $stokBelumRusak) {
            throw new Exception('jumlah_melebihi_stok');
        }

        // Simpan laporan dengan status awal Menunggu.
        $stmtLaporan = mysqli_prepare(
            $conn,
            "INSERT INTO laporan_kerusakan
            (
                id_barang,
                id_pengguna,
                jumlah_rusak,
                jenis_kerusakan,
                keterangan
            )
            VALUES (?, ?, ?, ?, ?)"
        );

        if (!$stmtLaporan) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $stmtLaporan,
            "iiiss",
            $id_barang,
            $id_pengguna,
            $jumlah_rusak,
            $jenis,
            $keterangan
        );

        if (!mysqli_stmt_execute($stmtLaporan)) {
            throw new Exception(mysqli_stmt_error($stmtLaporan));
        }

        mysqli_stmt_close($stmtLaporan);

        // Perbarui kolom kerusakan sesuai jenisnya.
        if ($jenis === 'Rusak Ringan') {
            $sqlUpdateBarang = "
                UPDATE barang
                SET jumlah_rusak_ringan =
                    COALESCE(jumlah_rusak_ringan, 0) + ?
                WHERE id_barang = ?
            ";
        } else {
            $sqlUpdateBarang = "
                UPDATE barang
                SET jumlah_rusak_berat =
                    COALESCE(jumlah_rusak_berat, 0) + ?
                WHERE id_barang = ?
            ";
        }

        $stmtUpdateBarang = mysqli_prepare($conn, $sqlUpdateBarang);

        if (!$stmtUpdateBarang) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $stmtUpdateBarang,
            "ii",
            $jumlah_rusak,
            $id_barang
        );

        if (!mysqli_stmt_execute($stmtUpdateBarang)) {
            throw new Exception(mysqli_stmt_error($stmtUpdateBarang));
        }

        mysqli_stmt_close($stmtUpdateBarang);

        mysqli_commit($conn);
        kembaliLaporan('berhasil=1');

    } catch (Throwable $e) {
        mysqli_rollback($conn);

        if ($e->getMessage() === 'barang_tidak_ditemukan') {
            kembaliLaporan('error=barang');
        }

        if ($e->getMessage() === 'jumlah_melebihi_stok') {
            kembaliLaporan('error=jumlah');
        }

        error_log("Pengajuan laporan kerusakan: " . $e->getMessage());
        kembaliLaporan('error=simpan');
    }
}

// ==========================================
// 3. TEKNISI MEMPERBARUI STATUS
// ==========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['ubah_status'])
) {
    if (!$isTeknisi) {
        http_response_code(403);
        exit("Hanya Teknisi yang dapat memperbarui status penanganan.");
    }

    $id_laporan = filter_input(INPUT_POST, 'id_laporan', FILTER_VALIDATE_INT);
    $statusBaru = $_POST['status'] ?? '';

    if (
        !$id_laporan ||
        !in_array($statusBaru, ['Diproses', 'Selesai'], true)
    ) {
        kembaliLaporan('error=status');
    }

    $stmtCek = mysqli_prepare(
        $conn,
        "SELECT id_laporan
         FROM laporan_kerusakan
         WHERE id_laporan = ?"
    );

    if (!$stmtCek) {
        error_log(mysqli_error($conn));
        kembaliLaporan('error=database');
    }

    mysqli_stmt_bind_param($stmtCek, "i", $id_laporan);
    mysqli_stmt_execute($stmtCek);

    $hasilCek = mysqli_stmt_get_result($stmtCek);
    $laporanAda = mysqli_fetch_assoc($hasilCek);
    mysqli_stmt_close($stmtCek);

    if (!$laporanAda) {
        kembaliLaporan('error=laporan');
    }

    $stmtUpdate = mysqli_prepare(
        $conn,
        "UPDATE laporan_kerusakan
         SET status = ?
         WHERE id_laporan = ?"
    );

    if (!$stmtUpdate) {
        error_log(mysqli_error($conn));
        kembaliLaporan('error=database');
    }

    mysqli_stmt_bind_param($stmtUpdate, "si", $statusBaru, $id_laporan);
    $berhasil = mysqli_stmt_execute($stmtUpdate);

    if (!$berhasil) {
        error_log(mysqli_stmt_error($stmtUpdate));
    }

    mysqli_stmt_close($stmtUpdate);

    // Perubahan status tidak menambah jumlah kerusakan lagi.
    kembaliLaporan($berhasil ? 'status_berhasil=1' : 'error=status');
}

// ==========================================
// 4. PESAN NOTIFIKASI
// ==========================================

$pesan = '';
$jenisPesan = '';

if (isset($_GET['berhasil'])) {
    $pesan = 'Laporan berhasil diajukan dan jumlah kerusakan diperbarui.';
    $jenisPesan = 'sukses';
} elseif (isset($_GET['status_berhasil'])) {
    $pesan = 'Status penanganan berhasil diperbarui.';
    $jenisPesan = 'sukses';
} elseif (isset($_GET['error'])) {
    $jenisPesan = 'gagal';

    switch ($_GET['error']) {
        case 'input':
            $pesan = 'Data laporan belum valid. Periksa kembali isian.';
            break;
        case 'barang':
            $pesan = 'Barang yang dipilih tidak ditemukan.';
            break;
        case 'jumlah':
            $pesan = 'Jumlah barang rusak melebihi stok yang masih tersedia.';
            break;
        case 'database':
            $pesan = 'Terjadi masalah database. Periksa log PHP.';
            break;
        case 'simpan':
            $pesan = 'Laporan gagal disimpan. Periksa struktur tabel dan log PHP.';
            break;
        case 'status':
            $pesan = 'Status laporan gagal diperbarui.';
            break;
        case 'laporan':
            $pesan = 'Laporan tidak ditemukan.';
            break;
        default:
            $pesan = 'Terjadi kesalahan. Silakan coba kembali.';
    }
}

// ==========================================
// 5. DAFTAR BARANG UNTUK FORM SARPRAS
// ==========================================

$daftarBarang = [];

if ($isSarpras) {
    $hasilBarang = mysqli_query(
        $conn,
        "SELECT
            id_barang,
            kode_inventaris,
            nama_barang,
            jumlah,
            COALESCE(jumlah_rusak_ringan, 0) AS jumlah_rusak_ringan,
            COALESCE(jumlah_rusak_berat, 0) AS jumlah_rusak_berat
         FROM barang
         ORDER BY nama_barang ASC"
    );

    if ($hasilBarang) {
        while ($baris = mysqli_fetch_assoc($hasilBarang)) {
            $daftarBarang[] = $baris;
        }
    }
}

// ==========================================
// 6. AMBIL SEMUA LAPORAN
// ==========================================

$sql = "
    SELECT
        lk.id_laporan,
        lk.id_barang,
        lk.id_pengguna,
        lk.jumlah_rusak,
        lk.jenis_kerusakan,
        lk.keterangan,
        lk.status,
        lk.tanggal_laporan,
        b.kode_inventaris,
        b.nama_barang,
        u.nama AS nama_pelapor
    FROM laporan_kerusakan lk
    INNER JOIN barang b
        ON lk.id_barang = b.id_barang
    INNER JOIN pengguna u
        ON lk.id_pengguna = u.id_pengguna
    ORDER BY lk.tanggal_laporan DESC, lk.id_laporan DESC
";

$resultLaporan = mysqli_query($conn, $sql);

if (!$resultLaporan) {
    error_log("Gagal mengambil laporan: " . mysqli_error($conn));
    die("Data laporan gagal dimuat. Periksa struktur tabel database.");
}

$daftarLaporan = [];

while ($baris = mysqli_fetch_assoc($resultLaporan)) {
    $daftarLaporan[] = $baris;
}

$totalLaporan = count($daftarLaporan);
$totalMenunggu = 0;
$totalDiproses = 0;
$totalSelesai = 0;

foreach ($daftarLaporan as $baris) {
    if ($baris['status'] === 'Menunggu') {
        $totalMenunggu++;
    } elseif ($baris['status'] === 'Diproses') {
        $totalDiproses++;
    } elseif ($baris['status'] === 'Selesai') {
        $totalSelesai++;
    }
}

// ==========================================
// 7. HITUNG JUMLAH BARANG RUSAK OTOMATIS
// ==========================================

$sqlJumlahRusak = "
    SELECT
        COALESCE(SUM(jumlah_rusak_ringan), 0) AS total_rusak_ringan,
        COALESCE(SUM(jumlah_rusak_berat), 0) AS total_rusak_berat
    FROM barang
";

$resultJumlahRusak = mysqli_query($conn, $sqlJumlahRusak);

if (!$resultJumlahRusak) {
    error_log("Gagal menghitung barang rusak: " . mysqli_error($conn));
    die("Jumlah barang rusak gagal dihitung.");
}

$dataJumlahRusak = mysqli_fetch_assoc($resultJumlahRusak);

$totalRusakRingan = (int) $dataJumlahRusak['total_rusak_ringan'];
$totalRusakBerat = (int) $dataJumlahRusak['total_rusak_berat'];

// Tautan kembali sesuai role.
$linkDashboard = $isSarpras
    ? '../admin/index.php'
    : '../pengguna/index.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Kerusakan | Inventaris Sekolah</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .kerusakan-page {
            max-width: 1300px;
            margin: 0 auto;
            padding: 28px;
            color: var(--text, #e5e7eb);
        }

        .kerusakan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 26px;
        }

        .kerusakan-header h1 { margin: 0 0 8px; }

        .kerusakan-header p {
            color: var(--text-muted, #9ca3af);
            line-height: 1.7;
        }

        .kerusakan-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .kr-btn {
            display: inline-block;
            padding: 11px 16px;
            border: 0;
            border-radius: 8px;
            background: #059669;
            color: white;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
        }

        .kr-btn:hover { background: #047857; }
        .kr-btn-secondary { background: #374151; }

        .kr-statistik {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .kr-card, .kr-panel {
            background: var(--surface-2, #111827);
            border: 1px solid var(--border, #263445);
            border-radius: 13px;
        }

        .kr-card { padding: 20px; }

        .kr-card small {
            display: block;
            color: var(--text-muted, #9ca3af);
            margin-bottom: 12px;
        }

        .kr-card strong { font-size: 28px; }
        .kr-card.total strong, .kr-card.ringan strong { color: #34d399; }
        .kr-card.berat strong { color: #fbbf24; }

        .kr-panel {
            padding: 22px;
            margin-bottom: 24px;
        }

        .kr-panel h2 {
            margin: 0 0 20px;
            font-size: 19px;
        }

        .kr-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .kr-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .kr-field.full { grid-column: 1 / -1; }
        .kr-field label { font-weight: 600; font-size: 14px; }

        .kr-field input, .kr-field select, .kr-field textarea,
        .kr-status-form select {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid var(--border, #374151);
            border-radius: 8px;
            background: var(--surface, #0b1220);
            color: var(--text, #e5e7eb);
            font: inherit;
        }

        .kr-field textarea { min-height: 100px; resize: vertical; }

        .kr-alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .kr-alert.sukses {
            border: 1px solid #34d399;
            background: #102c25;
        }

        .kr-alert.gagal {
            border: 1px solid #f87171;
            background: #351b21;
        }

        .kr-table-wrap { overflow-x: auto; }

        .kr-table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        .kr-table th, .kr-table td {
            padding: 13px 12px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid var(--border, #263445);
            font-size: 13px;
        }

        .kr-table th {
            background: #064e3b;
            color: #ecfdf5;
            white-space: nowrap;
        }

        .kr-table td { color: var(--text, #e5e7eb); }

        .kr-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .kr-menunggu { color: #fbbf24; background: #382d15; }
        .kr-diproses { color: #60a5fa; background: #172d49; }
        .kr-selesai { color: #34d399; background: #10382b; }

        .kr-status-form {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 130px;
        }

        .kr-empty {
            text-align: center !important;
            padding: 30px !important;
            color: var(--text-muted, #9ca3af) !important;
        }

        @media (max-width: 900px) {
            .kr-statistik { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 600px) {
            .kerusakan-page { padding: 16px; }
            .kr-statistik, .kr-form-grid { grid-template-columns: 1fr; }
            .kr-field.full { grid-column: auto; }
            .kr-panel { padding: 16px; }
            .kerusakan-actions { width: 100%; }
        }

        @media print {
            .kr-form, .kr-aksi, .kerusakan-actions {
                display: none !important;
            }

            body, .kr-card, .kr-panel {
                background: white !important;
                color: black !important;
                box-shadow: none !important;
            }

            .kr-card strong, .kr-table td, .kr-table th {
                color: black !important;
            }

            .kr-table th, .kr-table td {
                border: 1px solid #bbb !important;
            }

            .kr-table th { background: #eee !important; }
            .kr-table { min-width: 0; }
        }
    </style>
</head>

<body>
<div class="main-content">
<div class="kerusakan-page">

    <header class="kerusakan-header">
        <div>
            <h1>Laporan Kerusakan Barang</h1>
            <p>
                Sistem Inventaris Barang Sekolah SMK Negeri 2 Padang<br>
                Pengguna: <strong><?= aman($nama); ?></strong>
            </p>
        </div>

        <div class="kerusakan-actions">
            <a href="<?= aman($linkDashboard); ?>" class="kr-btn kr-btn-secondary">
                Kembali ke Dashboard
            </a>

            <button type="button" class="kr-btn" onclick="window.print()">
                Cetak Laporan
            </button>
        </div>
    </header>

    <?php if ($pesan !== ''): ?>
        <div class="kr-alert <?= $jenisPesan === 'sukses' ? 'sukses' : 'gagal'; ?>">
            <?= aman($pesan); ?>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN JUMLAH KERUSAKAN -->
    <section class="kr-statistik">
        <div class="kr-card total">
            <small>Total Laporan</small>
            <strong><?= $totalLaporan; ?></strong>
        </div>

        <div class="kr-card ringan">
            <small>Total Barang Rusak Ringan</small>
            <strong><?= $totalRusakRingan; ?></strong>
        </div>

        <div class="kr-card berat">
            <small>Total Barang Rusak Berat</small>
            <strong><?= $totalRusakBerat; ?></strong>
        </div>
    </section>

    <section class="kr-statistik">
        <div class="kr-card">
            <small>Laporan Menunggu</small>
            <strong><?= $totalMenunggu; ?></strong>
        </div>

        <div class="kr-card">
            <small>Laporan Diproses</small>
            <strong><?= $totalDiproses; ?></strong>
        </div>

        <div class="kr-card">
            <small>Laporan Selesai</small>
            <strong><?= $totalSelesai; ?></strong>
        </div>
    </section>

    <?php if ($isSarpras): ?>
        <section class="kr-panel kr-form">
            <h2>Ajukan Laporan Kerusakan</h2>

            <?php if (count($daftarBarang) > 0): ?>
                <form method="POST" action="barang_rusak.php">
                    <div class="kr-form-grid">

                        <div class="kr-field full">
                            <label for="id_barang">Pilih Barang</label>
                            <select name="id_barang" id="id_barang" required>
                                <option value="">-- Pilih Barang --</option>

                                <?php foreach ($daftarBarang as $barang): ?>
                                    <option value="<?= (int) $barang['id_barang']; ?>">
                                        <?= aman($barang['kode_inventaris']); ?>
                                        — <?= aman($barang['nama_barang']); ?>
                                        (Stok: <?= (int) $barang['jumlah']; ?>,
                                        Rusak ringan: <?= (int) $barang['jumlah_rusak_ringan']; ?>,
                                        Rusak berat: <?= (int) $barang['jumlah_rusak_berat']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="kr-field">
                            <label for="jumlah_rusak">Jumlah Barang Rusak</label>
                            <input
                                type="number"
                                name="jumlah_rusak"
                                id="jumlah_rusak"
                                min="1"
                                required
                            >
                        </div>

                        <div class="kr-field">
                            <label for="jenis_kerusakan">Jenis Kerusakan</label>
                            <select name="jenis_kerusakan" id="jenis_kerusakan" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>

                        <div class="kr-field full">
                            <label for="keterangan">Keterangan Kerusakan</label>
                            <textarea
                                name="keterangan"
                                id="keterangan"
                                placeholder="Jelaskan kerusakan barang..."
                            ></textarea>
                        </div>

                        <div class="kr-field full">
                            <button type="submit" name="kirim_laporan" class="kr-btn">
                                Ajukan Laporan
                            </button>
                        </div>

                    </div>
                </form>
            <?php else: ?>
                <p>Data barang belum tersedia untuk dipilih.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="kr-panel">
        <h2>Daftar Semua Laporan Kerusakan</h2>

        <div class="kr-table-wrap">
            <table class="kr-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>Barang</th>
                        <th>Pelapor</th>
                        <th>Jumlah</th>
                        <th>Jenis Kerusakan</th>
                        <th>Keterangan</th>
                        <th>Status</th>

                        <?php if ($isTeknisi): ?>
                            <th class="kr-aksi">Tindak Lanjut</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                <?php if ($totalLaporan > 0): ?>
                    <?php foreach ($daftarLaporan as $i => $laporan): ?>
                        <?php
                        $statusClass = 'kr-menunggu';

                        if ($laporan['status'] === 'Diproses') {
                            $statusClass = 'kr-diproses';
                        } elseif ($laporan['status'] === 'Selesai') {
                            $statusClass = 'kr-selesai';
                        }
                        ?>

                        <tr>
                            <td><?= $i + 1; ?></td>

                            <td>
                                <?= aman(date(
                                    'd-m-Y H:i',
                                    strtotime($laporan['tanggal_laporan'])
                                )); ?>
                            </td>

                            <td>
                                <strong><?= aman($laporan['nama_barang']); ?></strong><br>
                                <small><?= aman($laporan['kode_inventaris']); ?></small>
                            </td>

                            <td><?= aman($laporan['nama_pelapor']); ?></td>
                            <td><?= (int) $laporan['jumlah_rusak']; ?></td>
                            <td><?= aman($laporan['jenis_kerusakan']); ?></td>

                            <td>
                                <?= $laporan['keterangan'] !== ''
                                    ? nl2br(aman($laporan['keterangan']))
                                    : '-'; ?>
                            </td>

                            <td>
                                <span class="kr-badge <?= $statusClass; ?>">
                                    <?= aman($laporan['status']); ?>
                                </span>
                            </td>

                            <?php if ($isTeknisi): ?>
                                <td class="kr-aksi">
                                    <form
                                        method="POST"
                                        action="barang_rusak.php"
                                        class="kr-status-form"
                                    >
                                        <input
                                            type="hidden"
                                            name="id_laporan"
                                            value="<?= (int) $laporan['id_laporan']; ?>"
                                        >

                                        <select name="status" required>
                                            <?php if ($laporan['status'] === 'Menunggu'): ?>
                                                <option value="Menunggu" selected>
                                                    Menunggu
                                                </option>
                                            <?php endif; ?>

                                            <option
                                                value="Diproses"
                                                <?= $laporan['status'] === 'Diproses' ? 'selected' : ''; ?>
                                            >
                                                Diproses
                                            </option>

                                            <option
                                                value="Selesai"
                                                <?= $laporan['status'] === 'Selesai' ? 'selected' : ''; ?>
                                            >
                                                Selesai
                                            </option>
                                        </select>

                                        <button type="submit" name="ubah_status" class="kr-btn">
                                            Simpan Status
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $isTeknisi ? 9 : 8; ?>" class="kr-empty">
                            Belum ada laporan kerusakan.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>
</div>
</body>
</html>
