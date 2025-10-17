# JWT API Migration Notes

## Backend (PHP)

- New files under `api/`: `db.php`, `api.php`, `student_api.php`, `teacher_api.php`
- Install dependencies: run Composer in `api/` to install `firebase/php-jwt`
- Configure JWT secret via env var `JWT_SECRET_KEY` or edit `db.php`

## Database

- Import `database.sql` into MySQL (creates `school_db` schema and seed data)
- Default MySQL credentials in `db.php`: user `root` with empty password (adjust to your local setup)

## Frontend wiring

- `student-login.html` and `teacher-login.html` POST to `api/api.php?action=login`
- After login, token is stored as `localStorage.authToken`; use header `Authorization: Bearer <token>`
- `student.html` and `teacher.html` should call `api/api.php` with appropriate `action` and role via token

## Endpoints (examples)

- `POST /api/api.php?action=login` with JSON `{ email, password, role: 'student'|'teacher' }`
- `GET` with Bearer token & role inferred from token:
  - `action=getUser|dashboard|schedule|attendance|grades|announcements|materials|events` for student
  - `action=getUser|classes|assignments|save_assignment|delete_assignment|students|grade|attendance|schedule` for teacher

## Security

- Use HTTPS in production, rotate `JWT_SECRET_KEY`, shorten token expiration if needed.
