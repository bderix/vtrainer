<?php
/**
 * Save self-evaluated quiz answer (thumbs up/down)
 *
 * PHP version 8.0
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';

// Get database connection
$db = getDbConnection();

// Create database handler
$vocabDB = new VocabularyDatabase($db);

// Get parameters
$vocabId = isset($_GET['vocab_id']) ? intval($_GET['vocab_id']) : 0;
$direction = $_GET['direction'] ?? 'source_to_target';
$known = isset($_GET['known']) ? intval($_GET['known']) : 0;

// Validate parameters
if ($vocabId <= 0) {
	$_SESSION['errorMessage'] = 'Ungültige Vokabel-ID.';
	header('Location: quiz.php');
	exit;
}

// Get the correct answer for feedback
try {
	$vocab = $vocabDB->getVocabularyById($vocabId);

	if (!$vocab) {
		$_SESSION['errorMessage'] = 'Vokabel nicht gefunden.';
		header('Location: quiz.php');
		exit;
	}

	// Determine the correct answer based on direction
	$correctAnswer = ($direction === 'source_to_target') ? $vocab['word_target'] : $vocab['word_source'];
} catch (PDOException $e) {
	$_SESSION['errorMessage'] = 'Fehler beim Laden der Vokabel: ' . $e->getMessage();
	header('Location: quiz.php');
	exit;
}

// Save result to database
$isCorrect = ($known === 1) ? 1 : 0;
$saveSuccess = $vocabDB->saveQuizAttempt($vocabId, $direction, $isCorrect);

if (!$saveSuccess) {
	$_SESSION['errorMessage'] = 'Fehler beim Speichern des Ergebnisses.';
} else {
	// Store result in session for display
	$_SESSION['quiz_result'] = [
		'is_correct' => $isCorrect,
		'user_answer' => ($known === 1) ? 'Selbstbewertung: Gewusst' : 'Selbstbewertung: Nicht gewusst',
		'correct_answer' => $correctAnswer,
		'self_evaluated' => true
	];
}

// Redirect to next question (preserving filters)
$redirectUrl = 'quiz.php?mode=quiz&direction=' . urlencode($direction);

// Add importance filters if they exist in the session
if (isset($_SESSION['quiz_importance']) && is_array($_SESSION['quiz_importance'])) {
	foreach ($_SESSION['quiz_importance'] as $imp) {
		$redirectUrl .= '&importance[]=' . $imp;
	}
}

// Add search term if exists in session
if (isset($_SESSION['quiz_search']) && !empty($_SESSION['quiz_search'])) {
	$redirectUrl .= '&search=' . urlencode($_SESSION['quiz_search']);
}

// Add list_id if exists in session
if (isset($_SESSION['quiz_list_id']) && $_SESSION['quiz_list_id'] > 0) {
	$redirectUrl .= '&list_id=' . $_SESSION['quiz_list_id'];
}

if (isset($_SESSION['quiz_recent_limit']) && $_SESSION['quiz_recent_limit'] > 0) {
	$redirectUrl .= '&recent_limit=' . $_SESSION['quiz_recent_limit'];
}

header('Location: ' . $redirectUrl);
exit;