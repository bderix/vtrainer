<?php
/**
 * Delete vocabulary file
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

// Initialize variables
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if ID is valid
if ($id <= 0) {
	$_SESSION['errorMessage'] = 'Ungültige Vokabel-ID.';
	header('Location: list.php');
	exit;
}

// Get vocabulary info for confirmation message
try {
	$stmt = $db->prepare('SELECT word_source, word_target FROM vocabulary WHERE id = ?');
	$stmt->execute([$id]);
	$vocab = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$vocab) {
		$_SESSION['errorMessage'] = 'Vokabel nicht gefunden.';
		header('Location: list.php');
		exit;
	}
} catch (PDOException $e) {
	$_SESSION['errorMessage'] = 'Fehler beim Laden der Vokabel: ' . $e->getMessage();
	header('Location: list.php');
	exit;
}

// Delete vocabulary
try {
	$stmt = $db->prepare('DELETE FROM vocabulary WHERE id = ?');
	$stmt->execute([$id]);

	$_SESSION['successMessage'] = 'Vokabel "' . $vocab['word_source'] . ' - ' . $vocab['word_target'] . '" erfolgreich gelöscht.';
} catch (PDOException $e) {
	$_SESSION['errorMessage'] = 'Fehler beim Löschen der Vokabel: ' . $e->getMessage();
}

// Redirect back to list
header('Location: list.php');
exit;
?>