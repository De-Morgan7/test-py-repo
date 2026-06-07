-- Fee tracking for school_jhs — full schema for a new database.
-- For existing installs that already ran an older schema_fees.sql, run schema_fees_v2.sql instead.

CREATE TABLE IF NOT EXISTS fee_settings (
  academic_year VARCHAR(20) NOT NULL PRIMARY KEY,
  school_fee_expected DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  feeding_fee_expected DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  exam_fee_expected DECIMAL(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_fees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(64) NOT NULL,
  academic_year VARCHAR(20) NOT NULL,
  school_fee_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  feeding_fee_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  exam_fee_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  UNIQUE KEY uq_student_year (student_id, academic_year),
  KEY idx_student_fees_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
