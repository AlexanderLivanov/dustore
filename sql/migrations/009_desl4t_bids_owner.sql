-- 009_desl4t_bids_owner.sql — от чьего имени заявка (id студии, если owner_type = 'studio').
-- Колонки не было в боевой схеме: upsert_bid.php писал в неё и падал с 1054.
-- Код теперь работает и без неё, но тогда у студийной заявки не сохраняется, КАКАЯ студия.
USE desl4t;
ALTER TABLE bids ADD COLUMN owner_id INT NULL AFTER owner_type;
-- Для старых заявок владелец = автор (для студийных id студии неизвестен — остаётся автор).
UPDATE bids SET owner_id = bidder_id WHERE owner_id IS NULL;
