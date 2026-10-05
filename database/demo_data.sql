-- Wachtwoord voor beide demoaccounts: DrivePlan123!
INSERT INTO users(role_id,name,email,password_hash,contact_preference) VALUES
((SELECT id FROM roles WHERE name='student'),'Demo Leerling','leerling@driveplan.test','$2y$12$rx4wQL3EmwzlQ7MfF0LOW.DLGJA3RPjHmIyr5FoApMFB6pXRRAVFC','email'),
((SELECT id FROM roles WHERE name='instructor'),'D. Koster','instructeur@driveplan.test','$2y$12$rx4wQL3EmwzlQ7MfF0LOW.DLGJA3RPjHmIyr5FoApMFB6pXRRAVFC','email');
INSERT INTO lesson_credits(user_id,total_minutes,remaining_minutes) SELECT id,600,600 FROM users WHERE email='leerling@driveplan.test';
INSERT INTO availability(instructor_id,start_at,end_at,type) SELECT id,DATE_ADD(CURDATE(),INTERVAL 1 DAY)+INTERVAL 9 HOUR,DATE_ADD(CURDATE(),INTERVAL 1 DAY)+INTERVAL 17 HOUR,'available' FROM users WHERE email='instructeur@driveplan.test';
INSERT INTO availability(instructor_id,start_at,end_at,type) SELECT id,DATE_ADD(CURDATE(),INTERVAL 2 DAY)+INTERVAL 9 HOUR,DATE_ADD(CURDATE(),INTERVAL 2 DAY)+INTERVAL 17 HOUR,'available' FROM users WHERE email='instructeur@driveplan.test';
