<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <table border="1">
        <thead>
            <tr>
                <th>STT</th>
                <th>Ten</th>
                <th>Bộ phận</th>
                <th>Trạng thái</th>
                <th>Ma NV</th>
                <th>Công</th>
                <th>Công + tăng ca</th>
                <th>Số ngày đi trễ</th>
                <th>Tổng giờ đi trễ</th>
                <th>Về sớm (giờ)</th>
                <th>Tăng ca (giờ)</th>
                <th>NKP (ngay)</th>
                <th>Về sớm (ngày)</th>
                @foreach ($columns as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['stt'] }}</td>
                    <td>{{ $row['user']->name }}</td>
                    <td>{{ $row['user']->branch?->name ?? 'Chưa có' }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td style="mso-number-format:'\@';">{{ $row['employee_code'] }}</td>
                    <td>{{ $row['metrics']['work_units'] }}</td>
                    <td>{{ $row['metrics']['work_with_overtime'] }}</td>
                    <td>{{ $row['metrics']['late_days'] }}</td>
                    <td>{{ number_format((float) $row['metrics']['late_hours'], 2, '.', '') }}</td>
                    <td>{{ $row['metrics']['early_leave_hours'] }}</td>
                    <td>{{ $row['metrics']['overtime_hours'] }}</td>
                    <td>{{ $row['metrics']['unauthorized_absence_days'] }}</td>
                    <td>{{ $row['metrics']['early_leave_days'] }}</td>
                    @foreach ($columns as $key => $label)
                        <td>{{ $row['money'][$key] }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>