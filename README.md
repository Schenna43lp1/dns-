# DNS Checker / DNS Monitor

Ein einfacher PHP + Docker DNS-Monitor.

Funktionen:

- prüft DNS Records aus `config.php`
- erkennt neue DNS-Einträge
- erkennt entfernte DNS-Einträge
- speichert Snapshot in `data/state.json`
- optional Discord Alerts
- läuft mit Docker Compose

## Start

```bash
mkdir -p data
[ -f data/state.json ] || echo "{}" > data/state.json

docker compose up -d --build
```

Dann öffnen:

```text
http://SERVER-IP:8080
```

## Config bearbeiten

Domains und Records in `config.php` ändern.

Beispiel:

```php
["name" => "Root A", "domain" => "example.com", "type" => "A"],
["name" => "Root MX", "domain" => "example.com", "type" => "MX"],
["name" => "Root TXT", "domain" => "example.com", "type" => "TXT"],
```

Unterstützte Typen:

- A
- AAAA
- CNAME
- MX
- TXT
- NS
- SOA
- SRV
- CAA
- ANY

## Discord Alert aktivieren

In `config.php`:

```php
"discord_webhook" => "https://discord.com/api/webhooks/...",
```

## Automatisch alle 5 Minuten prüfen

Die Web-App prüft beim Aufruf.
Für echtes Monitoring Cron auf dem Docker-Host einrichten:

```bash
crontab -e
```

Einfügen:

```bash
*/5 * * * * curl -s http://127.0.0.1:8080 > /dev/null
```

## Wichtig

Beim ersten Start wird nur der aktuelle Zustand gespeichert.
Alerts kommen erst ab dem zweiten Check, wenn sich Records geändert haben.
