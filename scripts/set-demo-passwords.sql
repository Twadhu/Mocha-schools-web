-- Patch demo users passwords to bcrypt('password123')
USE school_db;
UPDATE users SET password = '$2y$10$cFqliyYVjUxmgARVOtuJMeoZNajRtQDehuQLeEMXMMe.pTGlPzc4m' WHERE email IN ('student@school.com','teacher@school.com');
SELECT email FROM users WHERE email IN ('student@school.com','teacher@school.com');
