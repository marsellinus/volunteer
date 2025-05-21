<?php
/**
 * Notifications System
 * Helper functions for creating and managing notifications
 */

/**
 * Create a new notification for a user
 * 
 * @param int $user_id - The user ID to send notification to
 * @param string $title - The notification title
 * @param string $message - The notification message
 * @param string $type - Notification type (info, success, warning, danger)
 * @param string $link - Optional link to include in the notification
 * @param mysqli $conn - Database connection
 * @return bool - Whether notification was successfully created
 */
function createUserNotification($user_id, $title, $message, $type = 'info', $link = null, $conn) {
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $title, $message, $type, $link);
        return $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other database error
        return false;
    }
}

/**
 * Create a new notification for an owner
 * 
 * @param int $owner_id - The owner ID to send notification to
 * @param string $title - The notification title
 * @param string $message - The notification message
 * @param string $type - Notification type (info, success, warning, danger)
 * @param string $link - Optional link to include in the notification
 * @param mysqli $conn - Database connection
 * @return bool - Whether notification was successfully created
 */
function createOwnerNotification($owner_id, $title, $message, $type = 'info', $link = null, $conn) {
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (owner_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $owner_id, $title, $message, $type, $link);
        return $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other database error
        return false;
    }
}

/**
 * Get unread notifications count for a user
 * 
 * @param int $user_id - The user ID
 * @param mysqli $conn - Database connection
 * @return int - Count of unread notifications
 */
function getUserUnreadNotificationsCount($user_id, $conn) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other database error
        return 0;
    }
}

/**
 * Get unread notifications count for an owner
 * 
 * @param int $owner_id - The owner ID
 * @param mysqli $conn - Database connection
 * @return int - Count of unread notifications
 */
function getOwnerUnreadNotificationsCount($owner_id, $conn) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE owner_id = ? AND is_read = 0");
        $stmt->bind_param("i", $owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other database error
        return 0;
    }
}

/**
 * Get all notifications for a user
 * 
 * @param int $user_id - The user ID
 * @param mysqli $conn - Database connection
 * @param int $limit - Maximum number of notifications to return
 * @return array - Array of notifications
 */
function getUserNotifications($user_id, $conn, $limit = 10) {
    try {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other database error
        return [];
    }
}

/**
 * Get all notifications for an owner
 * 
 * @param int $owner_id - The owner ID
 * @param mysqli $conn - Database connection
 * @param int $limit - Maximum number of notifications to return
 * @return array - Array of notifications
 */
function getOwnerNotifications($owner_id, $conn, $limit = 10) {
    try {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE owner_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $owner_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other database error
        return [];
    }
}

/**
 * Mark a notification as read
 * 
 * @param int $notification_id - The notification ID
 * @param mysqli $conn - Database connection
 * @return bool - Whether notification was successfully marked as read
 */
function markNotificationAsRead($notification_id, $conn) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $notification_id);
        return $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other database error
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $user_id - The user ID
 * @param mysqli $conn - Database connection
 * @return bool - Whether notifications were successfully marked as read
 */
function markAllUserNotificationsAsRead($user_id, $conn) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other database error
        return false;
    }
}

/**
 * Mark all notifications as read for an owner
 * 
 * @param int $owner_id - The owner ID
 * @param mysqli $conn - Database connection
 * @return bool - Whether notifications were successfully marked as read
 */
function markAllOwnerNotificationsAsRead($owner_id, $conn) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE owner_id = ?");
        $stmt->bind_param("i", $owner_id);
        return $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other database error
        return false;
    }
}
?>
