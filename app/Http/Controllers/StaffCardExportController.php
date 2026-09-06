<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffCardExportRequest;
use App\Models\Branch;
use App\Services\StaffCardExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;
use Illuminate\Http\RedirectResponse;

class StaffCardExportController extends Controller
{
    public function index(Request $request, StaffCardExportService $exports): View
    {
        $viewer = $request->user();
        $branches = $exports->branchesFor($viewer);
        $requestedBranchId = (int) $request->query('branch_id', $branches->first()?->id);
        $branch = $branches->firstWhere('id', $requestedBranchId) ?? $branches->first();
        $employees = collect();

        if ($branch) {
            Gate::authorize('exportStaffCards', $branch);
            $employees = $exports->availableEmployees($viewer, $branch);
        }

        return view('staff_cards.export', compact('branches', 'branch', 'employees'));
    }

    public function preview(
        StaffCardExportRequest $request,
        StaffCardExportService $exports,
    ): View {
        [$branch, $employees] = $this->resolveSelection(
            $request,
            $exports
        );

        $validated = $request->validated();

        $invalidQrNames =
            $exports->invalidRatingQrNames($employees);

        return view('staff_cards.export-preview', [
            'branchName' => $branch->name,

            'cards' => $exports->cardData(
                $employees
            ),

            'selection' => $validated,

            'invalidQrNames' => $invalidQrNames,
        ]);
    }

    public function download(
        StaffCardExportRequest $request,
        StaffCardExportService $exports,
    ): Response|BinaryFileResponse|RedirectResponse {
        [$branch, $employees] = $this->resolveSelection(
            $request,
            $exports
        );

        $invalidQrNames =
            $exports->invalidRatingQrNames($employees);

        if ($invalidQrNames->isNotEmpty()) {
            $message =
                'Không thể xuất PDF. Nhân sự ' .
                $invalidQrNames->implode(', ') .
                ' có mã QR đánh giá bị thiếu hoặc lỗi, ' .
                'cần cấp lại trước khi xuất.';

            return redirect()
                ->route(
                    'staff-cards.export.index',
                    [
                        'branch_id' => $branch->id,
                    ]
                )
                ->withErrors([
                    'rating_qr' => $message,
                ]);
        }

        $template = $request->validated('template');

        $cards = $exports->cardData(
            $employees,
            true
        );

        if ($template !== 'both') {
            return $this->pdfResponse(
                $exports->renderPdf($cards, $template),
                $template === 'horizontal'
                    ? 'the-nhan-su-ngang-80x24.pdf'
                    : 'bang-danh-gia-nhan-su-a5.pdf',
            );
        }

        Storage::disk('local')->makeDirectory('staff-card-exports');
        $zipPath = Storage::disk('local')->path(
            'staff-card-exports/the-nhan-su-' . now()->format('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.zip'
        );
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Không thể tạo tập tin ZIP.');
        }

        try {
            $zip->addFromString(
                'the-nhan-su-ngang-80x24.pdf',
                $exports->renderPdf($cards, 'horizontal')
            );

            $zip->addFromString(
                'bang-danh-gia-nhan-su-a5.pdf',
                $exports->renderPdf($cards, 'vertical')
            );
        } finally {
            $zip->close();
        }

        return response()
            ->download($zipPath, 'the-nhan-su-ca-hai-mau.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function resolveSelection(
        StaffCardExportRequest $request,
        StaffCardExportService $exports,
    ): array {
        $validated = $request->validated();
        $branch = Branch::query()->findOrFail($validated['branch_id']);
        Gate::authorize('exportStaffCards', $branch);

        $employees = $exports->selectedEmployees(
            $request->user(),
            $branch,
            $validated['selection_mode'],
            $validated['user_ids'] ?? [],
        );

        return [$branch, $employees];
    }

    private function pdfResponse(string $contents, string $filename): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
