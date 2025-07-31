<?php
include 'header.php';
include 'app/koneksi.php';

$message = '';
$error = '';

// Fungsi untuk mengkonversi umur ke kode
function umurToKode($umur) {
    $umur = (int)$umur;
    if ($umur >= 0 && $umur <= 10) return 1;
    elseif ($umur >= 11 && $umur <= 25) return 2;
    elseif ($umur >= 26 && $umur <= 40) return 3;
    else return 4;
}

// Fungsi untuk mengkonversi penyakit ke kode
function penyakitToKode($penyakit) {
    $penyakit_map = [
        'FLU' => 1, 'DIARE' => 2, 'BATUK' => 3, 'DEMAM' => 4, 'GIGI' => 5,
        'SAKIT KEPALA' => 6, 'CACAR AIR' => 7, 'BISULAN' => 8, 'AMBEIEN' => 9,
        'GATAL GATAL' => 10, 'SAKIT MATA' => 11, 'SAKIT PERUT' => 12,
        'SARIAWAN' => 13, 'SESAK NAFAS' => 14, 'SAKIT TENGGOROKAN' => 15
    ];
    return $penyakit_map[$penyakit] ?? 0;
}

// Fungsi untuk mengkonversi jenis kelamin ke kode
function jkToKode($jk) {
    return $jk === 'Laki-Laki' ? 1 : 2;
}

// Fungsi untuk menyimpan hasil pra-pemrosesan
function simpanPraPemrosesan($koneksi, $pasien_id, $no_rm, $umur, $jk, $penyakit) {
    $umur_kode = umurToKode($umur);
    $penyakit_kode = penyakitToKode($penyakit);
    $jk_kode = jkToKode($jk);
    $vector_data = "[$umur_kode, $jk_kode, $penyakit_kode]";
    
    // Hapus data lama jika ada
    $stmt = mysqli_prepare($koneksi, "DELETE FROM hasil_pra_pemrosesan WHERE pasien_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $pasien_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Insert data baru
    $stmt = mysqli_prepare($koneksi, "INSERT INTO hasil_pra_pemrosesan (pasien_id, no_rm, umur_asli, umur_kode, jk_asli, jk_kode, penyakit_asli, penyakit_kode, vector_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "issisiss", $pasien_id, $no_rm, $umur, $umur_kode, $jk, $jk_kode, $penyakit, $penyakit_kode, $vector_data);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle CSV Upload
    if (isset($_POST['upload_csv']) && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $filename = $file['tmp_name'];
            
            if (($handle = fopen($filename, "r")) !== FALSE) {
                try {
                    // Truncate table before inserting new data
                    mysqli_query($koneksi, "TRUNCATE TABLE pasien");
                    mysqli_query($koneksi, "TRUNCATE TABLE hasil_pra_pemrosesan");
                    
                    $row = 0;
                    $inserted = 0;
                    
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $row++;
                        
                        // Skip header row
                        if ($row === 1) {
                            continue;
                        }
                        
                        if (count($data) >= 4) {
                            $no_rm = trim($data[0]);
                            $umur = trim($data[1]);
                            $jk = trim($data[2]);
                            $penyakit = trim($data[3]);
                            
                            // Normalize gender values
                            if (strtolower($jk) === 'laki-laki' || strtolower($jk) === 'l') {
                                $jk = 'Laki-Laki';
                            } elseif (strtolower($jk) === 'perempuan' || strtolower($jk) === 'p') {
                                $jk = 'Perempuan';
                            }
                            
                            if (!empty($no_rm) && !empty($umur) && !empty($jk) && !empty($penyakit)) {
                                $stmt = mysqli_prepare($koneksi, "INSERT INTO pasien (no_rm, umur, jk, penyakit) VALUES (?, ?, ?, ?)");
                                mysqli_stmt_bind_param($stmt, "siss", $no_rm, $umur, $jk, $penyakit);
                                if (mysqli_stmt_execute($stmt)) {
                                    $pasien_id = mysqli_insert_id($koneksi);
                                    simpanPraPemrosesan($koneksi, $pasien_id, $no_rm, $umur, $jk, $penyakit);
                                    $inserted++;
                                }
                                mysqli_stmt_close($stmt);
                            }
                        }
                    }
                    fclose($handle);
                    $message = "Berhasil mengupload dan menyimpan $inserted data dari file CSV!";
                } catch(Exception $e) {
                    $error = "Gagal memproses file CSV: " . $e->getMessage();
                }
            } else {
                $error = "Gagal membaca file CSV!";
            }
        } else {
            $error = "Error saat upload file!";
        }
    }
    
    // Handle Manual Add
    elseif (isset($_POST['add_manual'])) {
        $no_rm = trim($_POST['no_rm']);
        $umur = trim($_POST['umur']);
        $jk = $_POST['jk'];
        $penyakit = trim($_POST['penyakit']);
        
        if (!empty($no_rm) && !empty($umur) && !empty($jk) && !empty($penyakit)) {
            // Check if no_rm already exists
            $stmt = mysqli_prepare($koneksi, "SELECT id FROM pasien WHERE no_rm = ?");
            mysqli_stmt_bind_param($stmt, "s", $no_rm);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = "No. RM sudah ada dalam database!";
            } else {
                $stmt2 = mysqli_prepare($koneksi, "INSERT INTO pasien (no_rm, umur, jk, penyakit) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt2, "siss", $no_rm, $umur, $jk, $penyakit);
                if (mysqli_stmt_execute($stmt2)) {
                    $pasien_id = mysqli_insert_id($koneksi);
                    // Simpan hasil pra-pemrosesan
                    if (simpanPraPemrosesan($koneksi, $pasien_id, $no_rm, $umur, $jk, $penyakit)) {
                        $message = "Data berhasil ditambahkan dan hasil pra-pemrosesan disimpan!";
                    } else {
                        $message = "Data berhasil ditambahkan, tetapi gagal menyimpan pra-pemrosesan!";
                    }
                } else {
                    $error = "Gagal menambahkan data!";
                }
                mysqli_stmt_close($stmt2);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Semua field harus diisi!";
        }
    }
    
    // Handle Edit
    elseif (isset($_POST['edit_data'])) {
        $id = (int)$_POST['id'];
        $no_rm = trim($_POST['no_rm']);
        $umur = trim($_POST['umur']);
        $jk = $_POST['jk'];
        $penyakit = trim($_POST['penyakit']);
        
        if (!empty($no_rm) && !empty($umur) && !empty($jk) && !empty($penyakit)) {
            // Check if no_rm already exists for other records
            $stmt = mysqli_prepare($koneksi, "SELECT id FROM pasien WHERE no_rm = ? AND id != ?");
            mysqli_stmt_bind_param($stmt, "si", $no_rm, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = "No. RM sudah ada dalam database!";
            } else {
                $stmt2 = mysqli_prepare($koneksi, "UPDATE pasien SET no_rm = ?, umur = ?, jk = ?, penyakit = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt2, "sissi", $no_rm, $umur, $jk, $penyakit, $id);
                if (mysqli_stmt_execute($stmt2)) {
                    // Update hasil pra-pemrosesan
                    if (simpanPraPemrosesan($koneksi, $id, $no_rm, $umur, $jk, $penyakit)) {
                        $message = "Data berhasil diupdate dan hasil pra-pemrosesan diperbarui!";
                    } else {
                        $message = "Data berhasil diupdate, tetapi gagal memperbarui pra-pemrosesan!";
                    }
                } else {
                    $error = "Gagal mengupdate data!";
                }
                mysqli_stmt_close($stmt2);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Semua field harus diisi!";
        }
    }
}

// Handle GET requests (Delete operations)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = mysqli_prepare($koneksi, "DELETE FROM pasien WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Data berhasil dihapus!";
    } else {
        $error = "Gagal menghapus data!";
    }
    mysqli_stmt_close($stmt);
}

// Handle Delete All
if (isset($_GET['delete_all'])) {
    if (mysqli_query($koneksi, "TRUNCATE TABLE pasien")) {
        $message = "Semua data berhasil dihapus!";
    } else {
        $error = "Gagal menghapus semua data!";
    }
}

// Get data for editing
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM pasien WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Get all patients
$patients = [];
$stmt = mysqli_query($koneksi, "SELECT * FROM pasien ORDER BY created_at DESC");
if ($stmt) {
    while ($row = mysqli_fetch_assoc($stmt)) {
        $patients[] = $row;
    }
} else {
    $error = "Gagal mengambil data pasien: " . mysqli_error($koneksi);
}
$total_patients = count($patients);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Data Pasien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card { 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            border: none;
            border-radius: 10px;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
        }
        .table th { 
            background-color: #495057; 
            color: white; 
            border: none;
        }
        .btn-group .btn { 
            margin: 0 2px; 
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="text-center">
                    <h1 class="display-5 fw-bold text-primary">
                        <i class="fas fa-hospital-user"></i> Sistem Manajemen Data Pasien
                    </h1>
                    <p class="lead text-muted">Upload CSV, Input Manual, dan Kelola Data Pasien</p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Upload CSV Section -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-upload"></i> Upload File CSV</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">Pilih File CSV</label>
                                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                <div class="form-text">
                                    Format CSV: no_rm, umur, jk, penyakit<br>
                                    <strong>Peringatan:</strong> Upload akan menghapus semua data yang ada!
                                </div>
                            </div>
                            <button type="submit" name="upload_csv" class="btn btn-info">
                                <i class="fas fa-cloud-upload-alt"></i> Upload dan Replace Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Data Statistics -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistik Data</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <h3 class="text-primary"><?= $total_patients ?></h3>
                                <small class="text-muted">Total Pasien</small>
                            </div>
                            <div class="col-4">
                                <?php
                                $stmt = mysqli_query($koneksi, "SELECT COUNT(*) as count FROM pasien WHERE jk = 'Laki-Laki'");
                                $male_count = mysqli_fetch_assoc($stmt)['count'];
                                ?>
                                <h3 class="text-info"><?= $male_count ?></h3>
                                <small class="text-muted">Laki-Laki</small>
                            </div>
                            <div class="col-4">
                                <?php
                                $stmt = mysqli_query($koneksi, "SELECT COUNT(*) as count FROM pasien WHERE jk = 'Perempuan'");
                                $female_count = mysqli_fetch_assoc($stmt)['count'];
                                ?>
                                <h3 class="text-danger"><?= $female_count ?></h3>
                                <small class="text-muted">Perempuan</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Input Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-plus"></i> 
                            <?= $edit_data ? 'Edit Data Pasien' : 'Input Manual Data Pasien' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($edit_data): ?>
                                <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="no_rm" class="form-label">No. RM <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="no_rm" name="no_rm" 
                                               value="<?= $edit_data ? htmlspecialchars($edit_data['no_rm']) : '' ?>" 
                                               placeholder="Contoh: 12345" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="umur" class="form-label">Umur <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="umur" name="umur" 
                                               value="<?= $edit_data ? htmlspecialchars($edit_data['umur']) : '' ?>" 
                                               placeholder="Contoh: 25 Tahun" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="jk" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                        <select class="form-select" id="jk" name="jk" required>
                                            <option value="">Pilih JK</option>
                                            <option value="Laki-Laki" <?= ($edit_data && $edit_data['jk'] === 'Laki-Laki') ? 'selected' : '' ?>>
                                                Laki-Laki
                                            </option>
                                            <option value="Perempuan" <?= ($edit_data && $edit_data['jk'] === 'Perempuan') ? 'selected' : '' ?>>
                                                Perempuan
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="penyakit" class="form-label">Penyakit <span class="text-danger">*</span></label>
                                        <select class="form-select" id="penyakit" name="penyakit" required>
                                            <option value="">Pilih Penyakit</option>
                                            <?php
                                            $daftar_penyakit = [
                                                'FLU', 'DIARE', 'BATUK', 'DEMAM', 'GIGI', 'SAKIT KEPALA', 'CACAR AIR',
                                                'BISULAN', 'AMBEIEN', 'GATAL GATAL', 'SAKIT MATA', 'SAKIT PERUT',
                                                'SARIAWAN', 'SESAK NAFAS', 'SAKIT TENGGOROKAN'
                                            ];
                                            $penyakit_terpilih = $edit_data ? $edit_data['penyakit'] : '';
                                            foreach ($daftar_penyakit as $p) {
                                                $selected = ($penyakit_terpilih === $p) ? 'selected' : '';
                                                echo "<option value=\"$p\" $selected>$p</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <?php if ($edit_data): ?>
                                    <button type="submit" name="edit_data" class="btn btn-warning">
                                        <i class="fas fa-save"></i> Update Data
                                    </button>
                                    <a href="?" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Batal
                                    </a>
                                <?php else: ?>
                                    <button type="submit" name="add_manual" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Tambah Data
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-table"></i> Data Pasien (<?= $total_patients ?> data)</h5>
                        <?php if ($total_patients > 0): ?>
                            <a href="?delete_all=1" class="btn btn-danger btn-sm" 
                               onclick="return confirm('Yakin ingin menghapus SEMUA data? Tindakan ini tidak dapat dibatalkan!')">
                                <i class="fas fa-trash-alt"></i> Hapus Semua Data
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($total_patients > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>No. RM</th>
                                            <th>Umur</th>
                                            <th>Jenis Kelamin</th>
                                            <th>Penyakit</th>
                                            <th>Dibuat</th>
                                            <th width="150">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patients as $index => $patient): ?>
                                            <tr>
                                                <td><strong><?= $index + 1 ?></strong></td>
                                                <td>
                                                    <span class="badge bg-primary"><?= htmlspecialchars($patient['no_rm']) ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($patient['umur']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $patient['jk'] === 'Laki-Laki' ? 'info' : 'danger' ?>">
                                                        <i class="fas fa-<?= $patient['jk'] === 'Laki-Laki' ? 'mars' : 'venus' ?>"></i>
                                                        <?= htmlspecialchars($patient['jk']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($patient['penyakit']) ?></span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y H:i', strtotime($patient['created_at'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="?edit=<?= $patient['id'] ?>" 
                                                           class="btn btn-warning btn-sm" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?delete=<?= $patient['id'] ?>" 
                                                           class="btn btn-danger btn-sm" 
                                                           onclick="return confirm('Yakin ingin menghapus data pasien <?= htmlspecialchars($patient['no_rm']) ?>?')" 
                                                           title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada data pasien</h5>
                                <p class="text-muted">Silakan upload file CSV atau tambah data manual</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="text-center text-muted">
                    <p>&copy; <?= date('Y') ?> Sistem Manajemen Data Pasien. Dibuat dengan <i class="fas fa-heart text-danger"></i></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Confirm before delete all
        document.addEventListener('DOMContentLoaded', function() {
            var deleteAllBtn = document.querySelector('a[href*="delete_all"]');
            if (deleteAllBtn) {
                deleteAllBtn.addEventListener('click', function(e) {
                    if (!confirm('Yakin ingin menghapus SEMUA data? Tindakan ini tidak dapat dibatalkan!')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>