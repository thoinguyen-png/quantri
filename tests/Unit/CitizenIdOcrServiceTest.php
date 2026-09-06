<?php

namespace Tests\Unit;

use App\Services\CitizenIdOcrService;
use PHPUnit\Framework\TestCase;

class CitizenIdOcrServiceTest extends TestCase
{
    public function test_it_parses_standard_vneid_text(): void
    {
        $rawText = <<<'TEXT'
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
TEXT;

        $data = (new CitizenIdOcrService())->parseRawText($rawText);

        $this->assertSame('064204013986', $data['citizen_id']);
        $this->assertSame('NGUYỄN HỮU TÀI', $data['name']);
        $this->assertSame('2004-12-03', $data['date_of_birth']);
        $this->assertSame('Nam', $data['gender']);
        $this->assertSame('Thanh Dương, Thanh Chương, Nghệ An', $data['place_of_origin']);
        $this->assertSame('Tổ dân phố 2, Thị trấn Chư Ty, Đức Cơ, Gia Lai', $data['address']);
        $this->assertSame('2021-09-06', $data['issue_date']);
    }

    public function test_it_handles_spaced_id_and_noisy_multiline_address(): void
    {
        $rawText = <<<'TEXT'
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
TEXT;

        $data = (new CitizenIdOcrService())->parseRawText($rawText);

        $this->assertSame('082204016369', $data['citizen_id']);
        $this->assertSame('NGUYEN TAN THO', $data['name']);
        $this->assertSame('2004-02-04', $data['date_of_birth']);
        $this->assertSame('Nam', $data['gender']);
        $this->assertSame('Tân Binh, TX. Cai Lay, Tiền Sing', $data['place_of_origin']);
        $this->assertSame('Ap Quy Chánh, Nhi Quy, TX. Cai Lay, Tiền Giang', $data['address']);
        $this->assertSame('2023-06-09', $data['issue_date']);
    }
}
