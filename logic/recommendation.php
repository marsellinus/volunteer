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
 * @param int $userId - The user ID to generate recommendations for
 * @param mysqli $conn - Database connection object
 * @return mysqli_result|false - Result set of recommended activities
 */
function getVolunteerRecommendations($userId, $conn) {
    // 1. Get user's activity history (views and applications)
    $userHistory = getUserActivityHistory($userId, $conn);
    
    // 2. Get category preferences based on history
    $categoryPreferences = getCategoryPreferences($userHistory, $conn);
    
    // 3. Get location preferences based on history
    $locationPreferences = getLocationPreferences($userHistory, $conn);
    
    // 4. Build recommendation query based on preferences
    $recommendationQuery = buildRecommendationQuery($userId, $categoryPreferences, $locationPreferences, $conn);
    
    // 5. Execute query and return results
    return $conn->query($recommendationQuery);
}

/**
 * Get user's activity history including views and applications
 * 
 * @param int $userId - The user ID
 * @param mysqli $conn - Database connection object
 * @return array - Array containing user's viewed and applied activities
 */
function getUserActivityHistory($userId, $conn) {
    $history = [
        'views' => [],
        'applications' => [],
        'last_search' => ''
    ];
    
    // Get viewed activities
    $viewsQuery = "SELECT activity_id, COUNT(*) as view_count 
                  FROM activity_views 
                  WHERE user_id = ? 
                  GROUP BY activity_id 
                  ORDER BY view_count DESC";
                  
    $stmt = $conn->prepare($viewsQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $history['views'][$row['activity_id']] = $row['view_count'];
    }
    
    // Get applied activities
    $applicationsQuery = "SELECT activity_id 
                         FROM applications 
                         WHERE user_id = ?";
                         
    $stmt = $conn->prepare($applicationsQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $history['applications'][] = $row['activity_id'];
    }
    
    // Get last search query
    $searchQuery = "SELECT search_query 
                   FROM user_searches 
                   WHERE user_id = ? 
                   ORDER BY search_time DESC 
                   LIMIT 1";
                   
    $stmt = $conn->prepare($searchQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $history['last_search'] = $row['search_query'];
    }
    
    return $history;
}

/**
 * Calculate category preferences based on user history
 * 
 * @param array $userHistory - User's activity history
 * @param mysqli $conn - Database connection object
 * @return array - Array of categories with weight scores
 */
function getCategoryPreferences($userHistory, $conn) {
    $categoryScores = [];
    
    // Extract categories from viewed activities
    foreach ($userHistory['views'] as $activityId => $viewCount) {
        $query = "SELECT category FROM volunteer_activities WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $category = $row['category'];
            if (!isset($categoryScores[$category])) {
                $categoryScores[$category] = 0;
            }
            $categoryScores[$category] += $viewCount;
        }
    }
    
    // Extract categories from applied activities (higher weight)
    foreach ($userHistory['applications'] as $activityId) {
        $query = "SELECT category FROM volunteer_activities WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $category = $row['category'];
            if (!isset($categoryScores[$category])) {
                $categoryScores[$category] = 0;
            }
            $categoryScores[$category] += 5; // Applied activities get higher weight
        }
    }
    
    // Sort by score in descending order
    arsort($categoryScores);
    
    return $categoryScores;
}

/**
 * Calculate location preferences based on user history
 * 
 * @param array $userHistory - User's activity history
 * @param mysqli $conn - Database connection object
 * @return array - Array of locations with weight scores
 */
function getLocationPreferences($userHistory, $conn) {
    $locationScores = [];
    
    // Extract locations from viewed activities
    foreach ($userHistory['views'] as $activityId => $viewCount) {
        $query = "SELECT location FROM volunteer_activities WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $location = $row['location'];
            if (!isset($locationScores[$location])) {
                $locationScores[$location] = 0;
            }
            $locationScores[$location] += $viewCount;
        }
    }
    
    // Extract locations from applied activities (higher weight)
    foreach ($userHistory['applications'] as $activityId) {
        $query = "SELECT location FROM volunteer_activities WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $activityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $location = $row['location'];
            if (!isset($locationScores[$location])) {
                $locationScores[$location] = 0;
            }
            $locationScores[$location] += 3; // Applied activities get higher weight
        }
    }
    
    // Sort by score in descending order
    arsort($locationScores);
    
    return $locationScores;
}

/**
 * Build SQL query for recommendations based on user preferences
 * 
 * @param int $userId - The user ID
 * @param array $categoryPreferences - User's category preferences
 * @param array $locationPreferences - User's location preferences
 * @param mysqli $conn - Database connection object
 * @return string - SQL query for fetching recommendations
 */
function buildRecommendationQuery($userId, $categoryPreferences, $locationPreferences, $conn) {
    // Start with base query
    $query = "SELECT * FROM volunteer_activities WHERE application_deadline >= CURDATE() ";
    
    // Exclude activities the user has already applied to
    $query .= "AND id NOT IN (SELECT activity_id FROM applications WHERE user_id = $userId) ";
    
    // Add category preferences if available
    if (!empty($categoryPreferences)) {
        $topCategories = array_slice(array_keys($categoryPreferences), 0, 3); // Get top 3 categories
        if (!empty($topCategories)) {
            $escapedCategories = array_map(function($category) use ($conn) {
                return "'" . $conn->real_escape_string($category) . "'";
            }, $topCategories);
            
            $categoriesStr = implode(',', $escapedCategories);
            $query .= "ORDER BY CASE 
                      WHEN category IN ($categoriesStr) THEN 0 
                      ELSE 1 
                      END, ";
                      
            // Prioritize by specific category order
            foreach ($topCategories as $index => $category) {
                $escapedCategory = $conn->real_escape_string($category);
                $query .= "CASE WHEN category = '$escapedCategory' THEN $index ELSE 999 END, ";
            }
        }
    }
    
    // Add location preferences if no strong category preferences
    if (empty($categoryPreferences) && !empty($locationPreferences)) {
        $topLocations = array_slice(array_keys($locationPreferences), 0, 2); // Get top 2 locations
        if (!empty($topLocations)) {
            $escapedLocations = array_map(function($location) use ($conn) {
                return "'" . $conn->real_escape_string($location) . "'";
            }, $topLocations);
            
            $locationsStr = implode(',', $escapedLocations);
            $query .= "CASE 
                      WHEN location IN ($locationsStr) THEN 0 
                      ELSE 1 
                      END, ";
        }
    }
    
    // Final ordering criteria
    $query .= "event_date ASC LIMIT 6";
    
    return $query;
}

/**
 * Log activity view for recommendation system
 * 
 * @param int $userId - The user ID
 * @param int $activityId - The activity ID being viewed
 * @param mysqli $conn - Database connection object
 */
function logActivityView($userId, $activityId, $conn) {
    $stmt = $conn->prepare("INSERT INTO activity_views (user_id, activity_id, view_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $userId, $activityId);
    $stmt->execute();
}

/**
 * Log search query for recommendation system
 * 
 * @param int $userId - The user ID
 * @param string $searchQuery - The search query text
 * @param mysqli $conn - Database connection object
 */
function logSearchQuery($userId, $searchQuery, $conn) {
    $stmt = $conn->prepare("INSERT INTO user_searches (user_id, search_query, search_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $userId, $searchQuery);
    $stmt->execute();
}
