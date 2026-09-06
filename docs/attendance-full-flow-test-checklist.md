# Checklist Test Full Flow Chấm Công

Ngày tạo: 06/06/2026

## Trạng Thái Kiểm Tra Tự Động

- [x] Route staff/manager vào được luồng chấm công: PASS
- [x] Migration đã chạy, `qr_tokens.branch_id` đã tồn tại: PASS
- [x] `attendances.shift_id` nullable: PASS
- [x] `attendances.overtime_hours` tồn tại, decimal default 0: PASS
- [x] Build frontend `npm run build`: PASS
- [x] PHP syntax check controller/model liên quan: PASS
- [x] Laravel view clear: PASS
- [x] Không còn `console.log`/`console.warn` trực tiếp trong camera flow: PASS
- [x] Laravel debug log chấm công chỉ ghi khi `APP_DEBUG=true`: PASS

## Checklist Test Thiết Bị Thật

- [ ] Staff quét QR bằng camera sau.
- [ ] Staff verify face bằng camera trước.
- [ ] Staff checkin lưu DB.
- [ ] Staff checkout lưu DB.
- [ ] Manager quét QR.
- [ ] Manager verify face.
- [ ] Manager checkin lưu DB.
- [ ] Manager checkout lưu DB.
- [ ] QR hết hạn bị chặn và yêu cầu quét lại.
- [ ] GPS ngoài vùng bị chặn theo `branch.gps_radius`, fallback 20m.
- [ ] Ca cố định: checkin dùng đúng `shift_id`, tính đi trễ theo ca.
- [ ] Ca cố định: checkout tính tăng ca theo thời gian sau giờ kết thúc ca.
- [ ] Ca gãy / ca tự do: không có `ShiftAssignment` vẫn checkin được.
- [ ] Ca gãy / ca tự do: checkout lưu `worked_minutes`.
- [ ] Ca gãy / ca tự do: toàn bộ `worked_minutes` được tính vào `overtime_minutes`.
- [ ] `overtime_hours = round(overtime_minutes / 60, 2)`.
- [ ] Ví dụ 15 phút tăng ca lưu `overtime_minutes = 15`, `overtime_hours = 0.25`.
- [ ] Thiết bị có nhiều camera: nút đổi camera chỉ toggle trước/sau, không lặp toàn bộ camera.
- [ ] Thiết bị có nhiều camera: QR ưu tiên camera sau, face ưu tiên camera trước.
- [ ] Thiết bị chỉ có 1 camera: QR vẫn fallback mở camera khả dụng.
- [ ] Thiết bị chỉ có 1 camera: face vẫn fallback mở camera khả dụng.

## Ghi Chú Test

- Tạo QR mới sau migration để token có `branch_id`.
- Test trên HTTPS hoặc localhost để browser cho phép camera/GPS.
- Khi `APP_DEBUG=false`, console camera không còn log debug tạm thời.
- Khi `APP_DEBUG=false`, Laravel không ghi các dòng `Attendance store ...` dạng debug.
