# JECA Psychotest Web App (PHP)

A PHP + MySQL web application used to run recruitment/selection psychotests in a browser, including **TPA**, **TAM**, and **Kraeplin**. The system records **test progress**, **results**, and **user activity logs**, and supports **basic anti-cheat signals** (e.g., tab visibility and camera-check events) for audit purposes.

> Tech: **PHP (native)**, **MySQL/MariaDB**, **Apache (XAMPP/WAMP/LAMP)**, **phpMyAdmin**.

---

## Modules

### User-side
- **Authentication** (login required to access tests).
- **TPA** (question bank by category/session; multiple-choice).
- **TAM** (stimulus-based attention/memory test; multiple-choice).
- **Kraeplin** (interval-based lines; duration/interval driven; stored as aggregated metrics + raw lines JSON).
- **Progress control** per test (prevents invalid submissions / re-submits).
- **Activity logging** per user action.

### Admin-side (core operational controls)
- **Test gate (Open/Close)** to allow/deny participant access based on schedule/status.
- **Question bank management** (TPA & TAM question tables).
- **Result viewing** (TPA/TAM/Kraeplin results tables).
- **Audit logs & anti-cheat attempts** review (e.g., TAB_HIDDEN / CAMERA_OK events).

---

## Project Structure (typical)
> Adjust to your repository’s actual structure if filenames differ.

```
psychotest-app/
├─ index.php
├─ controllers/
├─ models/
├─ views/
├─ assets/
└─ config/
   └─ db.php
```

---

## Database (MySQL)

### Core tables (commonly used)
- `users`
- `user_activity_logs`
- `tpa_questions`
- `tam_questions`
- `tpa_results`
- `tam_results`
- `kraeplin_results`
- `anti_cheat_attempts`
- (others depending on your build: progress/settings tables, etc.)

> Notes:
- Some tables may be linked by **foreign keys** (e.g., `kraeplin_results.user_id -> users.id`), so you must delete/truncate in the correct order.

---

## Local Setup (XAMPP on Windows)

1. **Clone / copy** project into XAMPP:
   - Put folder into: `C:\xampp\htdocs\psychotest-app`

2. **Start services**
   - XAMPP Control Panel → start **Apache** and **MySQL**

3. **Create database**
   - Open phpMyAdmin → create database (example):
     - `psychotest_db`

4. **Import schema**
   - Import your SQL dump (recommended) via phpMyAdmin → **Import**
   - If you do not have a dump, ensure your tables match the app’s expected schema.

5. **Configure DB connection**
   - Update your DB connection file (example `config/db.php`):

```php
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "psychotest_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
```

6. **Run**
   - Open: `http://localhost/psychotest-app/`

---

## Deployment Notes (shared hosting / Hostinger)
- Ensure PHP version matches your code (commonly **PHP 8.x**).
- Point your domain/subdomain to the project’s public path.
- Update DB credentials in your DB config file.
- If you use `index.php?page=...` routing, no rewrite rules are required; if you later add pretty routes, configure `.htaccess`.

---

## Reset Utilities (Common SQL)

### Reset question banks and restart IDs from 1
```sql
TRUNCATE TABLE `tpa_questions`;
TRUNCATE TABLE `tam_questions`;

ALTER TABLE `tpa_questions` AUTO_INCREMENT = 1;
ALTER TABLE `tam_questions` AUTO_INCREMENT = 1;
```

### Reset users and restart IDs from 1 (FK-safe pattern)
If you have FK constraints from results tables to `users`, delete child rows first:

```sql
START TRANSACTION;

-- delete children first (add/remove tables as needed)
DELETE FROM `anti_cheat_attempts`;
DELETE FROM `user_activity_logs`;
DELETE FROM `tpa_results`;
DELETE FROM `tam_results`;
DELETE FROM `kraeplin_results`;

DELETE FROM `users`;
ALTER TABLE `users` AUTO_INCREMENT = 1;

COMMIT;
```

> Tip: If phpMyAdmin blocks `TRUNCATE users` because of foreign keys, use `DELETE` + `ALTER TABLE ... AUTO_INCREMENT = 1` as above.

---

## Troubleshooting

### `#1701 - Cannot truncate a table referenced in a foreign key constraint`
This means the table you’re truncating is referenced by another table via FK (e.g., `kraeplin_results.user_id -> users.id`).
- **Solution:** delete/truncate the child tables first, then the parent (`users`).

### Duplicate attempt insert on Kraeplin
If your Kraeplin table enforces a unique attempt key (e.g., `(user_id, attempt_no)`), you must prevent double submits and ensure attempt numbers are computed consistently.

---

## Security Notes (important)
- Never commit real production DB credentials to public repos.
- Use environment-based configs or separate secrets for production.
- Consider rate-limiting and stronger session hardening for public deployments.

---

## License
No license is included by default. Add a `LICENSE` file if you intend to open-source this repository.
