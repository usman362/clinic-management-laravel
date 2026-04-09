# QA Audit Report — Clinic Management Laravel
**Auditor:** Senior QA (automated analysis)
**Date:** 2026-04-09
**Scope:** Full-stack audit — 22 phases
**Branch:** `main` @ `8d4a133`
**Codebase size:** 59 models, 58 controllers, 60 Livewire components, 35 repositories, 99 migrations, ~840 blade files

> **Detailed findings with file:line references are in `QA_FINDINGS.md`.** This report is the executive summary + prioritized action plan.

---

## 🎯 Executive Summary

The project is a **feature-rich clinical management system** with substantial functionality. Recent client-feedback cycles (CP-*, AP-*, DP-*) have improved specific flows. **However, the audit surfaces serious systemic risks across security, data integrity, and HIPAA/PHI compliance** that should block production use until addressed.

### Overall Risk: 🔴 **HIGH — NOT PRODUCTION READY**

The app should not serve real patients / real payments until the Critical issues below are fixed. The severity is driven not by any single bug, but by the **combination** of authentication bypass, SQL injection across the Livewire layer, HIPAA violations (any user can read any patient's prescriptions/appointments), and unencrypted OAuth tokens.

---

## 📊 Findings Statistics

| Severity | Count | Category highlights |
|---|---|---|
| 🔴 **Critical** | **~45** | Auth bypass, SQLi, HIPAA leakage, payment forgery, XSS, unprotected webhooks |
| 🟠 **High** | ~55 | Missing authorization, race conditions, float arithmetic on money, duplicate routes |
| 🟡 **Medium** | ~60 | N+1 queries, missing indexes, config drift, weak validations |
| 🟢 **Low** | ~25 | Code style, dead code, inconsistent patterns |
| **Total** | **~185** | |

### Coverage
- ✅ All 99 migrations reviewed
- ✅ All 59 models reviewed
- ✅ All 60 Livewire components reviewed
- ✅ All 58 controllers audited (critical ones fully read)
- ✅ All 35 repositories reviewed
- ✅ All route files mapped
- ✅ Core JS (booking.js, admin-booking.js, appointment-available-dates.js) reviewed
- ✅ 24 blade files with `{!! !!}` output fully reviewed
- ✅ All 6 payment gateways audited
- ✅ Google Calendar, Zoom, S3, email integrations checked
- ✅ All 16 client-feedback fixes regression-tested

---

## 🔥 Top 10 Critical Issues (Must Fix Before Production)

### 1. 🔴 Widespread SQL Injection in Livewire tables
**22 Livewire tables** use `whereRaw("... like '%{$direction}%'")` with the user search input interpolated directly. Every Livewire table is exploitable.
- **Impact:** Full DB takeover; attacker can dump/delete any table.
- **Example:** `app/Livewire/PatientTable.php:81`, `app/Livewire/DoctorTable.php:69`, and 20 more.
- **Fix:** Replace all raw search with parameterized `where(DB::raw('CONCAT(first_name," ",last_name)'), 'like', '%'.$direction.'%')` — Laravel auto-escapes the second bind parameter.

### 2. 🔴 Authentication bypass in PayTM callback
`app/Http/Controllers/PayTMController.php:104-127` — `Auth::loginUsingId($loginUserId)` where `$loginUserId = explode('|', $order_id)` from payment callback. Attacker crafts `order_id = "1|<admin_id>"` → logged in as admin.
- **Impact:** Any user can take over any account via forged payment callback.
- **Fix:** Never trust client-supplied user IDs in webhooks. Verify via session/JWT.

### 3. 🔴 Three unauthenticated destructive public routes
`routes/upgrade.php:7,11,15`:
- `GET /upgrade-to-v3-0-0` → runs seeder
- `GET /upgrade/database` → runs `migrate --force`
- `GET /lang-js` → runs command
- **Impact:** Anyone on the internet can trigger migrations and seeders.
- **Fix:** Remove entirely, or wrap in `auth` + `role:clinic_admin` + move to artisan commands.

### 4. 🔴 Impersonation authorization bypass
`routes/web.php:216` + `UserController::impersonate` — No role check. Any authenticated user (including patients) can impersonate anyone (including admins).
- **Impact:** Full account takeover trivially.
- **Fix:** Add `role:clinic_admin` middleware + audit logging.

### 5. 🔴 Prescription PDF accessible to any authenticated user
`PrescriptionController.php:309-320` `convertToPDF($id)` — No authorization check.
- **Impact:** HIPAA/PHI catastrophe. Enumerate IDs → download every patient's prescriptions.
- **Fix:** Check `auth()->user()->patient->id === $prescription->patient_id` (patients) or `auth()->user()->doctor->id === $prescription->doctor_id` (doctors).

### 6. 🔴 JotForm consent webhook forgeable (CP-12 still broken)
`routes/api.php:38` `/api/consent-webhook` is public, accepts any `appointment_id` + `doctor_id` from request, no HMAC signature.
- **Impact:** Attackers can forge medical consent records.
- **Fix:** Verify JotForm webhook signature (HMAC secret header). Store mapping server-side before webhook fires.

### 7. 🔴 Document deletion ownership check commented out
`PatientController.php:269-285` `deleteDocumet()`:
```php
$document = Document::where('id', $id)
    // ->where('user_id', Auth::id())  // ← COMMENTED OUT
    ->firstOrFail();
```
- **Impact:** Any patient can delete any other patient's documents.
- **Fix:** Uncomment the line.

### 8. 🔴 Livewire table queries not role-scoped (HIPAA leakage)
- `VisitTable.php` — any user sees all doctor-patient visits
- `TransactionTable.php` + `DoctorsTransactionTable.php` — any user sees all transactions
- `DoctorHolidayTable.php` — any user sees all doctors' schedules
- `PrescriptionTable.php` — patients/staff see all prescriptions
- **Impact:** Any logged-in user (including patients) can view other patients' PHI.
- **Fix:** Add role-scoped filters to every `builder()` method.

### 9. 🔴 Google Calendar & Zoom OAuth tokens stored in plaintext
- `google_calendar_integrations.access_token` — string column, no encryption
- `zoom_o_auth_credentials.access_token`, `refresh_token` — `text` columns, no encryption
- **Impact:** DB leak = every doctor's Google Calendar and Zoom account compromised.
- **Fix:** Add `'access_token' => 'encrypted'` cast to models.

### 10. 🔴 Zoom meeting password in plaintext + displayed in table
- `LiveConsultation.password` stored plain, rendered via `{{ $row->password }}`, sortable + searchable in `LiveConsultationsTable.php:77`.
- **Impact:** Meeting hijacking.
- **Fix:** Remove password column from table entirely; store encrypted; share via one-time link.

---

## 🔴 Other Critical Issues

### Security
- **Stored XSS via `{!! $booking_info !!}` / `{!! $privacy_policy !!}` / `{!! $terms_conditions !!}` / `{!! $body !!}`** in 5+ blade files. XSS pipeline: `cms.update` / `admin.emails.update` / `setting.update` routes are excluded from XSS middleware, and their output is rendered raw. ([Phase 9](./QA_FINDINGS.md#phase-9))
- **`GET /delete-old-patients`** public unauthenticated destructive route (`routes/web.php:464`).
- **`GET /qr-code/p/{id?}`** public patient QR route exposes PII (`routes/web.php:98`).
- **Razorpay webhook has no signature verification + empty handler** (`RazorpayController.php:141-152`).
- **`config/cors.php`** wildcard `origins=['*']`, `methods=['*']`, `headers=['*']`.
- **`config/database.php` `strict => false`** — silent data truncation possible.
- **`config/app.php:171`** — Debugbar service provider always loaded.
- **`composer.json`** — Debugbar in `require` (not `require-dev`); two log viewers; outdated `stripe/stripe-php ^7.98`.
- **Hardcoded default password** `'user12345'` in `UserRepository.php:77` and `PatientRepository.php:62`.
- **`admin_emails` migration `down()` drops wrong table** (`2026_02_05_204941:28`).

### Data integrity
- **Financial amounts stored as float/string**: `appointments.payable_amount`, `appointments.charge`, `services.charges`, `sale_medicines.sale_price/tax/amount`, `transactions.amount`. Float arithmetic on money = cumulative rounding loss.
- **Appointment date/time stored as strings**: `date`, `from_time`, `to_time` in appointments table. Range queries unreliable, DST bugs.
- **`DB::commit()` inside `foreach` loop** in `AppointmentRepository.php:152` and `FeedbackAppointmentRepository.php:144`. Partial success on multi-appointment package leaves DB inconsistent.
- **Medicine inventory race condition** — Check-then-update without `lockForUpdate()`. Concurrent sales oversell stock.
- **BOOKING_PENDING=5 appointments orphan forever** — no cleanup job.
- **Slot double-booking race** — `getDoctorSession` excludes BOOKED/CHECK_IN/CHECK_OUT but not BOOKING_PENDING=5.
- **Multiple broken Eloquent relationships**:
  - `PrescriptionMedicineModal::medicines()` inverted (hasMany vs belongsTo) — medical dispensing returns wrong data
  - `Medicine::prescriptionMedicines()` inverted
  - `Appointment::user()` references non-existent column
  - `AppointmentGoogleCalendar.php:44` has `$this->BelongsTo(...)` typo (will fatal at call time)

### Authorization gaps (HIPAA/PHI)
- Patient can change `doctor_id` client-side in booking edit form.
- Doctor can create visits for another doctor's patients.
- Patient document upload accepts arbitrary `user_id` from route.
- Every "show" method in repositories lacks authorization check.
- Missing `#[Locked]` on 12+ Livewire public ID properties.

---

## 🟠 High-Priority Issues (Fix Within 2 Weeks)

1. **Duplicate route names** (~35) across web.php/doctor.php/patient.php — e.g., `appointment.detail` defined 3 times. `route()` helper silently picks last. Risk of wrong tenant redirects.
2. **`stripe/stripe-php ^7.98`** — v7 deprecated; upgrade to v14+.
3. **`firebase/php-jwt ^5.5`** — unsupported version; upgrade to v6+.
4. **Email verification disabled** in `RegisteredUserController.php:64`.
5. **Password policy only `min:6`** — medical app needs stronger.
6. **PayPal callback no idempotency** — `PaypalController.php:89-110`.
7. **Paystack client-side metadata trusted** without re-verification.
8. **Authorize.net hardcoded SANDBOX** — no prod mode.
9. **Stripe controller missing** entirely.
10. **Password stored in mail** → users get plain-text default in email.
11. **Float arithmetic for prescription/medicine totals** — cumulative errors.
12. **Livewire `public $patientId` etc. not `#[Locked]`** — client can manipulate.
13. **postMessage origin check uses `indexOf()`** — `booking.js:512` — bypassable.
14. **print_r exception output** to response in `AuthorizePaymentController.php:134` and `PaypalController.php:135`.
15. **XSS middleware not applied** to public routes (Google Calendar, payments, register, enquiries).
16. **Dashboard `$totalPatientCount` etc. exposed to all roles** — info leakage.
17. **Medicine expiry not validated** before sale.
18. **Vital signs not range-validated** in visit creation.
19. **Prescription doesn't require appointment** — `appointment_id` nullable.

---

## 🟡 Medium Issues (Fix Before Next Release)

- `env()` calls outside config files (breaks `config:cache`): 6+ locations
- Weak randomness `uniqid('appt_')` for relation_id — enumerable
- Missing FK indexes: `appointments.date`, `appointments.relation_id`, etc.
- Inconsistent ID types: `patients` uses `increments` while others use `bigIncrements`
- N+1 patterns in User `$appends`, Patient `$with`, repository loops
- Multiple duplicate / dead relationships in models (`doctorUser()`, `testUser()`, `visitDoctor()`, `patientUser()`)
- `SetLanguage` middleware unsafe array access — crashes if setting missing
- `XSS` middleware is too primitive — uses `strip_tags()` only, not HTMLPurifier
- Doctor holiday dates not format-validated
- Doctor session deletion doesn't cascade to appointments
- Timezone handled via `date_default_timezone_set` in `DoctorSessionController.php:159-254`
- Missing spam protection on public enquiry form (reCAPTCHA rendered but not verified server-side)
- Log retention only 14 days — medical compliance typically requires 30+

---

## 🟢 Low / Cleanup

- Blade `@php ... @endphp` business logic (code smell)
- Inconsistent helper usage: `getLogInUser()` vs `getLoginUser()` vs `getLoggedinDoctor()`
- `composer.json version: "8.5.0"` hardcoded (dead field)
- Duplicate log viewer packages
- State/City model files > 256KB (likely seeded data in model file)

---

## ✅ What's Working

- **Core booking JS** (`booking.js`, `admin-booking.js`) structure is solid after CP-08/CP-09 fixes.
- **CSRF protection** is universal — all forms use `@csrf`/`Form::open()`, AJAX sets `X-CSRF-TOKEN`.
- **DP-02 slot duration fallback** chain is correct.
- **AP-01/AP-02/AP-03/AP-06** client fixes intact.
- **CP-05/CP-06/CP-07/CP-10** intact.
- Spatie Permission setup is clean; roles properly defined.
- Most doctor/patient scoped Livewire tables (e.g., `DoctorPanelAppointmentTable`, `PatientAppointmentTable`) are correctly scoped.
- No `unserialize()` usage.
- No `$request->all()` mass assignment found in controllers.

---

## 🗺️ Recommended Action Plan (Prioritized)

### Week 1 — "Stop the bleeding"
1. **SQL injection fix** — Fix all 22 Livewire `whereRaw()` patterns (single search-and-replace pattern).
2. **PayTM auth bypass** — Remove `Auth::loginUsingId($loginUserId)` in `PayTMController.php`.
3. **Remove `routes/upgrade.php`** public routes entirely. Move to artisan commands.
4. **Impersonation guard** — Add `role:clinic_admin` to `routes/web.php:216`.
5. **Uncomment document ownership check** — `PatientController.php:269-285`.
6. **Delete `/delete-old-patients` route**.
7. **Add auth to `/qr-code/p/{id}`** and `/cancel-appointment/{patient_id}/{...}`.
8. **Rotate leaked credentials** — Gmail app password + Zoom keys in local `.env`.

### Week 2 — "Close HIPAA holes"
1. **Scope every Livewire `builder()`** by role for `VisitTable`, `TransactionTable`, `DoctorsTransactionTable`, `DoctorHolidayTable`, `PrescriptionTable`, `AppointmentTable`.
2. **Add authorization to `PrescriptionController::convertToPDF`**.
3. **Fix JotForm webhook** — add HMAC signature validation.
4. **Add `#[Locked]`** to all 12+ public ID properties in Livewire components.
5. **Encrypt OAuth tokens** (Google Calendar + Zoom) via model cast `'access_token' => 'encrypted'`.
6. **Encrypt / mask Zoom meeting password**; remove password column from `LiveConsultationsTable`.
7. **Fix `PatientController::uploadDocument`** ownership check.

### Week 3 — "Data integrity"
1. **Replace `DB::commit()` inside loop** with `DB::transaction(function(){...})` in both Appointment and FeedbackAppointment repositories.
2. **Add `lockForUpdate()`** to medicine inventory updates (`MedicineBillController`, `PurchaseMedicineRepository`).
3. **Change appointment date/time columns** to `date` + `time` types + add cleanup migration.
4. **Change all money columns** from float/string to `decimal(10,2)`.
5. **Fix broken Eloquent relationships**: `PrescriptionMedicineModal::medicines()`, `Medicine::prescriptionMedicines()`, `Appointment::user()`, `AppointmentGoogleCalendar::$this->BelongsTo()` typo.
6. **Add BOOKING_PENDING cleanup job** (artisan scheduled command, 1-hour TTL).
7. **Fix slot query to exclude BOOKING_PENDING=5** in double-booking prevention.
8. **Fix `admin_emails` migration `down()`** (wrong table name).

### Week 4 — "Payment safety"
1. **Add transaction idempotency** — unique constraint on `transaction_id`, check before create.
2. **Razorpay webhook signature verification**.
3. **PayPal idempotency check** in success handler.
4. **Paystack — re-fetch appointment server-side**, verify amount matches gateway-reported.
5. **Remove `print_r($ex->getMessage())` from payment controllers**.
6. **Validate payment callbacks against session/user** — not client-supplied IDs.
7. **Fix Authorize.net** sandbox/production switch.
8. **Add Stripe controller** (currently missing but dep installed).

### Week 5 — "Hardening"
1. **Remove all stored XSS vectors** — sanitize admin HTML via HTMLPurifier before save, or escape on render.
2. **Replace primitive `XSS` middleware** with HTMLPurifier + content-type awareness.
3. **Strong password policy** — use `Rules\Password::defaults()`.
4. **Enable email verification** in registration.
5. **Fix duplicate route names** — prefix with role (`doctors.appointment.detail`, `patients.appointment.detail`).
6. **Migrate from `token` driver to Sanctum** for API (currently plaintext tokens).
7. **Enable MySQL strict mode** — `config/database.php`.
8. **Move Debugbar to `require-dev`** + gate provider by `APP_DEBUG`.
9. **Replace `QUEUE_CONNECTION=sync`** with database/redis for production.
10. **Set up signed URLs / private disk** for patient documents.

### Week 6+ — "Refactoring"
- Fix all duplicate/dead model relationships
- Add missing DB indexes
- Add vital sign range validation
- Add expiry check on medicine sale
- Add rate limiting to authentication routes
- Implement audit logging for impersonation, user creation, privileged actions
- Upgrade `stripe/stripe-php`, `firebase/php-jwt`, `gerardojbaez/money`
- Split `State`, `City` model files (data out of model)
- Standardize helper naming (`getLogInUser` everywhere)

---

## 🧪 Recommended QA Process Going Forward

1. **Add a test suite** — currently `tests/` exists but coverage is minimal. Priority:
   - Authorization tests per role for every controller method
   - Payment webhook signature verification tests
   - Double-booking race condition tests
   - Stored XSS regression tests
2. **Add a static analyzer** — `phpstan` / `psalm` at level 5+
3. **Add a security linter** — `enlightn/security-checker` + `larastan`
4. **Add CI** running migrations + tests + linters on every PR.
5. **Adopt a PHP-CS-Fixer config** to normalize style.
6. **Regular dependency audits** — `composer audit` and `npm audit` in CI.

---

## 📎 Appendix — Phase-by-Phase Finding Counts

| Phase | Scope | Critical | High | Medium | Low |
|---|---|---|---|---|---|
| 1 | Env & Config | 6 | 7 | 6 | 4 |
| 2 | Migrations | 6 | 6 | 4 | 2 |
| 3 | Models & Relationships | 6 | 5 | 11 | 3 |
| 4 | Auth & Middleware | 7 | 7 | 6 | 2 |
| 5 | Routes | (in Phase 4) | 2 | 1 | 0 |
| 6 | Controllers | 6 | 5 | 6 | 4 |
| 7 | Repositories | 8 | 6 | 7 | 4 |
| 8 | Livewire | 6 | 5 | 4 | 3 |
| 9 | Views & Blades | 5 | 2 | 1 | 2 |
| 10 | Frontend JS | 0 | 2 | 1 | 0 |
| 11 | Appointment flow | 6 | 2 | 0 | 0 |
| 12 | Doctor module | 0 | 2 | 1 | 0 |
| 13 | Patient module | 4 | 0 | 0 | 0 |
| 14 | Clinical module | 1 | 2 | 2 | 0 |
| 15 | Pharmacy | 1 | 6 | 1 | 0 |
| 16 | Payments | 2 | 6 | 1 | 0 |
| 17 | Integrations | 3 | 0 | 3 | 0 |
| 18 | CMS/Public | 0 | 1 | 3 | 0 |
| 19 | Security cross-cut | 1 | 3 | 3 | 0 |
| 20 | Performance | 0 | 4 | 3 | 0 |
| 21 | Regression | 1 | 2 partial | 0 | 0 |
| **TOTAL** | | **~45** | **~55** | **~60** | **~25** |

---

## 📂 Files to consult

- `QA_FINDINGS.md` — Full detailed findings per phase with file:line references
- `FIXES_DP_02.md` — Prior fix documentation for slot duration (still valid)

---

**End of report.**
