<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\RatingQrService;
use App\Services\StaffCardExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class StaffCardExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_select_branch_and_preview_active_employees_without_full_citizen_id(): void
    {
        $branch = Branch::create(['name' => 'Cơ sở Nguyễn Huệ']);
        $admin = User::factory()->create(['role' => 'admin']);
        $active = User::factory()->create([
            'name' => 'Nguyễn Tấn Thời',
            'role' => 'staff',
            'branch_id' => $branch->id,
            'citizen_id' => '079-1234-5678',
            'is_active' => true,
            'status' => 'chinh_thuc',
        ]);
        User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('staff-cards.export.index', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Xuất thẻ nhân sự')
            ->assertSee($active->name);

        $this->actingAs($admin)
            ->post(route('staff-cards.export.preview'), [
                'branch_id' => $branch->id,
                'selection_mode' => 'selected',
                'user_ids' => [$active->id],
                'template' => 'horizontal',
            ])
            ->assertOk()
            ->assertSee('Nguyễn Tấn Thời')
            ->assertSee('5678')
            ->assertDontSee('079-1234-5678')
            ->assertDontSee('07912345678');
    }

    public function test_manager_can_only_export_own_branch(): void
    {
        $ownBranch = Branch::create(['name' => 'Cơ sở A']);
        $otherBranch = Branch::create(['name' => 'Cơ sở B']);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $ownBranch->id]);
        User::factory()->create([
            'role' => 'staff',
            'branch_id' => $ownBranch->id,
            'is_active' => true,
            'status' => 'chinh_thuc',
        ]);

        $this->actingAs($manager)
            ->post(route('staff-cards.export.preview'), [
                'branch_id' => $ownBranch->id,
                'selection_mode' => 'all_active',
                'template' => 'vertical',
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->post(route('staff-cards.export.preview'), [
                'branch_id' => $otherBranch->id,
                'selection_mode' => 'all_active',
                'template' => 'vertical',
            ])
            ->assertForbidden();
    }

    public function test_pdf_html_has_exact_mm_layout_page_limits_vietnamese_font_and_vector_qr(): void
    {
        $exports = app(StaffCardExportService::class);
        $qrSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="420"><rect width="420" height="420" fill="#fff"/><path d="M0 0h210v210H0z" fill="#000"/></svg>';
        $card = [
            'name' => 'Nguyễn Tấn Thời',
            'position' => 'Nhân viên',
            'avatar_url' => null,
            'logo_url' => '',
            'citizen_last4' => '5678',
            'qr_svg_url' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            'has_rating_qr' => true,
        ];

        $horizontalHtml = $exports->pdfHtml(collect(array_fill(0, 17, $card)), 'horizontal');
        $verticalHtml = $exports->pdfHtml(collect(array_fill(0, 5, $card)), 'vertical');

        $this->assertStringContainsString('font-family: "DejaVu Sans"', $horizontalHtml);
        $this->assertStringContainsString('width: 80mm', $horizontalHtml);
        $this->assertStringContainsString('height: 30mm', $horizontalHtml);
        $this->assertSame(2, substr_count($horizontalHtml, '<section class="pdf-page'));
        $this->assertStringContainsString('width: 85mm', $verticalHtml);
        $this->assertStringContainsString('height: 140mm', $verticalHtml);
        $this->assertSame(2, substr_count($verticalHtml, '<section class="pdf-page'));
        $this->assertStringContainsString('Nguyễn Tấn Thời', $horizontalHtml);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $horizontalHtml);
        $this->assertStringContainsString('<svg', base64_decode(explode(',', $card['qr_svg_url'], 2)[1]));

        $horizontalPdf = $exports->renderPdf(collect(array_fill(0, 17, $card)), 'horizontal');
        $verticalPdf = $exports->renderPdf(collect(array_fill(0, 5, $card)), 'vertical');
        $this->assertStringStartsWith('%PDF-', $horizontalPdf);
        $this->assertStringStartsWith('%PDF-', $verticalPdf);
        $this->assertGreaterThan(5000, strlen($horizontalPdf));
        $this->assertGreaterThan(5000, strlen($verticalPdf));
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $horizontalPdf));
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $verticalPdf));
        $this->assertMatchesRegularExpression(
            '/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+595\.\d+\s+841\.\d+\s*\]/',
            $horizontalPdf
        );
    }

    public function test_both_templates_download_as_zip_with_two_valid_pdfs(): void
    {
        $branch = Branch::create(['name' => 'Cơ sở A']);
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create([
            'name' => 'Nhân sự có QR',
            'role' => 'staff',
            'branch_id' => $branch->id,
            'citizen_id' => '012345678999',
            'rating_qr_enabled' => true,
            'is_active' => true,
            'status' => 'chinh_thuc',
        ]);
        app(RatingQrService::class)->getOrCreateForUser($employee, $admin);

        $response = $this->actingAs($admin)
            ->post(route('staff-cards.export.download'), [
                'branch_id' => $branch->id,
                'selection_mode' => 'selected',
                'user_ids' => [$employee->id],
                'template' => 'both',
            ]);

        $response->assertOk()->assertDownload('the-nhan-su-ca-hai-mau.zip');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($path) === true);
        $this->assertSame(2, $zip->numFiles);
        $this->assertStringStartsWith('%PDF-', $zip->getFromName('the-nhan-su-ngang.pdf'));
        $this->assertStringStartsWith('%PDF-', $zip->getFromName('the-nhan-su-doc.pdf'));
        $zip->close();
        @unlink($path);
    }

    public function test_bulk_card_data_uses_eager_loaded_branch_and_qr_without_follow_up_queries(): void
    {
        $branch = Branch::create(['name' => 'Cơ sở A']);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (range(1, 3) as $number) {
            $employee = User::factory()->create([
                'name' => 'Nhân sự ' . $number,
                'role' => 'staff',
                'branch_id' => $branch->id,
                'rating_qr_enabled' => true,
                'is_active' => true,
                'status' => 'chinh_thuc',
            ]);
            app(RatingQrService::class)->getOrCreateForUser($employee, $admin);
        }

        $exports = app(StaffCardExportService::class);
        $employees = $exports->selectedEmployees($admin, $branch, 'all_active');

        $this->assertTrue($employees->every(
            fn (User $employee) => $employee->relationLoaded('branch')
                && $employee->relationLoaded('activeRatingQrToken')
        ));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $cards = $exports->cardData($employees, true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(3, $cards);
        $this->assertCount(0, $queries);
        $this->assertTrue($cards->every(function (array $card) {
            if (! str_starts_with((string) $card['qr_svg_url'], 'data:image/svg+xml;base64,')) {
                return false;
            }

            $svg = base64_decode(explode(',', $card['qr_svg_url'], 2)[1], true);

            return is_string($svg) && str_contains($svg, '<svg');
        }));
    }

    public function test_missing_optional_data_and_duplicate_last_four_digits_export_safely(): void
    {
        $branch = Branch::create(['name' => 'Cơ sở chưa có logo']);
        $admin = User::factory()->create(['role' => 'admin']);
        $first = User::factory()->create([
            'name' => 'Nguyễn Thị Nhân Sự Không Có Ảnh Đại Diện',
            'role' => 'staff',
            'branch_id' => $branch->id,
            'citizen_id' => '0123 4567 8988',
            'face_image_path' => null,
            'rating_qr_enabled' => false,
            'is_active' => true,
            'status' => 'chinh_thuc',
        ]);
        $second = User::factory()->create([
            'name' => 'Trần Văn Nhân Sự Trùng Bốn Số Cuối',
            'role' => 'cashier',
            'branch_id' => $branch->id,
            'citizen_id' => '9999-0000-8988',
            'face_image_path' => null,
            'rating_qr_enabled' => false,
            'is_active' => true,
            'status' => 'chinh_thuc',
        ]);
        $missingCitizenId = User::factory()->create([
            'name' => 'Lê Thị Thiếu CCCD',
            'role' => 'staff',
            'branch_id' => $branch->id,
            'citizen_id' => null,
            'face_image_path' => null,
            'rating_qr_enabled' => false,
            'is_active' => true,
            'status' => 'chinh_thuc',
        ]);

        $exports = app(StaffCardExportService::class);
        $employees = $exports->selectedEmployees(
            $admin,
            $branch,
            'selected',
            [$first->id, $second->id, $missingCitizenId->id],
        );
        $cards = $exports->cardData($employees, true);
        $serializedCards = json_encode($cards, JSON_UNESCAPED_UNICODE);

        $this->assertCount(3, $cards);
        $this->assertSame(2, $cards->where('citizen_last4', '8988')->count());
        $this->assertSame(1, $cards->where('citizen_last4', '----')->count());
        $this->assertTrue($cards->every(fn (array $card) => $card['avatar_url'] === null));
        $this->assertTrue($cards->every(
            fn (array $card) => str_starts_with($card['logo_url'], 'data:image/png;base64,')
        ));
        $this->assertStringNotContainsString('0123 4567 8988', $serializedCards);
        $this->assertStringNotContainsString('9999-0000-8988', $serializedCards);
        $this->assertStringNotContainsString('012345678988', $serializedCards);
        $this->assertStringNotContainsString('999900008988', $serializedCards);

        $pdf = $exports->renderPdf($cards, 'vertical');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf));
    }

    public function test_long_vietnamese_name_is_clipped_inside_fixed_size_cards(): void
    {
        $exports = app(StaffCardExportService::class);
        $card = [
            'name' => 'Nguyễn Thị Hoàng Anh Phương Uyên Trần Gia Bảo Minh Khang Quốc Khánh',
            'position' => 'Nhân viên',
            'avatar_url' => null,
            'logo_url' => '',
            'citizen_last4' => '1234',
            'qr_svg_url' => null,
            'has_rating_qr' => false,
        ];

        $horizontalHtml = $exports->pdfHtml(collect([$card]), 'horizontal');
        $verticalHtml = $exports->pdfHtml(collect([$card]), 'vertical');

        $this->assertStringContainsString('max-height: 12.5mm', $horizontalHtml);
        $this->assertStringContainsString('max-height: 31mm', $verticalHtml);
        $this->assertStringContainsString('word-break: break-word', $horizontalHtml);
        $this->assertSame(1, substr_count($horizontalHtml, $card['name']));
        $this->assertSame(1, substr_count($verticalHtml, $card['name']));
        $this->assertStringStartsWith('%PDF-', $exports->renderPdf(collect([$card]), 'horizontal'));
        $this->assertStringStartsWith('%PDF-', $exports->renderPdf(collect([$card]), 'vertical'));
    }
}
