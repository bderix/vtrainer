<?php
/**
 * AJAX handler for updating vocabulary importance
 *
 * PHP version 8.0
 */

// Include configuration
require_once 'config.php';
global $app;

$db = $app->db;
$vtrequest = $app->request;


// Set JSON content type
header('Content-Type: application/json');

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
	echo json_encode(['success' => false, 'message' => 'Nur AJAX-Anfragen sind erlaubt.']);
	exit;
}

// Get parameters
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = $_GET['action'] ?? '';

// Validate parameters
if ($id <= 0) {
	echo json_encode(['success' => false, 'message' => 'Ungültige Vokabel-ID.']);
	exit;
}

if (!in_array($action, ['increase', 'decrease'])) {
	echo json_encode(['success' => false, 'message' => 'Ungültige Aktion.']);
	exit;
}


// Get current importance value
try {
	$stmt = $db->prepare('SELECT importance FROM vocabulary WHERE id = ?');
	$stmt->execute([$id]);
	$vocab = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$vocab) {
		echo json_encode(['success' => false, 'message' => 'Vokabel nicht gefunden.']);
		exit;
	}

	$currentImportance = $vocab['importance'];

	// Calculate new importance value
	if ($action === 'increase' && $currentImportance < 3) {
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

		echo json_encode([
			'success' => true,
			'newImportance' => $newImportance,
			'message' => 'Wichtigkeit aktualisiert.'
		]);
	} else {
		echo json_encode([
			'success' => true,
			'newImportance' => $currentImportance,
			'message' => 'Keine Änderung notwendig.'
		]);
	}
} catch (PDOException $e) {
	echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
}
?>