-- ============================================
-- DIABETIC CARE MANAGEMENT SYSTEM - DATABASE
-- ============================================

CREATE DATABASE IF NOT EXISTS diabetic_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE diabetic_tracker;

-- Patients Table
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    diabetes_type ENUM('Type 1','Type 2','Gestational','Pre-diabetes','MODY','Other') NOT NULL,
    diagnosis_date DATE,
    doctor_name VARCHAR(100),
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    allergies TEXT,
    complications TEXT,
    insurance_number VARCHAR(50),
    status ENUM('Active','Inactive','Critical','Discharged') DEFAULT 'Active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Blood Sugar Readings Table
CREATE TABLE IF NOT EXISTS blood_sugar_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    reading_type ENUM('Fasting','Before Breakfast','After Breakfast','Before Lunch','After Lunch','Before Dinner','After Dinner','Bedtime','Random') NOT NULL,
    blood_sugar_value DECIMAL(6,2) NOT NULL,
    unit ENUM('mg/dL','mmol/L') DEFAULT 'mg/dL',
    reading_date DATE NOT NULL,
    reading_time TIME NOT NULL,
    notes TEXT,
    recorded_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- HbA1c Records Table
CREATE TABLE IF NOT EXISTS hba1c_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    hba1c_value DECIMAL(4,2) NOT NULL,
    test_date DATE NOT NULL,
    lab_name VARCHAR(100),
    notes TEXT,
    recorded_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Medications Table
CREATE TABLE IF NOT EXISTS medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medication_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50),
    frequency VARCHAR(50),
    start_date DATE,
    end_date DATE,
    prescribed_by VARCHAR(100),
    notes TEXT,
    status ENUM('Active','Completed','Discontinued') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    doctor_name VARCHAR(100),
    purpose VARCHAR(200),
    status ENUM('Scheduled','Completed','Cancelled','No Show') DEFAULT 'Scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Sample Data
INSERT INTO patients (patient_id, full_name, date_of_birth, gender, phone, email, address, diabetes_type, diagnosis_date, doctor_name, emergency_contact, emergency_phone, blood_group, weight, height, status) VALUES
('DM-2024-001', 'John Silva', '1975-04-12', 'Male', '+94771234567', 'john@email.com', '45 Main Street, Colombo 03', 'Type 2', '2020-01-15', 'Dr. Perera', 'Mary Silva', '+94771234568', 'O+', 78.5, 172.0, 'Active'),
('DM-2024-002', 'Priya Fernando', '1988-09-23', 'Female', '+94772345678', 'priya@email.com', '12 Lake Road, Kandy', 'Type 1', '2010-06-20', 'Dr. Wijesinghe', 'Rajan Fernando', '+94772345679', 'B+', 62.0, 158.0, 'Active'),
('DM-2024-003', 'Ananda Rajapaksa', '1960-12-05', 'Male', '+94773456789', 'ananda@email.com', '78 Temple Lane, Galle', 'Type 2', '2015-03-10', 'Dr. Perera', 'Kamala Rajapaksa', '+94773456790', 'A+', 85.0, 168.0, 'Critical');

INSERT INTO blood_sugar_readings (patient_id, reading_type, blood_sugar_value, reading_date, reading_time, recorded_by) VALUES
(1, 'Fasting', 126, '2025-01-10', '07:00:00', 'Nurse Dilani'),
(1, 'After Breakfast', 185, '2025-01-10', '09:30:00', 'Nurse Dilani'),
(1, 'Before Lunch', 145, '2025-01-10', '12:00:00', 'Nurse Dilani'),
(1, 'After Lunch', 210, '2025-01-10', '14:00:00', 'Nurse Dilani'),
(1, 'Fasting', 118, '2025-01-15', '07:15:00', 'Nurse Dilani'),
(1, 'After Breakfast', 170, '2025-01-15', '09:45:00', 'Nurse Dilani'),
(1, 'Fasting', 132, '2025-01-20', '07:00:00', 'Nurse Dilani'),
(1, 'After Breakfast', 190, '2025-01-20', '09:30:00', 'Nurse Dilani'),
(2, 'Fasting', 95, '2025-01-10', '07:00:00', 'Nurse Kamala'),
(2, 'After Breakfast', 140, '2025-01-10', '09:30:00', 'Nurse Kamala'),
(2, 'Fasting', 102, '2025-01-15', '07:00:00', 'Nurse Kamala'),
(3, 'Fasting', 198, '2025-01-10', '07:00:00', 'Nurse Dilani'),
(3, 'After Breakfast', 285, '2025-01-10', '09:30:00', 'Nurse Dilani');

INSERT INTO hba1c_records (patient_id, hba1c_value, test_date, lab_name, recorded_by) VALUES
(1, 7.8, '2024-10-01', 'City Lab', 'Dr. Perera'),
(1, 7.2, '2025-01-01', 'City Lab', 'Dr. Perera'),
(2, 6.5, '2024-10-01', 'Kandy Lab', 'Dr. Wijesinghe'),
(2, 6.8, '2025-01-01', 'Kandy Lab', 'Dr. Wijesinghe'),
(3, 9.5, '2024-10-01', 'Galle Lab', 'Dr. Perera'),
(3, 10.2, '2025-01-01', 'Galle Lab', 'Dr. Perera');
