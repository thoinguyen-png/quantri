<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::latest()->get();

        return view('shifts.index', compact('shifts'));
    }

    public function create()
    {
        return view('shifts.create');
    }

    public function edit(Shift $shift)
    {
        return view('shifts.edit', compact('shift'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedShiftData($request);

        Shift::create($data);

        return redirect()
            ->route('shifts.index')
            ->with('success', 'Đã tạo ca làm.');
    }

    public function update(
        Request $request,
        Shift $shift
    ) {
        $data = $this->validatedShiftData($request);

        /*
         * Không xóa shift_segments cũ để bảo toàn dữ liệu lịch sử.
         * Luồng mới chỉ đọc shifts.start_at và shifts.end_at.
         */
        $shift->update($data);

        return redirect()
            ->route('shifts.index')
            ->with('success', 'Đã cập nhật ca làm.');
    }

    private function validatedShiftData(
        Request $request
    ): array {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'start_at' => [
                'required',
                'date_format:H:i',
            ],
            'end_at' => [
                'required',
                'date_format:H:i',
                'different:start_at',
            ],
            'late_after_minutes' => [
                'required',
                'integer',
                'min:0',
            ],
            'checkin_open_before_minutes' => [
                'nullable',
                'integer',
                'min:0',
                'max:1440',
            ],
            'checkin_close_after_minutes' => [
                'nullable',
                'integer',
                'min:0',
                'max:1440',
            ],
            'checkout_min_after_checkin_minutes' => [
                'nullable',
                'integer',
                'min:0',
                'max:1440',
            ],
            'checkout_close_after_shift_end_minutes' => [
                'nullable',
                'integer',
                'min:0',
                'max:1440',
            ],
        ]);

        $start = Carbon::createFromFormat(
            'H:i',
            $data['start_at']
        )->setDate(2000, 1, 1);

        $end = Carbon::createFromFormat(
            'H:i',
            $data['end_at']
        )->setDate(2000, 1, 1);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $data['start_at'] = $start;
        $data['end_at'] = $end;

        $data['checkin_open_before_minutes'] ??= 60;
        $data['checkin_close_after_minutes'] ??= 240;
        $data['checkout_min_after_checkin_minutes'] ??= 3;

        return $data;
    }
}
