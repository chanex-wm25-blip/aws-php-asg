CREATE DATABASE IF NOT EXISTS shuttle_bus_db;
USE shuttle_bus_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  id_number VARCHAR(20) NULL,
  faculty VARCHAR(150) NULL,
  date_of_birth DATE NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed admin account: admin@example.com / admin123
-- Change this password immediately in any real deployment.
INSERT INTO users (name, email, password_hash, is_admin) VALUES
('Admin', 'admin@example.com', '$2y$10$HI3gLmyD4OGmfNLAGUIL8.eBhhKu5nzL7wTDws.6mUNO9V44kyM5q', 1);

CREATE TABLE routes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  route_name VARCHAR(100) NOT NULL,
  origin VARCHAR(100) NOT NULL,
  destination VARCHAR(100) NOT NULL,
  departure_time VARCHAR(20) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  total_seats INT NOT NULL DEFAULT 40,
  image_url VARCHAR(500) NULL
);

INSERT INTO routes (route_name, origin, destination, departure_time, price, total_seats, image_url) VALUES
('Campus - City Centre Express', 'Main Campus', 'City Centre', '08:00', 3.00, 40, '/uploads/sample-city-express.jpg'),
('Campus - LRT Shuttle', 'Main Campus', 'LRT Station', '09:30', 2.00, 30, '/uploads/sample-lrt-shuttle.jpg'),
('Campus - Hostel Loop', 'Main Campus', 'Student Hostel', '17:30', 0.00, 25, '/uploads/sample-hostel-loop.jpg'),
('Campus - Mid Valley Shuttle', 'Main Campus', 'Mid Valley', '12:00', 3.50, 35, '/uploads/Midvalley.jpg'),
('Campus - Bukit Bintang Shuttle', 'Main Campus', 'Bukit Bintang', '13:00', 4.50, 40, '/uploads/Pavilion Bukit Bintang.jpg'),
('Campus - Setapak Shuttle', 'Main Campus', 'Setapak', '16:00', 2.50, 40, '/uploads/Setapak Central.jpg'),
('Campus - Sunway Shuttle', 'Main Campus', 'Sunway Velocity Mall', '14:30', 5.00, 40, '/uploads/Sunway Velocity Mall.jpg'),
('Campus - Sport Complex Shuttle', 'Main Campus', 'Sport Complex', '17:00', 2.00, 30, '/uploads/Sport Complex.jpg'),
('Campus - Bandar Utama Shuttle', 'Main Campus', '1U Bandar Utama', '18:00', 4.00, 40, '/uploads/Bandar Utama.jpg'),
('Campus - Pasar Seni Shuttle', 'Main Campus', 'Pasar Seni', '10:00', 3.00, 40, '/uploads/Pasar Seni Central Market.jpg'),
('Campus - KL Edition Doraemon Shuttle', 'Main Campus', 'City Centre', '15:30', 3.00, 40, '/uploads/doraemon bus.jpg'),
('Campus - Suria KLCC Shuttle','Main Campus', 'Suria KLCC', '20:00', 4.50, 40,'/uploads/Suria KLCC Mall.jpg');



CREATE TABLE tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  route_id INT NOT NULL,
  travel_date DATE NOT NULL,
  seat_quantity INT NOT NULL DEFAULT 1,
  seat_numbers VARCHAR(100) NULL,
  total_price DECIMAL(10,2) NOT NULL,
  status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (route_id) REFERENCES routes(id)
);

CREATE TABLE testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  route_id INT NOT NULL,
  comment TEXT NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (route_id) REFERENCES routes(id)
);

CREATE TABLE contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  sender ENUM('user', 'admin') NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_chat_messages_user_id (user_id)
);

-- PHP sessions are stored here instead of on local disk, so that any EC2
-- instance behind an ALB/ASG can read a session written by a different
-- instance. See auth.php's DbSessionHandler.
CREATE TABLE sessions (
  id VARCHAR(128) PRIMARY KEY,
  data MEDIUMTEXT NOT NULL,
  last_activity INT NOT NULL,
  INDEX idx_last_activity (last_activity)
);
