<?php
/**
 * AJAX endpoint for quiz statistics
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';
require_once 'Helper.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
	echo json_encode(['success' => false, 'message' => 'Nur AJAX-Anfragen sind erlaubt.']);
	exit;
}

// Get database connection
$db = getDbConnection();
$vocabDB = new VocabularyDatabase($db);

// Get parameters
$direction = $_GET['direction'] ?? 'source_to_target';
$importance = isset($_GET['importance']) ? array_map('intval', (array)$_GET['importance']) : [1, 2, 3, 4, 5];
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$listId = isset($_GET['list_id']) ? intval($_GET['list_id']) : 0;
$recentLimit = $vtrequest->getRecentLimit();
// Get quiz statistics
$quizStats = $vocabDB->getQuizStats($direction, $importance, $searchTerm, $listId, $recentLimit);

// Return JSON response
echo json_encode([
	'success' => true,
	'stats' => $quizStats
]);
exit;
?>