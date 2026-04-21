# 🏋️ FitCore Pro — Gym Management System

A PHP + MySQL gym management system with role-based access for Admin, Manager, Staff, Trainer, and Member.

---

## 📋 Requirements

| Software | Version | Download |
|----------|---------|----------|
| XAMPP    | 8.x+    | https://www.apachefriends.org |
| PHP      | 8.0+    | Included with XAMPP |
| MySQL    | 8.0+    | Included with XAMPP |
| Browser  | Any modern | — |

---

## 🚀 Installation (Step-by-Step)

### Step 1 — Copy files to XAMPP

Copy the **`gym-system`** folder into your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\gym-system\        ← Windows
/Applications/XAMPP/htdocs/gym-system/  ← macOS
/opt/lampp/htdocs/gym-system/      ← Linux
```

> ⚠️ The folder **must** be named `gym-system`. Renaming it will break links unless you also update `APP_URL` in `config/database.php`.

---

### Step 2 — Start XAMPP services

Open the **XAMPP Control Panel** and start both:
- ✅ **Apache**
- ✅ **MySQL**

---

### Step 3 — Import the database

1. Open your browser and go to: **http://localhost/phpmyadmin**
2. Click **"New"** in the left sidebar to create a new database
3. Name it **`gym_system`** → click **Create**
4. Click the **`gym_system`** database → go to the **Import** tab
5. Click **"Choose File"** → select `gym-system/database/gym.sql`
6. Click **"Go"** / **"Import"**

> ✅ The SQL file will create all tables and insert demo accounts automatically.

---

### Step 4 — Open the app

Visit: **http://localhost/gym-system**

---

## 🔐 Demo Login Accounts

All accounts use their **email address as the default password**.

| Role    | Email               | Password            |
|---------|---------------------|---------------------|
| Admin   | admin@gym.com       | admin@gym.com       |
| Manager | manager@gym.com     | manager@gym.com     |
| Trainer | trainer@gym.com     | trainer@gym.com     |
| Staff   | staff@gym.com       | staff@gym.com       |
| Member  | member@gym.com      | member@gym.com      |

> 💡 On the login page, click any role button to auto-fill the credentials.

---

## 🗂️ Project Structure

```
gym-system/
├── config/
│   └── database.php          ← DB connection & app settings
├── auth/
│   ├── login.php             ← Login page
│   ├── logout.php            ← Logout handler
│   └── change_password.php   ← Password change (self + admin reset)
├── includes/
│   ├── auth_guard.php        ← Session protection & helper functions
│   ├── header.php            ← Page header
│   ├── sidebar.php           ← Navigation sidebar
│   └── footer.php            ← Page footer
├── assets/
│   ├── css/style.css         ← Global styles
│   └── js/app.js             ← Global scripts
├── database/
│   └── gym.sql               ← Full database schema + seed data
├── index.php                 ← Entry point (redirects to dashboard)
├── dashboard.php             ← Main dashboard (role-aware)
├── members.php               ← Member management
├── payments.php              ← Payment management
├── attendance.php            ← Attendance tracking
├── classes.php               ← Class scheduling
├── bmi.php                   ← BMI tracker
├── reports.php               ← Reports
├── trainer_members.php       ← Trainer's assigned members
└── README.md                 ← This file
```

---

## ⚙️ Configuration

All settings are in **`config/database.php`**:

```php
define('DB_HOST',  'localhost');    // MySQL host (don't change for XAMPP)
define('DB_USER',  'root');         // MySQL username (XAMPP default)
define('DB_PASS',  '');             // MySQL password (XAMPP default is empty)
define('DB_NAME',  'gym_system');   // Database name
define('APP_URL',  'http://localhost/gym-system');  // ← Must match your folder name
```

> ⚠️ If you rename the folder, update `APP_URL` to match.

---

## 🔑 Role Permissions

| Feature            | Admin | Manager | Staff | Trainer | Member |
|--------------------|:-----:|:-------:|:-----:|:-------:|:------:|
| Dashboard          | ✅    | ✅      | ✅    | ✅      | ✅     |
| Members (full)     | ✅    | ✅      | ✅    | ❌      | ❌     |
| My Members         | ❌    | ❌      | ❌    | ✅      | ❌     |
| Payments           | ✅    | ✅      | ✅    | ❌      | ✅*    |
| Attendance         | ✅    | ✅      | ✅    | ✅      | ✅*    |
| Classes            | ✅    | ✅      | ✅    | ✅      | ✅*    |
| BMI Tracker        | ✅    | ✅      | ✅    | ✅      | ✅*    |
| Reports            | ✅    | ✅      | ❌    | ❌      | ❌     |
| Reset Any Password | ✅    | ❌      | ❌    | ❌      | ❌     |

*Members see only their own data.

---

## 🔒 Security Notes

> This project is designed for **local development and learning purposes**.

- Passwords are stored as **plain text** — not suitable for production.
- For production, replace with `password_hash()` / `password_verify()`.
- `APP_URL` and database credentials should be stored in environment variables for production.

---

## ❓ Troubleshooting

| Problem | Solution |
|---------|----------|
| "Invalid email or password" | Re-import `gym.sql` via phpMyAdmin (drop `gym_system` DB first, then re-import) |
| Blank page after login | Check that `APP_URL` in `config/database.php` matches your folder name |
| "Database Error" on any page | Make sure MySQL is running in XAMPP and the DB name is `gym_system` |
| Links return 404 | Ensure the folder is named exactly `gym-system` and Apache is running |
| Can't access phpMyAdmin | Go to http://localhost/phpmyadmin — make sure MySQL is started in XAMPP |

---

## 📄 License

This project is for educational use only.
