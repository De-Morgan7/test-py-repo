-- Daily feeding fee log (separate from term-based school/exam payment log).
-- Run once in phpMyAdmin → SQL on database school_jhs.

CREATE TABLE IF NOT EXISTS feeding_fee_entries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(64) NOT NULL,
  academic_year VARCHAR(20) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_feeding_year_date (academic_year, payment_date),
  KEY idx_feeding_student_year (student_id, academic_year),
  KEY idx_feeding_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
