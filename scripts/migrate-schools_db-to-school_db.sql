-- Migration scaffold: move critical data from legacy `schools_db` to unified `school_db`
-- NOTE: Adjust mappings as needed before running. Execute once.

-- Example: copy schools to unified `schools` if structures match or adjust columns accordingly
-- INSERT INTO school_db.schools (name)
-- SELECT name FROM schools_db.schools_old; -- adjust source table/columns

-- Example: migrate users (manager/teacher/student) into unified `users` (varchar ids required)
-- INSERT INTO school_db.users (id, email, password, type, first_name, last_name)
-- SELECT CONCAT('LGC', u.id), u.email, u.password_hash, 
--        CASE u.role WHEN 'manager' THEN 'manager' WHEN 'teacher' THEN 'teacher' ELSE 'student' END,
--        u.first_name, u.last_name
-- FROM schools_db.users u
-- WHERE NOT EXISTS (SELECT 1 FROM school_db.users x WHERE x.email=u.email);

-- Add more INSERT ... SELECT statements for attendance, subjects, schedules, etc., mapping to the unified schema.

-- Always backup both databases before migration.
