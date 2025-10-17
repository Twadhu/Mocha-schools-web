# Copilot instructions for Mocha Schools Web

This repo is a PHP (XAMPP) + static HTML/CSS/JS project with a unified JWT-based API under `api/` and a modern portal UI under `front-mocha-schools-website/portal.html`.

## Stack and conventions
- Backend: PHP 8+ (XAMPP/Apache), MySQL (MariaDB) via PDO.
- Auth: JWT using `firebase/php-jwt` installed via Composer in `api/`.
- Router: `api/api.php` dispatches to role-specific handlers `student_api.php` and `teacher_api.php` by inspecting the JWT.
- DB schema: `database.sql` creates `school_db` and seed data. Defaults in `api/db.php` assume root/no password on localhost.
- Frontend: Static pages using Tailwind via CDN; portal UI is in `front-mocha-schools-website/portal.html` and communicates with the API using `Authorization: Bearer <token>`.

## API contract (high level)
- POST `api/api.php?action=login` with `{ email, password, role: 'student'|'teacher' }` returns `{ ok, token, user }`.
- All other actions require `Authorization: Bearer <token>` and are dispatched by user role:
  - Student actions: `getUser`, `dashboard`, `schedule`, `attendance`, `assignments`, `grades`, `announcements`, `materials`, `events`, `submit_assignment` (POST).
  - Teacher actions: `getUser`, `classes`, `assignments`, `submissions&assignmentId=...`, `save_assignment` (POST), `delete_assignment&id=...`, `students&classId=...`, `grade` (POST; accepts `student_id`), `attendance` (GET list; POST bulk save), `schedule`, `materials` (GET/POST/DELETE), `announce` (POST).

## Frontend usage
- Use `front-mocha-schools-website/unifilogin.html` to login; it saves `authToken` in `localStorage` and redirects to `portal.html`.
- In `portal.html`, requests are made via a tiny API wrapper that prefixes paths relatively and injects the JWT header.
- Avoid referencing legacy endpoints (`api/teacher.php`, `api/subjects.php`, `reports/attendance.pdf.php`). Prefer `api/api.php?action=...` actions above. If bridging is required, add actions in `teacher_api.php`/`student_api.php` instead of new top-level files.

## Coding guidelines
- Keep all PHP JSON responses consistent: `send_json(['ok'=>true|false, ...], statusCode)` helper in `api/db.php`.
- Parameterize SQL with prepared statements only.
- Extend the unified router rather than adding new loose PHP files.
- Prefer adding small bridging actions to match existing UI shapes when refactoring.
- Keep CORS behavior centralized in `api/db.php`.

## Local run
1. Install dependencies in `api/` using Composer to get `firebase/php-jwt`.
2. Import `database.sql` into MySQL; keep DB credentials aligned with `api/db.php`.
3. Start Apache+MySQL in XAMPP and open `front-mocha-schools-website/unifilogin.html`.

## Tests and QA
- No automated tests yet. For manual checks: login as `student@school.com` / `teacher@school.com` with `password123` and verify dashboard, assignments, schedule, grades, attendance, and materials flows.

## Security
- Never expose JWT secret; can be set via `JWT_SECRET_KEY` env var.
- Validate user input; ensure proper role checks where data is role-scoped.

## What Copilot should prefer
- When adding features, extend `student_api.php` / `teacher_api.php` with new `case` blocks and document the action name in this file.
- When the UI needs new data, consider a minimal mapping layer in JS first, then adjust PHP.
- Avoid touching legacy endpoints unless you’re replacing them with router actions.
