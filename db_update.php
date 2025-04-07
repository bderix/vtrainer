<?php
/**
 * Database schema update script to add vocabulary lists
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
	// Check if the vocabulary_lists table already exists
	$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='vocabulary_lists'");
	$tableExists = (bool)$result->fetchColumn();

	if (!$tableExists) {
		// Create vocabulary_lists table
		$db->exec("
            CREATE TABLE vocabulary_lists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

		// Create default list
		$db->exec("
            INSERT INTO vocabulary_lists (name, description) 
            VALUES ('Standard', 'Standardliste für Vokabeln')
        ");

		// Check if list_id column exists in vocabulary table
		$result = $db->query("PRAGMA table_info(vocabulary)");
		$columns = $result->fetchAll(PDO::FETCH_ASSOC);
		$listIdExists = false;

		foreach ($columns as $column) {
			if ($column['name'] === 'list_id') {
				$listIdExists = true;
				break;
			}
		}

		// Add list_id column if it doesn't exist
		if (!$listIdExists) {
			// In SQLite, we need to create a new table and move the data
			$db->exec("
                -- Create new table with list_id column
                CREATE TABLE vocabulary_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    word_source TEXT NOT NULL,
                    word_target TEXT NOT NULL,
                    example_sentence TEXT,
                    importance INTEGER NOT NULL DEFAULT 3 CHECK (importance BETWEEN 1 AND 5),
                    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    list_id INTEGER DEFAULT 1,
                    FOREIGN KEY (list_id) REFERENCES vocabulary_lists(id) ON DELETE SET DEFAULT
                );
                
                -- Copy data from old table to new table
                INSERT INTO vocabulary_new (id, word_source, word_target, example_sentence, importance, date_added)
                SELECT id, word_source, word_target, example_sentence, importance, date_added FROM vocabulary;
                
                -- Drop old table
                DROP TABLE vocabulary;
                
                -- Rename new table to vocabulary
                ALTER TABLE vocabulary_new RENAME TO vocabulary;
                
                -- Recreate indexes
                CREATE INDEX idx_vocabulary_importance ON vocabulary(importance);
                CREATE INDEX idx_vocabulary_list_id ON vocabulary(list_id);
            ");
		}

		echo "Database schema updated successfully. Vocabulary lists have been added.";
	} else {
		echo "Vocabulary lists already exist in the database schema.";
	}

	// Commit transaction
	$db->commit();

} catch (PDOException $e) {
	// Rollback in case of error
	$db->rollBack();
	echo "Error updating database schema: " . $e->getMessage();
}
?>

<p><a href="index.php" class="btn btn-primary mt-3">Zurück zur Startseite</a></p>