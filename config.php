<?php

return [
    "title" => "DNS Monitor",

    "admin_password" => "change-me-now",

    "discord_webhook" => "",

    "checks" => [
        ["name" => "Root A", "domain" => "markusstuefer.com", "type" => "A"],
        ["name" => "Root MX", "domain" => "markusstuefer.com", "type" => "MX"],
        ["name" => "Root TXT", "domain" => "markusstuefer.com", "type" => "TXT"],
        ["name" => "Root NS", "domain" => "markusstuefer.com", "type" => "NS"]
    ]
];
