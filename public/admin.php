<?php
session_start();

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    header("Location: admin.php");
    exit;
}

$login_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === 'Woz' && $password === 'lW6iyO8AGRJcV2') {
        $_SESSION['admin_loggedin'] = true;
        $_SESSION['admin_username'] = $username;
        header("Location: admin.php");
        exit;
    } else {
        $login_error = "Username atau password salah!";
    }
}

if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] === true) {
    if (isset($_REQUEST['action']) && (!isset($_GET['action']) || $_GET['action'] != 'logout')) {
        header('Content-Type: application/json');
        require_once 'database.php';
        $action = $_REQUEST['action'];
        switch ($action) {
            case 'get_orders':
                $status = isset($_GET['status']) && $_GET['status'] === 'selesai' ? 'Selesai' : 'Diproses';
                $sql = "SELECT 
                            p.id_pesanan, p.tanggal_order, p.tanggal_jadi, p.opsi_pengambilan, p.struk_path, p.status,
                            pl.nama_pelanggan, pl.nomor_wa, pl.alamat_lengkap,
                            dp.jumlah, dp.nuansa_warna, dp.ukuran, dp.kategori_harga, dp.jenis_buket
                        FROM pesanan p
                        JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
                        JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
                        WHERE p.status = ?
                        ORDER BY p.tanggal_order ASC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $status);
                $stmt->execute();
                $result = $stmt->get_result();
                if (!$result) {
                    echo json_encode(['success' => false, 'message' => 'Query Gagal: ' . $conn->error]);
                    exit;
                }
                $orders = [];
                while ($row = $result->fetch_assoc()) {
                    $orders[] = $row;
                }
                echo json_encode(['success' => true, 'data' => $orders]);
                break;

            case 'complete_order':
                $data = json_decode(file_get_contents('php://input'), true);
                if (isset($data['id_pesanan'])) {
                    $id_pesanan = $data['id_pesanan'];
                    $stmt = $conn->prepare("UPDATE pesanan SET status = 'Selesai' WHERE id_pesanan = ?");
                    $stmt->bind_param("i", $id_pesanan);
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil ditandai sebagai selesai.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status pesanan.']);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'message' => 'ID pesanan tidak ditemukan.']);
                }
                break;

            case 'get_products':
                $sql = "SELECT p.id_produk, p.id_kategori, p.gambar, p.unggulan, k.nama_kategori 
                        FROM produk p 
                        JOIN kategori_produk k ON p.id_kategori = k.id_kategori 
                        ORDER BY p.unggulan DESC, p.id_produk DESC";
                $result = $conn->query($sql);
                $products = [];
                while ($row = $result->fetch_assoc()) {
                    $products[] = $row;
                }
                echo json_encode(['success' => true, 'data' => $products]);
                break;
            case 'get_categories':
                $sql = "SELECT k.id_kategori, k.nama_kategori FROM kategori_produk k ORDER BY k.nama_kategori";
                $result = $conn->query($sql);
                $categories = [];
                while ($row = $result->fetch_assoc()) {
                    $categories[] = $row;
                }
                echo json_encode(['success' => true, 'data' => $categories]);
                break;
            case 'add_product':
                if (isset($_POST['id_kategori']) && isset($_FILES['gambar'])) {
                    $id_kategori = $_POST['id_kategori'];
                    $file = $_FILES['gambar'];
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                    if (!in_array($file['type'], $allowedTypes)) {
                        echo json_encode(['success' => false, 'message' => 'Tipe file tidak valid.']);
                        exit;
                    }
                    $uploadDir = 'images/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0755, true);
                    $newFileName = uniqid() . '-' . basename($file['name']);
                    $newFilePath = $uploadDir . $newFileName;
                    if (move_uploaded_file($file['tmp_name'], $newFilePath)) {
                        $stmt = $conn->prepare("INSERT INTO produk (id_kategori, gambar, unggulan) VALUES (?, ?, 0)");
                        $stmt->bind_param("is", $id_kategori, $newFilePath);
                        if ($stmt->execute()) {
                            echo json_encode(['success' => true, 'message' => 'Produk baru berhasil ditambahkan.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database.']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Gagal upload file.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
                }
                break;
            case 'delete_product':
                $data = json_decode(file_get_contents('php://input'), true);
                if (isset($data['id_produk'])) {
                    $id_produk = $data['id_produk'];
                    $conn->begin_transaction();
                    try {
                        $stmt_get = $conn->prepare("SELECT gambar FROM produk WHERE id_produk = ?");
                        $stmt_get->bind_param("i", $id_produk);
                        $stmt_get->execute();
                        $result = $stmt_get->get_result();
                        if ($row = $result->fetch_assoc()) {
                            if (file_exists($row['gambar']) && strpos($row['gambar'], 'images/') === 0) {
                                unlink($row['gambar']);
                            }
                        }
                        $stmt_get->close();
                        $stmt_delete = $conn->prepare("DELETE FROM produk WHERE id_produk = ?");
                        $stmt_delete->bind_param("i", $id_produk);
                        $stmt_delete->execute();
                        $stmt_delete->close();
                        $conn->commit();
                        echo json_encode(['success' => true, 'message' => 'Produk berhasil dihapus.']);
                    } catch (Exception $e) {
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Gagal menghapus produk: ' . $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'ID produk tidak ditemukan.']);
                }
                break;
            case 'toggle_featured':
                if (isset($_POST['id_produk'], $_POST['unggulan'])) {
                    $id_produk = $_POST['id_produk'];
                    $unggulan = $_POST['unggulan'];
                    $stmt = $conn->prepare("UPDATE produk SET unggulan = ? WHERE id_produk = ?");
                    $stmt->bind_param("ii", $unggulan, $id_produk);
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Status unggulan diperbarui.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Gagal update status.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
                }
                break;
            case 'swap_products':
                $data = json_decode(file_get_contents('php://input'), true);
                if (isset($data['sourceId'], $data['targetId'])) {
                    $sourceId = $data['sourceId'];
                    $targetId = $data['targetId'];
                    $conn->begin_transaction();
                    try {
                        $stmt_get_source = $conn->prepare("SELECT id_kategori, gambar, unggulan FROM produk WHERE id_produk = ?");
                        $stmt_get_source->bind_param("i", $sourceId);
                        $stmt_get_source->execute();
                        $source_data = $stmt_get_source->get_result()->fetch_assoc();
                        $stmt_get_source->close();
                        $stmt_get_target = $conn->prepare("SELECT id_kategori, gambar, unggulan FROM produk WHERE id_produk = ?");
                        $stmt_get_target->bind_param("i", $targetId);
                        $stmt_get_target->execute();
                        $target_data = $stmt_get_target->get_result()->fetch_assoc();
                        $stmt_get_target->close();
                        if ($source_data && $target_data) {
                            $stmt_update_source = $conn->prepare("UPDATE produk SET id_kategori = ?, gambar = ?, unggulan = ? WHERE id_produk = ?");
                            $stmt_update_source->bind_param("isii", $target_data['id_kategori'], $target_data['gambar'], $target_data['unggulan'], $sourceId);
                            $stmt_update_source->execute();
                            $stmt_update_source->close();
                            $stmt_update_target = $conn->prepare("UPDATE produk SET id_kategori = ?, gambar = ?, unggulan = ? WHERE id_produk = ?");
                            $stmt_update_target->bind_param("isii", $source_data['id_kategori'], $source_data['gambar'], $source_data['unggulan'], $targetId);
                            $stmt_update_target->execute();
                            $stmt_update_target->close();
                            $conn->commit();
                            echo json_encode(['success' => true, 'message' => 'Produk berhasil ditukar.']);
                        } else {
                            throw new Exception('Salah satu produk tidak ditemukan.');
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Gagal menukar produk: ' . $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'ID produk tidak lengkap.']);
                }
                break;
            case 'update_image':
                if (isset($_POST['id_produk']) && isset($_FILES['gambar'])) {
                    $id_produk = $_POST['id_produk'];
                    $file = $_FILES['gambar'];
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                    if (!in_array($file['type'], $allowedTypes)) {
                        echo json_encode(['success' => false, 'message' => 'Tipe file tidak valid.']);
                        exit;
                    }
                    $uploadDir = 'images/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0755, true);
                    $newFileName = uniqid() . '-' . basename($file['name']);
                    $newFilePath = $uploadDir . $newFileName;
                    if (move_uploaded_file($file['tmp_name'], $newFilePath)) {
                        $stmt = $conn->prepare("UPDATE produk SET gambar = ? WHERE id_produk = ?");
                        $stmt->bind_param("si", $newFilePath, $id_produk);
                        if ($stmt->execute()) {
                            echo json_encode(['success' => true, 'new_path' => $newFilePath]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Gagal update database.']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Gagal upload file.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
                }
                break;
            case 'update_category':
                if (isset($_POST['id_produk']) && isset($_POST['id_kategori'])) {
                    $id_produk = $_POST['id_produk'];
                    $id_kategori = $_POST['id_kategori'];
                    $stmt = $conn->prepare("UPDATE produk SET id_kategori = ? WHERE id_produk = ?");
                    $stmt->bind_param("ii", $id_kategori, $id_produk);
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Kategori berhasil diperbarui.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Gagal update kategori.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
                }
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
                break;
        }
        $conn->close();
        exit;
    }
}

if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] === true):
?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Dashboard Admin - WB Bouquet Shop</title>
        <link rel="icon" type="image/x-icon" href="images/Logo_round.png" />
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/admin-style.css">
        <script src="https://kit.fontawesome.com/9462eee823.js" crossorigin="anonymous"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
            rel="stylesheet" />
    </head>

    <body>
        <main>
            <div class="dashboard-container">
                <div class="dashboard-header">
                    <div class="dashboard-header-top">
                        <div>
                            <h1>Selamat Datang, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</h1>
                            <p id="current-date">Memuat tanggal...</p>
                        </div>
                        <a href="admin.php?action=logout" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i>
                            Logout</a>
                    </div>
                    <section class="stat-cards">
                        <div class="stat-card-content"><i class="fa-solid fa-spinner"></i>
                            <div class="stat-card-info">
                                <h3 id="pesanan-aktif-count">0</h3>
                                <p>Pesanan Aktif</p>
                            </div>
                        </div>
                        <div class="stat-card-content"><i class="fa-solid fa-gifts"></i>
                            <div class="stat-card-info">
                                <h3 id="produk-count">0</h3>
                                <p>Total Produk</p>
                            </div>
                        </div>
                        <div class="stat-card-content"><i class="fa-solid fa-star"></i>
                            <div class="stat-card-info">
                                <h3 id="unggulan-count">0</h3>
                                <p>Produk Unggulan</p>
                            </div>
                        </div>
                    </section>
                </div>

                <nav class="dashboard-nav">
                    <button class="tab-btn active" data-tab="pesanan">Log Pesanan</button>
                    <button class="tab-btn" data-tab="produk">Manajemen Produk</button>
                </nav>

                <div id="panel-pesanan" class="dashboard-panel active">
                    <div class="card">
                        <div class="card-content">
                            <nav class="sub-nav" id="pesanan-sub-nav">
                                <button class="sub-tab-btn active" data-subtab="pesanan-aktif">Pesanan Aktif</button>
                                <button class="sub-tab-btn" data-subtab="pesanan-selesai">Riwayat Pesanan Selesai</button>
                            </nav>

                            <div id="subpanel-pesanan-aktif" class="sub-panel active" style="overflow-x: auto;">
                                <p>Memuat pesanan aktif...</p>
                            </div>

                            <div id="subpanel-pesanan-selesai" class="sub-panel" style="overflow-x: auto;">
                                <p>Memuat riwayat pesanan...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="panel-produk" class="dashboard-panel">
                    <div class="card">
                        <div class="card-content">
                            <nav class="sub-nav" id="produk-sub-nav">
                                <button class="sub-tab-btn active" data-subtab="daftar">Daftar Produk</button>
                                <button class="sub-tab-btn" data-subtab="tambah">Menambahkan Produk</button>
                            </nav>
                            <div id="subpanel-daftar" class="sub-panel active">
                                <p style="margin-top:0; margin-bottom: 1.5rem; color: #6c757d; font-style: italic;">Drag &
                                    drop gambar untuk menukar posisi/urutan produk.</p>
                                <div id="product-list-container">
                                    <p>Memuat produk...</p>
                                </div>
                            </div>
                            <div id="subpanel-tambah" class="sub-panel">
                                <p style="margin-top:0; margin-bottom: 1.5rem; color: #6c757d; font-style: italic;">Gambar
                                    akan ter-upload di folder /images/ pada File Manager Hosting Anda.</p>
                                <form id="add-product-form" style="margin-top:1.5rem;">
                                    <div class="form-group"><label for="new-product-category">Pilih Kategori</label><select
                                            id="new-product-category" required></select></div>
                                    <div class="form-group"><label>Upload Gambar</label><input type="file"
                                            id="new-product-image" accept="image/*" required>
                                    </div>
                                    <div class="hero-buttons"> <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i>Tambah Produk</button></div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="image-viewer" class="modal"><span class="close">&times;</span><img class="modal-content-image"
                id="full-image"></div>
        <script src="js/admin-script.js"></script>
    </body>

    </html>
<?php else: ?>
    <?php //
    ?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Login Admin - WB Bouquet Shop</title>
        <link rel="icon" type="image/x-icon" href="images/Logo_round.png" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
            rel="stylesheet" />
        <style>
            :root {
                --primary: #240046;
                --accent: #5a189a;
                --white: #ffffff;
            }

            body,
            html {
                margin: 0;
                padding: 0;
                height: 100%;
                font-family: 'Inter', sans-serif;
                overflow: hidden;
            }

            .login-page-wrapper {
                height: 100%;
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                background-image: url('images/background_anime.png');
                background-size: cover;
                background-position: center;
            }

            .login-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.4);
                backdrop-filter: blur(8px);
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .login-box {
                position: relative;
                z-index: 1;
                background: rgba(255, 255, 255, 0.95);
                padding: 2.5rem;
                border-radius: 15px;
                box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
                border: 1px solid rgba(255, 255, 255, 0.18);
                width: 100%;
                max-width: 400px;
                text-align: center;
            }

            .login-box h2 {
                color: var(--primary);
                margin-bottom: 0.5rem;
            }

            .login-box p {
                color: #666;
                margin-bottom: 1.5rem;
            }

            .form-group {
                margin-bottom: 1.5rem;
                text-align: left;
            }

            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
            }

            .form-group input {
                width: 100%;
                padding: 12px 15px;
                border: 1px solid #ccc;
                border-radius: 8px;
                box-sizing: border-box;
            }

            .error-message {
                color: #D8000C;
                background-color: #FFD2D2;
                border: 1px solid #D8000C;
                border-radius: 5px;
                padding: 10px;
                margin-top: 1rem;
                display:
                    <?php echo empty($login_error) ? 'none' : 'block'; ?>;
            }

            .btn-login {
                width: 100%;
                padding: 12px;
                border: none;
                border-radius: 8px;
                background-color: var(--accent);
                color: var(--white);
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: background-color 0.2s;
            }

            .btn-login:hover {
                background-color: var(--primary);
            }
        </style>
    </head>

    <body>
        <div class="login-page-wrapper">
            <div class="login-overlay">
                <div class="login-box">
                    <h2>Admin Login</h2>
                    <p>Silakan masuk untuk mengakses dashboard.</p>
                    <form id="login-form" method="POST" action="admin.php">
                        <div class="form-group"><label for="username">Username</label><input type="text" id="username"
                                name="username" required></div>
                        <div class="form-group"><label for="password">Password</label><input type="password" id="password"
                                name="password" required></div>
                        <?php if (!empty($login_error)): ?>
                            <p class="error-message"><?php echo $login_error; ?></p><?php endif; ?>
                        <button type="submit" class="btn-login">Login</button>
                    </form>
                </div>
            </div>
        </div>
        <script src="js/admin-script.js"></script>
    </body>

    </html>
<?php endif; ?>