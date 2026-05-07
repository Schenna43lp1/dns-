<?php

return [
    "title" => "DNS Monitor",

    // Optional: Discord Webhook URL eintragen
    // Beispiel: "https://discord.com/api/webhooks/..."
    "discord_webhook" => "",

    "checks" => [
        ["name" => "Root A", "domain" => "markusstuefer.com", "type" => "A"],
        ["name" => "Root AAAA", "domain" => "markusstuefer.com", "type" => "AAAA"],
        ["name" => "Root MX", "domain" => "markusstuefer.com", "type" => "MX"],
        ["name" => "Root TXT", "domain" => "markusstuefer.com", "type" => "TXT"],
        ["name" => "Root NS", "domain" => "markusstuefer.com", "type" => "NS"],
        ["name" => "Root SOA", "domain" => "markusstuefer.com", "type" => "SOA"],
        ["name" => "Root CAA", "domain" => "markusstuefer.com", "type" => "CAA"],

        ["name" => "WWW CNAME", "domain" => "www.markusstuefer.com", "type" => "CNAME"],
        ["name" => "Mail A", "domain" => "mail.markusstuefer.com", "type" => "A"],
        ["name" => "Webmail A", "domain" => "webmail.markusstuefer.com", "type" => "A"],
        ["name" => "DMARC", "domain" => "_dmarc.markusstuefer.com", "type" => "TXT"],
        ["name" => "DKIM default", "domain" => "default._domainkey.markusstuefer.com", "type" => "TXT"],

        ["name" => "Optimum Root A", "domain" => "optimumservehosting.com", "type" => "A"],
        ["name" => "Optimum Root AAAA", "domain" => "optimumservehosting.com", "type" => "AAAA"],
        ["name" => "Optimum Root MX", "domain" => "optimumservehosting.com", "type" => "MX"],
        ["name" => "Optimum Root TXT", "domain" => "optimumservehosting.com", "type" => "TXT"],
        ["name" => "Optimum Root NS", "domain" => "optimumservehosting.com", "type" => "NS"],
        ["name" => "Optimum Root SOA", "domain" => "optimumservehosting.com", "type" => "SOA"],
        ["name" => "Optimum Root CAA", "domain" => "optimumservehosting.com", "type" => "CAA"],

        ["name" => "Optimum WWW CNAME", "domain" => "www.optimumservehosting.com", "type" => "CNAME"],
        ["name" => "Optimum Mail A", "domain" => "mail.optimumservehosting.com", "type" => "A"],
        ["name" => "Optimum Webmail A", "domain" => "webmail.optimumservehosting.com", "type" => "A"],
        ["name" => "Optimum DMARC", "domain" => "_dmarc.optimumservehosting.com", "type" => "TXT"],
        ["name" => "Optimum DKIM default", "domain" => "default._domainkey.optimumservehosting.com", "type" => "TXT"]
    ]
];
