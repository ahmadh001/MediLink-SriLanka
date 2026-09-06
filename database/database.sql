-- =======================================================================
-- Database: patient_doctor_booking
-- Patient–Doctor Subscription Booking System (Sri Lanka Edition)
-- =======================================================================

CREATE DATABASE IF NOT EXISTS `patient_doctor_booking`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `patient_doctor_booking`;

-- Drop tables in reverse order of foreign key dependencies
DROP TABLE IF EXISTS `APPOINTMENT`;
DROP TABLE IF EXISTS `SCHEDULED_SLOT`;
DROP TABLE IF EXISTS `USER_SUBSCRIPTION`;
DROP TABLE IF EXISTS `SUBSCRIPTION_PLAN`;
DROP TABLE IF EXISTS `DOCTOR_SPECIALIZATION`;
DROP TABLE IF EXISTS `SPECIALIZATION`;
DROP TABLE IF EXISTS `CENTRE_DOCTOR_LINK`;
DROP TABLE IF EXISTS `HEALTHCARE_CENTRE`;
DROP TABLE IF EXISTS `DOCTOR`;
DROP TABLE IF EXISTS `PROVIDER`;
DROP TABLE IF EXISTS `CLIENT`;
DROP TABLE IF EXISTS `USER`;

-- 1. USER Table (Central Authentication Table)
CREATE TABLE `USER` (
  `User_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `Email` VARCHAR(191) NOT NULL UNIQUE,
  `Password_Hash` VARCHAR(255) NOT NULL,
  `First_Name` VARCHAR(100) NOT NULL,
  `Last_Name` VARCHAR(100) NOT NULL,
  `Phone` VARCHAR(30) NOT NULL,
  `Role_Type` ENUM('CLIENT', 'PROVIDER', 'SYSTEM_ADMIN') NOT NULL,
  `Account_Status` ENUM('ACTIVE', 'SUSPENDED', 'PENDING') NOT NULL DEFAULT 'ACTIVE',
  `Created_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `Updated_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_email` (`Email`),
  INDEX `idx_user_role_status` (`Role_Type`, `Account_Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CLIENT Table (Patient Profile)
CREATE TABLE `CLIENT` (
  `Client_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `User_ID` INT NOT NULL UNIQUE,
  `Date_of_Birth` DATE NULL,
  `Gender` ENUM('MALE', 'FEMALE', 'OTHER') NULL,
  `Address` VARCHAR(255) NULL,
  `City` VARCHAR(100) NULL DEFAULT 'Colombo',
  `Latitude` DECIMAL(10, 8) NULL DEFAULT 6.92710000,
  `Longitude` DECIMAL(11, 8) NULL DEFAULT 79.86120000,
  `Created_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_client_user` FOREIGN KEY (`User_ID`) 
    REFERENCES `USER` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_client_city` (`City`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PROVIDER Table (Base Provider: Doctor or Healthcare Centre)
CREATE TABLE `PROVIDER` (
  `Provider_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `User_ID` INT NOT NULL UNIQUE,
  `Provider_Type` ENUM('DOCTOR', 'HEALTHCARE_CENTRE') NOT NULL,
  `Business_Name` VARCHAR(200) NOT NULL,
  `Address` VARCHAR(255) NOT NULL,
  `City` VARCHAR(100) NOT NULL,
  `Latitude` DECIMAL(10, 8) NOT NULL DEFAULT 6.92710000,
  `Longitude` DECIMAL(11, 8) NOT NULL DEFAULT 79.86120000,
  `Contact_Number` VARCHAR(30) NOT NULL,
  `Description` TEXT NULL,
  `Verification_Status` ENUM('PENDING', 'VERIFIED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
  `Created_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_provider_user` FOREIGN KEY (`User_ID`) 
    REFERENCES `USER` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_provider_city_type` (`City`, `Provider_Type`),
  INDEX `idx_provider_verification` (`Verification_Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. DOCTOR Table (Doctor Specific Information)
CREATE TABLE `DOCTOR` (
  `Doctor_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `Provider_ID` INT NOT NULL UNIQUE,
  `Medical_License_No` VARCHAR(100) NOT NULL UNIQUE,
  `Professional_Bio` TEXT NULL,
  `Experience_Years` INT NOT NULL DEFAULT 0,
  `Consultation_Duration` INT NOT NULL DEFAULT 30,
  `Verification_Status` ENUM('PENDING', 'VERIFIED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
  CONSTRAINT `fk_doctor_provider` FOREIGN KEY (`Provider_ID`) 
    REFERENCES `PROVIDER` (`Provider_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_doctor_license` (`Medical_License_No`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. HEALTHCARE_CENTRE Table (Centre Specific Information)
CREATE TABLE `HEALTHCARE_CENTRE` (
  `Centre_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `Provider_ID` INT NOT NULL UNIQUE,
  `Centre_Name` VARCHAR(200) NOT NULL,
  `Registration_No` VARCHAR(100) NOT NULL UNIQUE,
  `Description` TEXT NULL,
  `Verification_Status` ENUM('PENDING', 'VERIFIED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
  CONSTRAINT `fk_centre_provider` FOREIGN KEY (`Provider_ID`) 
    REFERENCES `PROVIDER` (`Provider_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_centre_reg_no` (`Registration_No`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. CENTRE_DOCTOR_LINK Table (M:N Healthcare Centre to Doctor Link)
CREATE TABLE `CENTRE_DOCTOR_LINK` (
  `Centre_ID` INT NOT NULL,
  `Doctor_ID` INT NOT NULL,
  `Joined_Date` DATE NOT NULL,
  `Status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`Centre_ID`, `Doctor_ID`),
  CONSTRAINT `fk_cdl_centre` FOREIGN KEY (`Centre_ID`) 
    REFERENCES `HEALTHCARE_CENTRE` (`Centre_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cdl_doctor` FOREIGN KEY (`Doctor_ID`) 
    REFERENCES `DOCTOR` (`Doctor_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. SPECIALIZATION Table
CREATE TABLE `SPECIALIZATION` (
  `Specialization_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL UNIQUE,
  `Description` TEXT NULL,
  INDEX `idx_specialization_name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. DOCTOR_SPECIALIZATION Table (M:N Doctor to Specialization)
CREATE TABLE `DOCTOR_SPECIALIZATION` (
  `Doctor_ID` INT NOT NULL,
  `Specialization_ID` INT NOT NULL,
  PRIMARY KEY (`Doctor_ID`, `Specialization_ID`),
  CONSTRAINT `fk_ds_doctor` FOREIGN KEY (`Doctor_ID`) 
    REFERENCES `DOCTOR` (`Doctor_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ds_specialization` FOREIGN KEY (`Specialization_ID`) 
    REFERENCES `SPECIALIZATION` (`Specialization_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. SUBSCRIPTION_PLAN Table
CREATE TABLE `SUBSCRIPTION_PLAN` (
  `Plan_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `Plan_Name` VARCHAR(100) NOT NULL,
  `Target_Role` ENUM('CLIENT', 'PROVIDER', 'ALL') NOT NULL DEFAULT 'CLIENT',
  `Price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `Duration_Days` INT NOT NULL DEFAULT 30,
  `Max_Book_per_Month` INT NOT NULL DEFAULT 5,
  `Search_Radius_KM` INT NOT NULL DEFAULT 10,
  `Description` TEXT NULL,
  `Status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `Created_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_plan_status_role` (`Status`, `Target_Role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. USER_SUBSCRIPTION Table (Subscription History and Active Record)
CREATE TABLE `USER_SUBSCRIPTION` (
  `User_Subscription_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `User_ID` INT NOT NULL,
  `Plan_ID` INT NOT NULL,
  `Start_Date` DATE NOT NULL,
  `End_Date` DATE NOT NULL,
  `Status` ENUM('ACTIVE', 'EXPIRED', 'CANCELLED', 'PENDING') NOT NULL DEFAULT 'ACTIVE',
  `Payment_Reference_No` VARCHAR(100) NOT NULL,
  `Created_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_us_user` FOREIGN KEY (`User_ID`) 
    REFERENCES `USER` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_us_plan` FOREIGN KEY (`Plan_ID`) 
    REFERENCES `SUBSCRIPTION_PLAN` (`Plan_ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_us_user_status_dates` (`User_ID`, `Status`, `Start_Date`, `End_Date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. SCHEDULED_SLOT Table (Provider & Affiliated Doctor Slots)
CREATE TABLE `SCHEDULED_SLOT` (
  `Slot_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `Provider_ID` INT NOT NULL,
  `Doctor_ID` INT NULL,
  `Slot_Date` DATE NOT NULL,
  `Start_Time` TIME NOT NULL,
  `End_Time` TIME NOT NULL,
  `Status` ENUM('AVAILABLE', 'BOOKED', 'BLOCKED') NOT NULL DEFAULT 'AVAILABLE',
  `Created_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_slot_provider` FOREIGN KEY (`Provider_ID`) 
    REFERENCES `PROVIDER` (`Provider_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_slot_doctor` FOREIGN KEY (`Doctor_ID`) 
    REFERENCES `DOCTOR` (`Doctor_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_slot_provider_date_status` (`Provider_ID`, `Slot_Date`, `Status`),
  INDEX `idx_slot_date_status` (`Slot_Date`, `Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. APPOINTMENT Table (Client Bookings with Unique Slot Constraint)
CREATE TABLE `APPOINTMENT` (
  `Appointment_ID` INT AUTO_INCREMENT PRIMARY KEY,
  `Client_ID` INT NOT NULL,
  `Slot_ID` INT NOT NULL UNIQUE,
  `Booking_DateTime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Status` ENUM('BOOKED', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'NO_SHOW') NOT NULL DEFAULT 'BOOKED',
  `Notes` TEXT NULL,
  `Created_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `Updated_At` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_appt_client` FOREIGN KEY (`Client_ID`) 
    REFERENCES `CLIENT` (`Client_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_slot` FOREIGN KEY (`Slot_ID`) 
    REFERENCES `SCHEDULED_SLOT` (`Slot_ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_appt_client_status` (`Client_ID`, `Status`, `Booking_DateTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =======================================================================
-- SEED DATA & REALISTIC SRI LANKAN DEMO RECORDS
-- Standard Demo Password for all accounts: Password123!
-- Bcrypt Hash: $2y$10$wT8K8U1y0VbZl7k2oQ7beOi2h7l9y0b2k5m6n7o8p9q0r1s2t3u4v
-- =======================================================================

-- Specializations
INSERT INTO `SPECIALIZATION` (`Specialization_ID`, `Name`, `Description`) VALUES
(1, 'General Medicine', 'Primary care, diagnosis, preventive health and general wellness management.'),
(2, 'Cardiology', 'Specialized heart care, cardiovascular diseases, hypertension and ECG evaluations.'),
(3, 'Pediatrics', 'Comprehensive child healthcare, immunization, infant development, and adolescent care.'),
(4, 'Dermatology', 'Skin, hair, nail disorders, cosmetic treatments, and allergy management.'),
(5, 'Neurology', 'Brain, nerve disorders, migraine treatments, and neurological assessments.'),
(6, 'Orthopedics', 'Bone, joint, spine disorders, sports injuries, and musculoskeletal care.'),
(7, 'Psychiatry', 'Mental wellness, counseling, stress management, and behavioral health.'),
(8, 'Gynecology & Obstetrics', 'Women health, prenatal care, maternity wellness, and reproductive health.');

-- Subscription Plans (LKR Currency)
-- Client Plans are Annual (365 Days), Provider/Doctor Plans are Monthly (30 Days)
INSERT INTO `SUBSCRIPTION_PLAN` (`Plan_ID`, `Plan_Name`, `Target_Role`, `Price`, `Duration_Days`, `Max_Book_per_Month`, `Search_Radius_KM`, `Description`, `Status`) VALUES
(1, 'Basic Care Annual Plan', 'CLIENT', 15000.00, 365, 5, 10, 'Ideal for individuals. 5 appointments per month and 10 km location radius. Billed annually.', 'ACTIVE'),
(2, 'Standard Family Annual Plan', 'CLIENT', 35000.00, 365, 15, 25, 'Great for families. 15 appointments per month and 25 km extended search radius. Billed annually.', 'ACTIVE'),
(3, 'Premium Platinum Annual Plan', 'CLIENT', 60000.00, 365, 30, 60, 'Maximum flexibility. 30 appointments per month and island-wide 60 km search radius with priority support. Billed annually.', 'ACTIVE'),
(4, 'Provider Professional Monthly Plan', 'PROVIDER', 5000.00, 30, 999, 100, 'Full verified directory listing, unlimited slots, and patient booking management. Billed monthly.', 'ACTIVE');

-- Demo Users (Password: Password123!)
-- Bcrypt Hash: $2y$10$qlIneiFjSMcG8G/ETAK73O9ZNbHLJ2zuQS.YeCNyFTB7U5kVpbi4u
INSERT INTO `USER` (`User_ID`, `Email`, `Password_Hash`, `First_Name`, `Last_Name`, `Phone`, `Role_Type`, `Account_Status`) VALUES
-- 1: System Admin
(1, 'admin@example.com', '$2y$10$qlIneiFjSMcG8G/ETAK73O9ZNbHLJ2zuQS.YeCNyFTB7U5kVpbi4u', 'Kasun', 'Perera', '0771234567', 'SYSTEM_ADMIN', 'ACTIVE'),
-- 2: Client with Active Subscription (Colombo)
(2, 'client@example.com', '$2y$10$qlIneiFjSMcG8G/ETAK73O9ZNbHLJ2zuQS.YeCNyFTB7U5kVpbi4u', 'Nimal', 'Silva', '0777654321', 'CLIENT', 'ACTIVE'),
-- 3: Client with Expired Subscription (Kandy)
(3, 'expired.client@example.com', '$2y$10$qlIneiFjSMcG8G/ETAK73O9ZNbHLJ2zuQS.YeCNyFTB7U5kVpbi4u', 'Sunil', 'Fernando', '0712345678', 'CLIENT', 'ACTIVE'),
-- 4: Doctor (Cardiologist in Colombo)
(4, 'doctor@example.com', '$2y$10$qlIneiFjSMcG8G/ETAK73O9ZNbHLJ2zuQS.YeCNyFTB7U5kVpbi4u', 'Ruwan', 'Jayasinghe', '0779876543', 'PROVIDER', 'ACTIVE'),
-- 5: Doctor (Pediatrician in Kandy)
(5, 'pediatrician@example.com', '$2y$10$qlIneiFjSMcG8G/ETAK73O9ZNbHLJ2zuQS.YeCNyFTB7U5kVpbi4u', 'Anoma', 'Weerasinghe', '0765432109', 'PROVIDER', 'ACTIVE'),
-- 6: Doctor (Dermatologist in Negombo)
(6, 'dermatologist@example.com', '$2y$10$qlIneiFjSMcG8G/ETAK73O9ZNbHLJ2zuQS.YeCNyFTB7U5kVpbi4u', 'Chaminda', 'Bandara', '0751239876', 'PROVIDER', 'ACTIVE'),
-- 7: Healthcare Centre (Colombo 07)
(7, 'centre@example.com', '$2y$10$qlIneiFjSMcG8G/ETAK73O9ZNbHLJ2zuQS.YeCNyFTB7U5kVpbi4u', 'Lanka Care', 'Medical Centre', '0112345678', 'PROVIDER', 'ACTIVE'),
-- 8: Healthcare Centre (Kandy)
(8, 'kandy.centre@example.com', '$2y$10$qlIneiFjSMcG8G/ETAK73O9ZNbHLJ2zuQS.YeCNyFTB7U5kVpbi4u', 'Suwasevana', 'Health Complex', '0812233445', 'PROVIDER', 'ACTIVE');

-- Client Profiles
INSERT INTO `CLIENT` (`Client_ID`, `User_ID`, `Date_of_Birth`, `Gender`, `Address`, `City`, `Latitude`, `Longitude`) VALUES
(1, 2, '1990-05-15', 'MALE', '45/2 Galle Road, Bambalapitiya', 'Colombo', 6.89610000, 79.85720000),
(2, 3, '1985-09-22', 'MALE', '120 Peradeniya Road', 'Kandy', 7.28450000, 80.62000000);

-- Provider Base Profiles
INSERT INTO `PROVIDER` (`Provider_ID`, `User_ID`, `Provider_Type`, `Business_Name`, `Address`, `City`, `Latitude`, `Longitude`, `Contact_Number`, `Description`, `Verification_Status`) VALUES
-- Doctor 1 (Dr. Ruwan - Colombo)
(1, 4, 'DOCTOR', 'Dr. Ruwan Jayasinghe Cardiology Clinic', '75 Ward Place', 'Colombo', 6.91820000, 79.86850000, '0779876543', 'Consultant Cardiologist with over 15 years experience in adult cardiology, hypertension, and preventive cardiology.', 'VERIFIED'),
-- Doctor 2 (Dr. Anoma - Kandy)
(2, 5, 'DOCTOR', 'Dr. Anoma Weerasinghe Child Care', '28 William Gopallawa Mawatha', 'Kandy', 7.28900000, 80.63000000, '0765432109', 'Senior Consultant Pediatrician dedicated to holistic child wellness, growth tracking, and childhood illnesses.', 'VERIFIED'),
-- Doctor 3 (Dr. Chaminda - Negombo)
(3, 6, 'DOCTOR', 'Dr. Chaminda Skin & Laser Clinic', '88 Main Street', 'Negombo', 7.20850000, 79.83900000, '0751239876', 'Specialist Dermatologist offering clinical dermatology, eczema treatments, and aesthetic skincare.', 'VERIFIED'),
-- Centre 1 (Lanka Care - Colombo)
(4, 7, 'HEALTHCARE_CENTRE', 'Lanka Care Specialist Medical Centre', '142 Horton Place', 'Colombo', 6.91450000, 79.87300000, '0112345678', 'Multi-specialty primary care and diagnostic centre with modern laboratory and outpatient consulting rooms.', 'VERIFIED'),
-- Centre 2 (Suwasevana - Kandy)
(5, 8, 'HEALTHCARE_CENTRE', 'Suwasevana Health Complex', '50 Peradeniya Road', 'Kandy', 7.28650000, 80.62500000, '0812233445', 'Central Province flagship clinical care facility with 24/7 specialist doctor consultation suites.', 'VERIFIED');

-- Doctor Specific Records
INSERT INTO `DOCTOR` (`Doctor_ID`, `Provider_ID`, `Medical_License_No`, `Professional_Bio`, `Experience_Years`, `Consultation_Duration`, `Verification_Status`) VALUES
(1, 1, 'SLMC-34921', 'MBBS (Colombo), MD (Cardiology), MRCP (UK). Senior Cardiologist at National Hospital.', 16, 20, 'VERIFIED'),
(2, 2, 'SLMC-28490', 'MBBS (Peradeniya), DCH, MD (Pediatrics). Consultant Pediatrician at Kandy General Hospital.', 12, 15, 'VERIFIED'),
(3, 3, 'SLMC-41052', 'MBBS (Kelaniya), MD (Dermatology). Specialist in advanced dermatological therapies.', 9, 20, 'VERIFIED');

-- Healthcare Centre Specific Records
INSERT INTO `HEALTHCARE_CENTRE` (`Centre_ID`, `Provider_ID`, `Centre_Name`, `Registration_No`, `Description`, `Verification_Status`) VALUES
(1, 4, 'Lanka Care Specialist Medical Centre', 'PHSRC/HC/2023/104', 'Premier outpatient and specialized healthcare facility in Colombo 07.', 'VERIFIED'),
(2, 5, 'Suwasevana Health Complex', 'PHSRC/HC/2021/058', 'Central Province flagship clinical care facility with 24/7 support.', 'VERIFIED');

-- Doctor Specializations Link
INSERT INTO `DOCTOR_SPECIALIZATION` (`Doctor_ID`, `Specialization_ID`) VALUES
(1, 2), -- Dr. Ruwan -> Cardiology
(1, 1), -- Dr. Ruwan -> General Medicine
(2, 3), -- Dr. Anoma -> Pediatrics
(3, 4); -- Dr. Chaminda -> Dermatology

-- Healthcare Centre Doctor Links (Affiliations)
INSERT INTO `CENTRE_DOCTOR_LINK` (`Centre_ID`, `Doctor_ID`, `Joined_Date`, `Status`) VALUES
(1, 1, '2023-01-10', 'ACTIVE'), -- Dr. Ruwan consults at Lanka Care Colombo
(2, 2, '2023-03-15', 'ACTIVE'); -- Dr. Anoma consults at Suwasevana Kandy

-- User Subscriptions
INSERT INTO `USER_SUBSCRIPTION` (`User_Subscription_ID`, `User_ID`, `Plan_ID`, `Start_Date`, `End_Date`, `Status`, `Payment_Reference_No`) VALUES
(1, 2, 2, DATE_SUB(CURRENT_DATE, INTERVAL 5 DAY), DATE_ADD(CURRENT_DATE, INTERVAL 360 DAY), 'ACTIVE', 'PAY-LKR-20260901-893214'),
(2, 3, 1, DATE_SUB(CURRENT_DATE, INTERVAL 400 DAY), DATE_SUB(CURRENT_DATE, INTERVAL 35 DAY), 'EXPIRED', 'PAY-LKR-20260715-442190'),
(3, 4, 4, DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY), DATE_ADD(CURRENT_DATE, INTERVAL 20 DAY), 'ACTIVE', 'PAY-LKR-20260825-778812'),
(4, 7, 4, DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY), DATE_ADD(CURRENT_DATE, INTERVAL 20 DAY), 'ACTIVE', 'PAY-LKR-20260825-992233');

-- Scheduled Slots for Tomorrow and Upcoming Days
INSERT INTO `SCHEDULED_SLOT` (`Slot_ID`, `Provider_ID`, `Doctor_ID`, `Slot_Date`, `Start_Time`, `End_Time`, `Status`) VALUES
-- Slots for Dr. Ruwan (Provider 1, Doctor 1)
(1, 1, 1, DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY), '09:00:00', '09:20:00', 'AVAILABLE'),
(2, 1, 1, DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY), '09:20:00', '09:40:00', 'AVAILABLE'),
(3, 1, 1, DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY), '09:40:00', '10:00:00', 'BOOKED'),
(4, 1, 1, DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), '16:00:00', '16:20:00', 'AVAILABLE'),
(5, 1, 1, DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), '16:20:00', '16:40:00', 'AVAILABLE'),
-- Slots for Dr. Anoma (Provider 2, Doctor 2 - Kandy)
(6, 2, 2, DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY), '10:00:00', '10:15:00', 'AVAILABLE'),
(7, 2, 2, DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY), '10:15:00', '10:30:00', 'AVAILABLE'),
(8, 2, 2, DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY), '10:30:00', '10:45:00', 'AVAILABLE'),
-- Slots for Dr. Chaminda (Provider 3, Doctor 3 - Negombo)
(9, 3, 3, DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), '14:00:00', '14:20:00', 'AVAILABLE'),
(10, 3, 3, DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY), '14:20:00', '14:40:00', 'AVAILABLE'),
-- Slots for Lanka Care Centre (Provider 4 - Affiliated Doctor 1)
(11, 4, 1, DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY), '17:00:00', '17:20:00', 'AVAILABLE'),
(12, 4, 1, DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY), '17:20:00', '17:40:00', 'AVAILABLE');

-- Sample Appointment
INSERT INTO `APPOINTMENT` (`Appointment_ID`, `Client_ID`, `Slot_ID`, `Booking_DateTime`, `Status`, `Notes`) VALUES
(1, 1, 3, NOW(), 'CONFIRMED', 'Regular cardiac checkup and blood pressure review.');
