const drop_zone = document.getElementById('drop-zone');
const file_input = document.getElementById('input-file');
const img_view = document.getElementById('img-view');

file_input.addEventListener('change', uploadImage);

function uploadImage() {
    let imgLink = URL.createObjectURL(file_input.files[0]);
    img_view.style.backgroundImage = `url(${imgLink})`;
    img_view.textContent = '';
    img_view.style.border = 'none';
}

drop_zone.addEventListener('dragover', function(event) {
    event.preventDefault();
});

drop_zone.addEventListener('drop', function(event) {
    event.preventDefault();
    file_input.files = event.dataTransfer.files;
    uploadImage();
});