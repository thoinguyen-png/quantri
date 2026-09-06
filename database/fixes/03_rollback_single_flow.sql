-- =========================================================
-- CHAMCONGV2 - ROLLBACK BẢN SỬA DỮ LIỆU SINGLE FLOW
--
-- Chỉ chạy khi cần quay lại dữ liệu trước bản sửa.
-- Các bảng backup phải còn tồn tại.
-- =========================================================

START TRANSACTION;

-- Khôi phục attendance đã tồn tại hoặc bị xóa nhầm.
INSERT INTO attendances (
    id,
    user_id,
    shift_id,
    work_date,
    checkin_at,
    checkout_at,
    checkin_latitude,
    checkin_longitude,
    checkout_latitude,
    checkout_longitude,
    late_minutes,
    overtime_minutes,
    overtime_hours,
    worked_minutes,
    work_day,
    status,
    face_verification_mode,
    is_locked,
    locked_at,
    locked_by,
    note,
    created_at,
    updated_at
)
SELECT
    id,
    user_id,
    shift_id,
    work_date,
    checkin_at,
    checkout_at,
    checkin_latitude,
    checkin_longitude,
    checkout_latitude,
    checkout_longitude,
    late_minutes,
    overtime_minutes,
    overtime_hours,
    worked_minutes,
    work_day,
    status,
    face_verification_mode,
    is_locked,
    locked_at,
    locked_by,
    note,
    created_at,
    updated_at
FROM backup_attendances_single_flow_20260806
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    shift_id = VALUES(shift_id),
    work_date = VALUES(work_date),
    checkin_at = VALUES(checkin_at),
    checkout_at = VALUES(checkout_at),
    checkin_latitude = VALUES(checkin_latitude),
    checkin_longitude = VALUES(checkin_longitude),
    checkout_latitude = VALUES(checkout_latitude),
    checkout_longitude = VALUES(checkout_longitude),
    late_minutes = VALUES(late_minutes),
    overtime_minutes = VALUES(overtime_minutes),
    overtime_hours = VALUES(overtime_hours),
    worked_minutes = VALUES(worked_minutes),
    work_day = VALUES(work_day),
    status = VALUES(status),
    face_verification_mode =
        VALUES(face_verification_mode),
    is_locked = VALUES(is_locked),
    locked_at = VALUES(locked_at),
    locked_by = VALUES(locked_by),
    note = VALUES(note),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at);

-- Xóa segment hiện tại của các attendance đã backup.
DELETE current_segment
FROM attendance_segments current_segment
JOIN backup_attendances_single_flow_20260806 backup
    ON backup.id = current_segment.attendance_id;

-- Khôi phục segment cũ.
INSERT INTO attendance_segments (
    id,
    attendance_id,
    segment_order,
    checkin_at,
    checkout_at,
    checkin_latitude,
    checkin_longitude,
    checkout_latitude,
    checkout_longitude,
    late_minutes,
    early_leave_minutes,
    worked_minutes,
    created_at,
    updated_at
)
SELECT
    id,
    attendance_id,
    segment_order,
    checkin_at,
    checkout_at,
    checkin_latitude,
    checkin_longitude,
    checkout_latitude,
    checkout_longitude,
    late_minutes,
    early_leave_minutes,
    worked_minutes,
    created_at,
    updated_at
FROM backup_attendance_segments_single_flow_20260806;

COMMIT;
