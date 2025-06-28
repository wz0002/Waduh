<?php
require_once 'database.php';
function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

$categories_sql = "SELECT DISTINCT k.nama_kategori 
                   FROM kategori_produk k
                   JOIN produk p ON k.id_kategori = p.id_kategori
                   ORDER BY k.nama_kategori ASC";
$categories_result = $conn->query($categories_sql);
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$products_sql = "SELECT p.gambar, k.nama_kategori 
                 FROM produk p 
                 JOIN kategori_produk k ON p.id_kategori = k.id_kategori 
                 ORDER BY p.unggulan DESC, p.id_produk DESC";
$products_result = $conn->query($products_sql);
$products = [];
if ($products_result) {
    while ($row = $products_result->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/x-icon" href="images/Logo_round.png" />
    <title>Produk - WB Bouquet Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/produk-style.css">
    <script src="https://kit.fontawesome.com/9462eee823.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
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
                <li><a href="order.php"><i class="fa-solid fa-gift"></i>Order</a></li>
                <li><a href="produk.php" class="active"><i class="fa-solid fa-store"></i>Produk</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h1 class="page-title">Galeri Produk Kami</h1>
        <p class="page-subtitle">Temukan inspirasi buket dan hampers impian Anda dari koleksi terbaik kami. Klik gambar
            untuk melihat lebih detail.</p>
        <div class="category-filter">
            <button class="category-btn active" data-category="all">Semua</button>
            <?php
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    $category_name = htmlspecialchars($category['nama_kategori']);
                    $category_slug = slugify($category_name);
                    echo '<button class="category-btn" data-category="' . $category_slug . '">' . $category_name . '</button>';
                }
            }
            ?>
        </div>

        <div class="product-grid">
            <?php
            if (!empty($products)) {
                foreach ($products as $product) {
                    $category_name = htmlspecialchars($product['nama_kategori']);
                    $category_slug = slugify($category_name);
                    $image_path = htmlspecialchars($product['gambar']);

                    echo '<div class="product-card" data-category="' . $category_slug . '">';
                    echo '    <div class="product-image">';
                    echo '        <img class="showcase-img" src="' . $image_path . '" alt="' . $category_name . '"/>';
                    echo '    </div>';
                    echo '</div>';
                }
            } else {
                echo "<p>Belum ada produk untuk ditampilkan.</p>";
            }
            ?>
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
    <div id="image-viewer">
        <span class="close">×</span>
        <img class="modal-content" id="full-image">
    </div>
    <script src="js/produk-script.js"></script>
</body>

</html>