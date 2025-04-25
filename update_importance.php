<?php
/**
 * Update vocabulary importance
 *
 * PHP version 8.0
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Include configuration
require_once 'config.php';

// Get database connection
$db = getDbConnection();
require_once 'auth_integration.php';

// Get parameters
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = $_GET['action'] ?? '';

// Validate parameters
if ($id <= 0) {
	$_SESSION['errorMessage'] = 'Ungültige Vokabel-ID.';
	header('Location: list.php');
	exit;
}

if (!in_array($action, ['increase', 'decrease'])) {
	$_SESSION['errorMessage'] = 'Ungültige Aktion.';
	header('Location: list.php');
	exit;
}

// Get current importance value
try {
	$stmt = $db->prepare('SELECT importance, word_source, word_target FROM vocabulary WHERE id = ?');
	$stmt->execute([$id]);
	$vocab = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$vocab) {
		$_SESSION['errorMessage'] = 'Vokabel nicht gefunden.';
		header('Location: list.php');
		exit;
	}

	$currentImportance = $vocab['importance'];

	// Calculate new importance value
	if ($action === 'increase' && $currentImportance < 5) {
		$newImportance = $currentImportance + 1;
	} elseif ($action === 'decrease' && $currentImportance > 1) {
		$newImportance = $currentImportance - 1;
	} else {
		// No change needed (already at min/max)
		$newImportance = $currentImportance;
	}

	// Update importance in database if changed
	if ($newImportance !== $currentImportance) {
		$stmt = $db->prepare('UPDATE vocabulary SET importance = ? WHERE id = ?');
		$stmt->execute([$newImportance, $id]);

		$_SESSION['successMessage'] = 'Wichtigkeit für "' . $vocab['word_source'] . ' - ' . $vocab['word_target'] .
			'" von ' . $currentImportance . ' auf ' . $newImportance . ' geändert.';
	}
} catch (PDOException $e) {
	$_SESSION['errorMessage'] = 'Fehler bei der Aktualisierung der Wichtigkeit: ' . $e->getMessage();
}

// Redirect back to the list with preserved filters
$redirectUrl = 'list.php';

// Preserve any filtering or sorting
$preserveParams = ['importance', 'search', 'sort', 'order'];
$queryParams = [];

foreach ($preserveParams as $param) {
	if (isset($_GET[$param])) {
		if (is_array($_GET[$param])) {
			foreach ($_GET[$param] as $value) {
				$queryParams[] = urlencode($param) . '[]=' . urlencode($value);
			}
		} else {
			$queryParams[] = urlencode($param) . '=' . urlencode($_GET[$param]);
		}
	}
}

if (!empty($queryParams)) {
	$redirectUrl .= '?' . implode('&', $queryParams);
}

header('Location: ' . $redirectUrl);
exit;
?>