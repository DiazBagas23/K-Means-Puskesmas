<?php
// pra-pemrosesan.php

// Menyertakan file header yang berisi navigasi dan tag <head> HTML
include 'header.php';
// Menyertakan file koneksi untuk berinteraksi dengan database
include 'app/koneksi.php';

// Inisialisasi variabel untuk menampung pesan notifikasi (sukses atau error)
$message = '';
$error = '';

// =================================================================
// FUNGSI-FUNGSI BANTU UNTUK PRA-PEMROSESAN
// Berisi fungsi untuk mengubah data mentah (teks) menjadi data numerik (kode).
// =================================================================

/**
 * Membersihkan string umur dan mengambil nilai numeriknya saja.
 * Contoh: "25 TAHUN" akan diubah menjadi 25.
 * @param string $umurStr String umur dari database.
 * @return int Umur dalam format integer.
 */
function bersihkanUmur($umurStr) {
    // Menggunakan regular expression untuk mencari angka di awal string.
    preg_match('/^\d+/', $umurStr, $matches);
    // Mengembalikan angka yang ditemukan, atau 0 jika tidak ada.
    return isset($matches[0]) ? (int)$matches[0] : 0;
}

/**
 * Mengkonversi umur (angka) ke dalam kode kategori.
 * Sesuai dengan aturan pada Tabel 4.2 di dokumen penelitian.
 * @param int $umur Umur pasien dalam format angka.
 * @return int Kode kategori umur.
 */
function umurToKode($umur) {
    $umur = (int)$umur;
    if ($umur >= 0 && $umur <= 10) return 1;
    if ($umur >= 11 && $umur <= 25) return 2;
    if ($umur >= 26 && $umur <= 40) return 3;
    return 4; // Untuk umur di atas 40
}

/**
 * Mengkonversi jenis kelamin (teks) ke dalam kode numerik.
 * Sesuai dengan aturan pada Tabel 4.3 di dokumen penelitian.
 * @param string $jk Jenis kelamin dalam format teks ('Laki-Laki' atau 'Perempuan').
 * @return int Kode jenis kelamin (1 untuk Laki-Laki, 2 untuk Perempuan).
 */
function jkToKode($jk) {
    // Memeriksa string (setelah diubah ke huruf kecil) dan mengembalikan kode yang sesuai.
    return (trim(strtolower($jk)) === 'laki-laki') ? 1 : 2;
}

/**
 * Mengkonversi nama penyakit (teks) ke dalam kode numerik.
 * Sesuai dengan aturan pada Tabel 4.4 di dokumen penelitian.
 * @param string $penyakit Nama penyakit dari database.
 * @return int Kode penyakit, atau 0 jika tidak ditemukan dalam pemetaan.
 */
function penyakitToKode($penyakit) {
    // Array asosiatif untuk memetakan nama penyakit ke kodenya.
    $penyakit_map = [
        'FLU' => 1, 'DIARE' => 2, 'BATUK' => 3, 'DEMAM' => 4, 'GIGI' => 5,
        'SAKIT KEPALA' => 6, 'CACAR AIR' => 7, 'BISULAN' => 8, 'AMBEIEN' => 9,
        'GATAL GATAL' => 10, 'SAKIT MATA' => 11, 'SAKIT PERUT' => 12,
        'SARIAWAN' => 13, 'SESAK NAFAS' => 14, 'SAKIT TENGGOROKAN' => 15
    ];
    // Mengubah input ke huruf besar dan menghapus spasi di awal/akhir agar cocok dengan kunci array.
    $penyakit_upper = strtoupper(trim($penyakit));
    // Mengembalikan kode jika ditemukan, jika tidak, kembalikan 0.
    return $penyakit_map[$penyakit_upper] ?? 0;
}

// =================================================================
// LOGIKA UTAMA SAAT TOMBOL "PROSES & TRANSFORMASI DATA" DITEKAN
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_data'])) {
    try {
        // 1. Kosongkan tabel `data_praproses` untuk memastikan data selalu yang terbaru.
        mysqli_query($koneksi, "TRUNCATE TABLE data_praproses");

        // 2. Ambil semua data dari tabel `pasien` (data mentah).
        $result_pasien = mysqli_query($koneksi, "SELECT * FROM pasien");
        if (!$result_pasien) {
            // Jika query gagal, lemparkan error untuk ditangkap oleh blok catch.
            throw new Exception("Gagal mengambil data pasien: " . mysqli_error($koneksi));
        }

        $jumlah_diproses = 0;
        // 3. Siapkan prepared statement untuk efisiensi dan keamanan saat memasukkan data berulang kali.
        $stmt = mysqli_prepare($koneksi, "INSERT INTO data_praproses (pasien_id, no_rm, umur_asli, jk_asli, penyakit_asli, umur_kode, jk_kode, penyakit_kode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        // 4. Loop melalui setiap baris data pasien yang telah diambil.
        while ($pasien = mysqli_fetch_assoc($result_pasien)) {
            // Lakukan pembersihan dan transformasi menggunakan fungsi-fungsi bantu.
            $umur_bersih = bersihkanUmur($pasien['umur']);
            $umur_kode = umurToKode($umur_bersih);
            $jk_kode = jkToKode($pasien['jk']);
            $penyakit_kode = penyakitToKode($pasien['penyakit']);

            // 5. Hanya proses dan simpan data jika penyakitnya dikenali (kodenya bukan 0).
            if ($penyakit_kode > 0) {
                 // Ikat variabel PHP ke parameter dalam prepared statement. 'isssiiii' mendefinisikan tipe data.
                 mysqli_stmt_bind_param($stmt, "isssiiii", 
                    $pasien['id'], $pasien['no_rm'], $pasien['umur'], 
                    $pasien['jk'], $pasien['penyakit'], $umur_kode, 
                    $jk_kode, $penyakit_kode
                );
                // Jalankan query insert.
                mysqli_stmt_execute($stmt);
                // Tambahkan penghitung untuk data yang berhasil diproses.
                $jumlah_diproses++;
            }
        }
        // Tutup statement setelah loop selesai.
        mysqli_stmt_close($stmt);
        // Siapkan pesan sukses untuk ditampilkan.
        $message = "Pra-pemrosesan selesai! Sebanyak $jumlah_diproses data berhasil ditransformasi dan disimpan.";
    } catch (Exception $e) {
        // Jika terjadi error di dalam blok try, tangkap dan tampilkan pesannya.
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// =================================================================
// MENGAMBIL DATA UNTUK DITAMPILKAN DI HALAMAN
// =================================================================

// Ambil jumlah total data mentah untuk ditampilkan di kartu statistik.
$total_data_mentah_result = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM pasien");
$total_data_mentah = mysqli_fetch_assoc($total_data_mentah_result)['total'];

// Ambil semua data yang sudah diproses dari tabel `data_praproses` untuk ditampilkan di tabel hasil.
$data_praproses = [];
$result_praproses = mysqli_query($koneksi, "SELECT * FROM data_praproses ORDER BY id ASC");
if ($result_praproses) {
    // Ambil semua hasil query sebagai array asosiatif.
    $data_praproses = mysqli_fetch_all($result_praproses, MYSQLI_ASSOC);
}
// Hitung jumlah data yang berhasil diproses untuk ditampilkan di kartu statistik.
$total_data_diproses = count($data_praproses);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pra-Pemrosesan Data</title>
    <!-- Memuat CSS dari Bootstrap dan Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Styling kustom untuk halaman ini -->
    <style>
        body { background-color: #f0f2f5; }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
        }
        .stat-card i { font-size: 2rem; opacity: 0.3; }
        .table-responsive { max-height: 600px; }
        .arrow-icon { font-size: 1.5rem; color: #0d6efd; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header Halaman -->
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold text-primary"><i class="fas fa-cogs"></i> Pra-Pemrosesan Data</h1>
            <p class="lead text-muted">Transformasi Data Mentah Menjadi Data Numerik Siap Analisis</p>
        </div>

        <!-- Bagian Notifikasi untuk menampilkan pesan sukses atau error -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Kolom Kiri: Kontrol dan Statistik -->
            <div class="col-lg-4">
                <!-- Kartu untuk memulai proses -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-play-circle"></i> Mulai Proses</h5>
                    </div>
                    <div class="card-body">
                        <p>Klik tombol di bawah untuk memulai transformasi data. Data yang ada di tabel pra-pemrosesan akan diganti dengan hasil yang baru.</p>
                        <form method="POST">
                            <!-- Tombol dinonaktifkan jika tidak ada data mentah untuk diproses -->
                            <button type="submit" name="proses_data" class="btn btn-primary w-100" <?= $total_data_mentah == 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-sync-alt"></i> Proses & Transformasi Data
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Kartu untuk menampilkan statistik proses -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistik Proses</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Total Data Mentah</span>
                            <span class="badge bg-secondary fs-6"><?= $total_data_mentah ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Data Berhasil Diproses</span>
                            <span class="badge bg-success fs-6"><?= $total_data_diproses ?></span>
                        </div>
                    </div>
                </div>

                <!-- Kartu untuk navigasi ke langkah selanjutnya -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-arrow-right"></i> Langkah Selanjutnya</h5>
                    </div>
                    <div class="card-body text-center">
                        <p>Setelah data siap, lanjutkan ke halaman perhitungan K-Means.</p>
                        <!-- Tombol dinonaktifkan jika tidak ada data yang sudah diproses -->
                        <a href="perhitungan-kmeans.php" class="btn btn-success <?= $total_data_diproses == 0 ? 'disabled' : '' ?>">
                            Lanjutkan ke Perhitungan <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Tabel Hasil Pra-Pemrosesan -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-table"></i> Hasil Transformasi Data</h5>
                        <span class="badge bg-light text-dark"><?= $total_data_diproses ?> Baris Data</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>No. RM</th>
                                        <th>Data Asli</th>
                                        <th class="text-center">Transformasi</th>
                                        <th>Hasil Kode (Numerik)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Jika ada data yang sudah diproses, tampilkan di tabel -->
                                    <?php if (!empty($data_praproses)): ?>
                                        <?php foreach ($data_praproses as $data): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($data['no_rm']) ?></span></td>
                                                <td>
                                                    <small class="d-block"><strong>Umur:</strong> <?= htmlspecialchars($data['umur_asli']) ?></small>
                                                    <small class="d-block"><strong>JK:</strong> <?= htmlspecialchars($data['jk_asli']) ?></small>
                                                    <small class="d-block"><strong>Penyakit:</strong> <?= htmlspecialchars($data['penyakit_asli']) ?></small>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <i class="fas fa-long-arrow-alt-right arrow-icon"></i>
                                                </td>
                                                <td>
                                                    <small class="d-block"><strong>Umur:</strong> <span class="badge bg-info"><?= $data['umur_kode'] ?></span></small>
                                                    <small class="d-block"><strong>JK:</strong> <span class="badge bg-warning text-dark"><?= $data['jk_kode'] ?></span></small>
                                                    <small class="d-block"><strong>Penyakit:</strong> <span class="badge bg-danger"><?= $data['penyakit_kode'] ?></span></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <!-- Jika tidak ada data, tampilkan pesan -->
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5">
                                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                                <h5 class="text-muted">Data belum diproses</h5>
                                                <p class="text-muted">Silakan klik tombol "Proses & Transformasi Data".</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Memuat JavaScript dari Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
