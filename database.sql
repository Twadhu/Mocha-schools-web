-- Schools JWT API schema and seed
-- Generated: 2025-10-17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `school_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `school_db`;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` varchar(20) NOT NULL,
  `content` text NOT NULL,
  `date` date NOT NULL DEFAULT (CURRENT_DATE),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `announcements` (`teacher_id`,`content`,`date`) VALUES
('TCH98765','تذكير: سيتم عقد اجتماع أولياء الأمور يوم الخميس القادم. يرجى الحضور.','2025-10-15'),
('TCH98766','تم رفع أوراق عمل إضافية لوحدة الحركة في الفيزياء.','2025-10-18');

CREATE TABLE IF NOT EXISTS `assignments` (
  `id` varchar(20) NOT NULL,
  `class_id` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('واجب','اختبار') NOT NULL,
  `due_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `assignments` (`id`,`class_id`,`title`,`type`,`due_date`) VALUES
('A1','C1','واجب التفاضل الأول','واجب','2025-10-25'),
('A2','C1','اختبار شهري - الوحدة الأولى','اختبار','2025-10-28'),
('A3','C2','واجب الهندسة التحليلية','واجب','2025-11-05');

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `class_id` varchar(20) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','late','absent') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_date` (`student_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `attendance` (`student_id`,`class_id`,`date`,`status`) VALUES
('STU12345','C1','2025-10-16','present'),
('STU12346','C1','2025-10-16','present'),
('STU12345','C1','2025-10-17','absent'),
('STU12346','C1','2025-10-17','present');

CREATE TABLE IF NOT EXISTS `classes` (
  `id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `teacher_id` varchar(20) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `classes` (`id`,`name`,`teacher_id`,`subject_id`) VALUES
('C1','الأول الثانوي أ','TCH98765','SUBJ01'),
('C2','الأول الثانوي ب','TCH98765','SUBJ01'),
('C3','الثاني الثانوي أ','TCH98766','SUBJ02');

CREATE TABLE IF NOT EXISTS `events` (
  `id` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `type` enum('meeting','exam','holiday','event') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `events` (`id`,`title`,`date`,`type`) VALUES
('E1','اجتماع أولياء الأمور','2025-10-23','meeting'),
('E2','بداية الاختبارات النصفية','2025-11-15','exam'),
('E3','إجازة اليوم الوطني','2025-12-18','holiday');

CREATE TABLE IF NOT EXISTS `materials` (
  `id` varchar(20) NOT NULL,
  `class_id` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('pdf','video','link') NOT NULL,
  `url` text NOT NULL,
  `added_by` varchar(20) NOT NULL,
  `date` date NOT NULL DEFAULT (CURRENT_DATE),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `materials` (`id`,`class_id`,`title`,`type`,`url`,`added_by`,`date`) VALUES
('M1','C1','ملخص قوانين التفاضل','pdf','#','TCH98765','2025-10-19'),
('M2','C1','فيديو شرح المتجهات','video','#','TCH98765','2025-10-21');

CREATE TABLE IF NOT EXISTS `schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` varchar(20) NOT NULL,
  `day` enum('الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس') NOT NULL,
  `period` int(11) NOT NULL,
  `subject_id` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `schedule` (`class_id`,`day`,`period`,`subject_id`) VALUES
('C1','الأحد',1,'SUBJ01'),
('C1','الأحد',2,'SUBJ02'),
('C1','الاثنين',3,'SUBJ01'),
('C1','الاثنين',4,'SUBJ03');

CREATE TABLE IF NOT EXISTS `submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `assignment_id` varchar(20) NOT NULL,
  `status` enum('submitted','graded') NOT NULL,
  `submitted_at` date DEFAULT NULL,
  `grade` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_assignment` (`student_id`,`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `submissions` (`student_id`,`assignment_id`,`status`,`submitted_at`,`grade`) VALUES
('STU12345','A1','graded','2025-10-24',95),
('STU12346','A1','submitted','2025-10-25',NULL),
('STU12345','A2','graded','2025-10-27',88);

CREATE TABLE IF NOT EXISTS `subjects` (
  `id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `subjects` (`id`,`name`) VALUES
('SUBJ01','الرياضيات'),('SUBJ02','الفيزياء'),('SUBJ03','الكيمياء');

CREATE TABLE IF NOT EXISTS `users` (
  `id` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `type` enum('student','teacher','director','manager') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `avatar_url` text DEFAULT NULL,
  `class_id` varchar(20) DEFAULT NULL,
  `details` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- bcrypt hash placeholder for demo accounts, set your own passwords
-- Demo accounts default password hash (bcrypt of 'password123')
SET @DEMO_HASH = '$2y$10$cFqliyYVjUxmgARVOtuJMeoZNajRtQDehuQLeEMXMMe.pTGlPzc4m';

INSERT INTO `users` (`id`,`email`,`password`,`type`,`first_name`,`last_name`,`avatar_url`,`class_id`,`details`) VALUES
('STU12345','student@school.com',@DEMO_HASH,'student','أحمد','علي','https://placehold.co/128x128/3B82F6/FFFFFF?text=A','C1','{"school":"مدرسة المستقبل الثانوية"}'),
('STU12346','sara@school.com',@DEMO_HASH,'student','سارة','محمد','https://placehold.co/128x128/EC4899/FFFFFF?text=S','C1','{"school":"مدرسة المستقبل الثانوية"}'),
('STU12347','khalid@school.com',@DEMO_HASH,'student','خالد','عبدالله','https://placehold.co/128x128/F97316/FFFFFF?text=K','C2','{"school":"مدرسة المستقبل الثانوية"}'),
('TCH98765','teacher@school.com',@DEMO_HASH,'teacher','فاطمة','الزهراء','https://placehold.co/128x128/0EA5E9/FFFFFF?text=F',NULL,'{"phone":"+966 55 555 1234","email":"teacher@school.com","office":"B-102","mainSubject":"الرياضيات"}'),
('TCH98766','y.hassan@school.com',@DEMO_HASH,'teacher','يوسف','حسن','https://placehold.co/128x128/10B981/FFFFFF?text=Y',NULL,'{"phone":"+966 55 555 5678","email":"y.hassan@school.com","office":"C-201","mainSubject":"الفيزياء"}');

-- Optional: Directorate role user (password: password123) seeded if absent
INSERT INTO users (id, email, password, type, first_name, last_name)
SELECT 'DIR10001', 'director@office.com', @DEMO_HASH, 'director', 'Edu', 'Director'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='director@office.com');

-- Optional tables for Directorate; non-breaking additions
CREATE TABLE IF NOT EXISTS schools (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  code VARCHAR(64) NOT NULL UNIQUE,
  director_name VARCHAR(255) NULL,
  phone VARCHAR(64) NULL,
  email VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS report_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requested_by VARCHAR(20) NOT NULL,
  type VARCHAR(128) NOT NULL,
  params JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (requested_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS report_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  school_id INT NOT NULL,
  submitted_by VARCHAR(20) NOT NULL,
  data JSON NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES report_requests(id),
  FOREIGN KEY (school_id) REFERENCES schools(id),
  FOREIGN KEY (submitted_by) REFERENCES users(id)
);

COMMIT;

-- Optional: Account requests table for student/teacher signup workflow
CREATE TABLE IF NOT EXISTS `account_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `school_id` INT NULL,
  `role` ENUM('student','teacher') NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `gender` VARCHAR(16) NULL,
  `grade_level` VARCHAR(16) NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `decided_at` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: Device locks for special accounts (restrict devices)
CREATE TABLE IF NOT EXISTS `device_locks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(64) NOT NULL,
  `device_hash` VARCHAR(128) NOT NULL,
  `label` VARCHAR(128) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `u_user_device` (`user_id`,`device_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: Special accounts store (username + password hash)
CREATE TABLE IF NOT EXISTS `special_accounts` (
  `username` VARCHAR(128) PRIMARY KEY,
  `password_hash` VARCHAR(255) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
