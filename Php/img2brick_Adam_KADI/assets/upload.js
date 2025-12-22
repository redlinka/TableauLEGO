document.addEventListener('DOMContentLoaded', () => {
  const dropzone   = document.getElementById('dropzone');
  const fileInput  = document.getElementById('file');
  const titleSpan  = document.getElementById('dropzone-title');
  const subSpan    = document.getElementById('dropzone-sub');

  if (!dropzone || !fileInput) {
    return;
  }

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  function highlight() {
    dropzone.classList.add('is-dragover');
  }

  function unhighlight() {
    dropzone.classList.remove('is-dragover');
  }

  function handleDrop(e) {
    preventDefaults(e);
    unhighlight();

    const dt = e.dataTransfer;
    if (!dt || !dt.files || dt.files.length === 0) {
      return;
    }

    const file = dt.files[0];

    // On ne garde que les images
    if (!file.type.startsWith('image/')) {
      if (subSpan) {
        subSpan.textContent = 'Fichier non valide. Merci de choisir une image.';
      }
      fileInput.value = '';
      return;
    }

    // File = input
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    fileInput.files = dataTransfer.files;

    // Update text
    if (titleSpan) {
      titleSpan.textContent = file.name;
    }
    if (subSpan) {
      subSpan.textContent = 'Fichier prêt à être envoyé.';
    }
  }

  // Drag and drop events
  ['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, e => {
      preventDefaults(e);
      highlight();
    });
  });

  ['dragleave', 'dragend'].forEach(eventName => {
    dropzone.addEventListener(eventName, e => {
      preventDefaults(e);
      unhighlight();
    });
  });

  dropzone.addEventListener('drop', handleDrop);

  // Don't open if not droppped properly
  ['dragenter', 'dragover', 'drop'].forEach(eventName => {
    document.addEventListener(eventName, e => {
      preventDefaults(e);
    });
  });

  // Click on zone = click on input
  dropzone.addEventListener('click', () => {
    fileInput.click();
  });

  // File choosing 
  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) {
      if (titleSpan) titleSpan.textContent = '';
      if (subSpan) subSpan.textContent = '';
      return;
    }
    if (titleSpan) {
      titleSpan.textContent = file.name;
    }
    if (subSpan) {
      subSpan.textContent = 'Fichier prêt à être envoyé.';
    }
  });
});
