-- StayFlow / Hotel Booking System V3 incremental update
-- Import this into an EXISTING hotel_booking_system_v3 database.
-- Public registration now creates customer accounts only; owner/admin roles remain admin-managed.

SET NAMES utf8mb4;
START TRANSACTION;

-- Keep the three account classes explicit and reset demo passwords to valid bcrypt hashes.
UPDATE users SET role='admin', status='active', password='$2y$12$QlEiz6qMu3ZKsU56Qf/A3.X3Q1ncKqlNqnV0rfNhBpSemHbd1gmUa' WHERE user_id=1;
UPDATE users SET role='owner', status='active', password='$2y$12$Uxo.wUxSHRS9aO560EzoZ.WBXzCUqJCY5naEbUN3a5.RIhPBpL4ge' WHERE user_id IN (2,3);
UPDATE users SET role='customer', status='active', password='$2y$12$Fsgv5zqBANqf9OUnAmiTJe35p2SgtQrVnj.H723Bdn68Y1PHFdbK2' WHERE user_id=4;

-- Additional demo customer accounts, inserted only if the username does not already exist.
INSERT INTO users (username,full_name,first_name,last_name,email,phone,password,profile_image,role,gender,date_of_birth,address,city,country,postal_code,status,email_verified)
SELECT 'guest_yangon','Su Su Aung','Su Su','Aung','guest.yangon@example.com','+959200000005','$2y$12$Fsgv5zqBANqf9OUnAmiTJe35p2SgtQrVnj.H723Bdn68Y1PHFdbK2','default.png','customer','Female','1998-03-10','Bahan Township','Yangon','Myanmar','11201','active',1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='guest_yangon');
INSERT INTO users (username,full_name,first_name,last_name,email,phone,password,profile_image,role,gender,date_of_birth,address,city,country,postal_code,status,email_verified)
SELECT 'guest_mandalay','Aung Min Khant','Aung Min','Khant','guest.mandalay@example.com','+959200000006','$2y$12$Fsgv5zqBANqf9OUnAmiTJe35p2SgtQrVnj.H723Bdn68Y1PHFdbK2','default.png','customer','Male','1997-11-22','Chanayethazan Township','Mandalay','Myanmar','05021','active',1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='guest_mandalay');

-- Normalize the old sample room that had an invalid empty enum bed_type.
UPDATE rooms SET bed_type='Queen Bed', room_description=COALESCE(room_description,'Premium queen room with a relaxing lounge corner.') WHERE hotel_id=1 AND room_name='Premium Deluxe';

-- Add room choices to every existing sample hotel without duplicating them on repeat imports.
INSERT INTO rooms (hotel_id,room_name,room_type,room_description,bed_type,room_size,room_size_unit,floor_no,total_rooms,max_adults,max_children,base_price,extra_bed_price,breakfast_included,free_cancellation,smoking_allowed,room_status)
SELECT 1,'Superior King Room','Double','Modern king room with work desk, Wi-Fi, and breakfast.','King Bed',34,'sqm',2,12,2,1,98000,20000,1,1,0,'available' WHERE EXISTS (SELECT 1 FROM hotels WHERE hotel_id=1) AND NOT EXISTS (SELECT 1 FROM rooms WHERE hotel_id=1 AND room_name='Superior King Room');
INSERT INTO rooms (hotel_id,room_name,room_type,room_description,bed_type,room_size,room_size_unit,floor_no,total_rooms,max_adults,max_children,base_price,extra_bed_price,breakfast_included,free_cancellation,smoking_allowed,room_status)
SELECT 1,'Family City Room','Family','Large family room with flexible bedding for parents and children.','Mixed',48,'sqm',2,6,3,2,135000,25000,1,1,0,'available' WHERE EXISTS (SELECT 1 FROM hotels WHERE hotel_id=1) AND NOT EXISTS (SELECT 1 FROM rooms WHERE hotel_id=1 AND room_name='Family City Room');
INSERT INTO rooms (hotel_id,room_name,room_type,room_description,bed_type,room_size,room_size_unit,floor_no,total_rooms,max_adults,max_children,base_price,extra_bed_price,breakfast_included,free_cancellation,smoking_allowed,room_status)
SELECT 2,'Palace View Twin','Twin','Twin-bed room with palace-side views and local design accents.','Twin Bed',38,'sqm',2,10,2,1,80000,18000,1,1,0,'available' WHERE EXISTS (SELECT 1 FROM hotels WHERE hotel_id=2) AND NOT EXISTS (SELECT 1 FROM rooms WHERE hotel_id=2 AND room_name='Palace View Twin');
INSERT INTO rooms (hotel_id,room_name,room_type,room_description,bed_type,room_size,room_size_unit,floor_no,total_rooms,max_adults,max_children,base_price,extra_bed_price,breakfast_included,free_cancellation,smoking_allowed,room_status)
SELECT 2,'Mandalay Executive King','Executive','Executive king room with seating area and business-friendly amenities.','King Bed',52,'sqm',4,6,2,1,125000,25000,1,1,0,'available' WHERE EXISTS (SELECT 1 FROM hotels WHERE hotel_id=2) AND NOT EXISTS (SELECT 1 FROM rooms WHERE hotel_id=2 AND room_name='Mandalay Executive King');
INSERT INTO rooms (hotel_id,room_name,room_type,room_description,bed_type,room_size,room_size_unit,floor_no,total_rooms,max_adults,max_children,base_price,extra_bed_price,breakfast_included,free_cancellation,smoking_allowed,room_status)
SELECT 2,'Royal Family Suite','Family','Family suite with separate sleeping and sitting areas.','Mixed',62,'sqm',3,4,4,2,165000,30000,1,1,0,'available' WHERE EXISTS (SELECT 1 FROM hotels WHERE hotel_id=2) AND NOT EXISTS (SELECT 1 FROM rooms WHERE hotel_id=2 AND room_name='Royal Family Suite');
INSERT INTO rooms (hotel_id,room_name,room_type,room_description,bed_type,room_size,room_size_unit,floor_no,total_rooms,max_adults,max_children,base_price,extra_bed_price,breakfast_included,free_cancellation,smoking_allowed,room_status)
SELECT 3,'Lake View Deluxe','Deluxe','Relaxing lake-view room inspired by Inle floating villages.','Queen Bed',42,'sqm',1,8,2,1,110000,20000,1,1,0,'available' WHERE EXISTS (SELECT 1 FROM hotels WHERE hotel_id=3) AND NOT EXISTS (SELECT 1 FROM rooms WHERE hotel_id=3 AND room_name='Lake View Deluxe');
INSERT INTO rooms (hotel_id,room_name,room_type,room_description,bed_type,room_size,room_size_unit,floor_no,total_rooms,max_adults,max_children,base_price,extra_bed_price,breakfast_included,free_cancellation,smoking_allowed,room_status)
SELECT 3,'Overwater King Suite','Suite','Premium suite with wide lake views and private relaxation space.','King Bed',58,'sqm',1,5,2,1,175000,30000,1,1,0,'available' WHERE EXISTS (SELECT 1 FROM hotels WHERE hotel_id=3) AND NOT EXISTS (SELECT 1 FROM rooms WHERE hotel_id=3 AND room_name='Overwater King Suite');
INSERT INTO rooms (hotel_id,room_name,room_type,room_description,bed_type,room_size,room_size_unit,floor_no,total_rooms,max_adults,max_children,base_price,extra_bed_price,breakfast_included,free_cancellation,smoking_allowed,room_status)
SELECT 3,'Inle Family Retreat','Family','Comfortable family room with extra space for children.','Mixed',50,'sqm',1,6,3,2,145000,25000,1,1,0,'available' WHERE EXISTS (SELECT 1 FROM hotels WHERE hotel_id=3) AND NOT EXISTS (SELECT 1 FROM rooms WHERE hotel_id=3 AND room_name='Inle Family Retreat');
INSERT INTO rooms (hotel_id,room_name,room_type,room_description,bed_type,room_size,room_size_unit,floor_no,total_rooms,max_adults,max_children,base_price,extra_bed_price,breakfast_included,free_cancellation,smoking_allowed,room_status)
SELECT 3,'Horizon Twin Room','Twin','Affordable twin room with resort amenities and free cancellation.','Twin Bed',36,'sqm',1,10,2,1,90000,18000,0,1,0,'available' WHERE EXISTS (SELECT 1 FROM hotels WHERE hotel_id=3) AND NOT EXISTS (SELECT 1 FROM rooms WHERE hotel_id=3 AND room_name='Horizon Twin Room');

COMMIT;
