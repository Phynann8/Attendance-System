# School Attendance System — Presentation Notes

Ready-to-use facts for slides. Everything below is taken directly from the codebase.

---

## 1. Title / Elevator pitch

**School Attendance System** — a role-based web application that digitizes the full
school attendance workflow: parent permission requests → admin verification → teacher
marking → late verification → final absence classification, with a complete audit trail.

- Built with **Laravel 13** (PHP 8.3+), **MySQL 8**, **Docker**, plain-CSS Blade UI
- **4 roles**, **15 frozen business rules**, **6 domain tables**, full audit logging
- ~**1,420 lines** of application code (26 PHP files) — deliberately lean, service-oriented
- **18 automated tests** covering the business workflow

---

## 2. The problem

- Paper attendance is slow, easy to fake, and hard to audit.
- Excused-vs-truant decisions cause disputes: *who approved the permission? who verified
  the parent's reason? when exactly did the student arrive?*
- Schools need **one workflow with enforced rules**, not just a checklist.

**The solution:** a system where the *software* enforces the school's 15 business rules —
teachers physically cannot mark a permission-locked student absent; only Student Affairs
can compute "late"; only Admin can close a case with the final classification.

---

## 3. The four roles & their portals

| Role | Login | What they do in the system |
|------|-------|----------------------------|
| **Admin** | `admin@school.test` | Verify parent permission requests (approve/reject), assign permissions manually, review absences of students who never arrived (excused / absent-without-permission), manage students & classes, view reports |
| **Teacher** | `teacher@school.test` | Open the attendance session for their class, mark each student **Present/Absent**, submit for the day |
| **Student Affairs** | `affairs@school.test` | After teacher submits: check absentees. Student showed up? → mark arrival → system computes **LATE**. Never arrived? → **escalate** to Admin |
| **Parent** | `parent@school.test` | Submit permission requests (student + date + reason) and view history |

Demo password for all accounts: `password`

---

## 4. The workflow (core diagram for a slide)

```
BEFORE CLASS
  Parent requests permission ──► Admin verifies reason
        ├─ valid + BEFORE class ──► APPROVED ──► row PRE-LOCKED for the teacher
        └─ invalid ──► REJECTED

DURING CLASS
  Teacher opens session ──► marks Present / Absent ──► SUBMITS
        (locked rows cannot be touched — Rule 10)

AFTER SUBMIT — Student Affairs checks the absentees
        ├─ Arrived late ──► LATE (minutes computed from teacher's submit time) ──► CLOSED
        └─ Never arrived ──► escalated to ADMIN
                ├─ valid parent reason ──► EXCUSED
                └─ invalid reason ──► ABSENT WITHOUT PERMISSION

Every single action is written to the attendance_logs audit table (Rule 15).
```


---

## 5. The 15 business rules (the heart of the project)

The full rule set is enforced in **one service class**: `app/Services/AttendanceService.php`

| # | Rule | How the code enforces it |
|---|------|--------------------------|
| 1–2 | Teacher marks only `present` or `absent` — never `late` | `saveMarks()` throws on any other value |
| 3–4 | Only Student Affairs determines LATE; `minutes_late` = arrival time − teacher's submit time | `markArrived()` computes from `submitted_at` |
| 5–8 | Absentee follow-up flow: SA closes simple cases, escalates the rest | `case_status`: `pending → closed / escalated` |
| 9 | An approved pre-class permission is **pre-locked** the moment the teacher opens attendance | `openSession()` sets `is_locked=true, status=permission, final_status=excused` |
| 10 | The teacher can **never** change a locked permission row | `saveMarks()` skips locked rows entirely |
| 9-live | Admin approving a permission while the session is open locks the row **instantly** | `approvePermission()` finds the open session and locks |
| 11–14 | Admin final decision on never-arrived students: valid reason → `excused` (create or reuse an approved permission); invalid → `absent_without_permission` | `finalizeExcused*()` / `finalizeAbsentWithoutPermission()` |
| 15 | Every action is written to the audit table | `AuditService::log()` on every state change |

**Guard rails everywhere:** cannot mark a submitted session, cannot submit with unmarked
students, cannot approve/reject a non-pending permission, cannot finalize an already
finalized record. All state changes run inside **DB transactions**.

---

## 6. Final status classification (great "results" slide)

Every attendance row ends with exactly one `final_status`:

| Final status | Set by | Meaning |
|--------------|--------|---------|
| `present` | Teacher submit | Was in class |
| `late` | Student Affairs | Arrived after submit; exact minutes computed |
| `excused` | Admin / pre-lock | Valid permission existed |
| `absent_without_permission` | Admin | No valid reason — truancy |

---

## 7. Database design (6 domain tables)

```
users (role: admin | teacher | student_affairs | parent)
  └─ classes (name, grade, teacher_id)
       └─ students (name, class_id, parent_user_id, parent_name, parent_phone, is_active)

permissions   (student, class, attendance_date, requested_by parent|admin, reason,
               evidence_path upload, status pending|approved|rejected, admin_note)
               index: (student_id, attendance_date, status)

attendance_sessions (class, teacher, session_date, opened_at, submitted_at,
               status open|submitted|closed)

attendances   (session, student,
               status present|absent|permission,  marked_by/at,
               is_locked, locked_by/at, lock_reason, permission_id,
               arrived_at, minutes_late, case_status pending|closed|escalated,
               final_status present|late|excused|absent_without_permission,
               finalized_by/at, admin_note)
               UNIQUE (session, student)

attendance_logs (audit trail: action, user, details JSON, timestamps)
```

- One attendance row **per student per session** (unique constraint)
- Every foreign key has a proper constraint; deletes cascade safely
- Three-layer status model: **teacher status → case status → final status**


---

## 8. Architecture & code organization

```
app/
  Http/Controllers/        11 thin controllers: Auth, Dashboard,
                           Admin (5), Teacher, StudentAffairs, Parent
  Http/Middleware/         EnsureRole  <- role-based access control
  Http/Requests/           3 FormRequest classes (server-side validation)
  Models/                  7 Eloquent models with status constants
  Services/
    AttendanceService.php  <- ALL 15 business rules (transactional)
    AuditService.php       <- audit trail writer
resources/views/           27 Blade templates (role-specific dashboards)
public/css/app.css         hand-written CSS - no build step needed
database/migrations/       6 domain migrations
database/seeders/          full demo-scenario replay
docker/                    nginx.conf + self-healing app-init.sh
tests/                     18 tests (PHPUnit, SQLite in-memory)
```

**Design decisions worth mentioning to the teacher:**
- **Fat service, thin controllers** — business rules live in one auditable place, not
  scattered across controllers.
- **Server-side enforcement, not UI enforcement** — hiding a button is not security;
  the service layer throws exceptions if a rule is violated via any channel.
- **Transactions** — a session open or permission approval either fully succeeds or
  rolls back; no half-written state.
- **RBAC middleware** (`EnsureRole`) + route-level guards — a parent cannot call an
  admin endpoint even with a crafted request.
- **Laravel CSRF protection** on all forms, plus validation via FormRequests.

---

## 9. Deployment & DevOps

- **Docker Compose** — 3 containers:
  - `app` — PHP 8.4-FPM
  - `nginx` 1.27 — port **8080 → 80**
  - `mysql` 8.0 — port **3307 → 3306**, with **healthcheck** gating the app start
- **Self-healing bootstrap** (`docker/app-init.sh`): on start it installs vendor deps if
  missing, waits for MySQL, generates the app key, migrates, seeds demo data, then
  serves → `docker compose up -d --build` is the *only* command needed.
- **Live internet demo:** free **Cloudflare quick-tunnel** (`cloudflared tunnel --url
  http://localhost:8080`) — the app is configured with trusted-proxy support so URLs,
  forms and redirects work over the public HTTPS tunnel.
- One-command test suite: `php artisan test` (SQLite in-memory — no Docker needed).

---

## 10. Demo data (seeded scenario — "replay-ready")

The seeder replays the complete worked example (spec section 21) for **yesterday** and
sets up a **live scenario for today**:

**Yesterday — finished, report-ready table:**

| Student | Outcome | Why |
|---------|---------|-----|
| Dara | `excused` | Approved pre-class permission (locked) |
| Ratha | `late` (17 min) | Arrived after teacher submitted |
| Sopheak | `excused` | Admin called parent — reason verified valid |
| Lina | `absent_without_permission` | Parent: "she didn't want to come" — invalid |

**Today — live workflow for the demo:**

- **Sokha** has a **pending** permission (medical appointment, 07:15) → Admin approves
  it *while the teacher's session is open* → the row locks live (Rule 9).
- **Dara** already approved + locked → teacher sees the 🔒 locked row (Rule 10).

---

## 11. Suggested live-demo script (5 minutes)

1. **Parent** — request a permission (or show Sokha's pending one). *(30s)*
2. **Admin** — approve Sokha's request → "watch what happens on the teacher's screen". *(1 min)*
3. **Teacher** — open today's session: Dara's row is 🔒 locked; mark everyone else; submit. *(1.5 min)*
4. **Student Affairs** — mark Sokha as "arrived" → LATE + minutes computed → case closed;
   escalate a never-arrived student to Admin. *(1 min)*
5. **Admin** — absence review: excuse / absent-without-permission → show the **Reports**
   page and the **audit log**. *(1 min)*

Demo accounts: `admin@school.test` · `teacher@school.test` · `affairs@school.test` ·
`parent@school.test` — password `password`

---

## 12. Key talking points (if the teacher asks)

- **Why Laravel?** Mature MVC framework: migrations, Eloquent ORM, validation, CSRF,
  middleware, testing built-in — lets the code focus on the *business rules*.
- **Why a Service class?** The 15 rules are the product. Centralizing them makes them
  testable and provable — 18 tests exercise the exact rules.
- **How do you prevent a teacher from faking attendance?** They can only mark
  present/absent; late/excused/absent-without-permission decisions belong to other
  roles; every change is attributed (`marked_by`, `locked_by`, `finalized_by`) and
  logged in the audit trail.
- **How is "late" calculated?** `minutes_late = student's actual arrival time −
  teacher's submit time` — computed by Student Affairs, never editable by the teacher.
- **Concurrency:** unique DB constraint (one row per student per session) +
  transactions prevent duplicate or partial records.
- **Security:** role middleware, CSRF tokens, mass-assignment protection (`$fillable`),
  server-side validation, foreign-key constraints, audit trail.
- **Zero-build frontend:** plain hand-written CSS served straight from `public/css` —
  fast, dependency-free, and easy to demo anywhere (no npm build required).


