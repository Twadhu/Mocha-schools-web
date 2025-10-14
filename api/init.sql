-- Ensure database exists and is selected (prevents #1046 No database selected)
CREATE DATABASE IF NOT EXISTS `schools_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `schools_db`;

-- Schema initialization for unified Schools DB
CREATE TABLE IF NOT EXISTS schools (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  principal_name VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS account_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  role ENUM('student','teacher') NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  gender ENUM('male','female') NULL,
  grade_level VARCHAR(50) NULL,
  section VARCHAR(10) NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  decided_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_school_role (email, school_id, role),
  KEY idx_status (status),
  CONSTRAINT fk_req_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  media_url VARCHAR(500) NULL,
  media_type ENUM('image','video','activity','news') DEFAULT 'activity',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_act_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  KEY idx_school_created (school_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schools (name) VALUES
 ('مدرسة الفقيد محمد عبدالله السراجي'),
 ('مدرسة الشهيد اللقية'),
 ('مدرسة النور بالثوباني')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Users table (يُنشأ الحساب عند الموافقة على الطلب)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  role ENUM('student','teacher','manager') NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  gender ENUM('male','female') NULL,
  grade_level VARCHAR(50) NULL,
  section VARCHAR(10) NULL,
  phone VARCHAR(50) NULL,
  specialization VARCHAR(100) NULL,
  id_number VARCHAR(100) NULL,
  dob DATE NULL,
  status ENUM('active','disabled') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user (school_id, role, email),
  KEY idx_role_school (role, school_id),
  CONSTRAINT fk_user_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tokens for جلسات الدخول (بسيط)
CREATE TABLE IF NOT EXISTS user_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token CHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  UNIQUE KEY uq_token (token),
  KEY idx_user (user_id),
  CONSTRAINT fk_tok_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login attempts for rudimentary rate limiting & security analytics
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_created (email, created_at),
  KEY idx_ip_created (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subjects catalog
CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_subject (school_id, name),
  CONSTRAINT fk_subject_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Teacher homework
CREATE TABLE IF NOT EXISTS homework (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  teacher_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  class_level VARCHAR(50) NOT NULL,
  section VARCHAR(10) NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  due_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_teacher (teacher_id),
  KEY idx_school (school_id),
  CONSTRAINT fk_hw_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_hw_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Teacher exams
CREATE TABLE IF NOT EXISTS exams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  teacher_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  class_level VARCHAR(50) NOT NULL,
  section VARCHAR(10) NULL,
  title VARCHAR(255) NOT NULL,
  duration INT NOT NULL,
  exam_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_teacher (teacher_id),
  KEY idx_school (school_id),
  CONSTRAINT fk_ex_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ex_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Class schedules (by period)
CREATE TABLE IF NOT EXISTS schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  semester TINYINT NOT NULL,
  grade VARCHAR(50) NOT NULL,
  section VARCHAR(10) NULL,
  day ENUM('Sat','Sun','Mon','Tue','Wed','Thu') NOT NULL,
  period TINYINT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  teacher_id INT NULL,
  UNIQUE KEY uq_slot (school_id, semester, grade, section, day, period),
  KEY idx_teacher (teacher_id),
  CONSTRAINT fk_sch_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_sch_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grades table
CREATE TABLE IF NOT EXISTS grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  student_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  term ENUM('first','second') NOT NULL,
  month TINYINT NOT NULL,
  grade DECIMAL(5,2) NOT NULL,
  teacher_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_student (student_id),
  KEY idx_teacher (teacher_id),
  CONSTRAINT fk_grade_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_grade_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_grade_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Term results (per student, per subject, with first/final components)
CREATE TABLE IF NOT EXISTS term_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  student_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  term ENUM('first','second') NOT NULL,
  year_label VARCHAR(20) NOT NULL,
  first_score INT NOT NULL,
  final_score INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_term_result (school_id, student_id, subject, term, year_label),
  KEY idx_student_term (student_id, term, year_label),
  CONSTRAINT fk_tr_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_tr_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lock state for gradebook (by grade/section/subject/term/year)
CREATE TABLE IF NOT EXISTS result_locks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  grade VARCHAR(50) NOT NULL,
  section VARCHAR(10) NOT NULL DEFAULT '',
  subject VARCHAR(100) NOT NULL,
  term ENUM('first','second') NOT NULL,
  year_label VARCHAR(20) NOT NULL,
  locked TINYINT(1) NOT NULL DEFAULT 0,
  locked_by INT NULL,
  locked_at TIMESTAMP NULL,
  approved TINYINT(1) NOT NULL DEFAULT 0,
  approved_by INT NULL,
  approved_at TIMESTAMP NULL,
  UNIQUE KEY uq_lock (school_id, grade, section, subject, term, year_label),
  KEY idx_school (school_id),
  CONSTRAINT fk_lock_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit log for grade changes
CREATE TABLE IF NOT EXISTS grade_audits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  actor_id INT NULL,
  student_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  term ENUM('first','second') NOT NULL,
  year_label VARCHAR(20) NOT NULL,
  prev_first INT NOT NULL,
  prev_final INT NOT NULL,
  new_first INT NOT NULL,
  new_final INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_school_created (school_id, created_at),
  KEY idx_student (student_id),
  CONSTRAINT fk_ga_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_ga_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ga_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  student_id INT NOT NULL,
  status ENUM('present','absent','late') NOT NULL,
  att_date DATE NOT NULL,
  teacher_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_att (student_id, att_date),
  KEY idx_date (att_date),
  CONSTRAINT fk_att_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Teacher subject/class assignments
CREATE TABLE IF NOT EXISTS teacher_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  teacher_id INT NOT NULL,
  subject_id INT NOT NULL,
  class_level VARCHAR(50) NOT NULL,
  UNIQUE KEY uq_ta (teacher_id, subject_id, class_level),
  KEY idx_teacher (teacher_id),
  CONSTRAINT fk_ta_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed some common subjects for each school
INSERT IGNORE INTO subjects (school_id, name)
SELECT s.id, v.subject
FROM schools s
CROSS JOIN (
  SELECT 'اللغة العربية' AS subject UNION ALL
  SELECT 'الرياضيات' UNION ALL
  SELECT 'العلوم' UNION ALL
  SELECT 'اللغة الإنجليزية' UNION ALL
  SELECT 'التربية الإسلامية'
) AS v;

-- School-level settings (score limits, pass thresholds, etc.)
CREATE TABLE IF NOT EXISTS school_settings (
  school_id INT NOT NULL PRIMARY KEY,
  max_first INT NOT NULL DEFAULT 20,
  max_final INT NOT NULL DEFAULT 30,
  pass_threshold INT NOT NULL DEFAULT 50,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed defaults for all schools (id 1..n) if not existing
INSERT INTO school_settings (school_id, max_first, max_final, pass_threshold)
SELECT id, 20, 30, 50 FROM schools s
WHERE NOT EXISTS (SELECT 1 FROM school_settings ss WHERE ss.school_id = s.id);

-- Feature flags (added post-initial deployment). Using separate ALTERs with IF NOT EXISTS for idempotency.
ALTER TABLE school_settings
  ADD COLUMN IF NOT EXISTS activities_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER pass_threshold,
  ADD COLUMN IF NOT EXISTS grades_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER activities_enabled,
  ADD COLUMN IF NOT EXISTS broadcasts_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER grades_enabled,
  ADD COLUMN IF NOT EXISTS attendance_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER broadcasts_enabled;

-- =====================================================================
-- Additional legacy/auxiliary tables required by certain endpoints
-- =====================================================================

-- Some older API endpoints expect father_name / grandfather_name columns
-- Add them if they do not already exist (MySQL 8+ supports IF NOT EXISTS)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS father_name VARCHAR(100) NULL AFTER first_name,
  ADD COLUMN IF NOT EXISTS grandfather_name VARCHAR(100) NULL AFTER father_name;

-- Legacy teacher_subjects mapping (newer unified code uses teacher_assignments)
CREATE TABLE IF NOT EXISTS teacher_subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  teacher_id INT NOT NULL,
  subject_id INT NOT NULL,
  UNIQUE KEY uq_teacher_subject (teacher_id, subject_id),
  KEY idx_subject (subject_id),
  CONSTRAINT fk_ts_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Requirements table referenced by schedule builder prototype
CREATE TABLE IF NOT EXISTS schedule_requirements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id INT NOT NULL,
  grade VARCHAR(50) NOT NULL,
  section VARCHAR(10) NOT NULL,
  semester TINYINT NOT NULL,
  subject_name VARCHAR(100) NOT NULL,
  teacher_id INT NULL,
  total_periods TINYINT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_sched_req (school_id, grade, section, semester, subject_name),
  KEY idx_teacher (teacher_id),
  CONSTRAINT fk_sr_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_sr_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- Seed demo users (manager, teachers, students) for quick local testing
-- NOTE: Password hashes below are for the plaintext password 'Pass1234'
-- You can regenerate with: PHP: password_hash('Pass1234', PASSWORD_BCRYPT);
-- =====================================================================

-- Managers (one per school) - IGNORE duplicates if rerun
INSERT IGNORE INTO users (school_id, role, first_name, last_name, email, password_hash, status)
VALUES
 (1,'manager','مدير','السراجي','manager1@example.com','$2y$10$e0NRa9bQ7BjqDdFZ3VAbgeZ1t/F7fs/3Gzdh0dX8GZFODdgNpTiLa','active'),
 (2,'manager','مدير','اللقية','manager2@example.com','$2y$10$e0NRa9bQ7BjqDdFZ3VAbgeZ1t/F7fs/3Gzdh0dX8GZFODdgNpTiLa','active'),
 (3,'manager','مدير','النور','manager3@example.com','$2y$10$e0NRa9bQ7BjqDdFZ3VAbgeZ1t/F7fs/3Gzdh0dX8GZFODdgNpTiLa','active');

-- A few teachers per school
INSERT IGNORE INTO users (school_id, role, first_name, last_name, email, password_hash, specialization, status)
VALUES
 (1,'teacher','أحمد','المعلم','teacher1@example.com','$2y$10$e0NRa9bQ7BjqDdFZ3VAbgeZ1t/F7fs/3Gzdh0dX8GZFODdgNpTiLa','رياضيات','active'),
 (1,'teacher','سعيد','القحطاني','teacher2@example.com','$2y$10$e0NRa9bQ7BjqDdFZ3VAbgeZ1t/F7fs/3Gzdh0dX8GZFODdgNpTiLa','لغة عربية','active'),
 (2,'teacher','محمد','الحسني','teacher3@example.com','$2y$10$e0NRa9bQ7BjqDdFZ3VAbgeZ1t/F7fs/3Gzdh0dX8GZFODdgNpTiLa','علوم','active');

-- Sample students (grade levels as simple strings: 1,2,3 ... )
INSERT IGNORE INTO users (school_id, role, first_name, father_name, grandfather_name, last_name, email, password_hash, grade_level, section, status)
VALUES
 (1,'student','خالد','محمد','أحمد','السراجي','st1@example.com','$2y$10$e0NRa9bQ7BjqDdFZ3VAbgeZ1t/F7fs/3Gzdh0dX8GZFODdgNpTiLa','1','A','active'),
 (1,'student','نورة','علي','صالح','الشيباني','st2@example.com','$2y$10$e0NRa9bQ7BjqDdFZ3VAbgeZ1t/F7fs/3Gzdh0dX8GZFODdgNpTiLa','1','A','active'),
 (1,'student','ليان','حسن','عبدالله','العدني','st3@example.com','$2y$10$e0NRa9bQ7BjqDdFZ3VAbgeZ1t/F7fs/3Gzdh0dX8GZFODdgNpTiLa','2','B','active');

-- Teacher ↔ Subject legacy assignments (teacher_subjects) using subselects
INSERT IGNORE INTO teacher_subjects (school_id, teacher_id, subject_id)
SELECT 1, t.id, s.id FROM users t JOIN subjects s ON s.school_id=1 AND s.name='الرياضيات' WHERE t.email='teacher1@example.com';
INSERT IGNORE INTO teacher_subjects (school_id, teacher_id, subject_id)
SELECT 1, t.id, s.id FROM users t JOIN subjects s ON s.school_id=1 AND s.name='اللغة العربية' WHERE t.email='teacher2@example.com';

-- Unified teacher_assignments for the consolidated admin endpoints
INSERT IGNORE INTO teacher_assignments (school_id, teacher_id, subject_id, class_level)
SELECT 1, t.id, s.id, '1' FROM users t JOIN subjects s ON s.school_id=1 AND s.name='الرياضيات' WHERE t.email='teacher1@example.com';
INSERT IGNORE INTO teacher_assignments (school_id, teacher_id, subject_id, class_level)
SELECT 1, t.id, s.id, '1' FROM users t JOIN subjects s ON s.school_id=1 AND s.name='اللغة العربية' WHERE t.email='teacher2@example.com';

-- Sample term results for quick gradebook testing (year_label current year)
INSERT IGNORE INTO term_results (school_id, student_id, subject, term, year_label, first_score, final_score)
SELECT 1, u.id, 'الرياضيات','first', YEAR(CURDATE()), 15, 25 FROM users u WHERE u.email='st1@example.com';
INSERT IGNORE INTO term_results (school_id, student_id, subject, term, year_label, first_score, final_score)
SELECT 1, u.id, 'اللغة العربية','first', YEAR(CURDATE()), 18, 27 FROM users u WHERE u.email='st1@example.com';
INSERT IGNORE INTO term_results (school_id, student_id, subject, term, year_label, first_score, final_score)
SELECT 1, u.id, 'الرياضيات','first', YEAR(CURDATE()), 14, 24 FROM users u WHERE u.email='st2@example.com';

-- Lock row example (unlocked) for grade 1 A الرياضيات
INSERT IGNORE INTO result_locks (school_id, grade, section, subject, term, year_label, locked, approved)
VALUES (1,'1','A','الرياضيات','first',YEAR(CURDATE()),0,0);

-- =============================================================
-- END OF EXTENDED SEED SECTION
-- =============================================================
