-- VolunteerHub Database Schema

-- Create database
CREATE DATABASE IF NOT EXISTS volunteer_db;
USE volunteer_db;

-- Users table (for volunteer seekers)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    bio TEXT,
    skills TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Owners table (for volunteer opportunity providers)
CREATE TABLE IF NOT EXISTS owners (
    owner_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    organization_name VARCHAR(255),
    organization_description TEXT,
    website VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Volunteer activities
CREATE TABLE IF NOT EXISTS volunteer_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    location VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    application_deadline DATE NOT NULL,
    required_skills TEXT,
    description TEXT NOT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES owners(owner_id) ON DELETE CASCADE
);

-- Applications for volunteer activities
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    message TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES volunteer_activities(id) ON DELETE CASCADE
);

-- Activity views for recommendation system
CREATE TABLE IF NOT EXISTS activity_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    view_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES volunteer_activities(id) ON DELETE CASCADE
);

-- User search history for recommendation system
CREATE TABLE IF NOT EXISTS user_searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_query TEXT NOT NULL,
    search_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Sample data for testing
INSERT INTO users (name, email, password, bio, skills) VALUES
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'I am passionate about helping others and making a difference.', 'Teaching, Communication, First Aid'),
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Environmental activist looking for conservation opportunities.', 'Gardening, Public Speaking, Organization');

INSERT INTO owners (name, email, password, organization_name, organization_description, website) VALUES
('Alex Johnson', 'alex@ngo.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EcoAwareness', 'Environmental conservation organization focused on local initiatives.', 'https://ecoawareness.org'),
('Maria Garcia', 'maria@example.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Community Helpers', 'Local community support group helping underprivileged neighborhoods.', 'https://communityhelpers.org');

-- Sample volunteer activities
INSERT INTO volunteer_activities (owner_id, title, category, location, event_date, application_deadline, required_skills, description, is_featured) VALUES
(1, 'Beach Cleanup Day', 'Environment', 'Miami Beach', DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'None required', 'Join us for a day of cleaning up the beach and protecting marine life. All cleaning supplies will be provided.', 1),
(1, 'Tree Planting Initiative', 'Environment', 'Central Park', DATE_ADD(CURDATE(), INTERVAL 45 DAY), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'Basic physical fitness', 'Help us plant 500 new trees in the city park to increase green spaces and fight climate change.', 0),
(2, 'Food Drive Volunteer', 'Community Service', 'Downtown Food Bank', DATE_ADD(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Organization, Teamwork', 'Volunteer to help collect, sort, and distribute food to families in need during our monthly food drive.', 1),
(2, 'After-School Tutoring', 'Education', 'Lincoln High School', DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Teaching, Patience, Subject knowledge', 'Provide tutoring assistance to high school students in math, science, and English after school hours.', 1);

-- Note: The password hash above corresponds to 'password' for easy testing
