-- Update Attendance Time Slots to the new schedule
-- 3-hour shifts: 5am-8am, 8am-11am, 11am-2pm, 2pm-5pm, 5pm-8pm, 8pm-11pm, 11pm-2am, 2am-5am
-- 4-hour shifts: 6am-10am, 10am-2pm, 2pm-6pm, 6pm-10pm, 10pm-2am, 2am-6am

-- Clear existing time slots
DELETE FROM attendance_time_slots;
ALTER TABLE attendance_time_slots AUTO_INCREMENT = 1;

-- Insert 3-hour time slots
INSERT INTO attendance_time_slots (name, duration_hours, start_time, end_time, is_active) VALUES
('5 AM - 8 AM', 3.0, '05:00:00', '08:00:00', 1),
('8 AM - 11 AM', 3.0, '08:00:00', '11:00:00', 1),
('11 AM - 2 PM', 3.0, '11:00:00', '14:00:00', 1),
('2 PM - 5 PM', 3.0, '14:00:00', '17:00:00', 1),
('5 PM - 8 PM', 3.0, '17:00:00', '20:00:00', 1),
('8 PM - 11 PM', 3.0, '20:00:00', '23:00:00', 1),
('11 PM - 2 AM', 3.0, '23:00:00', '02:00:00', 1),
('2 AM - 5 AM', 3.0, '02:00:00', '05:00:00', 1);

-- Insert 4-hour time slots
INSERT INTO attendance_time_slots (name, duration_hours, start_time, end_time, is_active) VALUES
('6 AM - 10 AM', 4.0, '06:00:00', '10:00:00', 1),
('10 AM - 2 PM', 4.0, '10:00:00', '14:00:00', 1),
('2 PM - 6 PM', 4.0, '14:00:00', '18:00:00', 1),
('6 PM - 10 PM', 4.0, '18:00:00', '22:00:00', 1),
('10 PM - 2 AM', 4.0, '22:00:00', '02:00:00', 1),
('2 AM - 6 AM', 4.0, '02:00:00', '06:00:00', 1);

-- Verify the time slots
SELECT * FROM attendance_time_slots ORDER BY duration_hours, start_time;
