<?php
/**
 * Central answer processing for vocabulary quizzes
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';
require_once 'Helper.php';

// Get database connection
$db = getDbConnection();
require_once 'auth_integration.php';

$vocabDB = new VocabularyDatabase($db);

xlog($_REQUEST);
// Get parameters
$vocabId = isset($_REQUEST['vocab_id']) ? intval($_REQUEST['vocab_id']) : 0;
$direction = $_REQUEST['direction'] ?? 'source_to_target';
$mode = $_REQUEST['mode'] ?? '';

// Get the correct answer for this vocabulary
$vocab = $vocabDB->getVocabularyById($vocabId);
if (!$vocab) {
	$_SESSION['errorMessage'] = 'Vokabel nicht gefunden.';
	header('Location: quiz.php');
	exit;
}

// Determine correct answer based on direction
$correctAnswer = ($direction === 'source_to_target') ? $vocab['word_target'] : $vocab['word_source'];


// Process answer based on mode
$isCorrect = 0;
$userAnswer = '';
$selfEvaluated = false;

switch ($mode) {
	case 'typed':
		// User typed an answer
		$userAnswer = trim($_REQUEST['user_answer'] ?? '');
		$userAnswerLower = strtolower($userAnswer);
		$correctAnswerVariants = array_map('trim', preg_split('/[,;]/', $correctAnswer));
		foreach ($correctAnswerVariants as $variant) {
			if (strtolower($variant) === $userAnswerLower) {
				$isCorrect = 1;
				break;
			}
		}
		break;

	case 'self_known':
		// User clicked "Ja, gewusst"
		$isCorrect = 1;
		$userAnswer = 'Selbstbewertung: Gewusst';
		$selfEvaluated = true;
		break;

	case 'self_unknown':
		// User clicked "Nein, nicht gewusst"
		$isCorrect = 0;
		$userAnswer = 'Selbstbewertung: Nicht gewusst';
		$selfEvaluated = true;
		break;

	default:
		$_SESSION['errorMessage'] = 'Ungültiger Antworttypus.';
		header('Location: quiz.php');
		exit;
}

// Save result to database
$saveSuccess = $vocabDB->saveQuizAttempt($vocabId, $direction, $isCorrect);

if (!$saveSuccess) {
	$_SESSION['errorMessage'] = 'Fehler beim Speichern des Ergebnisses.';
	header('Location: quiz.php');
	exit;
}


// Initialisiere oder aktualisiere Durchlauf-Statistik in der Session
if (!isset($_SESSION['quiz_session_stats'])) {
	$_SESSION['quiz_session_stats'] = [
		'total' => 0,
		'correct' => 0,
		'start_time' => time()
	];
}

// Aktualisiere die Zähler
$_SESSION['quiz_session_stats']['total']++;
if ($isCorrect) {
	$_SESSION['quiz_session_stats']['correct']++;
}

// Berechne die aktuelle Erfolgsrate
$sessionTotal = $_SESSION['quiz_session_stats']['total'];
$sessionCorrect = $_SESSION['quiz_session_stats']['correct'];
$sessionSuccessRate = $sessionTotal > 0 ? round(($sessionCorrect / $sessionTotal) * 100, 1) : 0;
$sessionDuration = time() - $_SESSION['quiz_session_stats']['start_time'];

// Speichere die aktuellen Statistiken zusammen mit dem Ergebnis
$_SESSION['quiz_session_stats_current'] = [
	'total' => $sessionTotal,
	'correct' => $sessionCorrect,
	'success_rate' => $sessionSuccessRate,
	'duration' => $sessionDuration
];


// Store quiz result in session for display
$_SESSION['quiz_result'] = [
	'is_correct' => $isCorrect,
	'user_answer' => $userAnswer,
	'correct_answer' => $correctAnswer,
	'self_evaluated' => $selfEvaluated
];

// Store vocabulary data for detailed display
$_SESSION['quiz_vocab'] = $vocab;

// Add vocabulary stats
$vocabStats = $vocabDB->getVocabularyQuizStats($vocabId);
$_SESSION['quiz_vocab']['stats'] = $vocabStats;

// Redirect to quiz with parameters
$redirectUrl = 'quiz.php?direction=' . urlencode($direction);

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

// Add recent_limit if exists in session
if (isset($_SESSION['quiz_recent_limit']) && $_SESSION['quiz_recent_limit'] > 0) {
	$redirectUrl .= '&recent_limit=' . $_SESSION['quiz_recent_limit'];
}

header('Location: ' . $redirectUrl);
exit;
?>