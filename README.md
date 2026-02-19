# 💰 Expense Tracker

> A modern, full-stack personal finance application built with PHP, MySQL, and vanilla JavaScript. Features a premium design, interactive charts, and comprehensive financial tools.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange.svg)

## ✨ Features

### CORE FUNCTIONALITY
- **Dashboard**: Real-time financial overview with smart insights and date filtering (Last 7 days, 30 days, Custom Range).
- **Transactions**: Add, edit, delete, and search expenses/income.
- **Budgets**: Set monthly budgets per category with visual progress bars and alerts when nearing limits.
- **Analytics**: Visualization of spending habits with 6-month trends and category breakdowns.

### ADVANCED FEATURES
- **Profile Management**: Update your name and password securely.
- **Authentication**: Secure registration, login, and **Forgot Password** flow via email OTP.
- **Financial Insights**: Automatic analysis of spending vs previous periods (e.g. "⚠️ You spent 12% more than last month").
- **Reporting**: Print-friendly monthly reports and PDF export.
- **Dark/Light Mode**: Fully responsive theme toggle with persistence.
- **CSV Export**: Export up to 5,000 transactions for external analysis.

---

## 🚀 Installation

### Prerequisites
- **XAMPP**, WAMP, or any PHP/MySQL environment.
- PHP 8.0 or higher.

### Setup Steps
1. **Clone the repository** into your `htdocs` folder:
   ```bash
   cd htdocs
   git clone https://github.com/khushpatel1615/Expense-Tracker.git
   ```

2. **Configure Database**:
   - Open `api/config.php` and update your database credentials if needed (default: `root`, no password).

3. **Run Setup**:
   - Open your browser and navigate to:
     `http://localhost/Expense-Tracker/setup.php`
   - This will automatically create the database and tables.

4. **Launch**:
   - Go to `http://localhost/Expense-Tracker/`
   - Register a new account to start tracking!

---

## 🛠️ Tech Stack

- **Frontend**: HTML5, CSS3 (Custom Properties), Vanilla JavaScript (ES6+), Chart.js
- **Backend**: PHP 8.2 (REST API architecture)
- **Database**: MySQL (PDO/MySQLi with prepared statements)
- **Security**: JWT Authentication (HS256), BCrypt Password Hashing, CSRF protection via tokens

---

## 📸 Screenshots

| Dashboard (Dark Mode) | Mobile Responsive |
|---|---|
| *Real-time stats, charts, and smart insights* | *Fully functional on all device sizes* |

| Analytics | Transaction Management |
|---|---|
| *Deep dive into spending habits* | *Search, filter, and export data* |

---

## 🔒 Security

- **SQL Injection**: Prevented via 100% usage of prepared statements.
- **XSS**: Output encoding on all user-generated content.
- **Auth**: Stateless JWT authentication with auto-logout on expiration.
- **Access Control**: Users can only access their own data.

## 📄 License

This project is open source and available under the [MIT License](LICENSE).
