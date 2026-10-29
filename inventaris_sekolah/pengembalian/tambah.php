<?php
session_start();
require_once "../koneksi.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'pengelola_sarpras'
) {
    header("Location: ../auth/login.php");
    exit;
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tanggalValid($tanggal)
{
    if (!is_string($tanggal) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

    return $date && $date->format('Y-m-d') === $tanggal;
}

function jalankan($conn, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);

    if ($types !== '') {
        $bind = [$types];

        foreach ($params as $key => &$value) {
            $bind[] = &$value;
        }
        unset($value);

        call_user_func_array(
            'mysqli_stmt_bind_param',
            array_merge([$stmt], $bind)
        );
    }

    mysqli_stmt_execute($stmt);

    return $stmt;
}

function ambilSatu($stmt)
{
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row;
}

$pesan = '';

$id_dipilih = filter_var(
    $_POST['id_peminjaman'] ?? $_GET['id_peminjaman'] ?? 0,
    FILTER_VALIDATE_INT
);

$id_dipilih = $id_dipilih === false ? 0 : (int)$id_dipilih;

$tanggal_input = $_POST['tanggal_pengembalian'] ?? date('Y-m-d');
$catatan_umum = $_POST['catatan'] ?? '';

$jumlah_baik_input = $_POST['jumlah_baik'] ?? [];
$jumlah_rr_input = $_POST['jumlah_rusak_ringan'] ?? [];
$jumlah_rb_input = $_POST['jumlah_rusak_berat'] ?? [];
$catatan_barang_input = $_POST['catatan_barang'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            $id_dipilih < 1 ||
            !tanggalValid($tanggal_input) ||
            $tanggal_input > date('Y-m-d') ||
            !is_string($catatan_umum) ||
            strlen($catatan_umum) > 1000 ||
            !is_array($jumlah_baik_input) ||
            !is_array($jumlah_rr_input) ||
            !is_array($jumlah_rb_input) ||
            !is_array($catatan_barang_input)
        ) {
            throw new Exception("Data formulir tidak valid.");
        }

        $catatan_umum = trim($catatan_umum);

        mysqli_begin_transaction($conn);

        // Kunci peminjaman agar tidak diproses bersamaan.
        $stmt = jalankan(
            $conn,
            "SELECT id_peminjaman, status
             FROM peminjaman
             WHERE id_peminjaman = ?
             FOR UPDATE",
            "i",
            [$id_dipilih]
        );

        $pinjam = ambilSatu($stmt);

        if (!$pinjam || $pinjam['status'] !== 'Disetujui') {
            throw new Exception(
                "Peminjaman tidak ditemukan atau belum berstatus Disetujui."
            );
        }

        // Cegah pengembalian ganda.
        $stmt = jalankan(
            $conn,
            "SELECT id_pengembalian
             FROM pengembalian
             WHERE id_peminjaman = ?
             LIMIT 1",
            "i",
            [$id_dipilih]
        );

        $sudah_ada = mysqli_num_rows(
            mysqli_stmt_get_result($stmt)
        ) > 0;

        mysqli_stmt_close($stmt);

        if ($sudah_ada) {
            throw new Exception(
                "Pengembalian untuk peminjaman ini sudah tercatat."
            );
        }

        // Ambil seluruh barang yang dipinjam.
        $stmt = jalankan(
            $conn,
            "SELECT id_barang, jumlah
             FROM detail_peminjaman
             WHERE id_peminjaman = ?
             ORDER BY id_barang
             FOR UPDATE",
            "i",
            [$id_dipilih]
        );

        $result = mysqli_stmt_get_result($stmt);
        $daftar_barang = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $daftar_barang[] = $row;
        }

        mysqli_stmt_close($stmt);

        if (!$daftar_barang) {
            throw new Exception("Detail barang yang dipinjam tidak ditemukan.");
        }

        // Validasi seluruh data sebelum mengubah stok.
        $data_pengembalian = [];

        foreach ($daftar_barang as $item) {
            $id_barang = (int)$item['id_barang'];
            $jumlah_pinjam = (int)$item['jumlah'];

            $masukan = [
                $jumlah_baik_input,
                $jumlah_rr_input,
                $jumlah_rb_input
            ];

            foreach ($masukan as $array) {
                if (
                    !array_key_exists($id_barang, $array) ||
                    !is_scalar($array[$id_barang]) ||
                    !preg_match('/^\d+$/', (string)$array[$id_barang])
                ) {
                    throw new Exception(
                        "Jumlah kondisi barang harus diisi dengan angka bulat nonnegatif."
                    );
                }
            }

            $baik = (int)$jumlah_baik_input[$id_barang];
            $ringan = (int)$jumlah_rr_input[$id_barang];
            $berat = (int)$jumlah_rb_input[$id_barang];

            if (
                $baik > $jumlah_pinjam ||
                $ringan > $jumlah_pinjam ||
                $berat > $jumlah_pinjam ||
                ($baik + $ringan + $berat) !== $jumlah_pinjam
            ) {
                throw new Exception(
                    "Total kondisi barang ID {$id_barang} harus sama dengan jumlah pinjaman ({$jumlah_pinjam} unit)."
                );
            }

            $catatan_item = $catatan_barang_input[$id_barang] ?? '';

            if (
                !is_string($catatan_item) ||
                strlen($catatan_item) > 1000
            ) {
                throw new Exception("Catatan barang tidak valid.");
            }

            $data_pengembalian[$id_barang] = [
                'jumlah' => $jumlah_pinjam,
                'baik' => $baik,
                'ringan' => $ringan,
                'berat' => $berat,
                'catatan' => trim($catatan_item)
            ];
        }

        // Buat catatan utama pengembalian.
        $stmt = jalankan(
            $conn,
            "INSERT INTO pengembalian
                (id_peminjaman, tanggal_pengembalian, kondisi_barang, catatan)
             VALUES (?, ?, 'Beragam', ?)",
            "iss",
            [$id_dipilih, $tanggal_input, $catatan_umum]
        );

        mysqli_stmt_close($stmt);
        $id_pengembalian = mysqli_insert_id($conn);

        foreach ($daftar_barang as $item) {
            $id_barang = (int)$item['id_barang'];
            $data = $data_pengembalian[$id_barang];

            $jumlah_pinjam = $data['jumlah'];
            $baik = $data['baik'];
            $ringan = $data['ringan'];
            $berat = $data['berat'];
            $catatan_item = $data['catatan'];

            // Kunci stok barang dan baca kondisi terkini.
            $stmt = jalankan(
                $conn,
                "SELECT nama_barang, jumlah, kondisi,
                        jumlah_baik, jumlah_rusak_ringan,
                        jumlah_rusak_berat
                 FROM barang
                 WHERE id_barang = ?
                 FOR UPDATE",
                "i",
                [$id_barang]
            );

            $barang = ambilSatu($stmt);

            if (!$barang) {
                throw new Exception(
                    "Barang ID {$id_barang} tidak ditemukan."
                );
            }

            $jumlah_lama = (int)$barang['jumlah'];
            $baik_lama = (int)$barang['jumlah_baik'];
            $ringan_lama = (int)$barang['jumlah_rusak_ringan'];
            $berat_lama = (int)$barang['jumlah_rusak_berat'];

            if (
                $jumlah_lama < 0 ||
                $baik_lama < 0 ||
                $ringan_lama < 0 ||
                $berat_lama < 0 ||
                $jumlah_lama !==
                    ($baik_lama + $ringan_lama + $berat_lama)
            ) {
                throw new Exception(
                    "Rincian stok {$barang['nama_barang']} tidak konsisten. Periksa data barang terlebih dahulu."
                );
            }

            $jumlah_baru = $jumlah_lama + $jumlah_pinjam;
            $baik_baru = $baik_lama + $baik;
            $ringan_baru = $ringan_lama + $ringan;
            $berat_baru = $berat_lama + $berat;

            if (
                $jumlah_baru > 2147483647 ||
                $baik_baru > 2147483647 ||
                $ringan_baru > 2147483647 ||
                $berat_baru > 2147483647
            ) {
                throw new Exception("Jumlah stok barang terlalu besar.");
            }

            // Tentukan kondisi ringkasan dari stok keseluruhan.
            if ($berat_baru > 0) {
                $kondisi_baru = 'Rusak Berat';
            } elseif ($ringan_baru > 0) {
                $kondisi_baru = 'Rusak Ringan';
            } else {
                $kondisi_baru = 'Baik';
            }

            if ($baik === $jumlah_pinjam) {
                $kondisi_detail = 'Baik';
            } elseif ($ringan === $jumlah_pinjam) {
                $kondisi_detail = 'Rusak Ringan';
            } elseif ($berat === $jumlah_pinjam) {
                $kondisi_detail = 'Rusak Berat';
            } else {
                $kondisi_detail = 'Beragam';
            }

            // Simpan rincian kondisi barang yang dikembalikan.
            $stmt = jalankan(
                $conn,
                "INSERT INTO detail_pengembalian
                    (
                        id_pengembalian, id_barang, jumlah,
                        kondisi_barang, catatan,
                        jumlah_baik, jumlah_rusak_ringan,
                        jumlah_rusak_berat
                    )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                "iiissiii",
                [
                    $id_pengembalian,
                    $id_barang,
                    $jumlah_pinjam,
                    $kondisi_detail,
                    $catatan_item,
                    $baik,
                    $ringan,
                    $berat
                ]
            );

            mysqli_stmt_close($stmt);

            // Perbarui stok secara atomik.
            $stmt = jalankan(
                $conn,
                "UPDATE barang
                 SET jumlah = ?,
                     jumlah_baik = ?,
                     jumlah_rusak_ringan = ?,
                     jumlah_rusak_berat = ?,
                     kondisi = ?
                 WHERE id_barang = ?",
                "iiiisi",
                [
                    $jumlah_baru,
                    $baik_baru,
                    $ringan_baru,
                    $berat_baru,
                    $kondisi_baru,
                    $id_barang
                ]
            );

            mysqli_stmt_close($stmt);

            // Catat setiap pengembalian pada riwayat barang.
            $keterangan =
                "Pengembalian peminjaman ID {$id_dipilih}. " .
                "Jumlah: {$jumlah_pinjam}; Baik: {$baik}; " .
                "Rusak Ringan: {$ringan}; Rusak Berat: {$berat}.";

            if ($catatan_item !== '') {
                $keterangan .= " Catatan barang: " . $catatan_item;
            }

            if ($catatan_umum !== '') {
                $keterangan .= " Catatan umum: " . $catatan_umum;
            }

            $id_petugas = (int)$_SESSION['id_pengguna'];
            $kondisi_lama = $barang['kondisi'];

            $stmt = jalankan(
                $conn,
                "INSERT INTO riwayat_barang
                    (
                        id_barang, id_pengguna,
                        kondisi_sebelumnya, kondisi_baru, keterangan
                    )
                 VALUES (?, ?, ?, ?, ?)",
                "iisss",
                [
                    $id_barang,
                    $id_petugas,
                    $kondisi_lama,
                    $kondisi_baru,
                    $keterangan
                ]
            );

            mysqli_stmt_close($stmt);
        }

        // Ubah status hanya jika masih Disetujui.
        $stmt = jalankan(
            $conn,
            "UPDATE peminjaman
             SET status = 'Dikembalikan'
             WHERE id_peminjaman = ?
               AND status = 'Disetujui'",
            "i",
            [$id_dipilih]
        );

        $berhasil = mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);

        if (!$berhasil) {
            throw new Exception("Status peminjaman gagal diperbarui.");
        }

        mysqli_commit($conn);

        header("Location: index.php?status=berhasil");
        exit;

    } catch (Throwable $ex) {
        mysqli_rollback($conn);

        error_log("Pengembalian gagal: " . $ex->getMessage());

        $pesan = $ex instanceof mysqli_sql_exception
            ? "Pengembalian gagal disimpan. Periksa struktur database dan log PHP."
            : $ex->getMessage();
    }
}

/* =========================
   DATA PEMINJAMAN AKTIF
========================= */

$query = mysqli_query(
    $conn,
    "SELECT p.id_peminjaman, u.nama,
            p.tanggal_pinjam, p.tanggal_kembali
     FROM peminjaman p
     JOIN pengguna u ON p.id_pengguna = u.id_pengguna
     WHERE p.status = 'Disetujui'
       AND NOT EXISTS (
           SELECT 1
           FROM pengembalian pg
           WHERE pg.id_peminjaman = p.id_peminjaman
       )
     ORDER BY p.id_peminjaman DESC"
);

$daftar_peminjaman = [];

while ($row = mysqli_fetch_assoc($query)) {
    $daftar_peminjaman[] = $row;
}

mysqli_free_result($query);

$barang_per_peminjaman = [];

if ($daftar_peminjaman) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT d.id_peminjaman, b.id_barang,
                b.kode_inventaris, b.nama_barang, d.jumlah
         FROM detail_peminjaman d
         JOIN barang b ON d.id_barang = b.id_barang
         JOIN peminjaman p ON p.id_peminjaman = d.id_peminjaman
         WHERE p.status = 'Disetujui'
           AND NOT EXISTS (
               SELECT 1 FROM pengembalian pg
               WHERE pg.id_peminjaman = p.id_peminjaman
           )
         ORDER BY d.id_peminjaman DESC, b.nama_barang ASC"
    );

    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($hasil)) {
        $barang_per_peminjaman[(int)$row['id_peminjaman']][] = $row;
    }

    mysqli_stmt_close($stmt);
}

$ids_aktif = array_map(
    static fn($item) => (int)$item['id_peminjaman'],
    $daftar_peminjaman
);

if (!in_array($id_dipilih, $ids_aktif, true)) {
    $id_dipilih = 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catat Pengembalian | Inventaris Sekolah</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .pengembalian-wrapper { max-width:1000px; margin:0 auto; }
        .info-box,.barang-card,.empty-state {
            padding:18px; margin-bottom:18px;
            border:1px solid var(--border,#285044);
            border-radius:12px; background:var(--surface,#102b24);
        }
        .form-grid,.jumlah-kondisi {
            display:grid; grid-template-columns:repeat(2,minmax(0,1fr));
            gap:18px;
        }
        .form-group { display:flex; flex-direction:column; gap:8px; min-width:0; }
        .form-group label { font-weight:600; }
        .form-group input,.form-group select,.form-group textarea {
            box-sizing:border-box; width:100%; padding:11px 12px;
            color:var(--text,#f0f7f3);
            background:var(--surface-2,#17382e);
            border:1px solid var(--border,#285044);
            border-radius:9px; font:inherit;
        }
        .full-width { grid-column:1/-1; }
        .barang-section { margin-top:25px; padding-top:22px; border-top:1px solid var(--border,#285044); }
        .barang-pinjam { display:none; flex-direction:column; gap:16px; }
        .barang-pinjam.aktif { display:flex; }
        .jumlah-kondisi { grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
        .barang-meta { color:var(--text-muted,#a5bcb1); font-size:13px; margin-bottom:16px; }
        .total-info { padding:12px; margin-top:14px; background:var(--surface-2,#17382e); border-radius:9px; font-size:14px; }
        .total-info.benar { color:#86efac; }
        .total-info.salah,.alert-error { color:#fecaca; }
        .alert-error { padding:14px; margin-bottom:20px; background:rgba(239,68,68,.12); border-radius:10px; }
        .form-actions { display:flex; gap:12px; margin-top:25px; flex-wrap:wrap; }
        @media(max-width:700px) {
            .form-grid,.jumlah-kondisi { grid-template-columns:1fr; }
            .full-width { grid-column:auto; }
            .form-actions { flex-direction:column; }
        }
    </style>
</head>

<body>
<div class="main-content pengembalian-wrapper">
    <div class="page-header">
        <div>
            <h1>Catat Pengembalian Barang</h1>
            <p>Inventaris Barang Sekolah - SMK Negeri 2 Padang</p>
        </div>
    </div>

    <div class="info-box">
        <strong>Petunjuk pengembalian</strong><br>
        Isi jumlah barang dalam kondisi baik, rusak ringan, dan rusak berat.
        Totalnya harus sama dengan jumlah barang yang dipinjam.
        Stok diperbarui setelah data berhasil disimpan.
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert-error" role="alert"><?= e($pesan) ?></div>
    <?php endif; ?>

    <?php if ($daftar_peminjaman): ?>
        <div class="content-card">
            <form method="POST" id="formPengembalian">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="id_peminjaman">Peminjaman *</label>
                        <select name="id_peminjaman" id="id_peminjaman" required>
                            <option value="">-- Pilih Peminjaman --</option>
                            <?php foreach ($daftar_peminjaman as $p): ?>
                                <option
                                    value="<?= (int)$p['id_peminjaman'] ?>"
                                    <?= $id_dipilih === (int)$p['id_peminjaman'] ? 'selected' : '' ?>>
                                    ID <?= (int)$p['id_peminjaman'] ?> -
                                    <?= e($p['nama']) ?> |
                                    Pinjam: <?= e($p['tanggal_pinjam']) ?> |
                                    Batas: <?= e($p['tanggal_kembali']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_pengembalian">Tanggal Pengembalian *</label>
                        <input
                            type="date"
                            name="tanggal_pengembalian"
                            id="tanggal_pengembalian"
                            value="<?= e($tanggal_input) ?>"
                            max="<?= date('Y-m-d') ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="catatan">Catatan Umum</label>
                        <textarea name="catatan" id="catatan" rows="3"
                                  maxlength="1000"><?= e($catatan_umum) ?></textarea>
                    </div>
                </div>

                <div class="barang-section">
                    <h3>Jumlah Kondisi Setiap Barang</h3>
                    <p class="barang-meta">Isi angka 0 jika tidak ada barang dalam kondisi tersebut.</p>

                    <?php foreach ($barang_per_peminjaman as $id_pinjam => $items): ?>
                        <div class="barang-pinjam <?= $id_dipilih === (int)$id_pinjam ? 'aktif' : '' ?>"
                             data-peminjaman="<?= (int)$id_pinjam ?>">

                            <?php foreach ($items as $b): ?>
                                <?php
                                $id_b = (int)$b['id_barang'];
                                $jumlah_pinjam = (int)$b['jumlah'];
                                ?>

                                <div class="barang-card">
                                    <h4><?= e($b['nama_barang']) ?></h4>
                                    <div class="barang-meta">
                                        Kode: <?= e($b['kode_inventaris']) ?> ·
                                        Jumlah dipinjam: <strong><?= $jumlah_pinjam ?></strong> unit
                                    </div>

                                    <div class="jumlah-kondisi">
                                        <?php
                                        $kolom = [
                                            'baik' => ['Baik', 'jumlah_baik', $jumlah_baik_input],
                                            'rr' => ['Rusak Ringan', 'jumlah_rusak_ringan', $jumlah_rr_input],
                                            'rb' => ['Rusak Berat', 'jumlah_rusak_berat', $jumlah_rb_input]
                                        ];
                                        ?>

                                        <?php foreach ($kolom as $key => $data): ?>
                                            <div class="form-group">
                                                <label for="<?= $key ?>_<?= $id_b ?>"><?= $data[0] ?> *</label>
                                                <input
                                                    type="number"
                                                    id="<?= $key ?>_<?= $id_b ?>"
                                                    name="<?= $data[1] ?>[<?= $id_b ?>]"
                                                    class="input-kondisi"
                                                    data-total="<?= $jumlah_pinjam ?>"
                                                    min="0"
                                                    max="<?= $jumlah_pinjam ?>"
                                                    step="1"
                                                    value="<?= e($data[2][$id_b] ?? '') ?>"
                                                    required
                                                    <?= $id_dipilih === (int)$id_pinjam ? '' : 'disabled' ?>>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="total-info" aria-live="polite">
                                        Total kondisi: <strong class="total-terisi">0</strong>
                                        / <?= $jumlah_pinjam ?> unit
                                        <span class="status-total">— isi jumlah kondisi</span>
                                    </div>

                                    <div class="form-group" style="margin-top:16px">
                                        <label for="catatan_<?= $id_b ?>">Catatan Barang</label>
                                        <textarea
                                            id="catatan_<?= $id_b ?>"
                                            name="catatan_barang[<?= $id_b ?>]"
                                            rows="2"
                                            maxlength="1000"
                                            <?= $id_dipilih === (int)$id_pinjam ? '' : 'disabled' ?>><?= e($catatan_barang_input[$id_b] ?? '') ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Simpan Pengembalian</button>
                    <a href="index.php" class="btn btn-secondary">Kembali</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>Tidak Ada Peminjaman Aktif</h3>
            <p>Tidak ada peminjaman berstatus Disetujui yang menunggu pengembalian.</p>
            <a href="index.php" class="btn btn-secondary">Kembali</a>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('id_peminjaman');
    const kelompok = document.querySelectorAll('.barang-pinjam');
    const form = document.getElementById('formPengembalian');

    function hitungTotal(card) {
        const inputs = card.querySelectorAll('.input-kondisi');
        let total = 0;
        let lengkap = true;
        let valid = true;

        inputs.forEach(function (input) {
            if (input.value === '') {
                lengkap = false;
                return;
            }

            const nilai = Number(input.value);

            if (!Number.isSafeInteger(nilai) || nilai < 0 ||
                nilai > Number(input.max)) {
                valid = false;
            }

            total += nilai;
        });

        const target = Number(inputs[0].dataset.total);
        const info = card.querySelector('.total-info');

        card.querySelector('.total-terisi').textContent = total;
        info.classList.remove('benar', 'salah');

        const status = card.querySelector('.status-total');

        if (!lengkap) {
            status.textContent = '— isi semua jumlah kondisi';
            return false;
        }

        if (!valid) {
            status.textContent = '— jumlah tidak valid';
            info.classList.add('salah');
            return false;
        }

        if (total === target) {
            status.textContent = '— jumlah sudah sesuai';
            info.classList.add('benar');
            return true;
        }

        status.textContent = '— harus sama dengan ' + target + ' unit';
        info.classList.add('salah');
        return false;
    }

    function tampilkanBarang() {
        const id = select.value;

        kelompok.forEach(function (bagian) {
            const aktif = bagian.dataset.peminjaman === id;
            bagian.classList.toggle('aktif', aktif);

            bagian.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !aktif;
            });

            bagian.querySelectorAll('.barang-card').forEach(hitungTotal);
        });
    }

    document.querySelectorAll('.barang-card').forEach(function (card) {
        card.querySelectorAll('.input-kondisi').forEach(function (input) {
            input.addEventListener('input', function () {
                hitungTotal(card);
            });
        });
    });

    select.addEventListener('change', tampilkanBarang);

    if (form) {
        form.addEventListener('submit', function (event) {
            let valid = true;

            document.querySelectorAll('.barang-pinjam.aktif .barang-card')
                .forEach(function (card) {
                    if (!hitungTotal(card)) {
                        valid = false;
                    }
                });

            if (!valid) {
                event.preventDefault();
                alert(
                    'Pastikan total kondisi baik, rusak ringan, dan rusak berat ' +
                    'sama dengan jumlah barang yang dipinjam.'
                );
            }
        });
    }

    tampilkanBarang();
});
</script>
</body>
</html>
