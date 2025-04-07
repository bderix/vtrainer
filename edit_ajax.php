<?php
/**
 * AJAX handler for editing vocabulary
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if it's an AJAX request
// if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
// 	echo json_encode(['success' => false, 'message' => 'Nur AJAX-Anfragen sind erlaubt.']);
	// exit;
// }

// Get database connection
$db = getDbConnection();
$vocabDB = new VocabularyDatabase($db);

// Handle GET request to fetch vocabulary data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
	$id = intval($_GET['id']);

	if ($id <= 0) {
		echo json_encode(['success' => false, 'message' => 'Ungültige Vokabel-ID.']);
		exit;
	}

	$vocab = $vocabDB->getVocabularyById($id);
	$listId = $vocab['list_id'];
	xlog($vocab);
	xlog($_SESSION);

	if (!$vocab) {
		echo json_encode(['success' => false, 'message' => 'Vokabel nicht gefunden.']);
		exit;
	}

	// Get all vocabulary lists
	$lists = $vocabDB->getAllLists();
	foreach ($lists as $list) {
		if ($list['id'] == $listId) {
			$currentList = $list;
			break;
		}
	}
	xlog($list);

	$vocab['source_language'] = $currentList['source_language'];
	$vocab['target_language'] = $currentList['target_language'];

	// Include lists in the response
	$vocab['lists'] = $lists;

	xlog($vocab);
	echo json_encode(['success' => true, 'vocab' => $vocab]);
	exit;
}

// Handle POST request to update vocabulary
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Decode JSON input
	$input = json_decode(file_get_contents('php://input'), true);

	if (!$input || !isset($input['id']) || !isset($input['word_source']) || !isset($input['word_target'])) {
		echo json_encode(['success' => false, 'message' => 'Fehlende Eingabedaten.']);
		exit;
	}

	$id = intval($input['id']);
	$wordSource = trim($input['word_source']);
	$wordTarget = trim($input['word_target']);
	$exampleSentence = trim($input['example_sentence'] ?? '');
	$importance = intval($input['importance'] ?? 3);

	$listId = intval($input['list_id'] ?? 1);

	// Validate input
	$errors = [];

	if (empty($wordSource)) {
		$errors[] = 'Bitte gib das Englisch ein.';
	}

	if (empty($wordTarget)) {
		$errors[] = 'Bitte gib das Deutsch ein.';
	}

	if ($importance < 1 || $importance > 5) {
		$errors[] = 'Die Wichtigkeit muss zwischen 1 und 5 liegen.';
	}

	// Check if vocabulary with same words already exists (excluding current one)
	if ($vocabDB->vocabExistsExcept($wordSource, $wordTarget, $id)) {
		$errors[] = 'Eine Vokabel mit diesen Wörtern existiert bereits.';
	}

	// Verify list exists
	$list = $vocabDB->getListById($listId);

	if (!$list) {
		$listId = 1; // Fallback to default list
		 $errors[] = 'Liste nicht vorhanden';
	}

	if (!empty($errors)) {
		echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
		exit;
	}

	// Update vocabulary
	if ($vocabDB->updateVocabulary($id, $wordSource, $wordTarget, $exampleSentence, $importance, $listId)) {
		// Get updated vocabulary
		$updatedVocab = $vocabDB->getVocabularyById($id);

		echo json_encode([
			'success' => true,
			'message' => 'Vokabel erfolgreich aktualisiert.',
			'vocab' => $updatedVocab
		]);
	} else {
		echo json_encode(['success' => false, 'message' => 'Fehler beim Aktualisieren der Vokabel.']);
	}

	exit;
}

// If we get here, it's an invalid request
echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage.']);
exit;
?>