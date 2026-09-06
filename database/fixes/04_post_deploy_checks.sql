-- =========================================================
-- CHAMCONGV2 - KIỂM TRA SAU KHI TRIỂN KHAI SINGLE FLOW
-- Chỉ SELECT, không thay đổi dữ liệu.
-- =========================================================

-- 1. Không còn segment giả thiếu checkin khi attendance cha có checkin.
SELECT
    COUNT(*) AS false_missing_checkin_segments
FROM attendances a
JOIN attendance_segments s
    ON s.attendance_id = a.id
WHERE a.checkin_at IS NOT NULL
  AND s.checkin_at IS NULL
  AND s.checkout_at IS NOT NULL;

-- 2. Không còn attendance có đủ vào/ra nhưng trạng thái vẫn báo thiếu.
SELECT
    COUNT(*) AS false_missing_parent_statuses
FROM attendances
WHERE checkin_at IS NOT NULL
  AND checkout_at IS NOT NULL
  AND status IN ('missing_checkin', 'missing_checkout');

-- 3. Không còn cặp quét trước 08:50 bị tạo thành checkin ngày mới.
SELECT
    COUNT(*) AS wrong_next_day_pairs
FROM attendances previous_attendance
JOIN attendances wrong_new_attendance
    ON wrong_new_attendance.user_id = previous_attendance.user_id
   AND wrong_new_attendance.work_date = DATE_ADD(
       previous_attendance.work_date,
       INTERVAL 1 DAY
   )
WHERE previous_attendance.checkin_at IS NOT NULL
  AND previous_attendance.checkout_at IS NULL
  AND previous_attendance.status IN ('checked_in', 'missing_checkout')
  AND wrong_new_attendance.checkin_at IS NOT NULL
  AND wrong_new_attendance.checkout_at IS NULL
  AND wrong_new_attendance.checkin_at <= TIMESTAMP(
      wrong_new_attendance.work_date,
      '08:50:00'
  );

-- 4. Kiểm tra riêng attendance của Nguyễn Tấn Thời ngày 03/08/2026.
SELECT
    a.id,
    a.user_id,
    u.name,
    a.work_date,
    a.checkin_at,
    a.checkout_at,
    a.worked_minutes,
    a.late_minutes,
    a.overtime_minutes,
    a.overtime_hours,
    a.work_day,
    a.status
FROM attendances a
JOIN users u ON u.id = a.user_id
WHERE a.id = 3532;
