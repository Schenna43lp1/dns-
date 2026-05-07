<?php

$config = require __DIR__ . "/config.php";

$dataDir = __DIR__ . "/data";
$stateFile = $dataDir . "/state.json";

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

if (!file_exists($stateFile)) {
    file_put_contents($stateFile, "{}");
}

function clean($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function dnsTypeConstant(string $type): int
{
    $type = strtoupper($type);

    return match ($type) {
        "A" => DNS_A,
        "AAAA" => DNS_AAAA,
        "CNAME" => DNS_CNAME,
        "MX" => DNS_MX,
        "TXT" => DNS_TXT,
        "NS" => DNS_NS,
        "SOA" => DNS_SOA,
        "SRV" => DNS_SRV,
        "CAA" => defined("DNS_CAA") ? DNS_CAA : DNS_ANY,
        "ANY" => DNS_ANY,
        default => DNS_ANY
    };
}

function normalizeRecords(array $records): array
{
    $normalized = [];

    foreach ($records as $record) {
        unset($record["ttl"]);

        ksort($record);

        $normalized[] = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    sort($normalized);

    return $normalized;
}

function loadState(string $file): array
{
    $json = file_get_contents($file);
    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

function saveState(string $file, array $state): void
{
    file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function sendDiscordAlert(string $webhook, string $message): void
{
    if (empty($webhook)) {
        return;
    }

    $payload = json_encode([
        "content" => $message
    ]);

    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

function prettyRecord(string $record): string
{
    $decoded = json_decode($record, true);

    if (!is_array($decoded)) {
        return $record;
    }

    $parts = [];

    foreach ($decoded as $key => $value) {
        if (is_array($value)) {
            $value = implode(" ", $value);
        }

        $parts[] = $key . ": " . $value;
    }

    return implode(" | ", $parts);
}

$title = $config["title"] ?? "DNS Monitor";
$checks = $config["checks"] ?? [];
$discordWebhook = $config["discord_webhook"] ?? "";

$state = loadState($stateFile);
$alerts = [];
$results = [];

$total = count($checks);
$okCount = 0;
$changedCount = 0;
$initCount = 0;
$failCount = 0;

foreach ($checks as $check) {
    $name = $check["name"] ?? "";
    $domain = $check["domain"] ?? "";
    $type = strtoupper($check["type"] ?? "A");

    $key = $domain . "_" . $type;

    $recordsRaw = [];
    $error = null;

    try {
        $recordsRaw = @dns_get_record($domain, dnsTypeConstant($type));

        if (!is_array($recordsRaw)) {
            $recordsRaw = [];
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $recordsRaw = [];
    }

    $records = normalizeRecords($recordsRaw);
    $oldRecords = $state[$key]["records"] ?? null;

    $status = "OK";
    $message = "Keine Änderung";

    if (count($records) === 0) {
        $status = "FAIL";
        $message = "Kein Record gefunden";
        $failCount++;
    } elseif ($oldRecords === null) {
        $status = "INIT";
        $message = "Erster Check gespeichert";
        $initCount++;
    } elseif ($oldRecords !== $records) {
        $newOnly = array_values(array_diff($records, $oldRecords));
        $removedOnly = array_values(array_diff($oldRecords, $records));

        if (count($newOnly) > 0) {
            $alertText = "🚨 Neuer DNS-Eintrag erkannt\n";
            $alertText .= "Name: $name\n";
            $alertText .= "Domain: $domain\n";
            $alertText .= "Typ: $type\n\n";
            $alertText .= "Neue Records:\n";
            $alertText .= implode("\n", array_map("prettyRecord", $newOnly));

            $alerts[] = $alertText;
            sendDiscordAlert($discordWebhook, $alertText);
        }

        if (count($removedOnly) > 0) {
            $alertText = "⚠️ DNS-Eintrag entfernt\n";
            $alertText .= "Name: $name\n";
            $alertText .= "Domain: $domain\n";
            $alertText .= "Typ: $type\n\n";
            $alertText .= "Entfernte Records:\n";
            $alertText .= implode("\n", array_map("prettyRecord", $removedOnly));

            $alerts[] = $alertText;
            sendDiscordAlert($discordWebhook, $alertText);
        }

        $status = "CHANGED";
        $message = "DNS Änderung erkannt";
        $changedCount++;
    } else {
        $okCount++;
    }

    $state[$key] = [
        "name" => $name,
        "domain" => $domain,
        "type" => $type,
        "records" => $records,
        "last_check" => date("Y-m-d H:i:s"),
        "last_status" => $status
    ];

    $results[] = [
        "name" => $name,
        "domain" => $domain,
        "type" => $type,
        "records" => $records,
        "status" => $status,
        "message" => $message,
        "error" => $error
    ];
}

saveState($stateFile, $state);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= clean($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">

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
            --purple: #a855f7;
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
            max-width: 1300px;
            margin: 0 auto;
            padding: 28px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            margin-bottom: 24px;
        }

        .brand h1 {
            margin: 0;
            font-size: 34px;
            letter-spacing: -0.5px;
        }

        .brand p {
            margin: 8px 0 0;
            color: var(--muted);
        }

        .badge {
            background: rgba(56, 189, 248, 0.12);
            color: var(--info);
            border: 1px solid rgba(56, 189, 248, 0.25);
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 14px;
            white-space: nowrap;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat {
            background: linear-gradient(180deg, var(--card2), var(--card));
            border: 1px solid var(--border);
            padding: 18px;
            border-radius: 18px;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.25);
        }

        .stat .label {
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .stat .value {
            font-size: 32px;
            font-weight: bold;
            margin-top: 8px;
        }

        .controls {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search {
            flex: 1;
            min-width: 260px;
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 13px 15px;
            border-radius: 12px;
            outline: none;
        }

        .select {
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 13px 15px;
            border-radius: 12px;
            outline: none;
        }

        .alerts {
            margin-bottom: 24px;
        }

        .alert {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fecaca;
            padding: 16px;
            border-radius: 16px;
            margin-bottom: 12px;
            white-space: pre-wrap;
        }

        .table-wrap {
            background: rgba(17, 24, 39, 0.86);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.28);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            background: rgba(15, 23, 42, 0.95);
            padding: 14px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 14px;
            border-bottom: 1px solid rgba(38, 50, 72, 0.7);
            vertical-align: top;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .name {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .domain {
            color: var(--muted);
            font-size: 13px;
        }

        .type {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            background: rgba(168, 85, 247, 0.14);
            color: #d8b4fe;
            border: 1px solid rgba(168, 85, 247, 0.25);
            font-size: 12px;
            font-weight: bold;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
        }

        .status-ok {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.13);
            border: 1px solid rgba(34, 197, 94, 0.28);
        }

        .status-fail {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.13);
            border: 1px solid rgba(239, 68, 68, 0.28);
        }

        .status-changed {
            color: #fed7aa;
            background: rgba(245, 158, 11, 0.13);
            border: 1px solid rgba(245, 158, 11, 0.28);
        }

        .status-init {
            color: #bae6fd;
            background: rgba(56, 189, 248, 0.13);
            border: 1px solid rgba(56, 189, 248, 0.28);
        }

        details {
            margin-top: 8px;
        }

        summary {
            cursor: pointer;
            color: var(--info);
            font-size: 13px;
        }

        pre {
            background: #050816;
            color: #d1d5db;
            padding: 12px;
            border-radius: 12px;
            overflow: auto;
            white-space: pre-wrap;
            font-size: 12px;
            border: 1px solid var(--border);
        }

        .empty {
            color: var(--muted);
        }

        .footer {
            margin-top: 18px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 1000px) {
            .grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            table, thead, tbody, th, td, tr {
                display: block;
            }

            thead {
                display: none;
            }

            tr {
                border-bottom: 1px solid var(--border);
                padding: 12px;
            }

            td {
                border-bottom: none;
                padding: 8px 0;
            }

            td::before {
                content: attr(data-label);
                display: block;
                color: var(--muted);
                font-size: 12px;
                text-transform: uppercase;
                margin-bottom: 4px;
            }
        }

        @media (max-width: 600px) {
            .container {
                padding: 18px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .brand h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="topbar">
        <div class="brand">
            <h1><?= clean($title) ?></h1>
            <p>DNS Records Monitoring mit Change Detection</p>
        </div>

        <div class="badge">
            Auto Refresh: 60s
        </div>
    </div>

    <div class="grid">
        <div class="stat">
            <div class="label">Gesamt</div>
            <div class="value"><?= clean($total) ?></div>
        </div>

        <div class="stat">
            <div class="label">OK</div>
            <div class="value" style="color: var(--ok);"><?= clean($okCount) ?></div>
        </div>

        <div class="stat">
            <div class="label">Changed</div>
            <div class="value" style="color: var(--warn);"><?= clean($changedCount) ?></div>
        </div>

        <div class="stat">
            <div class="label">Init</div>
            <div class="value" style="color: var(--info);"><?= clean($initCount) ?></div>
        </div>

        <div class="stat">
            <div class="label">Fail</div>
            <div class="value" style="color: var(--fail);"><?= clean($failCount) ?></div>
        </div>
    </div>

    <div class="controls">
        <input class="search" id="searchInput" type="text" placeholder="Domain, Name oder Record suchen...">

        <select class="select" id="statusFilter">
            <option value="">Alle Status</option>
            <option value="OK">OK</option>
            <option value="CHANGED">Changed</option>
            <option value="INIT">Init</option>
            <option value="FAIL">Fail</option>
        </select>

        <select class="select" id="typeFilter">
            <option value="">Alle Typen</option>
            <option value="A">A</option>
            <option value="AAAA">AAAA</option>
            <option value="CNAME">CNAME</option>
            <option value="MX">MX</option>
            <option value="TXT">TXT</option>
            <option value="NS">NS</option>
            <option value="SOA">SOA</option>
            <option value="SRV">SRV</option>
            <option value="CAA">CAA</option>
        </select>
    </div>

    <?php if (count($alerts) > 0): ?>
        <div class="alerts">
            <h2>Aktuelle Alerts</h2>

            <?php foreach ($alerts as $alert): ?>
                <div class="alert"><?= clean($alert) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Name / Domain</th>
                <th>Typ</th>
                <th>Status</th>
                <th>Records</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($results as $result): ?>
                <?php
                $statusClass = match ($result["status"]) {
                    "OK" => "status-ok",
                    "CHANGED" => "status-changed",
                    "INIT" => "status-init",
                    "FAIL" => "status-fail",
                    default => "status-fail"
                };

                $statusIcon = match ($result["status"]) {
                    "OK" => "✅",
                    "CHANGED" => "🚨",
                    "INIT" => "ℹ️",
                    "FAIL" => "❌",
                    default => "❌"
                };

                $searchBlob = strtolower(
                    $result["name"] . " " .
                    $result["domain"] . " " .
                    $result["type"] . " " .
                    $result["status"] . " " .
                    implode(" ", $result["records"])
                );
                ?>

                <tr
                    class="dns-row"
                    data-search="<?= clean($searchBlob) ?>"
                    data-status="<?= clean($result["status"]) ?>"
                    data-type="<?= clean($result["type"]) ?>"
                >
                    <td data-label="Name / Domain">
                        <div class="name"><?= clean($result["name"]) ?></div>
                        <div class="domain"><?= clean($result["domain"]) ?></div>
                    </td>

                    <td data-label="Typ">
                        <span class="type"><?= clean($result["type"]) ?></span>
                    </td>

                    <td data-label="Status">
                        <span class="status <?= clean($statusClass) ?>">
                            <?= clean($statusIcon) ?> <?= clean($result["status"]) ?>
                        </span>
                        <div class="domain" style="margin-top: 6px;">
                            <?= clean($result["message"]) ?>
                        </div>
                    </td>

                    <td data-label="Records">
                        <?php if (count($result["records"]) > 0): ?>
                            <details>
                                <summary><?= count($result["records"]) ?> Record(s) anzeigen</summary>
                                <pre><?php
                                    foreach ($result["records"] as $record) {
                                        echo clean(prettyRecord($record)) . "\n";
                                    }
                                ?></pre>
                            </details>
                        <?php else: ?>
                            <span class="empty">Keine Records gefunden</span>
                        <?php endif; ?>

                        <?php if (!empty($result["error"])): ?>
                            <pre><?= clean($result["error"]) ?></pre>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">
        Letzter Check: <?= clean(date("Y-m-d H:i:s")) ?> |
        State-Datei: data/state.json
    </div>

</div>

<script>
    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");
    const typeFilter = document.getElementById("typeFilter");
    const rows = document.querySelectorAll(".dns-row");

    function filterRows() {
        const search = searchInput.value.toLowerCase().trim();
        const status = statusFilter.value;
        const type = typeFilter.value;

        rows.forEach(row => {
            const rowSearch = row.dataset.search || "";
            const rowStatus = row.dataset.status || "";
            const rowType = row.dataset.type || "";

            const matchesSearch = rowSearch.includes(search);
            const matchesStatus = !status || rowStatus === status;
            const matchesType = !type || rowType === type;

            row.style.display = matchesSearch && matchesStatus && matchesType ? "" : "none";
        });
    }

    searchInput.addEventListener("input", filterRows);
    statusFilter.addEventListener("change", filterRows);
    typeFilter.addEventListener("change", filterRows);
</script>

</body>
</html>
