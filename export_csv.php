<?php
/**
 * Export vocabulary as CSV file
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
global $app;

$vocabDB = $app->vocabDB;
$vtrequest = $app->request;

// Get list ID from GET parameter
$listId = $app->getListId();
if (!$listId) $vtrequest->redirect('index');

// Get importance levels if specified
$importance = isset($_GET['importance']) ? array_map('intval', (array)$_GET['importance']) : [];

// Get search term if specified
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get vocabulary based on filters
$vocab = $vocabDB->getVocabularyByList($listId, $importance, $searchTerm);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="vokabeln_export_' . date('Y-m-d') . '.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fputs($output, "\xEF\xBB\xBF");

// Loop through the data and format it as CSV
foreach ($vocab as $row) {
	// Format the line in the same format as "Mehrere Vokabeln hinzufügen":
	// Englisch;Deutsch;Beispielsatz;Wichtigkeit
	$line = [
		$row['word_source'],
		$row['word_target'],
		$row['example_sentence'],
		$row['importance']
	];

	// Write the line to the CSV
	fputcsv($output, $line, ';');
}

// Close the file pointer
fclose($output);
exit;
?>