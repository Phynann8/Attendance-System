# 🏫 Attendance System

A role-based school attendance system built with **Laravel 13**, **MySQL 8**, and **Docker**.
It implements the frozen business workflow for parent permissions, teacher marking,
Student Affairs late-verification, and Admin absence review.

## Roles & access

| Role               | Email                 | Password  |
|--------------------|-----------------------|-----------|
| Admin              | `admin@school.test`   | `password`|
| Teacher            | `teacher@school.test` | `password`|
| Student Affairs    | `affairs@school.test` | `password`|
| Parent             | `parent@school.test`  | `password`|

The app runs at **http://localhost:8080**.

## Business workflow (the 15 rules)

```
PARENT → Admin verifies permission BEFORE class → APPROVED → teacher sees 🔒 locked row
Class starts → teacher marks Present/Absent → SUBMIT
        → Student Affairs checks absent students
              ├─ Arrived after check → LATE (case closed by Student Affairs)
              └─ Never arrived → Admin
                    ├─ Permission exists / valid parent reason → EXCUSED
                    └─ Invalid reason → ABSENT WITHOUT PERMISSION
```

- **Rule 9** – an approved pre-class permission is pre-locked when the teacher opens attendance.
- **Rule 10** – the teacher can never change a locked permission row.
- **Rule 1/2** – teachers only ever mark `present` or `absent` (never late).
- **Rule 3/4** – Student Affairs computes `minutes_late` from the teacher's submit time, then closes the case.
- **Rule 13** – valid parent reason → Admin grants permission → `excused`.
- **Rule 14** – invalid reason → `absent_without_permission`.
- **Rule 15** – every action is written to the `attendance_logs` audit table.

## Database design

```
users ──┬─ classes (teacher_id)
        ├─ students (parent_user_id)
        ├─ permissions (student, class, attendance_date, status, approved_by …)
        ├─ attendance_sessions (class, teacher, date, opened/submitted_at)
students ─ attendances
attendances: status (teacher) · is_locked · lock_reason · arrived_at ·
             minutes_late · case_status · final_status
attendance_logs: audit trail (action, user, details)
```

Final classification (`attendances.final_status`): `present`, `late`, `excused`, `absent_without_permission`.

## Quick start

### 1) Docker (recommended)

```bash
docker compose up -d --build
```

The `app` container, on first boot:

1. Installs Composer dependencies (if `vendor/` is missing),
2. generates an application key,
3. runs `php artisan migrate`,
4. seeds demo data (`php artisan db:seed`),
5. starts PHP-FPM behind Nginx on **http://localhost:8080**.

MySQL is exposed on host port **3307** (db: `attendance_system`, user: `attendance`, password: `secret`).

### 2) Local PHP (no Docker)

```bash
composer install
copy .env.example .env        # or: cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Demo data included

The seeder replays the full end-to-end example (section 21) for **yesterday**, and
sets up a live state for **today**:

- Yesterday → complete final table: Dara `excused` (pre-class permission), Ratha `late` (17 min),
  Sopheak `excused` (called parent), Lina `absent_without_permission`.
- Today → one **pending** permission (Sokha) for the Admin to approve while the session is open,
  and one already-approved + locked permission (Dara). Approve Sokha's permission and watch the
  teacher's open page lock the row live.

## Running the tests

```bash
php artisan test            # uses SQLite :memory: automatically
# or inside the container:
docker compose exec app php artisan test
```

## Project layout

```
app/
  Http/Controllers/        Admin · Teacher · StudentAffairs · Parent · Auth
  Http/Middleware/EnsureRole.php
  Models/                  User, ClassRoom, Student, Permission, AttendanceSession,
                           Attendance, AttendanceLog
  Services/AttendanceService.php   ← all business rules
  Services/AuditService.php        ← audit log writer
database/
  migrations/              full schema
  seeders/DatabaseSeeder.php       ← demo workflow replay
docker/                    nginx.conf + app-init.sh
resources/views/           Blade templates per role
public/css/app.css         plain CSS (no build step required)
```

## Note on the demo accounts

- The parent account is linked to the students **Dara** and **Sokha** so the
  parent portal request flow can be exercised.
- The teacher account is the homeroom teacher of **10A** and **9B**.
