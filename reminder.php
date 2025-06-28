<?php
$botToken = "7976375977:AAGtv9N6pNvG9vV5r0FlepMCQJcBCyYWv-0";
$chatId = "6215262055";

require_once 'database.php';

if (!isset($conn) || $conn->connect_error) {
    die("Koneksi ke database gagal. \$conn.");
}

$sql = "SELECT 
            p.id_pesanan,
            p.tanggal_jadi,
            pl.nama_pelanggan,
            pl.nomor_wa,
            dp.jumlah, 
            dp.nuansa_warna, 
            dp.ukuran, 
            dp.kategori_harga, 
            dp.jenis_buket
        FROM pesanan p
        JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
        JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
        WHERE 
            p.tanggal_jadi = CURDATE() + INTERVAL 1 DAY AND 
            p.status = 'Diproses'";

$result = $conn->query($sql);

if (!$result) {
    die("Query gagal: " . $conn->error);
}

if ($result->num_rows > 0) {
    $tanggal_besok = date('d F Y', strtotime('+1 day'));
    $pesanTelegram = "*Reminder Pesanan Siap Besok*\n\n";
    $pesanTelegram .= "Halo Admin, berikut adalah daftar pesanan yang harus disiapkan untuk tanggal *{$tanggal_besok}*:\n";
    
    while ($row = $result->fetch_assoc()) {
        $nomor_wa_clean = preg_replace('/\D/', '', $row['nomor_wa']);
        if (substr($nomor_wa_clean, 0, 1) === '0') {
            $nomor_wa_clean = substr($nomor_wa_clean, 1);
        }
        $wa_link = "https://wa.me/62" . $nomor_wa_clean;
        $tanggal_acara = date('d F Y', strtotime($row['tanggal_jadi']));

        $pesanTelegram .= "*Pesanan #{$row['id_pesanan']}*\n";
        $pesanTelegram .= "*Pelanggan:* {$row['nama_pelanggan']}\n";
        $pesanTelegram .= "*No. WA:* [{$row['nomor_wa']}]({$wa_link})\n";
        $pesanTelegram .= "*Tgl Acara:* {$tanggal_acara}\n\n";
        $pesanTelegram .= "*Detail Pesanan:*\n";
        $pesanTelegram .= "- *Produk:* {$row['jenis_buket']}\n";
        $pesanTelegram .= "- *Jumlah:* {$row['jumlah']}\n";
        
        if (!empty($row['ukuran'])) {
            $pesanTelegram .= "- *Ukuran:* {$row['ukuran']}\n";
        }
        if (!empty($row['nuansa_warna'])) {
            $pesanTelegram .= "- *Nuansa Warna:* {$row['nuansa_warna']}\n";
        }

        $pesanTelegram .= "- *Kategori Harga:* {$row['kategori_harga']}";
    }
    
    $pesanTelegram .= "\n\nHarap segera persiapkan pesanan tersebut. \nKetuk link dibawah untuk chat ke nomer pelanggan: ";

    $postData = [
        'chat_id' => $chatId,
        'text' => $pesanTelegram,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.telegram.org/bot{$botToken}/sendMessage",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, 
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "Gagal mengirim notifikasi (cURL Error): " . $error;
    } else {
        $responseData = json_decode($response);
        if ($responseData->ok) {
            echo "Notifikasi dikirim ke Telegram.";
        } else {
            echo "Gagal mengirim notifikasi!" . $responseData->description;
        }
    }

} else {
    $pesanKosong = "Pengecekan Selesai.\n\nTidak ada pesanan yang perlu disiapkan untuk besok.";
    $urlApi = "https://api.telegram.org/bot{$botToken}/sendMessage?chat_id={$chatId}&text=" . urlencode($pesanKosong);
    @file_get_contents($urlApi);
    echo "Tidak ada pesanan yang perlu disiapkan untuk besok. Notifikasi dikirim ke Telegram.";
}

$conn->close();
echo "\nSkrip selesai dijalankan.\n";

?>
