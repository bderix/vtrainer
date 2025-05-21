<?php
/**
 * AJAX endpoint for quiz statistics
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
global $app;
$vocabDB = $app->vocabDB;
$vtrequest = $app->request;

require_once 'Helper.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
	echo json_encode(['success' => false, 'message' => 'Nur AJAX-Anfragen sind erlaubt.']);
	exit;
}

// Get parameters
$direction = $vtrequest->get('direction', 'source_to_target');
$importance = $vtrequest->get('importance', array(1,2,3));
$searchTerm = i$vtrequest->get('search', '');
$listId = $app->getListId();
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