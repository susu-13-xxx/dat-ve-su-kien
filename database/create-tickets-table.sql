-- Tao bang luu tung ve dien tu sau khi booking thanh toan thanh cong.
-- Chay file nay mot lan trong database articket.

CREATE TABLE IF NOT EXISTS tickets (
    id INT(11) NOT NULL AUTO_INCREMENT,
    booking_id INT(11) NOT NULL,
    ticket_code VARCHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'valid',
    issued_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tickets_ticket_code (ticket_code),
    KEY idx_tickets_booking_id (booking_id),
    CONSTRAINT fk_tickets_booking
        FOREIGN KEY (booking_id) REFERENCES bookings(id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
