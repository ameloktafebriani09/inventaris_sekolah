<?php
session_start();
require_once "../koneksi.php";

// Cek login dan hak akses
if (
    !isset($_SESSION['id_pengguna'], $_SESSION['role']) ||
    !in_array(
        $_SESSION['role'],
        ['pengelola_sarpras', 'kepala_sekolah', 'teknisi'],
        true
    )
) {
    header("Location: ../auth/login.php");
    exit;
}

// Ambil data barang inventaris
$query = mysqli_query($conn, "
    SELECT
        b.kode_inventaris,
        b.nama_barang,
        k.nama_kategori,
        l.nama_lokasi,
        b.jumlah,
        b.jumlah_baik,
        b.jumlah_rusak_ringan,
        b.jumlah_rusak_berat
    FROM barang b
    LEFT JOIN kategori k
        ON b.id_kategori = k.id_kategori
    LEFT JOIN lokasi l
        ON b.id_lokasi = l.id_lokasi
    ORDER BY b.nama_barang ASC
");

if (!$query) {
    error_log("Query cetak barang gagal: " . mysqli_error($conn));
    die("Data inventaris gagal dimuat. Periksa kolom tabel barang di database.");
}

// Fungsi mengamankan output
function aman($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Inisialisasi total
$total_jenis = mysqli_num_rows($query);
$total_barang = 0;
$total_baik = 0;
$total_rusak_ringan = 0;
$total_rusak_berat = 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cetak Daftar Inventaris Barang</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
        }

        .main-content {
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 26px;
        }

        .page-header p {
            margin: 6px 0 0;
            color: var(--text-muted);
        }

        .aksi {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .laporan {
            background: #fff;
            color: #222;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
        }

        .judul-laporan {
            text-align: center;
            line-height: 1.5;
        }

        .judul-laporan h2 {
            margin: 0;
            font-size: 21px;
        }

        .judul-laporan h3 {
            margin: 5px 0;
            font-size: 17px;
        }

        .garis-laporan {
            border: 0;
            border-top: 2px solid #222;
            margin: 15px 0 20px;
        }

        .info-laporan {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 12px;
            margin-bottom: 15px;
        }

        .tabel-laporan {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .tabel-laporan th,
        .tabel-laporan td {
            border: 1px solid #555;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }

        .tabel-laporan th {
            background: #e8eee9;
            color: #222;
            text-align: center;
        }

        .tengah {
            text-align: center !important;
        }

        .baris-total {
            font-weight: bold;
            background: #f1f1f1;
        }

        .ttd {
            margin-top: 40px;
            text-align: right;
            line-height: 1.7;
            page-break-inside: avoid;
            font-size: 13px;
        }

        .ttd strong {
            display: inline-block;
            margin-top: 65px;
            text-decoration: underline;
        }

        @media (max-width: 700px) {
            .main-content {
                padding: 16px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .aksi {
                width: 100%;
            }

            .aksi .btn {
                flex: 1;
                text-align: center;
            }

            .laporan {
                padding: 15px;
                overflow-x: auto;
            }

            .tabel-laporan {
                min-width: 850px;
            }
        }

        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }

            body {
                background: #fff !important;
                color: #222 !important;
                margin: 0 !important;
                min-height: auto !important;
            }

            .main-content {
                padding: 0 !important;
                margin: 0 !important;
            }

            .page-header,
            .aksi {
                display: none !important;
            }

            .laporan {
                padding: 0 !important;
                margin: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #222 !important;
                overflow: visible !important;
            }

            .tabel-laporan {
                width: 100% !important;
                min-width: 0 !important;
                margin-top: 15px;
            }

            .tabel-laporan th,
            .tabel-laporan td {
                padding: 6px;
                font-size: 9px;
                border: 1px solid #555 !important;
            }

            .tabel-laporan th {
                background: #e8eee9 !important;
                color: #222 !important;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .baris-total {
                background: #f1f1f1 !important;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .judul-laporan,
            .info-laporan,
            .ttd {
                color: #222 !important;
            }

            thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <div class="page-header">
        <div>
            <p class="eyebrow">INVENTARIS SEKOLAH</p>
            <h1>Cetak Daftar Inventaris</h1>
            <p>SMK Negeri 2 Padang</p>
        </div>

        <div class="aksi">
            <button
                type="button"
                class="btn btn-primary"
                onclick="cetakLaporan()"
            >
                Cetak / Simpan PDF
            </button>

            <a
                href="daftar_barang.php"
                class="btn btn-secondary"
            >
                Kembali
            </a>
        </div>
    </div>

    <div class="laporan">

        <div class="judul-laporan">
            <h2>SMK NEGERI 2 PADANG</h2>
            <h3>DAFTAR INVENTARIS BARANG SEKOLAH</h3>
        </div>

        <hr class="garis-laporan">

        <div class="info-laporan">
            <span>
                Jumlah jenis barang: <?= $total_jenis; ?>
            </span>

            <span>
                Tanggal cetak: <?= date('d-m-Y'); ?>
            </span>
        </div>

        <table class="tabel-laporan">
            <thead>
                <tr>
                    <th rowspan="2">No.</th>
                    <th rowspan="2">Kode Inventaris</th>
                    <th rowspan="2">Nama Barang</th>
                    <th rowspan="2">Kategori</th>
                    <th rowspan="2">Lokasi</th>
                    <th colspan="4">Rincian Kondisi Barang</th>
                </tr>
                <tr>
                    <th>Baik</th>
                    <th>Rusak Ringan</th>
                    <th>Rusak Berat</th>
                    <th>Total</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($total_jenis > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($barang = mysqli_fetch_assoc($query)): ?>

                        <?php
                        $baik = (int) $barang['jumlah_baik'];
                        $rusak_ringan = (int) $barang['jumlah_rusak_ringan'];
                        $rusak_berat = (int) $barang['jumlah_rusak_berat'];

                        $jumlah_kondisi =
                            $baik + $rusak_ringan + $rusak_berat;

                        $total_baik += $baik;
                        $total_rusak_ringan += $rusak_ringan;
                        $total_rusak_berat += $rusak_berat;
                        $total_barang += $jumlah_kondisi;
                        ?>

                        <tr>
                            <td class="tengah">
                                <?= $no++; ?>
                            </td>

                            <td>
                                <?= aman($barang['kode_inventaris']); ?>
                            </td>

                            <td>
                                <?= aman($barang['nama_barang']); ?>
                            </td>

                            <td>
                                <?= aman($barang['nama_kategori'] ?? '-'); ?>
                            </td>

                            <td>
                                <?= aman($barang['nama_lokasi'] ?? '-'); ?>
                            </td>

                            <td class="tengah"><?= $baik; ?></td>

                            <td class="tengah"><?= $rusak_ringan; ?></td>

                            <td class="tengah"><?= $rusak_berat; ?></td>

                            <td class="tengah"><?= $jumlah_kondisi; ?></td>
                        </tr>

                    <?php endwhile; ?>

                    <tr class="baris-total">
                        <td colspan="5" class="tengah">
                            TOTAL KESELURUHAN
                        </td>

                        <td class="tengah"><?= $total_baik; ?></td>

                        <td class="tengah"><?= $total_rusak_ringan; ?></td>

                        <td class="tengah"><?= $total_rusak_berat; ?></td>

                        <td class="tengah"><?= $total_barang; ?></td>
                    </tr>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="tengah">
                            Belum ada data barang.
                        </td>
                    </tr>

                <?php endif; ?>
            </tbody>
        </table>

        <div class="ttd">
            Padang, <?= date('d-m-Y'); ?>
            <br>
            Petugas Inventaris
            <br>
            <strong>________________________</strong>
        </div>

    </div>

</div>

<script>
function cetakLaporan() {
    window.print();
}
</script>

</body>
</html>

<?php
mysqli_free_result($query);
?>
