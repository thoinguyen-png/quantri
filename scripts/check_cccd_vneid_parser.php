<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\CitizenIdOcrService;

$rawText = <<<'TEXT'
14:57
76
Thng tin thé Cän cuóc cng dän
CONG HOAXAHOI CHU NGHiA VIET NAM
Doc lap-Tudo-Hanh phüc
SOCIALIST REPUBLIC OF VIET NAM
CAN CUO'C CONG DAN
Cizen Identity Card
s6/No.082204016369
Ho vä tén Full name
NGUYÉN TAN THÖI
Ngay sinh l Date of birth: 04/02/2004
Gi6i tinh/ Sex: Nam Quóc tich/Nationality: Viet Nam
Que quan I Place of origin:
Tän Binh, TX. Cai Lay, Tién Giang
Noi thung trúI Place of residence: Áp Quý Chánh
Co gia tri dén.
Nhi Quy, TX. Cai Lay, Tién Giang
04/02/2029
Däc diém nhan dang
Nt rui C:4 cm sau cánh múi phái
Ngay cäp
09/06/2023
Cän cu6c dién tü
Lich sU cäp thé CC/CCCD/CMND
TEXT;

$service = new CitizenIdOcrService();
$data = $service->parseRawText($rawText);

$assert = function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$labelFragments = [
    'Full name',
    'Date of birth',
    'Sex',
    'Nationality',
    'Place of origin',
    'Place of residence',
    'Date of issue',
    'Date of expiry',
    'Ho va ten',
    'Ngay sinh',
    'Gioi tinh',
    'Que quan',
    'Noi thuong tru',
    'Co gia tri den',
    'Dac diem nhan dang',
    'Ngay cap',
];

$assert(preg_match('/^\d{12}$/', $data['citizen_id'] ?? '') === 1, 'citizen_id must be exactly 12 digits.');
$assert(trim((string) ($data['date_of_birth'] ?? '')) !== '', 'date_of_birth must not be empty.');
$assert(($data['gender'] ?? '') === 'Nam', 'gender must be Nam.');
$assert(trim((string) ($data['issue_date'] ?? '')) !== '', 'issue_date must not be empty.');
$assert(($data['issue_date'] ?? '') !== '2029-02-04', 'issue_date must not be expiry date.');
$assert(stripos((string) ($data['place_of_origin'] ?? ''), 'Place of origin') === false, 'place_of_origin must not contain Place of origin label.');
$assert(stripos((string) ($data['address'] ?? ''), 'Place of residence') === false, 'address must not contain Place of residence label.');
$assert(stripos((string) ($data['address'] ?? ''), 'Dac diem nhan dang') === false, 'address must not contain Dac diem nhan dang.');
$assert(stripos((string) ($data['address'] ?? ''), 'Đặc điểm nhận dạng') === false, 'address must not contain Đặc điểm nhận dạng.');
$assert(!str_contains((string) ($data['address'] ?? ''), '04/02/2029'), 'address must not contain expiry date.');
$assert(!str_contains((string) ($data['address'] ?? ''), '2029-02-04'), 'address must not contain normalized expiry date.');

foreach ($data as $field => $value) {
    if ($field === 'raw_text') {
        continue;
    }

    foreach ($labelFragments as $fragment) {
        $assert(stripos((string) $value, $fragment) === false, "{$field} must not contain leftover label {$fragment}.");
    }
}

echo json_encode([
    'ok' => true,
    'data' => [
        'citizen_id' => $data['citizen_id'],
        'date_of_birth' => $data['date_of_birth'],
        'gender' => $data['gender'],
        'issue_date' => $data['issue_date'],
        'place_of_origin' => $data['place_of_origin'],
        'address' => $data['address'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
