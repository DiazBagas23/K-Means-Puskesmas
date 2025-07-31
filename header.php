<?php
// Memulai sesi jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mendapatkan nama file halaman saat ini untuk menandai link yang aktif
$current_page = basename($_SERVER['PHP_SELF']);

// Mendefinisikan link navigasi dalam bentuk array agar mudah dikelola
$nav_links = [
    'index.php' => ['icon' => 'fas fa-chart-pie', 'text' => 'Dasbor'],
    'manajemen_data.php' => ['icon' => 'fas fa-database', 'text' => 'Manajemen Data'],
    'pra-pemrosesan.php' => ['icon' => 'fas fa-cogs', 'text' => 'Pra-Pemrosesan'],
    'perhitungan-kmeans.php' => ['icon' => 'fas fa-calculator', 'text' => 'Perhitungan K-Means'],
    'generate_pdf.php' => ['icon' => 'fas fa-file-pdf', 'text' => 'Generate PDF']
];
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-primary" href="index.php">
      <i class="fas fa-cubes"></i>
      K-Means Puskesmas
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php foreach ($nav_links as $file => $link): ?>
          <li class="nav-item">
            <a class="nav-link <?php if ($current_page === $file) echo 'active fw-bold'; ?>" href="<?= $file ?>">
              <i class="<?= $link['icon'] ?>"></i> <?= $link['text'] ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      
      <ul class="navbar-nav">
        <?php if (isset($_SESSION['login']) && isset($_SESSION['username'])): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($_SESSION['username']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
            <li><a class="dropdown-item" href="logot.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
          </ul>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
