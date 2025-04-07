<?php
/**
 * Database schema update script to add language fields to vocabulary lists
 *
 * PHP version 8.0
 */

// Include configuration
require_once 'config.php';

// Get database connection
$db = getDbConnection();

// Start transaction
$db->beginTransaction();

try {
	// Check if source_language and target_language columns already exist
	$result = $db->query("PRAGMA table_info(vocabulary_lists)");
	$columns = $result->fetchAll(PDO::FETCH_ASSOC);

	$sourceLanguageExists = false;
	$targetLanguageExists = false;

	foreach ($columns as $column) {
		if ($column['name'] === 'source_language') {
			$sourceLanguageExists = true;
		}
		if ($column['name'] === 'target_language') {
			$targetLanguageExists = true;
		}
	}

	// Add source_language column if it doesn't exist
	if (!$sourceLanguageExists) {
		$db->exec("ALTER TABLE vocabulary_lists ADD COLUMN source_language TEXT DEFAULT 'Deutsch'");
	}

	// Add target_language column if it doesn't exist
	if (!$targetLanguageExists) {
		$db->exec("ALTER TABLE vocabulary_lists ADD COLUMN target_language TEXT DEFAULT 'Englisch'");
	}

	// Commit transaction
	$db->commit();

	echo "Database schema updated successfully. Language fields have been added to vocabulary lists.";

} catch (PDOException $e) {
	// Rollback in case of error
	$db->rollBack();
	echo "Error updating database schema: " . $e->getMessage();
}
?>

<p><a href="index.php" class="btn btn-primary mt-3">Zurück zur Startseite</a></p>