<?php
/**
 * Copy vocabulary list
 *
 * This script handles copying a public vocabulary list to the user's own lists
 *
 * PHP version 8.0
 */

// Include configuration, database class and authentication
require_once 'config.php';
require_once 'VocabularyDatabase.php';


// Get database connection
$db = getDbConnection();
require_once 'auth_integration.php';

$vocabDB = new VocabularyDatabase($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
	redirectWithError('Du musst angemeldet sein, um diese Aktion auszuführen.', 'login.php');
}

// Initialize error message
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Get form data
	$sourceListId = intval($_POST['source_list_id'] ?? 0);
	$newName = trim($_POST['new_name'] ?? '');
	$isPrivate = isset($_POST['is_private']) ? true : false;

	// Validate input
	if ($sourceListId <= 0) {
		$errorMessage = 'Ungültige Quell-Listen-ID.';
	} else if (empty($newName)) {
		$errorMessage = 'Der Listenname darf nicht leer sein.';
	} else {
		// Get source list details to verify access rights
		$sourceList = $vocabDB->getListById($sourceListId, $_SESSION['user_id']);

		if (!$sourceList) {
			$errorMessage = 'Die Quellliste wurde nicht gefunden oder du hast keine Berechtigung darauf zuzugreifen.';
		} else {
			// Copy the list
			$newListId = $vocabDB->copyList($sourceListId, $_SESSION['user_id'], $newName, $isPrivate);

			if ($newListId) {
				// Success
				$_SESSION['successMessage'] = 'Liste "' . htmlspecialchars($newName) . '" wurde erfolgreich erstellt mit allen Vokabeln aus "' . htmlspecialchars($sourceList['name']) . '".';
				header('Location: list.php?list_id=' . $newListId);
				exit;
			} else {
				$errorMessage = 'Fehler beim Kopieren der Liste. Bitte versuche es später erneut.';
			}
		}
	}
}

// If there's an error or no form submission, redirect back to lists.php with error message
if (!empty($errorMessage)) {
	$_SESSION['errorMessage'] = $errorMessage;
}
header('Location: lists.php');
exit;
?>