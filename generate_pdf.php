<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Periksa apakah ada hasil clustering di session. Jika tidak, hentikan proses.
if (!isset($_SESSION['hasil_clustering_pdf'])) {
    die("Tidak ada data hasil clustering untuk diekspor. Silakan jalankan perhitungan K-Means terlebih dahulu.");
}

// Sertakan pustaka FPDF
require('fpdf/fpdf.php');

// Ambil data dari session
$hasil = $_SESSION['hasil_clustering_pdf'];
$klaster_assignments = $hasil['klaster_assignments'];
$centroids = $hasil['centroids'];
$iterasi = $hasil['iterasi'];
$K = $hasil['K'];

/**
 * PERBAIKAN: Fungsi untuk menerjemahkan kode penyakit kembali ke teks.
 * Fungsi ini ditambahkan di sini untuk memastikan PDF selalu menampilkan data yang benar.
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


// Buat class PDF kustom untuk header dan footer
class PDF extends FPDF
{
    // Header halaman
    function Header()
    {
        // Logo (opsional, hapus jika tidak ada)
        // $this->Image('logo.png',10,6,30);
        $this->SetFont('Arial','B',15);
        $this->Cell(80); // Pindah ke tengah
        $this->Cell(30,10,'Laporan Hasil Clustering K-Means',0,0,'C');
        $this->Ln(5);
        $this->SetFont('Arial','I',10);
        $this->Cell(80);
        $this->Cell(30,10,'Data Pasien Puskesmas',0,0,'C');
        $this->Ln(20);
    }

    // Footer halaman
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Halaman '.$this->PageNo().'/{nb}',0,0,'C');
    }

    // Fungsi untuk membuat tabel yang lebih baik
    function FancyTable($header, $data)
    {
        // Warna, lebar, dan font
        $this->SetFillColor(33, 150, 243); // Biru
        $this->SetTextColor(255);
        $this->SetDrawColor(128, 0, 0);
        $this->SetLineWidth(.3);
        $this->SetFont('','B');
        
        // Header
        $w = array(30, 35, 35, 90); // Lebar kolom disesuaikan
        for($i=0; $i<count($header); $i++)
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        $this->Ln();
        
        // Data
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('');
        
        $fill = false;
        foreach($data as $row)
        {
            // PERBAIKAN: Menggunakan fungsi kodeToPenyakit untuk mendapatkan teks penyakit
            $penyakit_teks = kodeToPenyakit($row['penyakit_kode']);

            $this->Cell($w[0], 6, $row['no_rm'], 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 6, $row['umur_asli'], 'LR', 0, 'L', $fill);
            $this->Cell($w[2], 6, $row['jk_asli'], 'LR', 0, 'L', $fill);
            $this->Cell($w[3], 6, $penyakit_teks, 'LR', 0, 'L', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

// Inisialisasi PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',12);

// Ringkasan Hasil
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0, 10, 'Ringkasan Perhitungan', 0, 1);
$pdf->SetFont('Arial','',12);
$pdf->Cell(50, 8, 'Jumlah Klaster (K)', 1, 0);
$pdf->Cell(0, 8, $K, 1, 1);
$pdf->Cell(50, 8, 'Total Iterasi', 1, 0);
$pdf->Cell(0, 8, $iterasi, 1, 1);
$pdf->Ln(10);

// Tabel Centroid Akhir
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0, 10, 'Posisi Centroid Akhir', 0, 1);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(40, 7, 'Centroid', 1, 0, 'C');
$pdf->Cell(50, 7, 'Umur (Kode)', 1, 0, 'C');
$pdf->Cell(50, 7, 'JK (Kode)', 1, 0, 'C');
$pdf->Cell(50, 7, 'Penyakit (Kode)', 1, 1, 'C');

$pdf->SetFont('Arial','',11);
foreach ($centroids as $i => $c) {
    $pdf->Cell(40, 7, 'C' . ($i + 1), 1, 0, 'C');
    $pdf->Cell(50, 7, round($c['umur_kode'], 2), 1, 0, 'C');
    $pdf->Cell(50, 7, round($c['jk_kode'], 2), 1, 0, 'C');
    $pdf->Cell(50, 7, round($c['penyakit_kode'], 2), 1, 1, 'C');
}
$pdf->Ln(10);

// Detail Anggota Setiap Klaster
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0, 10, 'Detail Anggota Klaster', 0, 1);

$header = array('No. RM', 'Umur', 'Jenis Kelamin', 'Penyakit');
foreach ($klaster_assignments as $k_id => $anggota) {
    if (empty($anggota)) continue;

    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0, 10, 'Klaster ' . ($k_id + 1) . ' (' . count($anggota) . ' Anggota)', 0, 1);
    $pdf->FancyTable($header, $anggota);
    $pdf->Ln(5);
}

// Output PDF
$pdf->Output('D', 'Laporan_Hasil_Clustering_KMeans.pdf');

?>
