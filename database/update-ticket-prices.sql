-- Cap nhat gia ve moi cho 6 trien lam ArtTicket.
-- Chay file nay mot lan trong phpMyAdmin, chon database artticket.
-- Gia luu trong database la VND: standard_price = ve tieu chuan,
-- special_price = ve dac biet.

START TRANSACTION;

UPDATE events SET standard_price = 15000, special_price = 25000 WHERE event_code = 'vangogh';
UPDATE events SET standard_price = 10000, special_price = 20000 WHERE event_code = 'colors';
UPDATE events SET standard_price = 12000, special_price = 22000 WHERE event_code = 'lightspace';
UPDATE events SET standard_price = 13000, special_price = 23000 WHERE event_code = 'impressionism';
UPDATE events SET standard_price = 11000, special_price = 21000 WHERE event_code = 'dream';
UPDATE events SET standard_price = 14000, special_price = 24000 WHERE event_code = 'modern';

COMMIT;

-- Kiem tra ket qua:
SELECT event_code, name, standard_price, special_price
FROM events
WHERE event_code IN ('vangogh', 'colors', 'lightspace', 'impressionism', 'dream', 'modern')
ORDER BY FIELD(event_code, 'vangogh', 'colors', 'lightspace', 'impressionism', 'dream', 'modern');
