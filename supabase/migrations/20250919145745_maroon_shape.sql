-- Pet Adoption Care Guide Database Schema (Optimized)

CREATE DATABASE pet_adoption_care_guide;
USE pet_adoption_care_guide;

-- Users table (Admin, Shelter, Adopter)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'shelter', 'adopter') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Shelters table
CREATE TABLE shelters (
    shelter_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    shelter_name VARCHAR(100) NOT NULL,
    license_number VARCHAR(50),
    capacity INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Pet categories
CREATE TABLE pet_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL,
    description TEXT
);

-- Pet breeds
CREATE TABLE pet_breeds (
    breed_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    breed_name VARCHAR(50) NOT NULL,
    FOREIGN KEY (category_id) REFERENCES pet_categories(category_id) ON DELETE CASCADE
);

-- Pets table (Shortened)
CREATE TABLE pets (
    pet_id INT PRIMARY KEY AUTO_INCREMENT,
    shelter_id INT NOT NULL,
    category_id INT NOT NULL,
    breed_id INT,
    pet_name VARCHAR(50) NOT NULL,
    age INT,
    gender ENUM('male', 'female') NOT NULL,
    size ENUM('small', 'medium', 'large'),
    description TEXT,
    health_status VARCHAR(255),
    adoption_fee DECIMAL(8,2) DEFAULT 0.00,
    status ENUM('available', 'pending', 'adopted') DEFAULT 'available',
    primary_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shelter_id) REFERENCES shelters(shelter_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES pet_categories(category_id),
    FOREIGN KEY (breed_id) REFERENCES pet_breeds(breed_id)
);

-- Pet images
CREATE TABLE pet_images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    pet_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE
);

-- Adoption applications (Shortened)
CREATE TABLE adoption_applications (
    application_id INT PRIMARY KEY AUTO_INCREMENT,
    pet_id INT NOT NULL,
    adopter_id INT NOT NULL,
    shelter_id INT NOT NULL,
    application_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    housing_type VARCHAR(50),
    has_experience BOOLEAN DEFAULT FALSE,
    reason_for_adoption TEXT,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT,
    FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE,
    FOREIGN KEY (adopter_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (shelter_id) REFERENCES shelters(shelter_id) ON DELETE CASCADE
);

-- Adoptions (Shortened)
CREATE TABLE adoptions (
    adoption_id INT PRIMARY KEY AUTO_INCREMENT,
    application_id INT NOT NULL,
    pet_id INT NOT NULL,
    adopter_id INT NOT NULL,
    shelter_id INT NOT NULL,
    adoption_date DATE NOT NULL,
    adoption_fee_paid DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES adoption_applications(application_id),
    FOREIGN KEY (pet_id) REFERENCES pets(pet_id),
    FOREIGN KEY (adopter_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (shelter_id) REFERENCES shelters(shelter_id) ON DELETE CASCADE
);

-- Vaccination records
CREATE TABLE vaccinations (
    vaccination_id INT PRIMARY KEY AUTO_INCREMENT,
    pet_id INT NOT NULL,
    vaccine_name VARCHAR(100) NOT NULL,
    vaccination_date DATE NOT NULL,
    next_due_date DATE,
    veterinarian_name VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE
);

-- Medical records (Shortened)
CREATE TABLE medical_records (
    record_id INT PRIMARY KEY AUTO_INCREMENT,
    pet_id INT NOT NULL,
    record_type ENUM('checkup', 'surgery', 'treatment', 'other'),
    record_date DATE NOT NULL,
    veterinarian_name VARCHAR(100),
    diagnosis TEXT,
    treatment TEXT,
    cost DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE
);

-- Care guides (Shortened)
CREATE TABLE care_guides (
    guide_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced'),
    is_published BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES pet_categories(category_id),
    FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Contact messages
CREATE TABLE contact_messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System settings
CREATE TABLE settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default data
INSERT INTO pet_categories (category_name, description) VALUES
('Dog', 'Domestic dogs of all breeds and sizes'),
('Cat', 'Domestic cats of all breeds and sizes'),
('Bird', 'Pet birds including parrots, canaries, etc.'),
('Rabbit', 'Domestic rabbits'),
('Other', 'Other small pets and animals');

INSERT INTO pet_breeds (category_id, breed_name) VALUES
(1, 'Mixed Breed'),
(1, 'Labrador Retriever'),
(1, 'German Shepherd'),
(1, 'Golden Retriever'),
(2, 'Mixed Breed'),
(2, 'Domestic Shorthair'),
(2, 'Persian'),
(2, 'Siamese');

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Pet Adoption Care Guide'),
('site_email', 'admin@petadoption.com'),
('max_upload_size', '5242880'),
('adoption_fee_required', '1');

-- Create indexes for performance
CREATE INDEX idx_pets_status ON pets(status);
CREATE INDEX idx_pets_shelter ON pets(shelter_id);
CREATE INDEX idx_applications_status ON adoption_applications(application_status);
CREATE INDEX idx_users_type ON users(user_type);
CREATE INDEX idx_users_email ON users(email);


-- Admin user (password = Admin#2025!)
INSERT INTO users (
  username, email, password_hash, user_type, first_name, last_name, phone, address, is_active
) VALUES (
  'admin_master',
  'admin@gmail.com',
  '$2b$12$ifTuQbHNOifbnBiiClYYj./AEnkrPgM9Ej/TxMQktwxgEuoMT/Kd6',  -- bcrypt hash of "Admin#2025!"
  'admin',
  'Admin',
  'Administrator',
  '+94234567890',
  '123 Main St, Colombo',
  TRUE
);