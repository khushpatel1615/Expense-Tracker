<?php
// Database Setup Script — Run Once
// Access via: http://localhost/Expense%20Tracker/setup.php

$host = 'localhost';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die('<div style="color:red;font-family:monospace;padding:20px">❌ DB Connection Failed: ' . $conn->connect_error . '</div>');
}

// ── Security guard: block re-running setup if database exists and has data ──
$conn->select_db('expense_tracker');
$guardResult = $conn->query("SHOW TABLES LIKE 'users'");
$alreadySetup = ($guardResult && $guardResult->num_rows > 0);

if ($alreadySetup) {
    $conn->close();
    // Allow forced re-run only with ?force=1 query param (for dev use)
    if (($_GET['force'] ?? '') !== '1') {
        ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <title>Setup — Already Complete</title>
            <style>
                body {
                    font-family: sans-serif;
                    background: #0a0b0f;
                    color: #f1f5f9;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0
                }

                .card {
                    background: #1a1d27;
                    border: 1px solid rgba(255, 255, 255, .07);
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 480px;
                    text-align: center
                }

                .icon {
                    font-size: 3rem;
                    margin-bottom: 16px
                }

                h1 {
                    margin-bottom: 8px
                }

                p {
                    color: #94a3b8;
                    margin-bottom: 24px
                }

                a {
                    display: inline-block;
                    padding: 12px 28px;
                    background: linear-gradient(135deg, #6366f1, #8b5cf6);
                    color: #fff;
                    border-radius: 10px;
                    text-decoration: none;
                    font-weight: 600;
                    margin: 4px
                }

                .warn {
                    color: #f59e0b;
                    font-size: .8rem;
                    margin-top: 16px
                }
            </style>
        </head>

        <body>
            <div class="card">
                <div class="icon">✅</div>
                <h1>Setup Already Complete</h1>
                <p>The database is already configured. For security, this page is blocked after the first setup.</p>
                <a href="index.html">🚀 Launch App</a>
                <div class="warn">⚠️ Delete setup.php from your server once in production.</div>
            </div>
        </body>

        </html>
        <?php
        exit();
    }
}

$sql = file_get_contents(__DIR__ . '/database.sql');
$stmts = array_filter(array_map('trim', explode(';', $sql)));

$errors = 0;
$success = 0;
$messages = [];

foreach ($stmts as $stmt) {
    if (empty($stmt))
        continue;
    if ($conn->query($stmt) === true) {
        $success++;
    } else {
        // Ignore "Duplicate entry" errors for seeds
        if (strpos($conn->error, 'Duplicate') !== false) {
            $messages[] = ['warn', 'Skipped (already exists): ' . substr($stmt, 0, 60) . '...'];
        } else {
            $errors++;
            $messages[] = ['error', '❌ Error: ' . $conn->error . ' in: ' . substr($stmt, 0, 80)];
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Setup — Expense Tracker</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, sans-serif;
            background: #0a0b0f;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            background: #1a1d27;
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 20px;
            padding: 40px;
            max-width: 560px;
            width: 100%;
        }

        h1 {
            font-size: 1.6rem;
            margin-bottom: 8px;
        }

        p {
            color: #94a3b8;
            margin-bottom: 24px;
            font-size: .9rem;
        }

        .msg {
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: .85rem;
            font-family: monospace;
        }

        .msg.success {
            background: rgba(16, 185, 129, .1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, .2);
        }

        .msg.error {
            background: rgba(239, 68, 68, .1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, .2);
        }

        .msg.warn {
            background: rgba(245, 158, 11, .1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, .2);
        }

        .btn {
            display: inline-block;
            padding: 12px 28px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
        }

        .summary {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat {
            flex: 1;
            background: rgba(255, 255, 255, .04);
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }

        .stat-val {
            font-size: 1.8rem;
            font-weight: 800;
        }

        .stat-label {
            font-size: .75rem;
            color: #64748b;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>💰 Database Setup</h1>
        <p>Setting up your Expense Tracker database and tables...</p>

        <div class="summary">
            <div class="stat">
                <div class="stat-val" style="color:#10b981">
                    <?= $success ?>
                </div>
                <div class="stat-label">Successful</div>
            </div>
            <div class="stat">
                <div class="stat-val" style="color:#ef4444">
                    <?= $errors ?>
                </div>
                <div class="stat-label">Errors</div>
            </div>
        </div>

        <?php if ($errors === 0): ?>
            <div class="msg success">✅ Database setup complete! All tables and seed data created successfully.</div>
        <?php else: ?>
            <div class="msg error">⚠️ Setup completed with
                <?= $errors ?> error(s). See details below.
            </div>
        <?php endif; ?>

        <?php foreach ($messages as [$type, $text]): ?>
            <div class="msg <?= $type ?>">
                <?= htmlspecialchars($text) ?>
            </div>
        <?php endforeach; ?>

        <a href="index.html" class="btn">🚀 Launch App →</a>
        <p style="margin-top:16px;font-size:.78rem;color:#64748b">⚠️ Delete this file after setup for security.</p>
    </div>
</body>

</html>