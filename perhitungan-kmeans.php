<?php
// perhitungan-kmeans.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'header.php';
include 'app/koneksi.php';

// Fungsi untuk menghitung jarak Euclidean
function hitungJarak($data, $centroid) {
    $umur_data = $data['umur_kode'] ?? 0;
    $jk_data = $data['jk_kode'] ?? 0;
    $penyakit_data = $data['penyakit_kode'] ?? 0;

    $umur_centroid = $centroid['umur_kode'] ?? 0;
    $jk_centroid = $centroid['jk_kode'] ?? 0;
    $penyakit_centroid = $centroid['penyakit_kode'] ?? 0;

    return sqrt(
        pow($umur_data - $umur_centroid, 2) +
        pow($jk_data - $jk_centroid, 2) +
        pow($penyakit_data - $penyakit_centroid, 2)
    );
}

/**
 * PERBAIKAN: Fungsi baru untuk menerjemahkan kode penyakit kembali ke teks.
 * Ini memastikan nama penyakit yang benar selalu ditampilkan.
 * @param int $kode Kode penyakit.
 * @return string Nama penyakit.
 */
function kodeToPenyakit($kode) {
    $map = [
        1 => 'FLU', 2 => 'DIARE', 3 => 'BATUK', 4 => 'DEMAM', 5 => 'GIGI',
        6 => 'SAKIT KEPALA', 7 => 'CACAR AIR', 8 => 'BISULAN', 9 => 'AMBEIEN',
        10 => 'GATAL GATAL', 11 => 'SAKIT MATA', 12 => 'SAKIT PERUT',
        13 => 'SARIAWAN', 14 => 'SESAK NAFAS', 15 => 'SAKIT TENGGOROKAN'
    ];
    return $map[$kode] ?? 'Tidak Diketahui';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perhitungan K-Means Clustering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .card { box-shadow: 0 4px 8px rgba(0,0,0,0.05); border-radius: 10px; border: none; }
        .centroid-table th { background-color: #343a40; color: white; }
        .iterasi-header { background-color: #0d6efd; color: white; padding: 0.75rem 1.25rem; border-radius: 10px 10px 0 0; }
        .table-responsive { max-height: 400px; }
        .badge { font-size: 0.9em; }
        #manual-centroid-container .card { border: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="text-center mb-4">
            <h1 class="display-5 fw-bold text-primary"><i class="fas fa-calculator"></i> Perhitungan Algoritma K-Means</h1>
            <p class="lead text-muted">Proses Pengelompokan Data Pasien Secara Iteratif</p>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-cogs"></i> Pengaturan Perhitungan</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="perhitungan-kmeans.php#hasil-akhir" class="row g-3">
                    <div class="col-md-6">
                        <label for="jumlah_klaster" class="form-label"><strong>Jumlah Klaster (K)</strong></label>
                        <input type="number" class="form-control" id="jumlah_klaster" name="jumlah_klaster" value="<?= isset($_POST['jumlah_klaster']) ? $_POST['jumlah_klaster'] : 0 ?>" min="2" required>
                    </div>
                    <div class="col-md-6">
                         <label for="max_iterasi" class="form-label"><strong>Maksimum Iterasi</strong></label>
                        <input type="number" class="form-control" id="max_iterasi" name="max_iterasi" value="<?= isset($_POST['max_iterasi']) ? $_POST['max_iterasi'] : 0 ?>" min="1" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label"><strong>Metode Penentuan Centroid Awal</strong></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="centroid_method" id="metode_acak" value="acak" <?= (!isset($_POST['centroid_method']) || $_POST['centroid_method'] === 'acak') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="metode_acak">Acak (Otomatis)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="centroid_method" id="metode_manual" value="manual" <?= (isset($_POST['centroid_method']) && $_POST['centroid_method'] === 'manual') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="metode_manual">Manual</label>
                        </div>
                    </div>

                    <!-- Container untuk input centroid manual -->
                    <div class="col-12">
                        <div id="manual-centroid-container" class="row g-3 mt-2">
                           <!-- Input fields akan digenerate oleh JavaScript di sini -->
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" name="mulai_hitung" class="btn btn-primary btn-lg">
                            <i class="fas fa-play"></i> Mulai Perhitungan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php
        // Logika utama K-Means hanya berjalan jika form disubmit
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mulai_hitung'])) {
            $K = (int)$_POST['jumlah_klaster'];
            $max_iterasi = (int)$_POST['max_iterasi'];
            $centroid_method = $_POST['centroid_method'];

            // 1. Ambil semua data pra-proses dari database
            $data_points = [];
            $result = mysqli_query($koneksi, "SELECT * FROM data_praproses");
            
            if (!$result) {
                echo '<div class="alert alert-danger">Query gagal: ' . mysqli_error($koneksi) . '</div>';
            } else {
                while ($row = mysqli_fetch_assoc($result)) {
                    $data_points[$row['id']] = $row;
                }
            }

            if (count($data_points) < $K) {
                echo '<div class="alert alert-danger">Jumlah data kurang dari jumlah klaster yang diminta.</div>';
            } else {
                // 2. Inisialisasi Centroid Awal
                $centroids = [];
                if ($centroid_method === 'manual' && isset($_POST['manual_centroids'])) {
                    foreach ($_POST['manual_centroids'] as $c) {
                        $centroids[] = [
                            'umur_kode' => (float)$c['umur_kode'],
                            'jk_kode' => (float)$c['jk_kode'],
                            'penyakit_kode' => (float)$c['penyakit_kode']
                        ];
                    }
                } else { // Metode Acak (default)
                    $centroid_keys = array_rand($data_points, $K);
                    foreach ($centroid_keys as $key) {
                        $centroids[] = $data_points[$key];
                    }
                }

                $iterasi = 0;
                $is_converged = false;

                // Loop utama iterasi K-Means
                while (!$is_converged && $iterasi < $max_iterasi) {
                    $iterasi++;
                    $klaster_assignments = array_fill(0, $K, []);

                    echo "<div class='card mb-4'><div class='iterasi-header'><h4>Iterasi ke-$iterasi</h4></div><div class='card-body'>";
                    echo "<h5><i class='fas fa-map-marker-alt'></i> Posisi Centroid Awal Iterasi</h5>";
                    echo "<table class='table table-bordered table-sm centroid-table'><thead><tr><th>Centroid</th><th>Umur (Kode)</th><th>JK (Kode)</th><th>Penyakit (Kode)</th></tr></thead><tbody>";
                    foreach ($centroids as $i => $c) {
                        echo "<tr><td><strong>C" . ($i + 1) . "</strong></td><td>" . round($c['umur_kode'], 2) . "</td><td>" . round($c['jk_kode'], 2) . "</td><td>" . round($c['penyakit_kode'], 2) . "</td></tr>";
                    }
                    echo "</tbody></table>";

                    echo "<h5 class='mt-4'><i class='fas fa-ruler-combined'></i> Perhitungan Jarak & Pengelompokan</h5>";
                    echo "<div class='table-responsive'><table class='table table-striped table-sm'><thead><tr><th>ID Data</th><th>No. RM</th>";
                    for ($i = 1; $i <= $K; $i++) {
                        echo "<th>Jarak ke C{$i}</th>";
                    }
                    echo "<th>Klaster Terdekat</th></tr></thead><tbody>";

                    foreach ($data_points as $id => $point) {
                        $jarak_list = [];
                        foreach ($centroids as $c_id => $centroid) {
                            $jarak_list[$c_id] = hitungJarak($point, $centroid);
                        }
                        
                        $klaster_terdekat = array_keys($jarak_list, min($jarak_list))[0];
                        $klaster_assignments[$klaster_terdekat][] = $point;

                        echo "<tr><td>{$point['id']}</td><td><span class='badge bg-secondary'>" . htmlspecialchars($point['no_rm']) . "</span></td>";
                        foreach ($jarak_list as $jarak) {
                            echo "<td>" . round($jarak, 2) . "</td>";
                        }
                        echo "<td><span class='badge bg-info'>C" . ($klaster_terdekat + 1) . "</span></td></tr>";
                    }
                    echo "</tbody></table></div>";

                    $new_centroids = [];
                    foreach ($klaster_assignments as $k_id => $points_in_klaster) {
                        if (count($points_in_klaster) > 0) {
                            $sum_umur = array_sum(array_column($points_in_klaster, 'umur_kode'));
                            $sum_jk = array_sum(array_column($points_in_klaster, 'jk_kode'));
                            $sum_penyakit = array_sum(array_column($points_in_klaster, 'penyakit_kode'));
                            $count = count($points_in_klaster);
                            $new_centroids[$k_id] = ['umur_kode' => $sum_umur / $count, 'jk_kode' => $sum_jk / $count, 'penyakit_kode' => $sum_penyakit / $count];
                        } else {
                            $new_centroids[$k_id] = $centroids[$k_id];
                        }
                    }
                    
                    $is_converged = true;
                    for ($i = 0; $i < $K; $i++) {
                        if (hitungJarak($centroids[$i], $new_centroids[$i]) > 0.0001) {
                            $is_converged = false;
                            break;
                        }
                    }

                    $centroids = $new_centroids;
                    
                    echo "<h5 class='mt-4'><i class='fas fa-sync-alt'></i> Posisi Centroid Baru (Hasil Iterasi)</h5>";
                    echo "<table class='table table-bordered table-sm centroid-table'><thead><tr><th>Centroid</th><th>Umur (Kode)</th><th>JK (Kode)</th><th>Penyakit (Kode)</th></tr></thead><tbody>";
                    foreach ($centroids as $i => $c) {
                        echo "<tr><td><strong>C" . ($i + 1) . "</strong></td><td>" . round($c['umur_kode'], 2) . "</td><td>" . round($c['jk_kode'], 2) . "</td><td>" . round($c['penyakit_kode'], 2) . "</td></tr>";
                    }
                    echo "</tbody></table>";

                    if ($is_converged) {
                        echo "<div class='alert alert-success mt-4'><i class='fas fa-check-circle'></i> <strong>Konvergen!</strong> Proses clustering selesai pada iterasi ke-$iterasi.</div>";
                    }
                     echo "</div></div>";
                }

                $_SESSION['hasil_clustering_pdf'] = [
                    'klaster_assignments' => $klaster_assignments,
                    'centroids' => $centroids,
                    'iterasi' => $iterasi,
                    'K' => $K
                ];

                echo "<div class='card mb-4' id='hasil-akhir'>";
                echo "<div class='card-header bg-success text-white'><h4><i class='fas fa-trophy'></i> Hasil Akhir Pengelompokan</h4></div>";
                echo "<div class='card-body'>";
                
                mysqli_query($koneksi, "TRUNCATE TABLE hasil_klaster");
                $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO hasil_klaster (pasien_id, iterasi, klaster) VALUES (?, ?, ?)");

                foreach ($klaster_assignments as $k_id => $points_in_klaster) {
                    echo "<h5 class='mt-3'>Klaster " . ($k_id + 1) . " (" . count($points_in_klaster) . " Anggota)</h5>";
                    echo "<div class='table-responsive'><table class='table table-bordered table-sm'><thead><tr><th>ID Praproses</th><th>No. RM</th><th>Umur (Asli)</th><th>JK (Asli)</th><th>Penyakit (Asli)</th></tr></thead><tbody>";
                    foreach($points_in_klaster as $p) {
                         // PERBAIKAN: Menggunakan fungsi kodeToPenyakit untuk memastikan teks yang benar ditampilkan
                         $penyakit_asli_text = kodeToPenyakit($p['penyakit_kode']);
                         echo "<tr>
                                 <td>" . htmlspecialchars($p['id']) . "</td>
                                 <td>" . htmlspecialchars($p['no_rm']) . "</td>
                                 <td>" . htmlspecialchars($p['umur_asli']) . "</td>
                                 <td>" . htmlspecialchars($p['jk_asli']) . "</td>
                                 <td>" . htmlspecialchars($penyakit_asli_text) . "</td>
                               </tr>";
                         $klaster_db = $k_id + 1;
                         $pasien_id_db = $p['pasien_id'];
                         mysqli_stmt_bind_param($stmt_insert, "iii", $pasien_id_db, $iterasi, $klaster_db);
                         mysqli_stmt_execute($stmt_insert);
                    }
                    echo "</tbody></table></div>";
                }
                mysqli_stmt_close($stmt_insert);
                echo "</div>";
                echo "<div class='card-footer text-end'><a href='generate_pdf.php' target='_blank' class='btn btn-danger'><i class='fas fa-file-pdf'></i> Export to PDF</a></div>";
                echo "</div>";
            }
        }
        ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const kInput = document.getElementById('jumlah_klaster');
        const centroidMethodRadios = document.querySelectorAll('input[name="centroid_method"]');
        const manualContainer = document.getElementById('manual-centroid-container');

        function updateManualInputs() {
            const k = parseInt(kInput.value) || 0;
            manualContainer.innerHTML = '';

            const isManual = document.getElementById('metode_manual').checked;
            if (!isManual) {
                manualContainer.style.display = 'none';
                return;
            }
            
            manualContainer.style.display = 'flex';

            for (let i = 0; i < k; i++) {
                const col = document.createElement('div');
                col.className = 'col-md-4';
                
                col.innerHTML = `
                    <div class="card card-body">
                        <h6 class="mb-2">Centroid C${i + 1}</h6>
                        <div class="mb-2">
                            <label class="form-label-sm">Umur (Kode)</label>
                            <input type="number" step="any" class="form-control form-control-sm" name="manual_centroids[${i}][umur_kode]" placeholder="e.g., 2.5" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label-sm">JK (Kode)</label>
                            <input type="number" step="any" class="form-control form-control-sm" name="manual_centroids[${i}][jk_kode]" placeholder="e.g., 1.2" required>
                        </div>
                        <div>
                            <label class="form-label-sm">Penyakit (Kode)</label>
                            <input type="number" step="any" class="form-control form-control-sm" name="manual_centroids[${i}][penyakit_kode]" placeholder="e.g., 5.8" required>
                        </div>
                    </div>
                `;
                manualContainer.appendChild(col);
            }
        }

        kInput.addEventListener('input', updateManualInputs);
        centroidMethodRadios.forEach(radio => radio.addEventListener('change', updateManualInputs));
        updateManualInputs();
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
