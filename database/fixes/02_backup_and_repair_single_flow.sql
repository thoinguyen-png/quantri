-- =========================================================
-- CHAMCONGV2 - SỬA DỮ LIỆU VỀ LUỒNG MỘT CHECKIN / MỘT CHECKOUT
--
-- An toàn:
-- 1. Không xóa bảng attendance_segments hoặc shift_segments.
-- 2. Sao lưu đúng các bản ghi sắp bị tác động.
-- 3. Giữ nguyên dữ liệu thiếu checkin thật sự
--    (attendance.checkin_at IS NULL).
-- =========================================================

-- ---------------------------------------------------------
-- A. Tạo bảng sao lưu
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS
    backup_attendances_single_flow_20260806
LIKE attendances;

CREATE TABLE IF NOT EXISTS
    backup_attendance_segments_single_flow_20260806
LIKE attendance_segments;

/*
 * CREATE TABLE gây implicit COMMIT trong MySQL.
 * Vì vậy chỉ bắt đầu transaction sau khi tạo xong
 * hai bảng backup.
 */
START TRANSACTION;

-- ---------------------------------------------------------
-- B. Xác định lần quét ngày mới lẽ ra phải là checkout
--    của ngày hôm trước.
-- ---------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS
    tmp_wrong_next_day_attendance;

CREATE TEMPORARY TABLE
    tmp_wrong_next_day_attendance
AS
SELECT
    previous_attendance.id AS previous_attendance_id,
    wrong_new_attendance.id AS wrong_new_attendance_id,
    wrong_new_attendance.checkin_at AS actual_checkout_at,
    wrong_new_attendance.checkin_latitude AS actual_checkout_latitude,
    wrong_new_attendance.checkin_longitude AS actual_checkout_longitude
FROM attendances previous_attendance
JOIN attendances wrong_new_attendance
    ON wrong_new_attendance.user_id =
       previous_attendance.user_id
   AND wrong_new_attendance.work_date =
       DATE_ADD(
           previous_attendance.work_date,
           INTERVAL 1 DAY
       )
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
      );

-- Chỉ giữ lần quét sớm nhất nếu có dữ liệu trùng.
DROP TEMPORARY TABLE IF EXISTS
    tmp_wrong_next_day_attendance_dedup;

CREATE TEMPORARY TABLE
    tmp_wrong_next_day_attendance_dedup
AS
SELECT source.*
FROM tmp_wrong_next_day_attendance source
JOIN (
    SELECT
        previous_attendance_id,
        MIN(actual_checkout_at) AS first_checkout_at
    FROM tmp_wrong_next_day_attendance
    GROUP BY previous_attendance_id
) first_scan
    ON first_scan.previous_attendance_id =
       source.previous_attendance_id
   AND first_scan.first_checkout_at =
       source.actual_checkout_at;

-- ---------------------------------------------------------
-- C. Sao lưu các attendance bị ảnh hưởng
-- ---------------------------------------------------------

INSERT IGNORE INTO
    backup_attendances_single_flow_20260806
SELECT DISTINCT a.*
FROM attendances a
LEFT JOIN attendance_segments segment
    ON segment.attendance_id = a.id
LEFT JOIN tmp_wrong_next_day_attendance_dedup wrong_pair
    ON a.id IN (
        wrong_pair.previous_attendance_id,
        wrong_pair.wrong_new_attendance_id
    )
WHERE (
        a.checkin_at IS NOT NULL
        AND a.checkout_at IS NOT NULL
        AND a.status IN (
            'missing_checkin',
            'missing_checkout'
        )
    )
   OR (
        a.checkin_at IS NOT NULL
        AND segment.checkin_at IS NULL
        AND segment.checkout_at IS NOT NULL
    )
   OR wrong_pair.previous_attendance_id IS NOT NULL;

INSERT IGNORE INTO
    backup_attendance_segments_single_flow_20260806
SELECT DISTINCT segment.*
FROM attendance_segments segment
JOIN backup_attendances_single_flow_20260806 backup
    ON backup.id = segment.attendance_id;

-- ---------------------------------------------------------
-- D. Ghép lần quét sai ngày thành checkout của ngày trước
-- ---------------------------------------------------------

UPDATE attendances previous_attendance
JOIN tmp_wrong_next_day_attendance_dedup wrong_pair
    ON wrong_pair.previous_attendance_id =
       previous_attendance.id
SET
    previous_attendance.checkout_at =
        wrong_pair.actual_checkout_at,
    previous_attendance.checkout_latitude =
        wrong_pair.actual_checkout_latitude,
    previous_attendance.checkout_longitude =
        wrong_pair.actual_checkout_longitude,
    previous_attendance.status = 'completed',
    previous_attendance.updated_at = NOW();

-- Xóa attendance ngày mới được tạo nhầm.
-- attendance_segments liên quan tự xóa theo khóa ngoại CASCADE.
DELETE wrong_new_attendance
FROM attendances wrong_new_attendance
JOIN tmp_wrong_next_day_attendance_dedup wrong_pair
    ON wrong_pair.wrong_new_attendance_id =
       wrong_new_attendance.id;

-- ---------------------------------------------------------
-- E. Tính lại attendance cha từ giờ vào/ra và giờ ca
-- ---------------------------------------------------------

UPDATE attendances attendance_to_fix
JOIN (
    SELECT
        calculated.id,
        calculated.worked_minutes,
        calculated.late_minutes,
        calculated.overtime_minutes,
        ROUND(
            calculated.overtime_minutes / 60,
            2
        ) AS overtime_hours,
        ROUND(
            LEAST(
                calculated.worked_minutes
                    / calculated.required_minutes,
                1
            ),
            2
        ) AS work_day
    FROM (
        SELECT
            absolute_times.id,
            GREATEST(
                TIMESTAMPDIFF(
                    MINUTE,
                    absolute_times.checkin_at,
                    absolute_times.checkout_at
                ),
                0
            ) AS worked_minutes,
            GREATEST(
                TIMESTAMPDIFF(
                    MINUTE,
                    absolute_times.scheduled_start,
                    absolute_times.checkin_at
                ),
                0
            ) AS late_minutes,
            GREATEST(
                TIMESTAMPDIFF(
                    MINUTE,
                    absolute_times.scheduled_end,
                    absolute_times.checkout_at
                ),
                0
            ) AS overtime_minutes,
            GREATEST(
                TIMESTAMPDIFF(
                    MINUTE,
                    absolute_times.scheduled_start,
                    absolute_times.scheduled_end
                ),
                1
            ) AS required_minutes
        FROM (
            SELECT
                a.id,
                a.checkin_at,
                a.checkout_at,
                TIMESTAMP(
                    a.work_date,
                    TIME(shift.start_at)
                ) AS scheduled_start,
                CASE
                    WHEN TIME(shift.end_at)
                        <= TIME(shift.start_at)
                    THEN DATE_ADD(
                        TIMESTAMP(
                            a.work_date,
                            TIME(shift.end_at)
                        ),
                        INTERVAL 1 DAY
                    )
                    ELSE TIMESTAMP(
                        a.work_date,
                        TIME(shift.end_at)
                    )
                END AS scheduled_end
            FROM attendances a
            JOIN shifts shift
                ON shift.id = a.shift_id
            WHERE a.checkin_at IS NOT NULL
              AND a.checkout_at IS NOT NULL
              AND (
                    a.status IN (
                        'missing_checkin',
                        'missing_checkout'
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM attendance_segments segment
                        WHERE segment.attendance_id = a.id
                          AND segment.checkin_at IS NULL
                          AND segment.checkout_at IS NOT NULL
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM backup_attendances_single_flow_20260806 backup
                        WHERE backup.id = a.id
                    )
              )
        ) absolute_times
    ) calculated
) new_values
    ON new_values.id = attendance_to_fix.id
SET
    attendance_to_fix.worked_minutes =
        new_values.worked_minutes,
    attendance_to_fix.late_minutes =
        new_values.late_minutes,
    attendance_to_fix.overtime_minutes =
        new_values.overtime_minutes,
    attendance_to_fix.overtime_hours =
        new_values.overtime_hours,
    attendance_to_fix.work_day =
        new_values.work_day,
    attendance_to_fix.status = 'completed',
    attendance_to_fix.updated_at = NOW();

-- ---------------------------------------------------------
-- F. Đồng bộ segment lịch sử từ attendance cha
--
-- Segment không còn được dùng trong luồng mới, nhưng sửa lại
-- để lịch sử dữ liệu không còn mâu thuẫn.
-- Không đụng các bản ghi thiếu checkin thật sự.
-- ---------------------------------------------------------

UPDATE attendance_segments segment
JOIN attendances attendance_parent
    ON attendance_parent.id = segment.attendance_id
JOIN shifts shift
    ON shift.id = attendance_parent.shift_id
SET
    segment.checkin_at =
        attendance_parent.checkin_at,
    segment.checkin_latitude =
        attendance_parent.checkin_latitude,
    segment.checkin_longitude =
        attendance_parent.checkin_longitude,
    segment.late_minutes =
        attendance_parent.late_minutes,
    segment.worked_minutes =
        GREATEST(
            TIMESTAMPDIFF(
                MINUTE,
                attendance_parent.checkin_at,
                segment.checkout_at
            ),
            0
        ),
    segment.early_leave_minutes =
        GREATEST(
            TIMESTAMPDIFF(
                MINUTE,
                segment.checkout_at,
                CASE
                    WHEN TIME(shift.end_at)
                        <= TIME(shift.start_at)
                    THEN DATE_ADD(
                        TIMESTAMP(
                            attendance_parent.work_date,
                            TIME(shift.end_at)
                        ),
                        INTERVAL 1 DAY
                    )
                    ELSE TIMESTAMP(
                        attendance_parent.work_date,
                        TIME(shift.end_at)
                    )
                END
            ),
            0
        ),
    segment.updated_at = NOW()
WHERE attendance_parent.checkin_at IS NOT NULL
  AND segment.checkin_at IS NULL
  AND segment.checkout_at IS NOT NULL;

COMMIT;

-- ---------------------------------------------------------
-- G. Kiểm tra sau sửa
-- ---------------------------------------------------------

SELECT
    COUNT(*) AS remaining_false_missing_checkin_segments
FROM attendances a
JOIN attendance_segments segment
    ON segment.attendance_id = a.id
WHERE a.checkin_at IS NOT NULL
  AND segment.checkin_at IS NULL
  AND segment.checkout_at IS NOT NULL;

SELECT
    COUNT(*) AS remaining_false_missing_statuses
FROM attendances
WHERE checkin_at IS NOT NULL
  AND checkout_at IS NOT NULL
  AND status IN (
      'missing_checkin',
      'missing_checkout'
  );
