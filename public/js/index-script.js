const imageViewer = document.getElementById("image-viewer");
const modalImg = document.getElementById("full-image");
const images = document.querySelectorAll(".showcase-img");
const closeBtn = document.querySelector("#image-viewer .close");

images.forEach((img) => {
  img.addEventListener("click", function () {
    imageViewer.style.display = "flex";
    modalImg.src = this.src;
  });
});

function closeViewer() {
  imageViewer.style.display = "none";
}

closeBtn.addEventListener("click", closeViewer);

imageViewer.addEventListener("click", function (e) {
  if (e.target === imageViewer) {
    closeViewer();
  }
});
