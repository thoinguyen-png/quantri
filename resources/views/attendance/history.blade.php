<x-app-layout>
    @php
        $statusLabels = [
            'checked_in' => 'Đã checkin',
            'completed' => 'Đã hoàn tất',
            'missing_checkout' => 'Thiếu checkout',
        ];

        $attendanceBadge = function ($attendance) {
            if (!$attendance->checkin_at) {
                return ['attendance-record-status--danger', 'Thiếu checkin'];
            }

            if (!$attendance->checkout_at || $attendance->status === 'missing_checkout') {
                return ['attendance-record-status--danger', 'Thiếu checkout'];
            }

            $workDay = $attendance->work_day;
            $workedMinutes = (int) ($attendance->worked_minutes ?? 0);
            $requiredMinutes = null;

            if ($attendance->shift) {
                $shiftStart = \Carbon\Carbon::parse($attendance->shift->start_at);
                $shiftEnd = \Carbon\Carbon::parse($attendance->shift->end_at);

                if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
                    $shiftEnd->addDay();
                }

                $requiredMinutes = max($shiftStart->diffInMinutes($shiftEnd), 1);
            }

            $isFullWork = $workDay !== null
                ? (float) $workDay >= 1
                : ($requiredMinutes ? $workedMinutes >= $requiredMinutes : $attendance->status === 'completed' && $workedMinutes > 0);

            if ($isFullWork) {
                return ['attendance-record-status--completed', 'Đủ công'];
            }

            $ratio = $requiredMinutes ? $workedMinutes / $requiredMinutes : 0;

            return [
                $ratio >= 0.5 ? 'attendance-record-status--warning' : 'attendance-record-status--danger',
                'Thiếu công',
            ];
        };

        $summary = [
            'total' => $attendances->count(),
            'completed' => $attendances->where('status', 'completed')->count(),
            'overtimeHours' => round($attendances->sum(fn ($attendance) => (float) ($attendance->overtime_hours ?? ($attendance->overtime_minutes / 60))), 2),
        ];
    @endphp

    <div class="attendance-record-page">
        <div class="attendance-record-shell">
            <section class="attendance-record-hero attendance-record-toolbar">
                <div>
                    <div class="attendance-record-eyebrow">My attendance</div>
                    <h1 class="attendance-record-title">Lịch sử chấm công</h1>
                    <p class="attendance-record-subtitle">{{ $summary['total'] }} bản ghi gần nhất của bạn.</p>
                </div>
            </section>

            <section class="attendance-record-summary">
                <div class="attendance-record-summary-card">
                    <span>Tổng bản ghi</span>
                    <strong>{{ $summary['total'] }}</strong>
                </div>
                <div class="attendance-record-summary-card">
                    <span>Hoàn tất</span>
                    <strong>{{ $summary['completed'] }}</strong>
                </div>
                <div class="attendance-record-summary-card">
                    <span>Tăng ca</span>
                    <strong>{{ $summary['overtimeHours'] }}h</strong>
                </div>
            </section>

            <section class="attendance-record-list attendance-record-list--dense">
                @forelse ($attendances as $attendance)
                    @php
                        [$statusClass, $statusText] = $attendanceBadge($attendance);
                    @endphp

                    <article class="attendance-record-card attendance-record-row attendance-record-row--history">
                        <div class="attendance-record-top">
                            <div class="attendance-record-user">
                                <div class="attendance-record-avatar">
                                    {{ strtoupper(substr($attendance->shift?->name ?? 'C', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <strong>{{ $attendance->shift?->name ?? 'Không có ca' }}</strong>
                                    <span>{{ $attendance->work_date?->format('d/m/Y') ?? $attendance->checkin_at?->format('d/m/Y') ?? '-' }}</span>
                                </div>
                            </div>

                            <span class="attendance-record-status {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </div>

                        <div class="attendance-record-shift">
                            <span>Ca làm</span>
                            <b>{{ $attendance->shift?->name ?? 'Không có ca' }}</b>
                        </div>

                        <div class="attendance-record-times">
                            <div class="attendance-record-box">
                                <span>Giờ vào</span>
                                <strong>{{ $attendance->checkin_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                            </div>

                            <div class="attendance-record-box">
                                <span>Giờ ra</span>
                                <strong>{{ $attendance->checkout_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                            </div>
                        </div>

                        <div class="attendance-record-metrics">
                            <div class="attendance-record-box">
                                <span>Làm việc</span>
                                <strong>{{ $attendance->worked_minutes }}p</strong>
                            </div>

                            <div class="attendance-record-box">
                                <span>Tăng ca</span>
                                <strong>{{ number_format((float) ($attendance->overtime_hours ?? ($attendance->overtime_minutes / 60)), 2) }}h</strong>
                                <small>{{ $attendance->overtime_minutes }}p</small>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="attendance-record-empty">
                        Chưa có dữ liệu chấm công.
                    </div>
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>
