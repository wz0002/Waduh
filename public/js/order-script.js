const getLocationBtn = document.getElementById("getLocationBtn");
const alamatTextarea = document.getElementById("alamat");
const zoomModal = document.getElementById("zoom-modal");
const zoomedReceiptImage = document.getElementById("zoomed-receipt-image");
const closeZoomBtn = document.getElementById("close-zoom-btn");
const form = document.getElementById("orderForm");
const kategoriBarangSelect = document.getElementById("kategori_barang");
const jenisBuketGroup = document.getElementById("jenis_buket_group");
const jenisBuketSelect = document.getElementById("jenis_buket");

function openZoomModal(imageUrl) {
  zoomedReceiptImage.src = imageUrl;
  zoomModal.style.display = "flex";
}

function closeZoomModal() {
  zoomModal.style.display = "none";
}

closeZoomBtn.addEventListener("click", closeZoomModal);
zoomModal.addEventListener("click", (e) => {
  if (e.target === zoomModal) {
    closeZoomModal();
  }
});

const handleLocationRequest = () => {
  if (!navigator.geolocation) {
    alert("Maaf, browser Anda tidak mendukung fitur geolokasi.");
    return;
  }
  getLocationBtn.disabled = true;
  getLocationBtn.classList.add("loading");
  navigator.geolocation.getCurrentPosition(
    async (position) => {
      const { latitude, longitude } = position.coords;
      try {
        const response = await fetch(
          `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`
        );
        if (!response.ok) throw new Error("Gagal menghubungi layanan peta.");
        const data = await response.json();
        if (data && data.display_name) {
          alamatTextarea.value = data.display_name;
          alamatTextarea.dispatchEvent(new Event("input"));
        } else {
          throw new Error("Tidak dapat menemukan alamat dari lokasi Anda.");
        }
      } catch (error) {
        alert(error.message);
      } finally {
        getLocationBtn.disabled = false;
        getLocationBtn.classList.remove("loading");
      }
    },
    (error) => {
      let message = "Terjadi kesalahan saat mengambil lokasi Anda.";
      if (error.code === 1) {
        message =
          "Anda menolak izin akses lokasi. Silakan izinkan di pengaturan browser Anda jika ingin menggunakan fitur ini.";
      } else if (error.code === 2) {
        message =
          "Lokasi tidak tersedia. Pastikan GPS atau layanan lokasi Anda aktif.";
      }
      alert(message);
      getLocationBtn.disabled = false;
      getLocationBtn.classList.remove("loading");
    }
  );
};
getLocationBtn.addEventListener("click", handleLocationRequest);

function populateReceiptTemplate() {
  const data = Object.fromEntries(new FormData(form).entries());
  const orderDate = new Date().toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
  const orderTime = new Date().toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  });
  document.getElementById(
    "receipt-details"
  ).innerHTML = `<div style="display:flex; justify-content:space-between;"><span>Tanggal:</span> <span>${orderDate} ${orderTime}</span></div><div style="display:flex; justify-content:space-between;"><span>Pemesan:</span> <span>${
    data.fullName
  }</span></div><div style="display:flex; justify-content:space-between;"><span>Tgl Acara:</span> <span>${new Date(
    data["tanggal-acara"]
  ).toLocaleDateString("id-ID")}</span></div>`;
  const hargaText =
    hargaMulaiSelect.options[hargaMulaiSelect.selectedIndex].text;
  document.getElementById(
    "receipt-items"
  ).innerHTML = `<p style="margin: 5px 0;"><strong>${data.kategori_barang} - ${
    data.jenis_buket
  } (x${
    data.jumlah_pesanan
  })</strong></p><p style="font-size: 10px; margin: 0 0 5px 10px;">${
    data.ukuran ? `Ukuran: ${data.ukuran}` : ""
  } ${
    data.nuansa_warna ? `| Nuansa: ${data.nuansa_warna}` : ""
  }</p><div style="display:flex; justify-content:space-between; margin-top: 10px; border-top: 1px dashed #eee; padding-top: 5px;"><span>Kategori Harga:</span><span>${hargaText}</span></div>`;
}

async function handleOrderSubmission() {
  switchModalView("loading");
  let canvas;
  try {
    populateReceiptTemplate();
    canvas = await html2canvas(document.getElementById("receipt-template"), {
      scale: 2,
      useCORS: true,
    });
  } catch (err) {
    errorText.textContent = "Gagal membuat gambar struk.";
    switchModalView("error");
    return;
  }
  const formData = new FormData(form);
  formData.append("receiptImage", canvas.toDataURL("image/png"));
  formData.append("orderDetailsText", getOrderDetailsText());
  formData.append(
    "harga_mulai_text",
    hargaMulaiSelect.options[hargaMulaiSelect.selectedIndex].text
  );
  uploadedFiles.forEach((file) => {
    formData.append("file-upload[]", file, file.name);
  });
  try {
    const response = await fetch("order.php", {
      method: "POST",
      body: formData,
    });
    const result = await response.json();
    if (result.success) {
      receiptImageContainer.innerHTML = `<img src="${result.receiptUrl}" alt="Struk Pesanan Anda" id="receipt-thumbnail"><p>Klik gambar untuk memperbesar</p>`;
      document
        .getElementById("receipt-thumbnail")
        .addEventListener("click", () => {
          openZoomModal(result.receiptUrl);
        });
      switchModalView("success");
      form.reset();
      jenisBuketSelect.disabled = true;
      uploadedFiles = [];
      renderPreviews();
    } else {
      let errorMessage = result.message || "Gagal mengirim pesanan.";
      if (result.telegram_status && result.telegram_status.ok === false) {
        errorMessage += ` Info Telegram: ${result.telegram_status.description}`;
      }
      errorText.textContent = errorMessage;
      switchModalView("error");
    }
  } catch (err) {
    errorText.textContent =
      "Tidak dapat terhubung ke server. Periksa koneksi internet Anda.";
    switchModalView("error");
  }
}

const modal = document.getElementById("confirmation-modal");
const modalViews = {
  choice: document.getElementById("modal-view-choice"),
  loading: document.getElementById("modal-view-loading"),
  success: document.getElementById("modal-view-success"),
  error: document.getElementById("modal-view-error"),
};
const btnConfirm = document.getElementById("btn-confirm");
const btnCancelModal = document.getElementById("btn-cancel-modal");
const btnCloseModal = document.getElementById("btn-close-modal");
const btnCloseErrorModal = document.getElementById("btn-close-error-modal");
const receiptImageContainer = document.getElementById(
  "receipt-image-container"
);
const errorText = document.getElementById("error-text");
const jenisBuketLabel = jenisBuketGroup.querySelector("label");
const ukuranGroup = document.getElementById("ukuran_group");
const nuansaWarnaGroup = document.getElementById("nuansa_warna_group");
const alamatGroup = document.getElementById("alamat-group");
const hargaMulaiSelect = document.getElementById("harga_mulai");
const dropZone = document.getElementById("drop-zone");
const fileInput = document.getElementById("file-upload");
const previewContainer = document.getElementById("preview-container");
const uploadPrompt = document.getElementById("upload-prompt");
let uploadedFiles = [];
const renderPreviews = () => {
  previewContainer.innerHTML = "";
  uploadPrompt.style.display = uploadedFiles.length > 0 ? "none" : "block";
  if (uploadedFiles.length === 0) fileInput.value = "";
  uploadedFiles.forEach((file, index) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const previewItem = document.createElement("div");
      previewItem.className = "preview-item";
      previewItem.innerHTML = `<img src="${e.target.result}" alt="${file.name}" class="preview-img"><button type="button" class="delete-btn" data-index="${index}">&times;</button>`;
      previewContainer.appendChild(previewItem);
    };
    reader.readAsDataURL(file);
  });
};
const handleFiles = (files) => {
  const newFiles = [...files].filter(
    (file) =>
      file.type.startsWith("image/") &&
      !uploadedFiles.some((f) => f.name === file.name && f.size === file.size)
  );
  uploadedFiles.push(...newFiles);
  renderPreviews();
};
fileInput.addEventListener("change", (e) => handleFiles(e.target.files));
dropZone.addEventListener("dragover", (e) => {
  e.preventDefault();
  dropZone.classList.add("border-indigo-400");
});
dropZone.addEventListener("dragleave", (e) => {
  e.preventDefault();
  dropZone.classList.remove("border-indigo-400");
});
dropZone.addEventListener("drop", (e) => {
  e.preventDefault();
  dropZone.classList.remove("border-indigo-400");
  handleFiles(e.dataTransfer.files);
});
previewContainer.addEventListener("click", (e) => {
  if (e.target.classList.contains("delete-btn")) {
    uploadedFiles.splice(parseInt(e.target.dataset.index, 10), 1);
    renderPreviews();
  }
});

function capitalizeName(input) {
  input.value = input.value.replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatPhoneNumber(input) {
  let value = input.value.replace(/\D/g, "");
  if (value.startsWith("0")) value = value.substring(1);
  value = value.substring(0, 12);
  if (value.length > 7)
    value = value.replace(/(\d{3})(\d{4})(\d{1,5})/, "$1-$2-$3");
  else if (value.length > 3) value = value.replace(/(\d{3})(\d{1,4})/, "$1-$2");
  input.value = value;
}

const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
form.elements["tanggal-acara"].min = tomorrow.toISOString().split("T")[0];
const optionsData = {
  "Buket Bunga": {
    label: "Jenis Buket",
    jenis: ["Buket Wisuda", "Buket Ulang Tahun", "Buket Anniversary"],
    harga: {
      "15000-50000": "Rp 15.000 - 50.000",
      100000: "Rp 100.000",
      150000: "Rp 150.000",
      200000: "Rp 200.000",
      250000: "Rp 250.000",
      350000: "Rp 350.000",
      500000: "Rp 500.000",
    },
    showUkuran: true,
    showNuansa: true,
  },
  "Buket Balon": {
    label: "Jenis Buket Balon",
    jenis: ["Balon Karakter", "Balon Angka/Huruf", "Balon Custom"],
    harga: {
      "150000-185000": "Rp 150.000 - 185.000",
    },
    showUkuran: false,
    showNuansa: true,
  },
  Hampers: {
    label: "Jenis Hampers",
    jenis: ["Hampers Lebaran", "Hampers Natal", "Hampers Makanan Ringan"],
    harga: {
      100000: "Rp 100.000",
      250000: "Rp 250.000",
      500000: "Rp 500.000",
    },
    showUkuran: false,
    showNuansa: false,
  },
};

function updateFormFields(category) {
  if (!optionsData[category]) return;
  const config = optionsData[category];
  jenisBuketLabel.textContent = config.label;
  jenisBuketSelect.innerHTML =
    '<option value="" disabled selected>Pilih Jenis...</option>';
  config.jenis.forEach((opt) => jenisBuketSelect.add(new Option(opt, opt)));
  hargaMulaiSelect.innerHTML =
    '<option value="" disabled selected>Pilih Kategori Harga</option>';
  Object.entries(config.harga)
    .sort(
      (a, b) =>
        parseInt(a[0].split("-")[0], 10) - parseInt(b[0].split("-")[0], 10)
    )
    .forEach(([val, txt]) => hargaMulaiSelect.add(new Option(txt, val)));
  ukuranGroup.style.display = config.showUkuran ? "flex" : "none";
  nuansaWarnaGroup.style.display = config.showNuansa ? "flex" : "none";
  jenisBuketSelect.disabled = false;
}

kategoriBarangSelect.addEventListener("change", (e) =>
  updateFormFields(e.target.value)
);

jenisBuketGroup.addEventListener("click", (e) => {
  if (jenisBuketSelect.disabled) {
    e.preventDefault();
    showError(kategoriBarangSelect, "Pilih Kategori Produk terlebih dahulu.");
    kategoriBarangSelect.focus();
    kategoriBarangSelect.scrollIntoView({
      behavior: "smooth",
      block: "center",
    });
  }
});

document.querySelectorAll('input[name="opsi_pengambilan"]').forEach((opt) =>
  opt.addEventListener("change", (e) => {
    alamatGroup.style.display = e.target.value === "Diantar" ? "flex" : "none";
    alamatTextarea.required = e.target.value === "Diantar";
  })
);

function switchModalView(viewName) {
  Object.values(modalViews).forEach((v) => (v.style.display = "none"));
  if (modalViews[viewName]) modalViews[viewName].style.display = "block";
}

function hideModal() {
  modal.classList.remove("show");
}

function getOrderDetailsText() {
  const data = Object.fromEntries(new FormData(form).entries());
  const tglAcara = new Date(data["tanggal-acara"]).toLocaleDateString("id-ID", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
  let details = `*Nama Pelanggan:* ${data.fullName}\n`;
  details += `*Kategori:* ${data.kategori_barang}\n`;
  details += `*Jenis:* ${data.jenis_buket}\n`;
  if (data.ukuran) details += `*Ukuran:* ${data.ukuran}\n`;
  if (data.nuansa_warna) details += `*Nuansa Warna:* ${data.nuansa_warna}\n`;
  details += `*Jumlah:* ${data.jumlah_pesanan}\n`;
  details += `*Kategori Harga:* ${
    hargaMulaiSelect.options[hargaMulaiSelect.selectedIndex].text
  }\n`;
  details += `*Tgl Acara:* ${tglAcara}\n`;
  details += `*Pengambilan:* ${data.opsi_pengambilan}\n`;
  if (data.opsi_pengambilan === "Diantar")
    details += `*Alamat:* ${data.alamat}\n`;
  return details;
}

btnConfirm.addEventListener("click", handleOrderSubmission);

function showError(el, msg) {
  const errEl = document.getElementById(`${el.id}-error`);
  if (errEl) {
    errEl.textContent = msg;
    errEl.style.display = "block";
  }
  (el.closest(".input-group") || el).classList.add("input-error");
}

function clearError(el) {
  const errEl = document.getElementById(`${el.id}-error`);
  if (errEl) {
    errEl.style.display = "none";
  }
  (el.closest(".input-group") || el).classList.remove("input-error");
}

form.addEventListener("submit", function (e) {
  e.preventDefault();
  let isValid = true;
  let firstInvalidElement = null;
  document
    .querySelectorAll(".error-message")
    .forEach((el) => (el.style.display = "none"));
  document
    .querySelectorAll(".input-error")
    .forEach((el) => el.classList.remove("input-error"));
  const fieldsToValidate = [
    {
      el: form.fullName,
      msg: "Nama lengkap tidak boleh kosong.",
    },
    {
      el: form.whatsappNumber,
      msg: "Nomor WhatsApp tidak boleh kosong.",
    },
    {
      el: form.kategori_barang,
      msg: "Kategori produk harus dipilih.",
    },
    {
      el: form.jenis_buket,
      msg: "Jenis produk harus dipilih.",
    },
    {
      el: form["tanggal-acara"],
      msg: "Tanggal acara harus diisi.",
    },
    {
      el: form.harga_mulai,
      msg: "Kategori harga harus dipilih.",
    },
    {
      el: form.ukuran,
      msg: "Ukuran harus dipilih.",
      cond: (el) => ukuranGroup.style.display !== "none" && !el.value,
    },
    {
      el: form.alamat,
      msg: "Alamat lengkap tidak boleh kosong saat memilih opsi diantar.",
      cond: (el) =>
        document.querySelector('input[name="opsi_pengambilan"]:checked')
          .value === "Diantar" && !el.value.trim(),
    },
  ];
  fieldsToValidate.forEach((field) => {
    const condition = field.cond ? field.cond(field.el) : !field.el.value;
    if (condition) {
      showError(field.el, field.msg);
      isValid = false;
      if (!firstInvalidElement) firstInvalidElement = field.el;
    }
  });
  if (!isValid && firstInvalidElement) {
    firstInvalidElement.scrollIntoView({
      behavior: "smooth",
      block: "center",
    });
  } else if (isValid) {
    switchModalView("choice");
    modal.classList.add("show");
  }
});
form.querySelectorAll("input, select, textarea").forEach((input) => {
  input.addEventListener("input", () => clearError(input));
  input.addEventListener("change", () => clearError(input));
});
[btnCancelModal, btnCloseModal, btnCloseErrorModal].forEach((btn) =>
  btn.addEventListener("click", hideModal)
);
modal.addEventListener("click", (e) => {
  if (e.target === modal) hideModal();
});
document.addEventListener("DOMContentLoaded", () => {});
