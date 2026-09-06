<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\CitizenIdOcrService;

$service = new CitizenIdOcrService();

$cases = [
    [
        'name' => 'standard-vneid',
        'raw' => <<<'TEXT'
CĂN CƯỚC CÔNG DÂN
Số / No.: 06420401 3986
Họ và tên / Full name:
NGUYỄN HỮU TÀI
Ngày sinh / Date of birth: 03/12/2004
Giới tính / Sex: Nam Quốc tịch / Nationality: Việt Nam
Quê quán / Place of origin:
Thanh Dương, Thanh Chương, Nghệ An
Nơi thường trú / Place of residence: Tổ dân phố 2
Thị trấn Chư Ty, Đức Cơ, Gia Lai
Có giá trị đến / Date of expiry: 03/12/2029
Đặc điểm nhân dạng: Sẹo nhỏ
Ngày cấp / Date of issue: 06/09/2021
TEXT,
        'expect' => [
            'citizen_id' => '064204013986',
            'name' => 'NGUYỄN HỮU TÀI',
            'date_of_birth' => '2004-12-03',
            'gender' => 'Nam',
            'place_of_origin' => 'Thanh Dương, Thanh Chương, Nghệ An',
            'address' => 'Tổ dân phố 2, Thị trấn Chư Ty, Đức Cơ, Gia Lai',
            'issue_date' => '2021-09-06',
        ],
    ],
    [
        'name' => 'noisy-ocr',
        'raw' => <<<'TEXT'
$o/ No.:
08220401 6369
Ho va tén / Full name:
NGUYEN TAN THO!
Ngày sinh / Date of birth; 04/02/2004
Giditinh/ Sex: Nam Quốc PO Bg Việt Nam
Qué quán / Place of origin:
Tân Binh, TX. Cai Lay, Tiền Sing
_Cóg
w
đến:
Nơi ine trú / Place of residence: Ap Quy Chánh:
“
Te of expiry
Nhi Quy, TX. Cai Lay, Tiền Giang ~
,
(04/02/2029
Dac diém nhan dang
N6t rudi C:4 cm sau cánh mũi phải
Ngay cap
09/06/2023
TEXT,
        'expect' => [
            'citizen_id' => '082204016369',
            'name' => 'NGUYEN TAN THO',
            'date_of_birth' => '2004-02-04',
            'gender' => 'Nam',
            'place_of_origin' => 'Tân Binh, TX. Cai Lay, Tiền Sing',
            'address' => 'Ap Quy Chánh, Nhi Quy, TX. Cai Lay, Tiền Giang',
            'issue_date' => '2023-06-09',
        ],
    ],
];

$failed = false;

foreach ($cases as $case) {
    $data = $service->parseRawText($case['raw']);

    foreach ($case['expect'] as $field => $expected) {
        $actual = $data[$field] ?? null;

        if ($actual !== $expected) {
            $failed = true;
            fwrite(STDERR, "[FAIL] {$case['name']} {$field}\nExpected: {$expected}\nActual: " . var_export($actual, true) . "\n");
        }
    }

    if (!$failed) {
        echo "[PASS] {$case['name']}\n";
    }
}

exit($failed ? 1 : 0);
