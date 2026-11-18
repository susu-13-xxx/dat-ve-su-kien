-- Chay mot lan trong phpMyAdmin tren database ArtTicket.
-- Cot nay luu thoi diem email xac nhan da gui thanh cong.
ALTER TABLE bookings
ADD COLUMN email_sent_at DATETIME NULL DEFAULT NULL;
