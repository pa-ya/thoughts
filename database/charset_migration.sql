-- Run this only if the database/tables already existed before the utf8mb4_0900_ai_ci update.
-- It preserves data while converting text storage/comparison settings for Persian, English, and emoji support.

ALTER DATABASE thoughtsdb CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

ALTER TABLE events CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE comments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
