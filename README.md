# 💰 Expense Tracker + Dashboard

> A full-stack expense tracking application with a premium dark UI, real-time charts, JWT authentication, and budget management.

![Tech Stack](https://img.shields.io/badge/Stack-PHP%20%7C%20MySQL%20%7C%20JS%20%7C%20Chart.js-6366f1?style=flat-square)
![Status](https://img.shields.io/badge/Status-Complete-10b981?style=flat-square)

---

## ✨ Features

### Phase 1 — Core
- ✅ User Registration & Login with **JWT authentication** (bcrypt + HS256)
- ✅ Add **Income** and **Expense** transactions
- ✅ Each transaction: Amount, Category, Date, Note
- ✅ View all transactions — **sortable** by date or amount
- ✅ **Filter** by type, category, and date range
- ✅ Edit and **delete** transactions
- ✅ Total balance calculation (income minus expenses)

### Phase 2 — Dashboard
- ✅ **Monthly bar chart** — income vs. expenses (last 6 months)
- ✅ **Doughnut pie chart** — spending by category
- ✅ Current month summary card (income, expenses, net, balance)
- ✅ **Top 5 expense categories** with progress bars
- ✅ Recent transactions widget

### Phase 3 — Extras
- ✅ **Set monthly budgets** per expense category
- ✅ Budget warning at **80% usage** + over-budget alerts
- ✅ **Export transactions to CSV** (downloadable file)
- ✅ Full **Analytics page** with line chart + polar area chart
- ✅ Paginated transaction list
- ✅ Mobile responsive with sidebar toggle

---

## 🛠️ Tech Stack

| Layer       | Technology                          |
|-------------|-------------------------------------|
| Backend     | PHP 8+ (REST API)                   |
| Auth        | JWT (HS256) + bcrypt                |
| Database    | MySQL (raw SQL)                     |
| Frontend    | HTML5, CSS3, Vanilla JS (Fetch API) |
| Charts      | Chart.js v4                         |
| Fonts       | Google Fonts (Inter + Space Grotesk)|
| Server      | XAMPP (Apache + PHP + MySQL)        |

---

## ⚙️ Getting Started

### Prerequisites
- XAMPP installed (or any Apache + PHP 8 + MySQL stack)
- XAMPP running (Apache + MySQL services started)

### Setup Steps

**1. Place in XAMPP htdocs**
```
Your files should be at: E:\XAMP\htdocs\Expense Tracker\
```

**2. Start XAMPP Services**
- Open XAMPP Control Panel
- Start **Apache** and **MySQL**

**3. Run Database Setup**
Open your browser and go to:
```
http://localhost/Expense%20Tracker/setup.php
```
You should see: ✅ Database setup complete!

**4. Launch the App**
```
http://localhost/Expense%20Tracker/index.html
```

**5. (Optional) Delete setup file**
```bash
del "E:\XAMP\htdocs\Expense Tracker\setup.php"
```

---

## 🗄️ Database Schema

```sql
Users        — id, email, password_hash, name, created_at
Categories   — id, name, icon, type (Income|Expense), color
Transactions — id, user_id, category_id, amount, note, date
Budgets      — id, user_id, category_id, amount, month, year
```

**Seeded Categories (15 total):**
- Income: Salary 💼, Freelance 💻, Investment 📈, Gift 🎁, Other Income 💰
- Expense: Food 🍔, Housing 🏠, Transport 🚗, Shopping 🛍️, Healthcare ⚕️, Education 📚, Entertainment 🎮, Utilities 💡, Travel ✈️, Other 📦

---

## 🔌 REST API Endpoints

```http
POST   /Expense Tracker/api/auth/register.php    — Register new user
POST   /Expense Tracker/api/auth/login.php        — Login, returns JWT

GET    /Expense Tracker/api/transactions/index.php     — Get all (auth required)
POST   /Expense Tracker/api/transactions/index.php     — Add new transaction
PUT    /Expense Tracker/api/transactions/index.php?id= — Update transaction
DELETE /Expense Tracker/api/transactions/index.php?id= — Delete transaction

GET    /Expense Tracker/api/categories/index.php   — Get all categories

GET    /Expense Tracker/api/dashboard/index.php?endpoint=summary
GET    /Expense Tracker/api/dashboard/index.php?endpoint=by-category
GET    /Expense Tracker/api/dashboard/index.php?endpoint=monthly-trend
GET    /Expense Tracker/api/dashboard/index.php?endpoint=top-expenses
GET    /Expense Tracker/api/dashboard/index.php?endpoint=budget-status

GET    /Expense Tracker/api/budgets/index.php     — Get budgets
POST   /Expense Tracker/api/budgets/index.php     — Set budget (upsert)
DELETE /Expense Tracker/api/budgets/index.php?id= — Delete budget
```

---

## 📁 Folder Structure

```
Expense Tracker/
├── index.html               ← Single Page Application entry point
├── setup.php                ← One-time DB setup (delete after use)
├── database.sql             ← Full schema + seed data
├── .htaccess                ← Apache configuration
├── assets/
│   ├── css/
│   │   └── style.css        ← Complete design system (dark theme)
│   └── js/
│       └── app.js           ← All frontend logic (auth, charts, CRUD)
└── api/
    ├── config.php           ← DB connection, JWT helpers, CORS
    ├── auth/
    │   ├── register.php
    │   └── login.php
    ├── transactions/
    │   └── index.php        ← Full CRUD with filters & pagination
    ├── categories/
    │   └── index.php
    ├── dashboard/
    │   └── index.php        ← 5 analytics endpoints
    └── budgets/
        └── index.php        ← Budget CRUD with upsert
```

---

## 🔒 Security

- Passwords hashed with **bcrypt** (PHP `password_hash`)
- JWT tokens signed with **HMAC-SHA256**
- All transaction endpoints require valid JWT in `Authorization: Bearer <token>` header
- Prepared statements throughout — **zero SQL injection risk**
- Users can only access **their own data** (user_id verified on every query)
- CORS headers configured for API access

---

## 💼 Interview Talking Points

> *"I built a full-stack expense tracker with a separate PHP REST API backend and a JavaScript SPA frontend. The dashboard uses Chart.js to visualize spending trends and category breakdowns using data from custom SQL aggregate queries (GROUP BY, SUM with CASE). I secured the API with custom JWT tokens using HMAC-SHA256 and handled CORS for cross-origin requests. I used raw SQL with prepared statements instead of an ORM to demonstrate my SQL knowledge."*

---

## 📄 License

MIT — Built by **Khush Patel** | khushpatel1615@gmail.com | [github.com/khushpatel1615](https://github.com/khushpatel1615)
