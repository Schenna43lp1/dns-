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

    $payload = json_encode(["content" => $message]);

    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

$title = $config["title"] ?? "DNS Monitor";
$checks = $config["checks"] ?? [];
$discordWebhook = $config["discord_webhook"] ?? "";

$state = loadState($stateFile);
$alerts = [];
$results = [];

foreach ($checks as $check) {
    $name = $check["name"] ?? "";
    $domain = $check["domain"] ?? "";
    $type = strtoupper($check["type"] ?? "A");
    $key = $domain . "_" . $type;

    $recordsRaw = @dns_get_record($domain, dnsTypeConstant($type));
    $recordsRaw = is_array($recordsRaw) ? $recordsRaw : [];
    $records = normalizeRecords($recordsRaw);

    $oldRecords = $state[$key]["records"] ?? null;

    $status = "OK";
    $message = "Keine Änderung";

    if ($oldRecords === null) {
        $status = "INIT";
        $message = "Erster Check gespeichert";
    } elseif ($oldRecords !== $records) {
        $newOnly = array_values(array_diff($records, $oldRecords));
        $removedOnly = array_values(array_diff($oldRecords, $records));

        if (count($newOnly) > 0) {
            $alertText = "🚨 Neuer DNS-Eintrag erkannt\n";
            $alertText .= "Name: $name\n";
            $alertText .= "Domain: $domain\n";
            $alertText .= "Typ: $type\n\n";
            $alertText .= "Neue Records:\n";
            $alertText .= implode("\n", $newOnly);

            $alerts[] = $alertText;
            sendDiscordAlert($discordWebhook, $alertText);
        }

        if (count($removedOnly) > 0) {
            $alertText = "⚠️ DNS-Eintrag entfernt\n";
            $alertText .= "Name: $name\n";
            $alertText .= "Domain: $domain\n";
            $alertText .= "Typ: $type\n\n";
            $alertText .= "Entfernte Records:\n";
            $alertText .= implode("\n", $removedOnly);

            $alerts[] = $alertText;
            sendDiscordAlert($discordWebhook, $alertText);
        }

        $status = "CHANGED";
        $message = "DNS Änderung erkannt";
    }

    $state[$key] = [
        "name" => $name,
        "domain" => $domain,
        "type" => $type,
        "records" => $records,
        "last_check" => date("Y-m-d H:i:s")
    ];

    $results[] = [
        "name" => $name,
        "domain" => $domain,
        "type" => $type,
        "records" => $records,
        "status" => $status,
        "message" => $message
    ];
}

saveState($stateFile, $state);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= clean($title) ?></title>
    <meta http-equiv="refresh" content="60">
    <style>
        body { font-family: Arial, sans-serif; background: #111; color: #eee; padding: 30px; }
        .card { background: #1e1e1e; padding: 15px; margin-bottom: 15px; border-radius: 10px; }
        .ok { color: #00ff88; font-weight: bold; }
        .changed { color: #ffaa00; font-weight: bold; }
        .init { color: #55aaff; font-weight: bold; }
        .alert { background: #3a1111; border: 1px solid #ff5555; padding: 15px; border-radius: 10px; margin-bottom: 15px; white-space: pre-wrap; }
        .meta { color: #aaa; font-size: 14px; }
        pre { background: #000; padding: 10px; border-radius: 8px; overflow: auto; white-space: pre-wrap; }
    </style>
</head>
<body>

<h1><?= clean($title) ?></h1>
<p class="meta">Letzter Check: <?= date("Y-m-d H:i:s") ?></p>

<?php if (count($alerts) > 0): ?>
    <h2>Alerts</h2>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert"><?= clean($alert) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php foreach ($results as $result): ?>
    <div class="card">
        <h2><?= clean($result["name"]) ?></h2>
        <p class="meta"><?= clean($result["domain"]) ?> — <?= clean($result["type"]) ?></p>

        <?php if ($result["status"] === "OK"): ?>
            <p class="ok">✅ OK — <?= clean($result["message"]) ?></p>
        <?php elseif ($result["status"] === "INIT"): ?>
            <p class="init">ℹ️ INIT — <?= clean($result["message"]) ?></p>
        <?php else: ?>
            <p class="changed">🚨 CHANGED — <?= clean($result["message"]) ?></p>
        <?php endif; ?>

        <pre><?= clean(implode("\n", $result["records"])) ?></pre>
    </div>
<?php endforeach; ?>

</body>
</html>
