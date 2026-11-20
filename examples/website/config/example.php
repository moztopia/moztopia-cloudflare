<?php

declare(strict_types=1);

return [
    'locales' => ['en' => 'English', 'th' => 'ไทย', 'ar' => 'العربية'],
    'planned_locales' => ['en', 'th', 'vi', 'id', 'ms', 'fil', 'my', 'km', 'lo', 'zh', 'ja', 'ko', 'hi', 'bn', 'ar', 'es', 'pt', 'fr', 'de', 'ru'],
    'scenarios' => [
        'bangkok' => ['peer' => '192.0.2.10', 'headers' => [
            'CF-IPCountry' => 'TH', 'CF-IPContinent' => 'AS', 'CF-Region' => 'Bangkok', 'CF-Region-Code' => '10',
            'CF-IPCity' => 'กรุงเทพมหานคร', 'CF-Timezone' => 'Asia/Bangkok',
            'CF-Connecting-IP' => '203.0.113.42', 'CF-Ray' => '0123456789abcdef-BKK', 'CF-Visitor' => '{"scheme":"https"}',
        ]],
        'london' => ['peer' => '192.0.2.10', 'headers' => [
            'CF-IPCountry' => 'GB', 'CF-IPContinent' => 'EU', 'CF-Region' => 'England', 'CF-Region-Code' => 'ENG',
            'CF-IPCity' => 'London', 'CF-Timezone' => 'Europe/London',
            'CF-Connecting-IP' => '203.0.113.43', 'CF-Ray' => '0123456789abcdef-LHR', 'CF-Visitor' => '{"scheme":"https"}',
        ]],
        'dubai' => ['peer' => '192.0.2.10', 'headers' => [
            'CF-IPCountry' => 'AE', 'CF-IPContinent' => 'AS', 'CF-Region' => 'دبي', 'CF-Region-Code' => 'DU',
            'CF-IPCity' => 'دبي', 'CF-Timezone' => 'Asia/Dubai',
            'CF-Connecting-IP' => '2001:db8::42', 'CF-Ray' => '0123456789abcdef-DXB', 'CF-Visitor' => '{"scheme":"https"}',
        ]],
        'missing' => ['peer' => '192.0.2.10', 'headers' => []],
        'tor' => ['peer' => '192.0.2.10', 'headers' => [
            'CF-IPCountry' => 'T1', 'CF-Connecting-IP' => '203.0.113.44', 'CF-Ray' => '0123456789abcdef-AMS', 'CF-Visitor' => '{"scheme":"https"}',
        ]],
        'untrusted' => ['peer' => '198.51.100.10', 'headers' => [
            'CF-IPCountry' => 'TH', 'CF-Region' => 'Bangkok', 'CF-Connecting-IP' => '203.0.113.42', 'CF-Visitor' => '{"scheme":"https"}',
        ]],
        'malformed' => ['peer' => '192.0.2.10', 'headers' => [
            'CF-IPCountry' => 'ZZ', 'CF-Timezone' => 'Not/A-Timezone', 'CF-Connecting-IP' => 'not-an-ip', 'CF-Ray' => 'invalid', 'CF-Visitor' => 'not-json',
        ]],
    ],
];
