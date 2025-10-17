# Run locally (Windows + XAMPP)

Follow these steps to run the unified JWT-based portal locally.

## 1) Start XAMPP services
- Open XAMPP Control Panel
- Start Apache and MySQL

## 2) Install PHP dependencies (JWT)
- Option A (Composer installed globally)
  ```powershell
  powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1
  ```
- Option B (composer.phar in `api/` and PHP from XAMPP)
  - Place `composer.phar` in `api/`
  ```powershell
  cd .\api
  C:\xampp\php\php.exe composer.phar install
  ```

Expected: `api\vendor\autoload.php` exists after install.

## 3) Import the database
- Default creds: user `root`, empty password
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\import-db.ps1 -User root -Password ""
```

This imports `database.sql` -> creates `school_db` with seed data.

## 4) Open the portal
- Login page: http://localhost/Schools-sites/front-mocha-schools-website/unifilogin.html
- Test accounts:
  - Student: `student@school.com` / `password123`
  - Teacher: `teacher@school.com` / `password123`

## Troubleshooting
- If login fails with 500 and message about `vendor/autoload.php`, rerun step (2).
- If DB errors appear, ensure `api/db.php` matches your MySQL user/password, and redo step (3).
- Token 401 redirects you back to the login page with `?expired=1`.

## Notes
- Unified API router: `api/api.php?action=...` protects all calls via JWT.
- The modern portal is `portal.html`; `student.html`/`teacher.html` are legacy and not used in the new flow.
