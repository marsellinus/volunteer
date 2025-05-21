<?php
/**
 * Volunteer Activity Recommendation System
 * 
 * This file contains functions to generate personalized volunteer activity recommendations
 * based on user history, preferences, and behavior patterns.
 */

/**
 * Get volunteer activity recommendations for a specific user
 * 
 * @param int $user_id User ID
 * @param mysqli $conn Database connection
 * @return mysqli_result Recommendations result set
 */
function getVolunteerRecommendations($user_id, $conn) {
    // Get user's past applications
    $history_query = "SELECT va.category, va.location 
                      FROM applications a
                      JOIN volunteer_activities va ON a.activity_id = va.id
                      WHERE a.user_id = $user_id";
    
    $history_result = $conn->query($history_query);
    
    // Collect user's interests based on categories and locations they've applied to
    $categories = [];
    $locations = [];
    
    if ($history_result && $history_result->num_rows > 0) {
        while ($row = $history_result->fetch_assoc()) {
            $categories[] = $row['category'];
            $locations[] = $row['location'];
        }
    }
    
    // Check if user has search history - handle case when table doesn't exist
    $search_terms = [];
    try {
        // Check if search_history table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'search_history'");
        if ($table_check && $table_check->num_rows > 0) {
            $search_query = "SELECT search_term FROM search_history WHERE user_id = $user_id ORDER BY search_date DESC LIMIT 10";
            $search_result = $conn->query($search_query);
            
            if ($search_result && $search_result->num_rows > 0) {
                while ($row = $search_result->fetch_assoc()) {
                    $search_terms[] = $row['search_term'];
                }
            }
        } else {
            // Create search_history table if it doesn't exist
            createSearchHistoryTable($conn);
        }
    } catch (Exception $e) {
        // Silently handle the error - this isn't critical functionality
    }
    
    // Build recommendation query based on user history
    $base_query = "SELECT va.*, o.name as organization_name, 
                   (SELECT COUNT(*) FROM applications WHERE activity_id = va.id) as application_count";
    
    // Add scoring formula
    $score_formula = [];
    
    if (!empty($categories)) {
        $category_weights = array_count_values($categories);
        $category_conditions = [];
        
        foreach ($category_weights as $category => $weight) {
            $escaped_category = $conn->real_escape_string($category);
            $normalized_weight = min($weight * 10, 50); // Cap at 50 points
            $category_conditions[] = "CASE WHEN va.category = '$escaped_category' THEN $normalized_weight ELSE 0 END";
        }
        
        if (!empty($category_conditions)) {
            $score_formula[] = '(' . implode(' + ', $category_conditions) . ')';
        }
    }
    
    if (!empty($locations)) {
        $location_weights = array_count_values($locations);
        $location_conditions = [];
        
        foreach ($location_weights as $location => $weight) {
            $escaped_location = $conn->real_escape_string($location);
            $normalized_weight = min($weight * 8, 40); // Cap at 40 points
            $location_conditions[] = "CASE WHEN va.location = '$escaped_location' THEN $normalized_weight ELSE 0 END";
        }
        
        if (!empty($location_conditions)) {
            $score_formula[] = '(' . implode(' + ', $location_conditions) . ')';
        }
    }
    
    // Add search term matching
    if (!empty($search_terms)) {
        $search_conditions = [];
        $search_count = count($search_terms);
        
        foreach ($search_terms as $index => $term) {
            $escaped_term = $conn->real_escape_string($term);
            $recency_weight = max(5, 30 - ($index * 5)); // More recent searches get higher weight
            
            $search_conditions[] = "CASE 
                WHEN va.title LIKE '%$escaped_term%' THEN $recency_weight
                WHEN va.description LIKE '%$escaped_term%' THEN " . ($recency_weight / 2) . "
                WHEN va.category LIKE '%$escaped_term%' THEN " . ($recency_weight / 3) . "
                ELSE 0 
            END";
        }
        
        if (!empty($search_conditions)) {
            $score_formula[] = '(' . implode(' + ', $search_conditions) . ')';
        }
    }
    
    // Include featured activities bonus
    $score_formula[] = "(CASE WHEN va.is_featured = 1 THEN 20 ELSE 0 END)";
    
    // Include recency bonus (newer activities score higher)
    $score_formula[] = "(CASE 
        WHEN DATEDIFF(va.event_date, CURDATE()) BETWEEN 0 AND 7 THEN 15
        WHEN DATEDIFF(va.event_date, CURDATE()) BETWEEN 8 AND 14 THEN 10
        WHEN DATEDIFF(va.event_date, CURDATE()) BETWEEN 15 AND 30 THEN 5
        ELSE 0 END)";
    
    // Build final query with scoring
    $from_clause = "FROM volunteer_activities va 
                   JOIN owners o ON va.owner_id = o.owner_id";
    
    if (!empty($score_formula)) {
        $score_calculation = implode(' + ', $score_formula);
        $base_query .= ", ($score_calculation) as recommendation_score $from_clause";
        $where_clause = "WHERE va.application_deadline >= CURDATE() AND 
                         NOT EXISTS (SELECT 1 FROM applications WHERE user_id = $user_id AND activity_id = va.id)";
        $order_by = "ORDER BY recommendation_score DESC, va.event_date ASC";
        $limit = "LIMIT 6";
    } else {
        // Fallback to default sorting if no personalization data available
        $base_query .= " $from_clause";
        $where_clause = "WHERE va.application_deadline >= CURDATE()";
        $order_by = "ORDER BY va.is_featured DESC, va.event_date ASC";
        $limit = "LIMIT 6";
    }
    
    // Execute final recommendation query
    $recommendation_query = "$base_query $where_clause $order_by $limit";
    return $conn->query($recommendation_query);
}

/**
 * Log user search queries for better recommendations
 * 
 * @param int $user_id User ID
 * @param string $search_term Search term
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function logSearchQuery($user_id, $search_term, $conn) {
    // Check if search_history table exists, create it if not
    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'search_history'");
        if ($table_check->num_rows == 0) {
            createSearchHistoryTable($conn);
        }

        // Log the search query
        $stmt = $conn->prepare("INSERT INTO search_history (user_id, search_term) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $search_term);
        return $stmt->execute();
    } catch (Exception $e) {
        // Silently fail if there's an error - this isn't critical functionality
        return false;
    }
}

/**
 * Create search_history table if it doesn't exist
 *
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function createSearchHistoryTable($conn) {
    try {
        $create_table = "CREATE TABLE IF NOT EXISTS search_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            search_term VARCHAR(255) NOT NULL,
            search_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )";
        return $conn->query($create_table);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Log user views of volunteer activities for better recommendations
 * 
 * @param int $user_id User ID
 * @param int $activity_id Activity ID
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function logActivityView($user_id, $activity_id, $conn) {
    try {
        // Check if activity_views table exists, create it if not
        $table_check = $conn->query("SHOW TABLES LIKE 'activity_views'");
        if ($table_check->num_rows == 0) {
            $create_table = "CREATE TABLE activity_views (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                activity_id INT NOT NULL,
                view_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                FOREIGN KEY (activity_id) REFERENCES volunteer_activities(id) ON DELETE CASCADE,
                INDEX idx_user_activity (user_id, activity_id)
            )";
            $conn->query($create_table);
        }

        // Log the view (only once per day per user per activity)
        $stmt = $conn->prepare("
            INSERT INTO activity_views (user_id, activity_id) 
            SELECT ?, ?
            FROM dual
            WHERE NOT EXISTS (
                SELECT 1 FROM activity_views 
                WHERE user_id = ? AND activity_id = ? AND DATE(view_date) = CURDATE()
            )
        ");
        $stmt->bind_param("iiii", $user_id, $activity_id, $user_id, $activity_id);
        return $stmt->execute();
    } catch (Exception $e) {
        // Silently fail if there's an error - this isn't critical functionality
        return false;
    }
}
?>
