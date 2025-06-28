document.addEventListener("DOMContentLoaded", () => {
  let activeOrdersCache = [];
  let completedOrdersCache = [];
  let productsCache = [];
  let categoriesCache = [];
  let draggedItem = null;

  const activeOrderContainer = document.getElementById(
    "subpanel-pesanan-aktif"
  );
  const completedOrderContainer = document.getElementById(
    "subpanel-pesanan-selesai"
  );
  const productListContainer = document.getElementById(
    "product-list-container"
  );
  const pesananAktifCountEl = document.getElementById("pesanan-aktif-count");
  const produkCountEl = document.getElementById("produk-count");
  const unggulanCountEl = document.getElementById("unggulan-count");
  const imageViewer = document.getElementById("image-viewer");
  const modalImg = document.getElementById("full-image");
  const closeModalBtn = imageViewer.querySelector(".close");
  const addProductForm = document.getElementById("add-product-form");
  const newProductCategorySelect = document.getElementById(
    "new-product-category"
  );

  async function fetchAPI(action, options = {}) {
    try {
      const response = await fetch(`admin.php?action=${action}`, options);
      if (!response.ok)
        throw new Error(`HTTP error! status: ${response.status}`);
      const result = await response.json();
      if (!result.success)
        throw new Error(result.message || "API request failed");
      return result;
    } catch (error) {
      console.error(`Gagal melakukan aksi ${action}:`, error);
      alert(`Error: ${error.message}`);
      return {
        success: false,
        data: [],
      };
    }
  }

  async function loadInitialData() {
    const [
      activeOrdersResult,
      completedOrdersResult,
      productsResult,
      categoriesResult,
    ] = await Promise.all([
      fetchAPI("get_orders&status=aktif"),
      fetchAPI("get_orders&status=selesai"),
      fetchAPI("get_products"),
      fetchAPI("get_categories"),
    ]);

    activeOrdersCache = activeOrdersResult.data || [];
    completedOrdersCache = completedOrdersResult.data || [];
    productsCache = productsResult.data || [];
    categoriesCache = categoriesResult.data || [];

    updateHeaderStats();
    populateCategorySelects();
    renderAllTabs();
  }

  async function refreshAllData() {
    await loadInitialData();
  }

  function renderAllTabs() {
    renderOrderTable(activeOrderContainer, activeOrdersCache, true);
    renderOrderTable(completedOrderContainer, completedOrdersCache, false);
    renderProducts();
  }

  function updateHeaderStats() {
    pesananAktifCountEl.textContent = activeOrdersCache.length;
    produkCountEl.textContent = productsCache.length;
    unggulanCountEl.textContent = productsCache.filter(
      (p) => p.unggulan == 1
    ).length;
  }

  function populateCategorySelects() {
    if (newProductCategorySelect) {
      newProductCategorySelect.innerHTML = categoriesCache
        .map(
          (cat) =>
            `<option value="${cat.id_kategori}">${cat.nama_kategori}</option>`
        )
        .join("");
    }
  }

  function renderOrderTable(container, orders, showCompleteButton) {
    if (orders.length === 0) {
      const message = showCompleteButton
        ? "Tidak ada pesanan aktif saat ini."
        : "Belum ada pesanan yang diselesaikan.";
      container.innerHTML = `<p class="no-orders" style="text-align: center; padding: 2rem; color: #6c757d;">${message}</p>`;
      return;
    }

    let tableHTML = `<table id="order-table"><thead><tr>
                <th>Tgl Order</th><th>Pemesan</th><th>Kontak</th><th>Detail Pesanan</th><th>Tgl Acara</th><th>Pengambilan</th><th>Aksi</th>
            </tr></thead><tbody>`;

    orders.forEach((order) => {
      const tglOrder = new Date(order.tanggal_order).toLocaleDateString(
        "id-ID",
        {
          day: "2-digit",
          month: "short",
          year: "numeric",
        }
      );
      const tglAcara = new Date(order.tanggal_jadi).toLocaleDateString(
        "id-ID",
        {
          day: "2-digit",
          month: "long",
          year: "numeric",
        }
      );
      const waLink = `https://wa.me/62${order.nomor_wa.replace(/[^0-9]/g, "")}`;

      const detailPesananHTML = `
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <img src="${
                              order.struk_path
                            }" class="struk-thumbnail" alt="Struk" title="Perbesar Struk">
                            <div>
                                <b>${order.jenis_buket}</b><br>
                                <small>
                                Qty: ${order.jumlah}<br>
                                Nuansa: ${order.nuansa_warna || "-"}<br>
                                Ukuran: ${order.ukuran || "-"}<br>
                                Harga: ${order.kategori_harga}
                                </small>
                            </div>
                        </div>`;

      const aksiCellHTML = `
                        <td>
                            ${
                              showCompleteButton
                                ? `<button class="btn-selesai" data-id="${order.id_pesanan}" title="Selesaikan Pesanan">Selesai</button>`
                                : "Selesai"
                            }
                        </td>`;

      let pengambilanInfo = order.opsi_pengambilan.replace(/_/g, " ");
      if (order.opsi_pengambilan === "Diantar" && order.alamat_lengkap) {
        pengambilanInfo += `<br><small style="color: #555;">${order.alamat_lengkap}</small>`;
      }

      tableHTML += `<tr>
                    <td>${tglOrder}</td>
                    <td>${order.nama_pelanggan}</td>
                    <td><a href="${waLink}" target="_blank">${order.nomor_wa}</a></td>
                    <td>${detailPesananHTML}</td>
                    <td>${tglAcara}</td>
                    <td>${pengambilanInfo}</td>
                    ${aksiCellHTML}
                    </tr>`;
    });

    tableHTML += "</tbody></table>";
    container.innerHTML = tableHTML;
  }

  function createCategoryDropdown(selectedCategoryId) {
    return categoriesCache
      .map(
        (cat) =>
          `<option value="${cat.id_kategori}" ${
            cat.id_kategori == selectedCategoryId ? "selected" : ""
          }>${cat.nama_kategori}</option>`
      )
      .join("");
  }

  function renderProducts() {
    productListContainer.innerHTML = "";
    if (productsCache.length > 0) {
      productsCache.forEach((product) => {
        const productEl = document.createElement("div");
        productEl.className = "product-card";
        productEl.dataset.id = product.id_produk;
        productEl.draggable = true;
        const isFeatured = product.unggulan == 1;
        productEl.innerHTML = `
                        <div class="product-img-wrapper">
                            <button class="btn-delete-product" title="Hapus Produk">&times;</button>
                            <button class="featured-badge ${
                              isFeatured ? "is-featured" : ""
                            }" title="Toggle Unggulan"><i class="fa-solid fa-star"></i></button>
                            <img src="${
                              product.gambar
                            }" class="product-img" alt="Produk" loading="lazy">
                            <div class="image-overlay">
                                <button type="button" class="overlay-btn btn-zoom" title="Perbesar Gambar"><i class="fa-solid fa-search-plus"></i></button>
                                <button type="button" class="overlay-btn btn-edit-image" title="Ubah Gambar"><i class="fa-solid fa-pen"></i></button>
                            </div>
                        </div>
                        <div class="product-details">
                            <div class="form-group"><label>Kategori</label><select class="category-select">${createCategoryDropdown(
                              product.id_kategori
                            )}</select></div>
                            <input type="file" class="product-image-input" accept="image/*">
                        </div>`;
        productListContainer.appendChild(productEl);
      });
    } else {
      productListContainer.innerHTML = "<p>Belum ada produk.</p>";
    }
  }

  document.querySelectorAll(".tab-btn").forEach((tab) => {
    tab.addEventListener("click", (e) => {
      if (e.currentTarget.classList.contains("active")) return;
      document.querySelector(".tab-btn.active").classList.remove("active");
      document
        .querySelector(".dashboard-panel.active")
        .classList.remove("active");
      e.currentTarget.classList.add("active");
      document
        .getElementById(`panel-${e.currentTarget.dataset.tab}`)
        .classList.add("active");
    });
  });

  document.querySelectorAll(".sub-nav").forEach((nav) => {
    nav.addEventListener("click", (e) => {
      if (
        e.target.matches(".sub-tab-btn") &&
        !e.target.classList.contains("active")
      ) {
        nav.querySelector(".sub-tab-btn.active").classList.remove("active");
        e.target.classList.add("active");
        const subtabId = e.target.dataset.subtab;
        nav.parentElement
          .querySelectorAll(".sub-panel")
          .forEach((panel) => panel.classList.remove("active"));
        document.getElementById(`subpanel-${subtabId}`).classList.add("active");
      }
    });
  });

  document
    .getElementById("panel-pesanan")
    .addEventListener("click", async (e) => {
      if (e.target.classList.contains("struk-thumbnail")) {
        modalImg.src = e.target.src;
        imageViewer.style.display = "flex";
      }
      if (e.target.classList.contains("btn-selesai")) {
        const pesananId = e.target.dataset.id;
        if (
          confirm(
            `Konfirmasi mengubah status pesanan ini (dengan ID: ${pesananId}) sebagai Selesai?`
          )
        ) {
          const result = await fetchAPI("complete_order", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              id_pesanan: pesananId,
            }),
          });
          if (result.success) {
            alert(result.message);
            await refreshAllData();
          }
        }
      }
    });

  productListContainer.addEventListener("click", async (e) => {
    const productCard = e.target.closest(".product-card");
    if (!productCard) return;
    const productId = productCard.dataset.id;
    if (e.target.closest(".btn-zoom")) {
      modalImg.src = productCard.querySelector(".product-img").src;
      imageViewer.style.display = "flex";
    }
    if (e.target.closest(".btn-edit-image")) {
      productCard.querySelector(".product-image-input").click();
    }
    if (e.target.closest(".btn-delete-product")) {
      if (
        confirm(
          "Anda yakin ingin menghapus produk ini secara permanen?"
        )
      ) {
        const result = await fetchAPI("delete_product", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            id_produk: productId,
          }),
        });
        if (result.success) {
          alert(result.message);
          await refreshAllData();
        }
      }
    }
    if (e.target.closest(".featured-badge")) {
      const newFeaturedState = e.target
        .closest(".featured-badge")
        .classList.contains("is-featured")
        ? 0
        : 1;
      const formData = new FormData();
      formData.append("id_produk", productId);
      formData.append("unggulan", newFeaturedState);
      const result = await fetchAPI("toggle_featured", {
        method: "POST",
        body: formData,
      });
      if (result.success) {
        await refreshAllData();
      }
    }
  });

  productListContainer.addEventListener("change", async (e) => {
    const productCard = e.target.closest(".product-card");
    if (!productCard) return;
    const productId = productCard.dataset.id;
    if (e.target.classList.contains("product-image-input")) {
      const file = e.target.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append("id_produk", productId);
      formData.append("gambar", file);
      const result = await fetchAPI("update_image", {
        method: "POST",
        body: formData,
      });
      if (result.success) {
        alert("Gambar berhasil diperbarui!");
        await refreshAllData();
      }
    }
    if (e.target.classList.contains("category-select")) {
      const newCategoryId = e.target.value;
      const formData = new FormData();
      formData.append("id_produk", productId);
      formData.append("id_kategori", newCategoryId);
      const result = await fetchAPI("update_category", {
        method: "POST",
        body: formData,
      });
      if (result.success) {
        await refreshAllData();
      }
    }
  });

  addProductForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const imageInput = document.getElementById("new-product-image");
    if (imageInput.files.length === 0) {
      alert("Silakan pilih gambar terlebih dahulu.");
      return;
    }
    const formData = new FormData();
    formData.append("id_kategori", newProductCategorySelect.value);
    formData.append("gambar", imageInput.files[0]);
    const result = await fetchAPI("add_product", {
      method: "POST",
      body: formData,
    });
    if (result.success) {
      alert(result.message);
      addProductForm.reset();
      await refreshAllData();
      // Auto-switch to product list view
      const produkNav = document.getElementById("produk-sub-nav");
      produkNav
        .querySelector('[data-subtab="tambah"]')
        .classList.remove("active");
      produkNav.querySelector('[data-subtab="daftar"]').classList.add("active");
      document.getElementById("subpanel-tambah").classList.remove("active");
      document.getElementById("subpanel-daftar").classList.add("active");
    }
  });

  closeModalBtn.addEventListener(
    "click",
    () => (imageViewer.style.display = "none")
  );
  imageViewer.addEventListener("click", (e) => {
    if (e.target === imageViewer) imageViewer.style.display = "none";
  });

  productListContainer.addEventListener("dragstart", (e) => {
    if (e.target.classList.contains("product-card")) {
      draggedItem = e.target;
      setTimeout(() => e.target.classList.add("dragging"), 0);
    }
  });
  productListContainer.addEventListener("dragend", () => {
    if (draggedItem) {
      draggedItem.classList.remove("dragging");
      draggedItem = null;
    }
  });
  productListContainer.addEventListener("dragover", (e) => {
    e.preventDefault();
    const target = e.target.closest(".product-card");
    if (target && target !== draggedItem) {
      document
        .querySelectorAll(".drag-over")
        .forEach((el) => el.classList.remove("drag-over"));
      target.classList.add("drag-over");
    }
  });
  productListContainer.addEventListener("dragleave", (e) => {
    if (e.target.closest(".product-card")) {
      e.target.closest(".product-card").classList.remove("drag-over");
    }
  });
  productListContainer.addEventListener("drop", async (e) => {
    e.preventDefault();
    const dropTarget = e.target.closest(".product-card");
    if (dropTarget) dropTarget.classList.remove("drag-over");
    if (dropTarget && draggedItem && dropTarget !== draggedItem) {
      const sourceId = draggedItem.dataset.id;
      const targetId = dropTarget.dataset.id;
      const result = await fetchAPI("swap_products", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          sourceId: sourceId,
          targetId: targetId,
        }),
      });
      if (result.success) {
        await refreshAllData();
      }
    }
  });

  document.getElementById("current-date").textContent =
    new Date().toLocaleDateString("id-ID", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  loadInitialData();
});
