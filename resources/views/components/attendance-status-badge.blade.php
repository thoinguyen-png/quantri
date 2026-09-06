@props([
    'status',
])

@php
    $label = $status['label'] ?? 'Không xác định';

    $badgeClass = $status['badge_class']
        ?? 'bg-slate-100 text-slate-700 border-slate-200';

    $lateMinutes = (int) (
        $status['late_minutes'] ?? 0
    );

    $earlyLeaveMinutes = (int) (
        $status['early_leave_minutes'] ?? 0
    );

    $overtimeMinutes = (int) (
        $status['overtime_minutes'] ?? 0
    );
@endphp

<div class="flex flex-col items-start gap-1">
    <span
        class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold {{ $badgeClass }}">
        {{ $label }}
    </span>

    @if ($lateMinutes > 0)
        <span class="text-xs font-medium text-orange-700">
            Trễ {{ $lateMinutes }} phút
        </span>
    @endif

    @if ($earlyLeaveMinutes > 0)
        <span class="text-xs font-medium text-amber-700">
            Về sớm {{ $earlyLeaveMinutes }} phút
        </span>
    @endif

    @if ($overtimeMinutes > 0)
        <span class="text-xs font-medium text-emerald-700">
            Tăng ca {{ $overtimeMinutes }} phút
        </span>
    @endif
</div>