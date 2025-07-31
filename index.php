<?php
session_start();
// Jika pengguna belum login, arahkan ke halaman login
if (!isset($_SESSION['login'])) {
    header('Location: login.php');
    exit();
}
// Sertakan file header dan koneksi database
include 'header.php';
include 'app/koneksi.php';

// =================================================================
// Mengambil Data untuk Statistik dan Diagram dari Database
// =================================================================

// 1. Statistik Umum
// PERBAIKAN: Mengambil total jumlah pasien dari tabel data_praproses (data bersih)
$total_pasien_result = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM data_praproses");
$total_pasien = mysqli_fetch_assoc($total_pasien_result)['total'];

// Mengambil jumlah klaster unik yang terbentuk
$total_klaster_result = mysqli_query($koneksi, "SELECT COUNT(DISTINCT klaster) as total FROM hasil_klaster");
$total_klaster = mysqli_fetch_assoc($total_klaster_result)['total'];

// Mengambil jumlah iterasi maksimum dari perhitungan terakhir
$iterasi_result = mysqli_query($koneksi, "SELECT MAX(iterasi) as max_iterasi FROM hasil_klaster");
$iterasi = mysqli_fetch_assoc($iterasi_result)['max_iterasi'] ?? 0;

// 2. Data untuk Diagram Distribusi Klaster (Doughnut Chart)
$distribusi_klaster = [];
$query_distribusi = "SELECT klaster, COUNT(*) as jumlah FROM hasil_klaster GROUP BY klaster ORDER BY klaster ASC";
$result_distribusi = mysqli_query($koneksi, $query_distribusi);
if ($result_distribusi) {
    while($row = mysqli_fetch_assoc($result_distribusi)) {
        $distribusi_klaster[] = $row;
    }
}

// Konversi data ke format JSON yang bisa dibaca oleh Chart.js
$labels_pie = [];
$data_pie = [];
foreach ($distribusi_klaster as $dist) {
    $labels_pie[] = "Klaster " . $dist['klaster'];
    $data_pie[] = $dist['jumlah'];
}

// 3. Data untuk Detail Anggota Setiap Klaster (Accordion)
$detail_klaster = [];
if ($total_klaster > 0) {
    // Menggabungkan tabel hasil_klaster dan pasien untuk mendapatkan detail lengkap
    $query_detail = "
        SELECT 
            hk.klaster, 
            p.no_rm, 
            p.umur, 
            p.jk, 
            p.penyakit 
        FROM hasil_klaster hk
        JOIN pasien p ON hk.pasien_id = p.id
        ORDER BY hk.klaster, p.id
    ";
    $result_detail = mysqli_query($koneksi, $query_detail);
    if($result_detail){
        while($row = mysqli_fetch_assoc($result_detail)){
            // Kelompokkan data pasien berdasarkan ID klasternya
            $detail_klaster[$row['klaster']][] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor K-Means Clustering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f0f2f5; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.3;
        }
        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }
        .accordion-button:not(.collapsed) {
            color: #fff;
            background-color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold text-primary">
                <i class="fas fa-chart-pie"></i> Dasbor Analisis Klaster
            </h1>
            <p class="lead text-muted">Visualisasi Hasil Pengelompokan Pasien dengan K-Means</p>
        </div>

        <!-- Kartu Statistik Utama -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card p-3 stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Pasien (Telah Diproses)</h6>
                            <h2 class="fw-bold mb-0"><?= $total_pasien ?></h2>
                        </div>
                        <i class="fas fa-users text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Jumlah Klaster</h6>
                            <h2 class="fw-bold mb-0"><?= $total_klaster ?></h2>
                        </div>
                        <i class="fas fa-cubes text-success"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Iterasi</h6>
                            <h2 class="fw-bold mb-0"><?= $iterasi ?></h2>
                        </div>
                        <i class="fas fa-sync-alt text-warning"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visualisasi Diagram -->
        <div class="row g-4 mb-5">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0"><i class="fas fa-chart-pie text-info"></i> Distribusi Pasien per Klaster</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <?php if (!empty($data_pie)): ?>
                                <canvas id="distribusiKlasterChart"></canvas>
                            <?php else: ?>
                                <div class="d-flex justify-content-center align-items-center h-100 flex-column">
                                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Data hasil klaster tidak ditemukan.</p>
                                    <p class="text-muted small">Silakan jalankan perhitungan K-Means terlebih dahulu.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Anggota Klaster -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0"><i class="fas fa-users-cog text-danger"></i> Detail Anggota Setiap Klaster</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($detail_klaster)): ?>
                            <div class="accordion" id="accordionKlaster">
                                <?php foreach ($detail_klaster as $klaster_id => $anggota): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?= $klaster_id ?>">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $klaster_id ?>" aria-expanded="false" aria-controls="collapse<?= $klaster_id ?>">
                                                <strong>Klaster <?= $klaster_id ?></strong>&nbsp; (<?= count($anggota) ?> Anggota)
                                            </button>
                                        </h2>
                                        <div id="collapse<?= $klaster_id ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $klaster_id ?>" data-bs-parent="#accordionKlaster">
                                            <div class="accordion-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-hover mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>No. RM</th>
                                                                <th>Umur</th>
                                                                <th>Jenis Kelamin</th>
                                                                <th>Penyakit</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($anggota as $pasien): ?>
                                                                <tr>
                                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($pasien['no_rm']) ?></span></td>
                                                                    <td><?= htmlspecialchars($pasien['umur']) ?></td>
                                                                    <td><?= htmlspecialchars($pasien['jk']) ?></td>
                                                                    <td><?= htmlspecialchars($pasien['penyakit']) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                             <div class="text-center py-5">
                                <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada detail anggota klaster.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="py-4 mt-5 text-center text-muted">
            &copy; <?= date('Y') ?> K-Means Clustering Puskesmas | Dibuat dengan <i class="fas fa-heart text-danger"></i>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Ambil data yang sudah disiapkan oleh PHP
        const labelsPie = <?= json_encode($labels_pie) ?>;
        const dataPie = <?= json_encode($data_pie) ?>;

        // Hanya jalankan skrip jika ada data untuk ditampilkan
        if (labelsPie.length > 0) {
            const ctxPie = document.getElementById('distribusiKlasterChart').getContext('2d');
            new Chart(ctxPie, {
                type: 'doughnut', // Tipe diagram lingkaran
                data: {
                    labels: labelsPie,
                    datasets: [{
                        label: 'Jumlah Pasien',
                        data: dataPie,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 159, 64, 0.8)'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed + ' pasien';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    </script>
</body>
</html>
