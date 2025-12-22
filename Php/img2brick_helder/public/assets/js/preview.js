const image = document.getElementById("image");
const form = document.getElementById("form");
const hiddenInput = document.getElementById("hidden-input");
const resizeButton = document.getElementById("resizeButton");
const errorLabel = document.getElementById("errorLabel");
const MAX_WIDTH = 2000;
const MAX_HEIGHT = 2000;
let cropper = null;

form.addEventListener("submit", (e) => {
  // check if the hidden input has a value
  if (!hiddenInput.value) {
    e.preventDefault();
    alert("Vous devez d'abord appliquer le crop de l'image !");
  }
});

//--------------------Initialize Cropper---------------------
function initCropper() {
  console.log("Cropper initialisé");
  if (cropper) cropper.destroy();
  cropper = new Cropper(image, {
    aspectRatio: 1,
    viewMode: 1,
    background: false,
    movable: false,
    zoomable: false,
  });
}

//--------------------cropper---------------------
document.getElementById("btn-crop").addEventListener("click", function () {
  var croppedImage = cropper.getCroppedCanvas().toDataURL("image/png");
  var c_preview = document.getElementById("c-preview");
  c_preview.textContent = "";
  c_preview.style.backgroundImage = `url(${croppedImage})`;

  var hiddenInput = document.getElementById("hidden-input");
  hiddenInput.value = croppedImage;
  //cropper.destroy();
});

//---------------------Shape----------------------
const radios = document.querySelectorAll('input[name="shape"]');
radios.forEach((radio) => {
  radio.addEventListener("change", () => {
    let ratio = radio.value === "rectangle" ? 16 / 9 : 1;

    cropper.clear();
    cropper.setAspectRatio(ratio);
    cropper.crop();
  });
});

//----------------------resize----------------------

function resizeImage(img, callback) {
  const canvas = document.createElement("canvas");
  let width = img.width;
  let height = img.height;
  console.log("Dimensions avant redimensionnement:", width, height);
  // ratio calcul
  if (width > MAX_WIDTH || height > MAX_HEIGHT) {
    const scale = Math.min(MAX_WIDTH / width, MAX_HEIGHT / height);
    width = width * scale;
    height = height * scale;
  }

  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext("2d");
  ctx.drawImage(img, 0, 0, width, height);

  console.log("Dimensions après redimensionnement:", width, height);

  // get the new image in base64
  canvas.toBlob((blob) => {
    console.log("🧩 Blob créé:", blob);
    callback(blob);
  }, "image/jpeg");
}

if (resizeButton) {
  resizeButton.addEventListener("click", () => {
    errorLabel.style.display = "none";
    function lancerResize() {
      resizeImage(image, (resizedBlob) => {
        const url = URL.createObjectURL(resizedBlob);

        image.onload = () => {
          initCropper();
        };

        image.src = url;
      });
    }

    if (!image.complete) {
      image.onload = lancerResize;
    } else {
      lancerResize();
    }
  });
} else {
  initCropper();
}
