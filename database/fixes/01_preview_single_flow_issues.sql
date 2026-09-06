-- =========================================================
-- CHAMCONGV2 - KIỂM TRA TRƯỚC KHI BỎ LUỒNG CA CHIA ĐOẠN
-- Chỉ SELECT, không thay đổi dữ liệu.
-- =========================================================

-- 1. Kiểm tra cấu hình đoạn ca hiện tại.
SELECT
    COUNT(*) AS total_shift_segment_rows,
    COUNT(DISTINCT shift_id) AS shifts_using_segment_table,
    MAX(segment_count) AS max_segments_per_shift
FROM (
    SELECT
        shift_id,
        COUNT(*) AS segment_count
    FROM shift_segments
    GROUP BY shift_id
) segment_counts;

-- 2. Đoạn có giờ ra nhưng thiếu giờ vào,
-- trong khi attendance cha đã có giờ vào.
SELECT
    a.id AS attendance_id,
    a.user_id,
    u.name,
    a.shift_id,
    a.work_date,
    a.checkin_at AS attendance_checkin,
    a.checkout_at AS attendance_checkout,
    a.status,
    s.id AS attendance_segment_id,
    s.segment_order,
    s.checkin_at AS segment_checkin,
    s.checkout_at AS segment_checkout
FROM attendances a
JOIN users u
    ON u.id = a.user_id
JOIN attendance_segments s
    ON s.attendance_id = a.id
WHERE a.checkin_at IS NOT NULL
  AND s.checkin_at IS NULL
  AND s.checkout_at IS NOT NULL
ORDER BY a.work_date, a.user_id;

-- 3. Attendance có đủ giờ vào/ra nhưng trạng thái vẫn báo thiếu.
SELECT
    a.id,
    a.user_id,
    u.name,
    a.shift_id,
    a.work_date,
    a.checkin_at,
    a.checkout_at,
    a.status,
    a.worked_minutes,
    a.overtime_minutes,
    a.work_day
FROM attendances a
JOIN users u
    ON u.id = a.user_id
WHERE a.checkin_at IS NOT NULL
  AND a.checkout_at IS NOT NULL
  AND a.status IN ('missing_checkin', 'missing_checkout')
ORDER BY a.work_date, a.user_id;

-- 4. Lần quét trước 08:50 bị tạo thành checkin ngày mới
-- trong khi ngày hôm trước vẫn còn attendance chưa checkout.
SELECT
    previous_attendance.id AS previous_attendance_id,
    wrong_new_attendance.id AS wrong_new_attendance_id,
    previous_attendance.user_id,
    u.name,
    previous_attendance.work_date AS previous_work_date,
    previous_attendance.checkin_at AS previous_checkin,
    wrong_new_attendance.work_date AS wrong_work_date,
    wrong_new_attendance.checkin_at AS scan_that_should_be_checkout
FROM attendances previous_attendance
JOIN attendances wrong_new_attendance
    ON wrong_new_attendance.user_id =
       previous_attendance.user_id
   AND wrong_new_attendance.work_date =
       DATE_ADD(
           previous_attendance.work_date,
           INTERVAL 1 DAY
       )
JOIN users u
    ON u.id = previous_attendance.user_id
WHERE previous_attendance.checkin_at IS NOT NULL
  AND previous_attendance.checkout_at IS NULL
  AND previous_attendance.status IN (
      'checked_in',
      'missing_checkout'
  )
  AND wrong_new_attendance.checkin_at IS NOT NULL
  AND wrong_new_attendance.checkout_at IS NULL
  AND wrong_new_attendance.checkin_at <=
      TIMESTAMP(
          wrong_new_attendance.work_date,
          '08:50:00'
      )
ORDER BY wrong_new_attendance.checkin_at;
