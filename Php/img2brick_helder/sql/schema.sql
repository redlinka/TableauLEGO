-- Table: users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_verified BOOLEAN DEFAULT FALSE,
    address JSON DEFAULT NULL,
    otp_code VARCHAR(6) DEFAULT NULL,
    otp_expires_at DATETIME DEFAULT NULL,
    verification_token VARCHAR(64) DEFAULT NULL,
    token_expires_at DATETIME DEFAULT NULL
);

-- Table: images
CREATE TABLE images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    filename VARCHAR(255) NOT NULL,  -- Path relative to uploads/
    path VARCHAR(255) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT NOT NULL,
    image_blob LONGBLOB NULL, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    img_hash VARCHAR(64) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL  -- Link to the users table
);

-- Table: orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    image_id INT NOT NULL,
    size VARCHAR(50) NOT NULL,  -- E.g., "64x64"
    palette_choice VARCHAR(50) NOT NULL,
    price_cents INT NOT NULL,  -- Price in cents to avoid floating point issues
    status ENUM('pending', 'preparing', 'shipped', 'delivered', 'cancelled') NOT NULL,
    address VARCHAR(100) NOT NULL, 
    phone VARCHAR(20) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,  -- Link to the users table
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE  -- Link to the images table
);

-- Table: traces
CREATE TABLE traces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,  -- ex : "upload", "generate", "order"
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL -- Link to the users table (optional)
);

-- Table: i18n_texts (for multi-language content)
CREATE TABLE trad_texts (
    `key` VARCHAR(255) NOT NULL,
    lang ENUM('fr', 'en') NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (`key`, lang)  -- Combined primary key for key and language
);

