<?php
require_once 'database.php';
define('TELEGRAM_BOT_TOKEN', '7870425994:AAHdBwDSwq3B84ERYSXa78YUMcG27azBrEc');
define('TELEGRAM_CHAT_ID', '6215262055');

function sendTelegramReceipt($caption, $photo_path)
{
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendPhoto";
    $absolute_photo_path = realpath($photo_path);
    if (!$absolute_photo_path || !file_exists($absolute_photo_path)) {
        return ['ok' => false, 'description' => 'File struk tidak ditemukan di server. Path: ' . $photo_path];
    }
    $post_fields = ['chat_id' => TELEGRAM_CHAT_ID, 'caption' => $caption, 'parse_mode' => 'Markdown', 'photo' => new CURLFile($absolute_photo_path)];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output, true);
}

function sendTelegramMediaGroup($photo_paths)
{
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMediaGroup";
    $media = [];
    $post_fields = ['chat_id' => TELEGRAM_CHAT_ID];

    foreach ($photo_paths as $i => $path) {
        $absolute_path = realpath($path);
        if ($absolute_path && file_exists($absolute_path)) {
            $file_key = 'file' . $i;
            $post_fields[$file_key] = new CURLFile($absolute_path);
            $media[] = ['type' => 'photo', 'media' => 'attach://' . $file_key];
        }
    }

    if (empty($media)) {
        return null;
    }

    $post_fields['media'] = json_encode($media);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output, true);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $conn->begin_transaction();
    try {
        $receipt_img_data = $_POST['receiptImage'];
        list(, $receipt_img_data) = explode(',', $receipt_img_data);
        $receipt_img_data = base64_decode($receipt_img_data);
        $receipt_dir = 'struk/';
        if (!is_dir($receipt_dir)) mkdir($receipt_dir, 0755, true);
        $receipt_filename = 'struk-' . date('Ymd-His') . '-' . uniqid() . '.png';
        $struk_path = $receipt_dir . $receipt_filename;
        if (!file_put_contents($struk_path, $receipt_img_data)) {
            throw new Exception("Gagal menyimpan file struk. Periksa izin folder 'struk'.");
        }

        $ref_image_paths = [];
        if (isset($_FILES['file-upload'])) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            foreach ($_FILES['file-upload']['name'] as $key => $name) {
                if ($_FILES['file-upload']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['file-upload']['tmp_name'][$key];
                    $ref_filename = 'ref-' . date('Ymd-His') . '-' . uniqid() . '-' . basename($name);
                    $ref_path = $upload_dir . $ref_filename;
                    if (move_uploaded_file($tmp_name, $ref_path)) {
                        $ref_image_paths[] = $ref_path;
                    }
                }
            }
        }
        $referensi_gambar_json = count($ref_image_paths) > 0 ? json_encode($ref_image_paths) : null;

        $id_pelanggan;
        $nomor_wa_clean = str_replace(['-', ' '], '', $_POST['whatsappNumber']);
        $nama_pelanggan = $_POST['fullName'];
        $alamat_lengkap = isset($_POST['alamat']) ? $_POST['alamat'] : null;
        $stmt_check = $conn->prepare("SELECT id_pelanggan FROM pelanggan WHERE nomor_wa = ?");
        $stmt_check->bind_param("s", $nomor_wa_clean);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        if ($result->num_rows > 0) {
            $pelanggan = $result->fetch_assoc();
            $id_pelanggan = $pelanggan['id_pelanggan'];
            $stmt_check->close();
            $stmt_update = $conn->prepare("UPDATE pelanggan SET nama_pelanggan = ?, alamat_lengkap = ? WHERE id_pelanggan = ?");
            $stmt_update->bind_param("ssi", $nama_pelanggan, $alamat_lengkap, $id_pelanggan);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            $stmt_check->close();
            $stmt_insert = $conn->prepare("INSERT INTO pelanggan (nama_pelanggan, nomor_wa, alamat_lengkap) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $nama_pelanggan, $nomor_wa_clean, $alamat_lengkap);
            $stmt_insert->execute();
            $id_pelanggan = $conn->insert_id;
            $stmt_insert->close();
        }

        $stmt_pesanan = $conn->prepare("INSERT INTO pesanan (id_pelanggan, tanggal_jadi, opsi_pengambilan, struk_path) VALUES (?, ?, ?, ?)");
        $opsi_pengambilan = $_POST['opsi_pengambilan'];
        $stmt_pesanan->bind_param("isss", $id_pelanggan, $_POST['tanggal-acara'], $opsi_pengambilan, $struk_path);
        $stmt_pesanan->execute();
        $id_pesanan = $conn->insert_id;
        $stmt_pesanan->close();

        $stmt_detail = $conn->prepare("INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, nuansa_warna, ukuran, referensi_gambar, kategori_harga, jenis_buket) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
        $jumlah = $_POST['jumlah_pesanan'];
        $nuansa = isset($_POST['nuansa_warna']) && !empty($_POST['nuansa_warna']) ? $_POST['nuansa_warna'] : null;
        $ukuran = isset($_POST['ukuran']) && !empty($_POST['ukuran']) ? $_POST['ukuran'] : null;
        $harga_text = $_POST['harga_mulai_text'];
        $jenis_buket = $_POST['jenis_buket'];
        $stmt_detail->bind_param("iisssss", $id_pesanan, $jumlah, $nuansa, $ukuran, $referensi_gambar_json, $harga_text, $jenis_buket);
        $stmt_detail->execute();
        $stmt_detail->close();

        $conn->commit();

        $nomor_wa_raw = $_POST['whatsappNumber'];
        $nomor_wa_digits = preg_replace('/\D/', '', $_POST['whatsappNumber']);
        $raw_confirmation_text = "Halo kak " . $_POST['fullName'] . ", terima kasih telah memesan " . $_POST['jenis_buket'] . " di Wiwid Bouquet Shop. Pesanan Anda sedang kami proses dan akan kami konfirmasi lebih lanjut ya. Terima kasih! 😊";
        $encoded_confirmation_text = urlencode($raw_confirmation_text);
        $wa_link_with_template = "https://wa.me/62" . $nomor_wa_digits . "?text=" . $encoded_confirmation_text;
        $telegram_caption = "*🔔 Pesanan Baru Masuk!*\n\n" . $_POST['orderDetailsText'] . "\n\n*Pesan Tambahan:*\n" . (empty($_POST['pesan']) ? "_Tidak ada_" : $_POST['pesan']) . "\n\n⬇️ *Balas Pesanan ke Pelanggan* ⬇️\n" . $wa_link_with_template;
        $telegram_result = sendTelegramReceipt($telegram_caption, $struk_path);

        if (!empty($ref_image_paths)) {
            sendTelegramMediaGroup($ref_image_paths);
        }

        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dikirim!', 'receiptUrl' => $struk_path, 'telegram_status' => $telegram_result]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()]);
    }

    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/x-icon" href="images/Logo_round.png" />
    <title>Order - WB Bouquet Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/order-style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/9462eee823.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>

<body>

    <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo_brand">
                <img src="images/Logo.png" alt="Logo" />
                <span>Wiwid Beauty | Bouquet Shop</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php"><i class="fa-solid fa-house"></i>Beranda</a></li>
                <li><a href="order.php" class="active"><i class="fa-solid fa-gift"></i>Order</a></li>
                <li><a href="produk.php"> <i class="fa-solid fa-store"></i>Produk </a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="form-container">
            <div class="form-header">
                <h1>Formulir Pemesanan</h1>
                <p>Silakan isi detail pesanan Anda di bawah ini.</p>
            </div>
            <form id="orderForm" novalidate>
                <div class="form-grid-wrapper">
                    <div class="form-grid grid-col-2">
                        <div class="form-group"><label for="fullName">Nama Lengkap</label><input type="text" id="fullName"
                                name="fullName"
                                class="input-field"
                                placeholder="Contoh: Naufal Abbad"
                                required
                                oninput="capitalizeName(this)">
                            <p class="error-message" id="fullName-error"></p>
                        </div>
                        <div class="form-group"><label for="whatsappNumber">Nomor WhatsApp</label>
                            <div class="input-group" id="whatsapp-group"><span class="prefix">+62</span><input type="tel"
                                    id="whatsappNumber"
                                    name="whatsappNumber"
                                    class="input-field"
                                    placeholder="812-3456-7890"
                                    required
                                    oninput="formatPhoneNumber(this)"
                                    maxlength="15">
                            </div>
                            <p class="error-message" id="whatsappNumber-error"></p>
                        </div>
                    </div>
                    <div class="form-grid grid-col-2">
                        <div class="form-group"><label for="kategori_barang">Kategori Produk</label><select
                                id="kategori_barang" name="kategori_barang" class="input-field" required>
                                <option value="" disabled selected>Pilih Kategori</option>
                                <option value="Buket Bunga">Buket Bunga</option>
                                <option value="Buket Balon">Buket Balon</option>
                                <option value="Hampers">Hampers</option>
                            </select>
                            <p class="error-message" id="kategori_barang-error"></p>
                        </div>
                        <div class="form-group" id="jenis_buket_group"><label for="jenis_buket">Jenis Produk</label><select
                                id="jenis_buket" name="jenis_buket" class="input-field" required disabled>
                                <option value="" disabled selected>Pilih Jenis</option>
                            </select>
                            <p class="error-message" id="jenis_buket-error"></p>
                        </div>
                    </div>
                    <div class="form-grid grid-col-3">
                        <div class="form-group"><label for="jumlah_pesanan">Jumlah</label><input type="number"
                                id="jumlah_pesanan"
                                name="jumlah_pesanan"
                                class="input-field" min="1"
                                value="1" required>
                            <p class="error-message" id="jumlah_pesanan-error"></p>
                        </div>
                        <div class="form-group" id="nuansa_warna_group"><label for="nuansa_warna">Nuansa Warna</label><input
                                type="text" id="nuansa_warna" name="nuansa_warna" class="input-field"
                                placeholder="Contoh: Pastel, Earth tone"></div>
                        <div class="form-group" id="ukuran_group"><label for="ukuran">Ukuran</label><select id="ukuran"
                                name="ukuran"
                                class="input-field"
                                required>
                                <option value="" disabled selected>Pilih Ukuran</option>
                                <option value="Small">Small</option>
                                <option value="large">Large</option>
                                <option value="Big">Big</option>
                            </select>
                            <p class="error-message" id="ukuran-error"></p>
                        </div>
                    </div>
                    <div class="form-grid grid-col-2">
                        <div class="form-group"><label for="harga_mulai">Kategori Harga</label><select id="harga_mulai"
                                name="harga_mulai"
                                class="input-field"
                                required>
                                <option value="" disabled selected>Pilih Kategori Harga</option>
                            </select>
                            <p class="error-message" id="harga_mulai-error"></p>
                        </div>
                        <div class="form-group"><label for="tanggal-acara">Untuk Tanggal Berapa</label><input type="date"
                                name="tanggal-acara"
                                id="tanggal-acara"
                                class="input-field"
                                required>
                            <p class="error-message" id="tanggal-acara-error"></p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Unggah Foto Referensi <span class="font-normal text-gray-400">(Opsional)</span></label>
                        <label for="file-upload" id="drop-zone"
                            class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg transition-colors duration-300 cursor-pointer">
                            <div id="upload-prompt" class="space-y-1 text-center pointer-events-none">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none"
                                    viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600 justify-center"><span
                                        class="relative bg-white rounded-md font-medium text-gray-600"><span>Pilih file</span></span>
                                    <p class="pl-1">atau tarik dan lepas</p>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF hingga 10MB</p>
                            </div>
                        </label>
                        <input id="file-upload" name="file-upload[]" type="file" class="sr-only" multiple accept="image/*">
                        <div id="preview-container" class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4"></div>
                    </div>
                    <div class="form-group">
                        <label>Opsi Pengambilan</label>
                        <div class="radio-options-group"><label class="radio-label"><input type="radio"
                                    name="opsi_pengambilan"
                                    value="Diambil Sendiri" checked>
                                <div class="custom-radio"></div>
                                <i class="fa-solid fa-store icon"></i><span>Diambil Sendiri</span>
                            </label><label
                                class="radio-label"><input type="radio" name="opsi_pengambilan" value="Diantar">
                                <div class="custom-radio"></div>
                                <i class="fa-solid fa-truck icon"></i><span>Diantar</span>
                            </label></div>
                    </div>
                    <div id="alamat-group" class="form-group" style="display: none;">
                        <div class="label-with-button">
                            <label for="alamat">Alamat Lengkap</label>
                            <button type="button" id="getLocationBtn">
                                <i class="fa-solid fa-location-crosshairs"></i>
                                <span>Gunakan Lokasi Saat Ini</span>
                                <i class="fa-solid fa-spinner"></i>
                            </button>
                        </div>
                        <textarea id="alamat" name="alamat" class="input-field"
                            placeholder="Ketik alamat Anda di sini atau gunakan tombol lokasi di atas..."></textarea>
                        <p class="error-message" id="alamat-error"></p>
                    </div>
                    <div class="form-group"><label for="pesan">Pesan (Opsional)</label><textarea id="pesan" name="pesan"
                            class="input-field"
                            placeholder="Tinggalkan catatan tambahan di sini jika ada..."></textarea>
                    </div>
                    <div class="flex justify-center">
                        <button type="submit" id="submit-btn" class="btn btn-outline"><i
                                class="fa-solid fa-paper-plane"></i>Kirim Pesanan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-column">
                <h3>Wiwid Bouquet</h3>
                <p>Merangkai cerita Anda dalam keindahan yang tak terlupakan. Temukan buket yang bukan hanya indah
                    dipandang, tapi juga hangat di hati.</p>
            </div>
            <div class="footer-column">
                <h3>Hubungi Kami</h3>
                <ul>
                    <li><a href="https://maps.app.goo.gl/HRthCoydBxJRTCFr8" target="_blank"><i
                                class="fa-solid fa-location-dot"></i><span>Kel No.RT. 01, RT.01/RW.TW. 03, Talok, Pojok, Kec. Garum, Kabupaten Blitar, Jawa Timur 66182</span></a>
                    </li>
                    <li><a href="tel:+6285736834477"><i class="fa-solid fa-phone"></i><span>+62 857-3683-4477</span></a>
                    </li>
                    <li><a href="https://www.instagram.com/wiwidbeauty/" target="_blank"><i
                                class="fab fa-instagram"></i><span>@wiwidbeauty</span></a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 Wiwid Beauty. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <div id="receipt-template"
        style="position: absolute; left: -9999px; top: 0; background: white; padding: 20px; width: 300px; font-family: 'Fira Code', monospace; font-size: 12px; border: 1px solid #ccc;">
        <h4 style="text-align: center; margin: 0 0 5px 0;">Wiwid Bouquet Shop</h4>
        <p style="text-align: center; margin: 0 0 10px 0; font-size: 10px;">Jl. Raya Talok, Garum, Blitar</p>
        <div id="receipt-details"></div>
        <div style="border-top: 1px dashed #000; margin: 10px 0;"></div>
        <div id="receipt-items"></div>
        <div style="border-top: 1px dashed #000; margin: 10px 0;"></div>
        <div style="text-align: center; font-size: 10px; margin-top: 10px;">--- Terima Kasih Telah Berbelanja ---<br>Struk
            ini adalah bukti pemesanan.
        </div>
    </div>

    <div id="confirmation-modal" class="modal-overlay">
        <div class="modal-content">
            <div id="modal-view-choice">
                <h2>Konfirmasi Pesanan</h2>
                <p>Pesanan Anda sudah siap dikirim ke admin. Lanjutkan?</p>
                <div class="modal-actions">
                    <button id="btn-confirm" class="btn btn-outline">Ya, Lanjutkan</button>
                    <button id="btn-cancel-modal" class="btn btn-cancel-modal">Batal</button>
                </div>
            </div>
            <div id="modal-view-loading" style="display: none;">
                <h2>Memproses Pesanan...</h2>
                <div class="spinner"></div>
                <p>Mohon tunggu sebentar...</p>
            </div>
            <div id="modal-view-success" style="display: none;">
                <h2>Berhasil!</h2>
                <p>Pesanan Anda telah terkirim. Admin kami akan segera menghubungi Anda via WhatsApp untuk konfirmasi.</p>
                <div id="receipt-image-container"></div>
            </div>
            <div id="modal-view-error" style="display: none;">
                <h2>Gagal</h2>
                <p id="error-text">Maaf, terjadi kesalahan.</p>
                <div class="modal-actions">
                    <button id="btn-close-error-modal" class="btn btn-primary">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div id="zoom-modal"><span id="close-zoom-btn">&times;</span><img id="zoomed-receipt-image"></div>
    <script src="js/order-script.js"></script>
</body>

</html>