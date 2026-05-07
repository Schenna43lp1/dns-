<?php
session_start();

$configFile = __DIR__ . "/config.php";
$config = require $configFile;

$allowedTypes = ["A", "AAAA", "CNAME", "MX", "TXT", "NS", "SOA", "SRV", "CAA", "ANY"];

function clean($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function saveConfig(string $file, array $config): bool
{
    $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";

    return file_put_contents($file, $content) !== false;
}

function redirectSelf(): void
{
    header("Location: config-editor.php");
    exit;
}

$error = "";
$success = "";

$adminPassword = $config["admin_password"] ?? "change-me-now";

if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: config-editor.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login"])) {
    $password = $_POST["password"] ?? "";

    if (hash_equals($adminPassword, $password)) {
        $_SESSION["dns_admin_logged_in"] = true;
        redirectSelf();
    } else {
        $error = "Falsches Passwort.";
    }
}

$isLoggedIn = $_SESSION["dns_admin_logged_in"] ?? false;

if ($isLoggedIn && $_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "settings") {
        $config["title"] = trim($_POST["title"] ?? "DNS Monitor");
        $config["discord_webhook"] = trim($_POST["discord_webhook"] ?? "");

        if (!empty($_POST["new_admin_password"])) {
            $config["admin_password"] = trim($_POST["new_admin_password"]);
        }

        if (saveConfig($configFile, $config)) {
            $success = "Einstellungen gespeichert.";
        } else {
            $error = "Konnte config.php nicht speichern. Prüfe Dateirechte.";
        }
    }

    if ($action === "add") {
        $name = trim($_POST["name"] ?? "");
        $domain = trim($_POST["domain"] ?? "");
        $type = strtoupper(trim($_POST["type"] ?? "A"));

        if ($name === "" || $domain === "") {
            $error = "Name und Domain dürfen nicht leer sein.";
        } elseif (!in_array($type, $allowedTypes, true)) {
            $error = "Ungültiger DNS-Typ.";
        } else {
            $config["checks"][] = [
                "name" => $name,
                "domain" => $domain,
                "type" => $type
            ];

            if (saveConfig($configFile, $config)) {
                $success = "DNS-Eintrag hinzugefügt.";
            } else {
                $error = "Konnte config.php nicht speichern. Prüfe Dateirechte.";
            }
        }
    }

    if ($action === "delete") {
        $index = (int)($_POST["index"] ?? -1);

        if (isset($config["checks"][$index])) {
            array_splice($config["checks"], $index, 1);

            if (saveConfig($configFile, $config)) {
                $success = "DNS-Eintrag gelöscht.";
            } else {
                $error = "Konnte config.php nicht speichern. Prüfe Dateirechte.";
            }
        }
    }

    if ($action === "update_records") {
        $newChecks = [];

        $names = $_POST["names"] ?? [];
        $domains = $_POST["domains"] ?? [];
        $types = $_POST["types"] ?? [];

        foreach ($names as $i => $name) {
            $name = trim($name);
            $domain = trim($domains[$i] ?? "");
            $type = strtoupper(trim($types[$i] ?? "A"));

            if ($name === "" || $domain === "") {
                continue;
            }

            if (!in_array($type, $allowedTypes, true)) {
                $type = "A";
            }

            $newChecks[] = [
                "name" => $name,
                "domain" => $domain,
                "type" => $type
            ];
        }

        $config["checks"] = $newChecks;

        if (saveConfig($configFile, $config)) {
            $success = "DNS-Einträge aktualisiert.";
        } else {
            $error = "Konnte config.php nicht speichern. Prüfe Dateirechte.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>DNS Config Editor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --bg: #0b1020;
            --card: #111827;
            --card2: #151f32;
            --border: #263248;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --ok: #22c55e;
            --fail: #ef4444;
            --warn: #f59e0b;
            --info: #38bdf8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.15), transparent 35%),
                radial-gradient(circle at top right, rgba(168, 85, 247, 0.12), transparent 30%),
                var(--bg);
            color: var(--text);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 22px;
        }

        h1 {
            margin: 0;
            font-size: 32px;
        }

        h2 {
            margin-top: 0;
        }

        .muted {
            color: var(--muted);
        }

        .card {
            background: linear-gradient(180deg, var(--card2), var(--card));
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.25);
        }

        .row {
            display: grid;
            grid-template-columns: 1.2fr 1.6fr 140px 100px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        label {
            display: block;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 6px;
        }

        input, select {
            width: 100%;
            background: #0b1220;
            border: 1px solid var(--border);
            color: var(--text);
            padding: 12px 13px;
            border-radius: 12px;
            outline: none;
        }

        input:focus, select:focus {
            border-color: var(--info);
        }

        .btn {
            border: none;
            cursor: pointer;
            padding: 12px 15px;
            border-radius: 12px;
            font-weight: bold;
            color: white;
            background: #2563eb;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            filter: brightness(1.1);
        }

        .btn-danger {
            background: #dc2626;
        }

        .btn-green {
            background: #16a34a;
        }

        .btn-gray {
            background: #374151;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .notice {
            padding: 14px;
            border-radius: 14px;
            margin-bottom: 18px;
        }

        .success {
            background: rgba(34, 197, 94, 0.13);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #bbf7d0;
        }

        .error {
            background: rgba(239, 68, 68, 0.13);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fecaca;
        }

        .login-box {
            max-width: 420px;
            margin: 80px auto;
        }

        .table-head {
            display: grid;
            grid-template-columns: 1.2fr 1.6fr 140px 100px;
            gap: 10px;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }

        .footer {
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            margin-top: 22px;
        }

        @media (max-width: 850px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .row, .table-head {
                grid-template-columns: 1fr;
            }

            .table-head {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="container">

<?php if (!$isLoggedIn): ?>

    <div class="login-box card">
        <h1>DNS Config Login</h1>
        <p class="muted">Bitte Admin-Passwort eingeben.</p>

        <?php if ($error): ?>
            <div class="notice error"><?= clean($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="login" value="1">

            <label>Passwort</label>
            <input type="password" name="password" placeholder="Admin Passwort" required>

            <br><br>

            <button class="btn" type="submit">Einloggen</button>
        </form>
    </div>

<?php else: ?>

    <div class="topbar">
        <div>
            <h1>DNS Config Editor</h1>
            <p class="muted">Bearbeite DNS-Checks direkt über die Web-GUI.</p>
        </div>

        <div class="actions">
            <a class="btn btn-gray" href="index.php">Dashboard</a>
            <a class="btn btn-danger" href="config-editor.php?logout=1">Logout</a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="notice success"><?= clean($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error"><?= clean($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Allgemeine Einstellungen</h2>

        <form method="post">
            <input type="hidden" name="action" value="settings">

            <label>Titel</label>
            <input type="text" name="title" value="<?= clean($config["title"] ?? "DNS Monitor") ?>">

            <br><br>

            <label>Discord Webhook</label>
            <input type="text" name="discord_webhook" value="<?= clean($config["discord_webhook"] ?? "") ?>" placeholder="https://discord.com/api/webhooks/...">

            <br><br>

            <label>Neues Admin-Passwort</label>
            <input type="password" name="new_admin_password" placeholder="Leer lassen = nicht ändern">

            <br><br>

            <button class="btn btn-green" type="submit">Einstellungen speichern</button>
        </form>
    </div>

    <div class="card">
        <h2>Neuen DNS-Eintrag hinzufügen</h2>

        <form method="post">
            <input type="hidden" name="action" value="add">

            <div class="row">
                <div>
                    <label>Name</label>
                    <input type="text" name="name" placeholder="Root A" required>
                </div>

                <div>
                    <label>Domain</label>
                    <input type="text" name="domain" placeholder="example.com" required>
                </div>

                <div>
                    <label>Typ</label>
                    <select name="type">
                        <?php foreach ($allowedTypes as $type): ?>
                            <option value="<?= clean($type) ?>"><?= clean($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>&nbsp;</label>
                    <button class="btn" type="submit">Hinzufügen</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>DNS-Einträge bearbeiten</h2>

        <form method="post">
            <input type="hidden" name="action" value="update_records">

            <div class="table-head">
                <div>Name</div>
                <div>Domain</div>
                <div>Typ</div>
                <div>Aktion</div>
            </div>

            <?php foreach (($config["checks"] ?? []) as $i => $check): ?>
                <div class="row">
                    <div>
                        <input type="text" name="names[]" value="<?= clean($check["name"] ?? "") ?>">
                    </div>

                    <div>
                        <input type="text" name="domains[]" value="<?= clean($check["domain"] ?? "") ?>">
                    </div>

                    <div>
                        <select name="types[]">
                            <?php foreach ($allowedTypes as $type): ?>
                                <option value="<?= clean($type) ?>" <?= (($check["type"] ?? "") === $type) ? "selected" : "" ?>>
                                    <?= clean($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <button
                            class="btn btn-danger"
                            type="submit"
                            formaction="config-editor.php"
                            formmethod="post"
                            name="delete_index"
                            value="<?= clean($i) ?>"
                            onclick="return confirm('Diesen DNS-Eintrag wirklich löschen?');"
                            style="display:none;"
                        >
                            Löschen
                        </button>

                        <form method="post" style="display:inline;">
                        </form>

                        <button
                            class="btn btn-danger"
                            type="button"
                            onclick="deleteRecord(<?= (int)$i ?>)"
                        >
                            Löschen
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <br>

            <button class="btn btn-green" type="submit">Alle Änderungen speichern</button>
        </form>

        <?php foreach (($config["checks"] ?? []) as $i => $check): ?>
            <form method="post" id="delete-form-<?= (int)$i ?>" style="display:none;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="index" value="<?= (int)$i ?>">
            </form>
        <?php endforeach; ?>
    </div>

    <div class="footer">
        Datei: config.php
    </div>

<?php endif; ?>

</div>

<script>
function deleteRecord(index) {
    if (!confirm("Diesen DNS-Eintrag wirklich löschen?")) {
        return;
    }

    const form = document.getElementById("delete-form-" + index);

    if (form) {
        form.submit();
    }
}
</script>

</body>
</html>
