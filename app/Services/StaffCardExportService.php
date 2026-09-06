<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StaffCardExportService
{
    public function __construct(
        private readonly StaffCardPreviewService $cards,
    ) {}

    public function branchesFor(User $viewer): Collection
    {
        return Branch::active()
            ->when($viewer->role === 'manager', fn(Builder $query) => $query->whereKey($viewer->branch_id))
            ->orderBy('name')
            ->get();
    }

    public function availableEmployees(User $viewer, Branch $branch): Collection
    {
        return $this->activeEmployeeQuery($viewer, $branch)
            ->select(['id', 'name', 'role', 'branch_id', 'employee_code'])
            ->orderBy('name')
            ->get();
    }

    public function selectedEmployees(
        User $viewer,
        Branch $branch,
        string $selectionMode,
        array $selectedIds = [],
    ): Collection {
        $query = $this->activeEmployeeQuery($viewer, $branch)
            ->select([
                'id',
                'name',
                'role',
                'position_id',
                'branch_id',
                'citizen_id',
                'face_image_path',
                'rating_qr_enabled',
                'public_rating_code',
            ])
            ->with([
                'branch',
                'position',
                'activeRatingQrToken',
            ])
            ->orderBy('name');

        if ($selectionMode === 'selected') {
            $ids = collect($selectedIds)->map(fn($id) => (int) $id)->unique()->values();
            $query->whereKey($ids->all());
            $employees = $query->get();

            if ($ids->isEmpty() || $employees->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Danh sách nhân sự không hợp lệ, không hoạt động hoặc ngoài phạm vi cơ sở.',
                ]);
            }

            return $employees;
        }

        $employees = $query->get();

        if ($employees->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => 'Cơ sở chưa có nhân sự đang hoạt động để xuất thẻ.',
            ]);
        }

        return $employees;
    }

    public function cardData(Collection $employees, bool $forPdf = false): Collection
    {
        return $employees
            ->map(fn(User $employee) => $this->cards->dataFor($employee, $forPdf))
            ->values();
    }

    public function invalidRatingQrNames(
        Collection $employees
    ): Collection {
        return $employees
            ->filter(
                fn(User $employee) =>
                ! $this->cards->hasUsableRatingQr($employee)
            )
            ->map(function (User $employee): string {
                $name = trim((string) $employee->name);

                return $name !== ''
                    ? $name
                    : 'Nhân sự #' . $employee->id;
            })
            ->unique()
            ->values();
    }

    public function renderPdf(Collection $cards, string $template): string
    {
        $html = $this->pdfHtml($cards, $template);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $paper = $template === 'vertical'
            ? 'a5'
            : 'a4';

        $dompdf->setPaper($paper, 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function pdfHtml(Collection $cards, string $template): string
    {
        $perPage = $this->cardsPerPage($template);

        return view('staff_cards.pdf', [
            'pages' => $cards->chunk($perPage),
            'template' => $template,
        ])->render();
    }

    private function cardsPerPage(string $template): int
    {
        if ($template === 'vertical') {
            return 1;
        }

        // A4 portrait with 10mm horizontal and 8mm vertical margins.
        // The gaps match .sheet--horizontal in the PDF stylesheet.
        $usableWidth = 210 - (10 * 2);
        $usableHeight = 297 - (8 * 2);
        $cardWidth = 80;
        $cardHeight = 24;
        $gapX = 8;
        $gapY = 2;

        $columns = (int) floor(($usableWidth + $gapX) / ($cardWidth + $gapX));
        $rows = (int) floor(($usableHeight + $gapY) / ($cardHeight + $gapY));

        return max(1, $columns * $rows);
    }

    private function activeEmployeeQuery(User $viewer, Branch $branch): Builder
    {
        $roles = $viewer->role === 'manager'
            ? ['staff', 'cashier']
            : ['staff', 'cashier', 'manager'];

        return User::query()
            ->where('branch_id', $branch->id)
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('status')->orWhere('status', '!=', 'da_nghi');
            });
    }
}
