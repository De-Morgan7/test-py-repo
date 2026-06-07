-- Upgrade: exam fees, payment log for daily/term reporting.
-- Run once in MySQL on database school_jhs (phpMyAdmin → SQL).
-- If you see "Duplicate column name", that column already exists — skip that line.

ALTER TABLE fee_settings
  ADD COLUMN exam_fee_expected DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER feeding_fee_expected;

ALTER TABLE student_fees
  ADD COLUMN exam_fee_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER feeding_fee_paid;

CREATE TABLE IF NOT EXISTS fee_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(64) NOT NULL,
  academic_year VARCHAR(20) NOT NULL,
  term VARCHAR(32) NOT NULL DEFAULT 'First Term',
  fee_type ENUM('school','feeding','exam') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pay_date_type (payment_date, fee_type),
  KEY idx_pay_year_term (academic_year, term),
  KEY idx_student_year (student_id, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
