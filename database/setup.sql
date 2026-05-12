CREATE DATABASE IF NOT EXISTS vehicle_rental;
USE vehicle_rental;

-- ── Admin ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Vehicles ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('Motorcycle','Car','SUV','Van','Truck') NOT NULL,
    fuel_type VARCHAR(50) NOT NULL DEFAULT 'Petrol',
    seats INT NOT NULL DEFAULT 5,
    transmission VARCHAR(30) DEFAULT 'Manual',
    year INT DEFAULT NULL,
    doors INT DEFAULT NULL,
    mileage VARCHAR(50) DEFAULT NULL,
    luggage_capacity VARCHAR(50) DEFAULT NULL,
    pickup_location VARCHAR(200) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    features TEXT DEFAULT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vehicle_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- ── Users ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) UNIQUE DEFAULT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    given_name  VARCHAR(100) NOT NULL,
    family_name VARCHAR(100) DEFAULT '',
    username    VARCHAR(255) NOT NULL,
    dob DATE DEFAULT NULL,
    picture VARCHAR(500) DEFAULT NULL,
    email_verified TINYINT(1) DEFAULT 0,
    auth_provider VARCHAR(20) NOT NULL DEFAULT 'email',
    password VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── OTP Tokens ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);

-- ── Terms & Conditions ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Bookings ───────────────────────────────────────────────────────────────
-- status flow: pending → confirmed (admin approves)
--                      → pending_review (admin returns with note, user must re-read)
--                      → cancelled (user or admin cancels)
--                      → completed (rental finished)
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    pickup_location VARCHAR(200) DEFAULT NULL,
    dropoff_location VARCHAR(200) DEFAULT NULL,
    contact_phone VARCHAR(20) DEFAULT NULL,
    payment_method VARCHAR(20) DEFAULT 'esewa',
    status ENUM('pending','pending_review','confirmed','cancelled','completed') DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);
