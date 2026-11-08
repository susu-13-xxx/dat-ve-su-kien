-- Chay mot lan trong phpMyAdmin, database articket.
-- Them quyen tai khoan: user (mac dinh) va admin.
ALTER TABLE users
ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user';
 
-- Sau do cap quyen admin cho tai khoan cua ban bang lenh:
-- Thay email@example.com bang email dang nhap tai khoan cua ban.
-- UPDATE users SET role = 'admin' WHERE email = 'email@example.com';
