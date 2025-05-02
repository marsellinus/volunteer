-- Add certificate columns to applications table

ALTER TABLE applications 
ADD COLUMN certificate_generated TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN certificate_date DATE DEFAULT NULL AFTER certificate_generated;

-- Create directory for certificate assets
-- Note: This needs to be done manually in the file system
-- mkdir -p d:/try/pweb2/assets/certificate_templates

-- Add notifications table for users and owners

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    owner_id INT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'info', 'success', 'warning', 'danger'
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES owners(owner_id) ON DELETE CASCADE
);

-- Add an index for better performance
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX idx_notifications_owner ON notifications(owner_id, is_read);
