<?php
/**
 * Add multiple vocabulary entries at once
 *
 * PHP version 8.0
 */

// Include configuration
require_once 'config.php';

// Get database connection
$db = getDbConnection();
require_once 'auth_integration.php';

// Initialize variables
$successCount = 0;
$errorCount = 0;
$errorMessages = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {


	// Get form data
	$multipleVocab = trim($_POST['multiple_vocab'] ?? '');
	$listId = intval($_POST['list_id_multiple'] ?? 1); // Default to list ID 1 if not specified

// Validate list ID
	$stmt = $db->prepare('SELECT COUNT(*) FROM vocabulary_lists WHERE id = ?');
	$stmt->execute([$listId]);
	if ($stmt->fetchColumn() == 0) {
		$listId = 1; // Use default list if specified list doesn't exist
	}

// Split input by lines
	$lines = preg_split('/\r\n|\r|\n/', $multipleVocab);

// Process each line
	foreach ($lines as $line) {
		if (empty(trim($line))) {
			continue;
		}

		// Split line by semicolon
		$parts = explode(';', $line);

		// Check if we have at least source and target words
		if (count($parts) >= 2) {
			// Entferne Anführungszeichen am Anfang und Ende von jedem Teil
			$wordSource = trim(trim($parts[0]), '"\'');
			$wordTarget = trim(trim($parts[1]), '"\'');
			$exampleSentence = isset($parts[2]) ? trim(trim($parts[2]), '"\'') : '';
			$importance = isset($parts[3]) ? intval(trim(trim($parts[3]), '"\'')) : 3;

			// $wordSource = trim($parts[0]);
			// $wordTarget = trim($parts[1]);
			// $exampleSentence = trim($parts[2] ?? '');
			// $importance = isset($parts[3]) ? intval($parts[3]) : 3;

			// Validate importance
			if ($importance < 1 || $importance > 5) {
				$importance = 3;
			}

			// Check if vocabulary already exists
			$stmt = $db->prepare('SELECT COUNT(*) FROM vocabulary WHERE word_source = ? AND word_target = ?');
			$stmt->execute([$wordSource, $wordTarget]);
			if ($stmt->fetchColumn() > 0) {
				$errorCount++;
				$errorMessages[] = "Vokabel '$wordSource - $wordTarget' existiert bereits.";
				continue;
			}

			// Insert into database
			try {
				$stmt = $db->prepare('
                INSERT INTO vocabulary (word_source, word_target, example_sentence, importance, list_id)
                VALUES (?, ?, ?, ?, ?)
            ');
				$stmt->execute([$wordSource, $wordTarget, $exampleSentence, $importance, $listId]);
				$successCount++;
			} catch (PDOException $e) {
				$errorCount++;
				$errorMessages[] = "Fehler bei '$wordSource - $wordTarget': " . $e->getMessage();
			}
		} else {
			$errorCount++;
			$errorMessages[] = "Ungültiges Format in Zeile: $line";
		}
	}

    // Set session messages
    if ($successCount > 0) {
        $_SESSION['successMessage'] = "$successCount Vokabeln erfolgreich hinzugefügt!";
    }

    if ($errorCount > 0) {
        $_SESSION['errorMessage'] = "$errorCount Fehler beim Hinzufügen von Vokabeln.<br>" . implode('<br>', $errorMessages);
    }

    // Redirect to list page
    header('Location: list.php');
    exit;
}

// If no POST data, redirect to add page
header('Location: add.php');
exit;
?>
