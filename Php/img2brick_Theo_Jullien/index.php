<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Image submission</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="p-index">
    <?php include "header.inc.php"; ?>
    <img src="assets/images/img2brick.png" alt="img2brick logo">
    <p>Transform your images into brick pictures!</p>
    <h2>Upload your image</h2>
    <?php if (isset($_GET["error"])): ?>
        <p style="color:red;">
            <?php
            switch ($_GET["error"]) {
                case "extension":   echo "File extension not allowed."; break;
                case "filesize":    echo "File is too large (max 2 Mo)."; break; // change the size
                case "filename":    echo "File name too long (Max: 100 characters)"; break;
                case "nofile":      echo "No files have been sent."; break;
                case "expired":     echo "File expired."; break;
                case "db":          echo "Unable to save the file."; break;
                case "fileerror":   echo "Error uploading file"; break;
                default:            echo htmlspecialchars($_GET["error"]); // system errors
            }
            ?>
        </p>
    <?php endif; ?>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <div class="drop-zone" id="drop-zone">
            <img src="assets/images/upload-icon.png" alt="Upload icon" class="upload-icon">
            <span class="drop-zone-text">Drag and drop your image or click to select it</span>
            <div id="file-info" style="margin-top: 10px; font-weight: bold;"></div>
        </div>
        <label for="image" class="custom-button">Choose a file</label>
        <input class="file-input" type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp" required><br>
        <button type="submit">Continue</button>
    </form>
    <script>
        // Select elements
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('image');
        const fileInfo = document.getElementById('file-info');

        // Trigger file input when clicking the drop zone
        dropZone.addEventListener('click', () => fileInput.click());

        // Update UI when a file is selected (via click or drop)
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                fileInfo.textContent = `Selected: ${fileInput.files[0].name}`;
            }
        });

        // Prevent default browser behavior for drag events
        ['dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        // Handle hover state
        dropZone.addEventListener('dragover', () => {
            dropZone.classList.add('drop-zone--over');
        });

        ['dragleave', 'dragend', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('drop-zone--over');
            });
        });

        // Handle file drop
        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files; // Transfer dropped files to the input
                fileInfo.textContent = `Dropped: ${files[0].name}`;
            }
        });
    </script>
    <?php include "footer.inc.php"; ?>
</body>
</html>