<?php
global $conn;
include "database.php";

$sql = "SELECT p.gambar
        FROM produk p
        WHERE p.unggulan = 1 
        ORDER BY p.id_produk DESC
        LIMIT 4";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/x-icon" href="images/Logo_round.png" />
    <title>Beranda - WB Bouquet Shop</title>
    <script
        src="https://kit.fontawesome.com/9462eee823.js"
        crossorigin="anonymous"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
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
                <li><a href="index.php" class="active"><i class="fa-solid fa-house"></i>Beranda</a></li>
                <li><a href="order.php"><i class="fa-solid fa-gift"></i>Order</a></li>
                <li><a href="produk.php"><i class="fa-solid fa-store"></i>Produk </a></li>
            </ul>
        </nav>
    </header>
    <main>
        <section class="hero">
            <div class="hero-container">
                <div class="hero-image"
                    style="background-image: url('images/background_anime.png')"></div>
                <div class="hero-content">
                    <h1>Selamat Datang!</h1>
                    <p>
                        Setiap tangkai bunga kami adalah ungkapan cinta, harapan, dan
                        perhatian. Dari momen bahagia hingga saat penuh haru, kami hadir
                        untuk merangkai cerita Anda dalam keindahan yang tak terlupakan.
                    </p>
                    <div class="hero-buttons">
                        <a href="order.php" class="btn btn-outline"
                            style="border-color: var(--accent); color: var(--accent);">
                            <i class="fa-solid fa-gift"></i>Order Sekarang
                        </a>
                        <a href="produk.php" class="btn btn-primary" style="background: var(--accent); color: white;">
                            <i class="fa-solid fa-circle-info"></i>Lihat Semua Produk
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <h1 class="page-title" style="color: var(--primary);">Produk Unggulan Kami</h1>

        <div class="product-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="product-card showcase-only">
                        <div class="product-image">
                            <img class="showcase-img"
                                src="<?php echo htmlspecialchars($row['gambar']); ?>"
                                alt="<?php echo htmlspecialchars($row['nama_produk'] ?? 'Produk Unggulan'); ?>" />
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; width: 100%;">Ini belum ada produk unggulan, ngaturnya di Dashboard
                    Admin.</p>
            <?php endif; ?>
        </div>


        <section class="more-products">
            <div class="more-products-content">
                <h2>Temukan Lebih Banyak</h2>
                <p>
                    Jelajahi koleksi lengkap bouquet kami dengan berbagai pilihan
                    menarik
                </p>
                <a href="produk.php" class="btn btn-outline" style="border-color: var(--accent); color: var(--accent);">
                    <i class="fa-solid fa-right-to-bracket"></i>Jelajahi Semua Produk
                </a>
            </div>
        </section>
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
        <span class="close">&times;</span>
        <img class="modal-content" id="full-image">
    </div>
    <script src="js/index-script.js"></script>
    <?php
    $conn->close();
    ?>
</body>

</html>