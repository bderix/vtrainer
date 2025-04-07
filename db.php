<?php
// --- Konfiguration ---
// Pfad zur SQLite-Datenbankdatei. Stellen Sie sicher, dass der Webserver Schreibrechte hat!
define('DB_FILE', './vocabulary.db');

// Anzahl der Zeilen pro Seite bei der Tabellenansicht
define('ROWS_PER_PAGE', 50);

// --- Initialisierung ---
session_start(); // Für Flash-Nachrichten
$pdo = null;
$error_message = '';
$success_message = '';
$tables = [];
$current_table = $_GET['table'] ?? null;
$action = $_REQUEST['action'] ?? 'list_tables';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * ROWS_PER_PAGE;

// Flash-Nachrichten abrufen und löschen
if (isset($_SESSION['success_message'])) {
	$success_message = $_SESSION['success_message'];
	unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
	$error_message = $_SESSION['error_message'];
	unset($_SESSION['error_message']);
}

// --- Datenbankverbindung ---
try {
	$pdo = new PDO('sqlite:' . DB_FILE);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
	$error_message = "Datenbankverbindung fehlgeschlagen: " . $e->getMessage();
	// Bei Verbindungsfehler weitere Aktionen verhindern
	$action = 'error';
}

// --- Hilfsfunktionen ---
function get_tables($pdo) {
	$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
	return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_table_columns($pdo, $table) {
	if (!$table) return [];
	try {
		// Verwende PRAGMA, um Spalteninformationen zu erhalten
		$stmt = $pdo->query("PRAGMA table_info(" . quote_identifier($table) . ")");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		// Fehler bei der Abfrage der Spalteninformationen
		$_SESSION['error_message'] = "Fehler beim Abrufen der Spalten für Tabelle '$table': " . $e->getMessage();
		return []; // Leeres Array zurückgeben, um weitere Fehler zu vermeiden
	}
}


function get_primary_key($pdo, $table) {
	$columns = get_table_columns($pdo, $table);
	foreach ($columns as $column) {
		if ($column['pk'] > 0) {
			return $column['name'];
		}
	}
	// Fallback auf rowid, wenn kein expliziter PK gefunden wird (typisch für SQLite)
	// Beachte: Tabellen mit 'WITHOUT ROWID' haben dies nicht.
	// Prüfen, ob rowid existiert (rudimentär)
	try {
		$pdo->query("SELECT rowid FROM " . quote_identifier($table) . " LIMIT 1");
		return 'rowid';
	} catch (PDOException $e) {
		return null; // Kein einfacher PK oder rowid gefunden/abfragbar
	}
}

// Funktion zum sicheren Zitieren von Bezeichnern (Tabellen-/Spaltennamen)
function quote_identifier($identifier) {
	// Erlaubt nur alphanumerische Zeichen und Unterstriche
	if (preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
		return '"' . str_replace('"', '""', $identifier) . '"';
	}
	// Bei ungültigen Zeichen einen Fehler auslösen oder null zurückgeben
	// Hier wird ein Fehler ausgelöst, um unsichere Operationen zu verhindern
	throw new InvalidArgumentException("Ungültiger Tabellen- oder Spaltenname: " . htmlspecialchars($identifier));
}


// --- Aktionen verarbeiten ---
if ($pdo && $action !== 'error') {
	try {
		$tables = get_tables($pdo); // Tabellenliste immer abrufen

		// Sicherstellen, dass die aktuelle Tabelle existiert (falls angegeben)
		if ($crrent_table && !in_array($current_table, $tables)) {
			$_SESSION['error_message'] = "Tabelle '$current_table' nicht gefunden.";
			header('Location: ' . $_SERVER['PHP_SELF']); // Redirect zur Hauptseite
			exit;
		}

        echo $action;

        if ($_POST['table']) $current_table = $_POST['table'];

		switch ($action) {
			case 'view_table':
				// Daten für die Anzeige abrufen (wird später im HTML-Teil erledigt)
				break;

			case 'edit_row_form':
			case 'insert_row_form':
				// Formularanzeige wird später im HTML-Teil erledigt
				break;

			case 'save_insert':
                echo $current_table;
                print_r($_POST);
				if ($current_table && isset($_POST['data']) && is_array($_POST['data'])) {
					$data = $_POST['data'];
					$columns = array_keys($data);
					$placeholders = array_map(function($col) { return ':' . $col; }, $columns);

					// Zitiere Spaltennamen sicher
					$quotedColumns = array_map('quote_identifier', $columns);

					$sql = "INSERT INTO " . quote_identifier($current_table) . " (" . implode(', ', $quotedColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
					// echo $sql; exit;

					$stmt = $pdo->prepare($sql);

					// Bereinige Daten (setze leere Strings auf NULL, falls gewünscht oder nötig)
					$params = [];
					foreach ($data as $key => $value) {
						$params[':' . $key] = ($value === '') ? null : $value;
					}

					if ($stmt->execute($params)) {
						$_SESSION['success_message'] = "Zeile erfolgreich eingefügt.";
					} else {
						$_SESSION['error_message'] = "Fehler beim Einfügen der Zeile.";
					}
				} else {
					$_SESSION['error_message'] = "Ungültige Anfrage zum Einfügen.";
				}
				header('Location: ' . $_SERVER['PHP_SELF'] . '?table=' . urlencode($current_table));
				exit;


			case 'save_edit':
				$pk_name = $_POST['pk_name'] ?? null;
				$pk_value = $_POST['pk_value'] ?? null;

				if ($current_table && $pk_name && $pk_value !== null && isset($_POST['data']) && is_array($_POST['data'])) {
					$data = $_POST['data'];
					$set_parts = [];
					$params = [];
					print_r($params);

					foreach ($data as $key => $value) {
						// Prüfen, ob der Spaltenname gültig ist (optional, aber sicherer)
						if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
							throw new InvalidArgumentException("Ungültiger Spaltenname im Update: " . htmlspecialchars($key));
						}
						$set_parts[] = quote_identifier($key) . " = :" . $key;
						// Leere Strings als NULL behandeln? Hier optional
						$params[':' . $key] = ($value === '') ? null : $value;
					}
					$params[':pk_value'] = $pk_value;

					// Primärschlüssel-Spalte sicher zitieren
					$quotedPkName = quote_identifier($pk_name);

					$sql = "UPDATE " . quote_identifier($current_table) . " SET " . implode(', ', $set_parts) . " WHERE " . $quotedPkName . " = :pk_value";
					$stmt = $pdo->prepare($sql);

					if ($stmt->execute($params)) {
						$_SESSION['success_message'] = "Zeile erfolgreich aktualisiert.";
					} else {
						$_SESSION['error_message'] = "Fehler beim Aktualisieren der Zeile.";
					}
				} else {
					$_SESSION['error_message'] = "Ungültige Anfrage zum Speichern.";
				}
				// Redirect zurück zur Tabellenansicht, aktuelle Seite beibehalten
				$redirect_page = $_POST['page'] ?? 1;
				header('Location: ' . $_SERVER['PHP_SELF'] . '?table=' . urlencode($current_table) . '&page=' . $redirect_page);
				exit;


			case 'delete_row':
				$pk_name = $_GET['pk_name'] ?? null;
				$pk_value = $_GET['pk_value'] ?? null;

				if ($current_table && $pk_name && $pk_value !== null) {
					// Primärschlüssel-Spalte sicher zitieren
					$quotedPkName = quote_identifier($pk_name);
					$sql = "DELETE FROM " . quote_identifier($current_table) . " WHERE " . $quotedPkName . " = :pk_value";
					$stmt = $pdo->prepare($sql);

					if ($stmt->execute([':pk_value' => $pk_value])) {
						$_SESSION['success_message'] = "Zeile erfolgreich gelöscht.";
					} else {
						$_SESSION['error_message'] = "Fehler beim Löschen der Zeile.";
					}
				} else {
					$_SESSION['error_message'] = "Ungültige Anfrage zum Löschen.";
				}
				// Redirect zurück zur Tabellenansicht, aktuelle Seite beibehalten
				$redirect_page = $_GET['page'] ?? 1;
				header('Location: ' . $_SERVER['PHP_SELF'] . '?table=' . urlencode($current_table) . '&page=' . $redirect_page);
				exit;

			case 'exec_sql':
				$sql_query = $_POST['sql_query'] ?? '';
				$result_data = null;
				$result_columns = null;
				$affected_rows = null;

				if (!empty($sql_query)) {
					// Einfache Unterscheidung zwischen SELECT und anderen Befehlen (rudimentär!)
					if (stripos(trim($sql_query), 'SELECT') === 0) {
						try {
							$stmt = $pdo->query($sql_query);
							$result_columns = [];
							if ($stmt->columnCount() > 0) {
								for ($i = 0; $i < $stmt->columnCount(); $i++) {
									$meta = $stmt->getColumnMeta($i);
									$result_columns[] = $meta['name'];
								}
								$result_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
								$success_message = "SELECT-Abfrage erfolgreich ausgeführt. " . count($result_data) . " Zeilen gefunden.";
							} else {
								// Manchmal gibt SELECT 0 Spalten zurück (z.B. `SELECT count(*)...`)
								// Versuchen, das Ergebnis anders zu holen
								$result_data = $stmt->fetchAll(PDO::FETCH_NUM);
								if (!empty($result_data) && isset($result_data[0][0])) {
									$success_message = "SELECT-Abfrage erfolgreich ausgeführt. Ergebnis: " . htmlspecialchars($result_data[0][0]);
									$result_data = null; // Nicht als Tabelle anzeigen
								} else {
									$success_message = "SELECT-Abfrage ausgeführt, aber keine Spalten oder Daten zurückgegeben.";
								}
							}
						} catch (PDOException $e) {
							$error_message = "Fehler bei SQL-Abfrage: " . $e->getMessage();
						}
					} else {
						// Für INSERT, UPDATE, DELETE, CREATE, etc.
						try {
							$affected_rows = $pdo->exec($sql_query);
							$success_message = "SQL-Befehl erfolgreich ausgeführt. Betroffene Zeilen: " . ($affected_rows !== false ? $affected_rows : 'Unbekannt');
							// Tabellenliste neu laden, falls Schema geändert wurde
							$tables = get_tables($pdo);
						} catch (PDOException $e) {
							$error_message = "Fehler bei SQL-Befehl: " . $e->getMessage();
						}
					}
				} else {
					$error_message = "Keine SQL-Abfrage angegeben.";
				}
				// Damit die Ergebnisse angezeigt werden, setzen wir die Action zurück
				$action = 'show_sql_result';
				break;

		}
	} catch (PDOException $e) {
		// Allgemeine Datenbankfehler abfangen
		$error_message = "Datenbankfehler: " . $e->getMessage();
		$action = 'error'; // Verhindert weitere DB-Operationen im HTML
	} catch (InvalidArgumentException $e) {
		// Fehler bei ungültigen Bezeichnern abfangen
		$error_message = "Sicherheitsfehler: " . $e->getMessage();
		$action = 'error';
	}

}

?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>SQLite Editor</title>
	<style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; background-color: #f4f4f4; color: #333; }
        h1, h2, h3 { color: #555; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        nav ul { list-style: none; padding: 0; margin: 0 0 20px 0; display: flex; flex-wrap: wrap; gap: 10px; }
        nav ul li a { display: block; padding: 8px 15px; background: #e0e0e0; color: #333; text-decoration: none; border-radius: 4px; transition: background-color 0.3s ease; }
        nav ul li a:hover, nav ul li a.active { background: #007bff; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .actions a, .actions button { margin-right: 5px; color: #007bff; text-decoration: none; background: none; border: none; cursor: pointer; padding: 0; font-size: inherit; }
        .actions a.delete, .actions button.delete { color: #dc3545; }
        .actions a:hover, .actions button:hover { text-decoration: underline; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        form label { display: block; margin-bottom: 5px; font-weight: bold; }
        form input[type="text"], form input[type="number"], form input[type="date"], form textarea { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        form textarea { min-height: 100px; }
        form button { padding: 10px 20px; background-color: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        form button:hover { background-color: #0056b3; }
        form .form-group { margin-bottom: 15px; }
        .pagination { margin-top: 20px; }
        .pagination a, .pagination span { margin-right: 5px; padding: 5px 10px; border: 1px solid #ccc; text-decoration: none; color: #007bff; }
        .pagination span { background-color: #f0f0f0; color: #666; }
        .pagination a:hover { background-color: #eee; }
        .sql-exec-result table { margin-top: 10px; }
	</style>
</head>
<body>
<div class="container">
	<h1>SQLite Editor</h1>
	<p>Datenbankdatei: <strong><?php echo htmlspecialchars(DB_FILE); ?></strong></p>

	<div class="warning">
		<strong>Sicherheitshinweis:</strong> Dieser Editor ist nur für lokale Entwicklung oder interne Zwecke gedacht. Nicht auf öffentlichen Servern ohne zusätzliche Absicherung verwenden!
	</div>

	<?php if ($success_message): ?>
		<div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
	<?php endif; ?>
	<?php if ($error_message): ?>
		<div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
	<?php endif; ?>

	<?php if ($pdo && $action !== 'error'): ?>
		<nav>
			<h3>Tabellen:</h3>
			<ul>
				<?php foreach ($tables as $table_item): ?>
					<li>
						<a href="?table=<?php echo urlencode($table_item); ?>&action=view_table"
						   class="<?php echo ($table_item === $current_table) ? 'active' : ''; ?>">
							<?php echo htmlspecialchars($table_item); ?>
						</a>
					</li>
				<?php endforeach; ?>
				<li><a href="?action=exec_sql_form" class="<?php echo ($action === 'exec_sql_form' || $action === 'show_sql_result') ? 'active' : ''; ?>">SQL ausführen</a></li>
			</ul>
		</nav>

		<hr>

		<?php if ($current_table && ($action === 'view_table' || $action === 'edit_row_form' || $action === 'insert_row_form')): ?>
			<h2>Tabelle: <?php echo htmlspecialchars($current_table); ?></h2>

			<?php
			$columns_info = get_table_columns($pdo, $current_table);
			$pk_name = get_primary_key($pdo, $current_table);
			if (!$pk_name && $action !== 'insert_row_form') {
				echo "<p class='message error'>Warnung: Konnte keinen eindeutigen Primärschlüssel oder 'rowid' für Tabelle '" . htmlspecialchars($current_table) . "' finden. Bearbeiten und Löschen sind möglicherweise nicht zuverlässig.</p>";
				// Setze einen Fallback, um Fehler zu vermeiden, aber Bearbeiten/Löschen wird nicht funktionieren
				$pk_name = 'rowid'; // Annahme, kann aber fehlschlagen
			}
			?>

			<?php // --- Formular zum Bearbeiten ---
			if ($action === 'edit_row_form' && isset($_GET['pk_value']) && $pk_name):
				$pk_value_to_edit = $_GET['pk_value'];
				// Zitiere PK sicher
				$quotedPkName = quote_identifier($pk_name);
				$stmt = $pdo->prepare("SELECT * FROM " . quote_identifier($current_table) . " WHERE " . $quotedPkName . " = ?");
				$stmt->execute([$pk_value_to_edit]);
				$row_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

				if ($row_to_edit): ?>
					<h3>Zeile bearbeiten (PK: <?php echo htmlspecialchars($pk_name) . ' = ' . htmlspecialchars($pk_value_to_edit); ?>)</h3>
					<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
						<input type="hidden" name="action" value="save_edit">
						<input type="hidden" name="table" value="<?php echo htmlspecialchars($current_table); ?>">
						<input type="hidden" name="pk_name" value="<?php echo htmlspecialchars($pk_name); ?>">
						<input type="hidden" name="pk_value" value="<?php echo htmlspecialchars($pk_value_to_edit); ?>">
						<input type="hidden" name="page" value="<?php echo $page; ?>"> <!-- Seite für Redirect merken -->

						<?php foreach ($columns_info as $column):
							$col_name = $column['name'];
							// PK nicht bearbeitbar machen, wenn es nicht die 'rowid' ist oder wenn es autoincrement sein könnte
							// Einfache Annahme: Wenn PK und Integer, dann nicht bearbeiten
							$is_pk_readonly = ($col_name === $pk_name && $pk_name !== 'rowid');
							// Alternativ: detailliertere Prüfung auf AUTOINCREMENT, falls verfügbar
							// $is_pk_readonly = ($column['pk'] > 0 && stripos($column['type'], 'INTEGER') !== false);
							?>
							<div class="form-group">
								<label for="data_<?php echo htmlspecialchars($col_name); ?>">
									<?php echo htmlspecialchars($col_name); ?>
									(<?php echo htmlspecialchars($column['type']); ?>)
									<?php echo ($column['pk'] > 0) ? ' <strong>[PK]</strong>' : ''; ?>
									<?php echo ($column['notnull'] == 0 && $column['pk'] == 0) ? '' : ' [NOT NULL]'; ?>
								</label>
								<?php if ($is_pk_readonly): ?>
									<input type="text" id="data_<?php echo htmlspecialchars($col_name); ?>"
										   value="<?php echo htmlspecialchars($row_to_edit[$col_name]); ?>" readonly disabled>
									<!-- PK-Wert trotzdem mitsenden, falls er Teil der 'data' sein muss (normalerweise nicht nötig) -->
									<!-- <input type="hidden" name="data[<?php echo htmlspecialchars($col_name); ?>]" value="<?php echo htmlspecialchars($row_to_edit[$col_name]); ?>"> -->
								<?php else: ?>
									<textarea id="data_<?php echo htmlspecialchars($col_name); ?>"
											  name="data[<?php echo htmlspecialchars($col_name); ?>]"
											  rows="2"><?php echo htmlspecialchars($row_to_edit[$col_name] ?? ''); ?></textarea>
									<!-- Verwende Textarea für mehr Platz, auch für Zahlen/Daten -->
									<!--
                                         <input type="text" id="data_<?php echo htmlspecialchars($col_name); ?>"
                                               name="data[<?php echo htmlspecialchars($col_name); ?>]"
                                               value="<?php echo htmlspecialchars($row_to_edit[$col_name]); ?>">
                                         -->
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
						<button type="submit">Änderungen speichern</button>
						<a href="?table=<?php echo urlencode($current_table); ?>&action=view_table&page=<?php echo $page; ?>">Abbrechen</a>
					</form>
				<?php else: ?>
					<p class="message error">Zeile nicht gefunden.</p>
				<?php endif; ?>
			<?php endif; // Ende Edit Form ?>


			<?php // --- Formular zum Einfügen ---
			if ($action === 'insert_row_form'): ?>
				<h3>Neue Zeile einfügen</h3>
				<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
					<input type="hidden" name="action" value="save_insert">
					<input type="hidden" name="table" value="<?php echo htmlspecialchars($current_table); ?>">

					<?php foreach ($columns_info as $column):
						$col_name = $column['name'];
						// Annahme: Integer-PKs sind oft AUTOINCREMENT, diese nicht anzeigen/eingeben lassen
						$is_autoincrement_pk = ($column['pk'] > 0 && stripos($column['type'], 'INTEGER') !== false);
						if ($is_autoincrement_pk) continue; // Überspringe Auto-Inkrement PK
						?>
						<div class="form-group">
							<label for="data_<?php echo htmlspecialchars($col_name); ?>">
								<?php echo htmlspecialchars($col_name); ?>
								(<?php echo htmlspecialchars($column['type']); ?>)
								<?php echo ($column['pk'] > 0) ? ' <strong>[PK]</strong>' : ''; ?>
								<?php echo ($column['notnull'] == 0 && $column['pk'] == 0) ? '' : ' [NOT NULL]'; ?>
							</label>
							<textarea id="data_<?php echo htmlspecialchars($col_name); ?>"
									  name="data[<?php echo htmlspecialchars($col_name); ?>]"
									  rows="2"
									  placeholder="<?php echo htmlspecialchars($column['dflt_value'] ?? ''); ?>"></textarea>
							<!--
                                  <input type="text" id="data_<?php echo htmlspecialchars($col_name); ?>"
                                        name="data[<?php echo htmlspecialchars($col_name); ?>]"
                                        placeholder="<?php echo htmlspecialchars($column['dflt_value'] ?? ''); ?>">
                                   -->
						</div>
					<?php endforeach; ?>
					<button type="submit">Neue Zeile speichern</button>
					<a href="?table=<?php echo urlencode($current_table); ?>&action=view_table&page=<?php echo $page; ?>">Abbrechen</a>
				</form>
			<?php endif; // Ende Insert Form ?>


			<?php // --- Tabellenansicht ---
			if ($action === 'view_table'):
				// Gesamtzahl der Zeilen für Paginierung ermitteln
				try {
					$count_stmt = $pdo->query("SELECT COUNT(*) FROM " . quote_identifier($current_table));
					$total_rows = $count_stmt->fetchColumn();
					$total_pages = ceil($total_rows / ROWS_PER_PAGE);
				} catch (PDOException $e) {
					echo "<p class='message error'>Fehler beim Zählen der Zeilen: " . htmlspecialchars($e->getMessage()) . "</p>";
					$total_rows = 0;
					$total_pages = 1;
				}


				// Daten für die aktuelle Seite abrufen
				$data = [];
				if ($total_rows > 0) {
					try {
						$query = "SELECT * FROM " . quote_identifier($current_table) . " LIMIT :limit OFFSET :offset";
						$stmt = $pdo->prepare($query);
						$stmt->bindValue(':limit', ROWS_PER_PAGE, PDO::PARAM_INT);
						$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
						$stmt->execute();
						$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
					} catch (PDOException $e) {
						echo "<p class='message error'>Fehler beim Abrufen der Daten: " . htmlspecialchars($e->getMessage()) . "</p>";
					}
				}


				if (!empty($columns_info)): ?>
					<p>
						<a href="?table=<?php echo urlencode($current_table); ?>&action=insert_row_form&page=<?php echo $page; ?>">+ Neue Zeile einfügen</a>
					</p>
					<p>Zeige Zeilen <?php echo $offset + 1; ?> bis <?php echo min($offset + ROWS_PER_PAGE, $total_rows); ?> von <?php echo $total_rows; ?></p>

					<?php if (!empty($data)): ?>
						<table>
							<thead>
							<tr>
								<?php foreach ($columns_info as $column): ?>
									<th>
										<?php echo htmlspecialchars($column['name']); ?>
										<?php echo ($column['pk'] > 0) ? ' <strong>[PK]</strong>' : ''; ?>
									</th>
								<?php endforeach; ?>
								<?php if ($pk_name): // Nur Aktionen anzeigen, wenn PK bekannt ist ?>
									<th>Aktionen</th>
								<?php endif; ?>
							</tr>
							</thead>
							<tbody>
							<?php foreach ($data as $row): ?>
								<tr>
									<?php foreach ($columns_info as $column):
										$col_name = $column['name'];
										$value = $row[$col_name];
										?>
										<td>
											<?php
											if ($value === null) {
												echo '<em>NULL</em>';
											} else {
												// Gekürzte Anzeige für lange Texte
												echo nl2br(htmlspecialchars(mb_substr($value, 0, 100) . (mb_strlen($value) > 100 ? '...' : '')));
											}
											?>
										</td>
									<?php endforeach; ?>

									<?php if ($pk_name && isset($row[$pk_name])):
										$pk_value = $row[$pk_name]; ?>
										<td class="actions">
											<a href="?table=<?php echo urlencode($current_table); ?>&action=edit_row_form&pk_name=<?php echo urlencode($pk_name); ?>&pk_value=<?php echo urlencode($pk_value); ?>&page=<?php echo $page; ?>">Bearbeiten</a>
											<a href="?table=<?php echo urlencode($current_table); ?>&action=delete_row&pk_name=<?php echo urlencode($pk_name); ?>&pk_value=<?php echo urlencode($pk_value); ?>&page=<?php echo $page; ?>"
											   class="delete"
											   onclick="return confirm('Sind Sie sicher, dass Sie diese Zeile (PK: <?php echo htmlspecialchars($pk_name) . '=' . htmlspecialchars($pk_value); ?>) löschen möchten?');">Löschen</a>
										</td>
									<?php elseif ($pk_name): ?>
										<td><em>Aktion nicht verfügbar (PK '<?php echo htmlspecialchars($pk_name); ?>' nicht im Ergebnis?)</em></td>
									<?php endif; ?>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>

						<!-- Paginierung -->
						<?php if ($total_pages > 1): ?>
							<div class="pagination">
								<?php if ($page > 1): ?>
									<a href="?table=<?php echo urlencode($current_table); ?>&action=view_table&page=<?php echo $page - 1; ?>">« Zurück</a>
								<?php endif; ?>

								<?php for ($i = 1; $i <= $total_pages; $i++): ?>
									<?php if ($i == $page): ?>
										<span><?php echo $i; ?></span>
									<?php else: ?>
										<a href="?table=<?php echo urlencode($current_table); ?>&action=view_table&page=<?php echo $i; ?>"><?php echo $i; ?></a>
									<?php endif; ?>
								<?php endfor; ?>

								<?php if ($page < $total_pages): ?>
									<a href="?table=<?php echo urlencode($current_table); ?>&action=view_table&page=<?php echo $page + 1; ?>">Weiter »</a>
								<?php endif; ?>
							</div>
						<?php endif; ?>

					<?php else: ?>
						<p>Die Tabelle '<?php echo htmlspecialchars($current_table); ?>' ist leer.</p>
					<?php endif; ?>
				<?php elseif($current_table): ?>
					<p class="message error">Konnte Spalteninformationen für Tabelle '<?php echo htmlspecialchars($current_table); ?>' nicht laden.</p>
				<?php endif; // Ende if columns_info valid ?>
			<?php endif; // Ende View Table ?>

		<?php endif; // Ende if $current_table ?>


		<?php // --- SQL Ausführungsformular ---
		if ($action === 'exec_sql_form' || $action === 'show_sql_result'): ?>
			<h2>SQL ausführen</h2>
			<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				<input type="hidden" name="action" value="exec_sql">
				<div class="form-group">
					<label for="sql_query">SQL-Befehl(e):</label>
					<textarea id="sql_query" name="sql_query" rows="5" required><?php echo isset($sql_query) ? htmlspecialchars($sql_query) : ''; ?></textarea>
					<small>Achtung: Befehle werden direkt ausgeführt. Seien Sie vorsichtig, besonders mit `DROP`, `DELETE`, `UPDATE`!</small>
				</div>
				<button type="submit">Ausführen</button>
			</form>

			<?php // --- Anzeige der SQL-Ergebnisse ---
			if ($action === 'show_sql_result'): ?>
				<div class="sql-exec-result">
					<h3>Ergebnis der SQL-Ausführung</h3>
					<?php if (isset($result_data) && $result_data !== null && !empty($result_columns)): ?>
						<p>Ergebnis für: <code><?php echo htmlspecialchars($sql_query); ?></code></p>
						<table>
							<thead>
							<tr>
								<?php foreach ($result_columns as $col): ?>
									<th><?php echo htmlspecialchars($col); ?></th>
								<?php endforeach; ?>
							</tr>
							</thead>
							<tbody>
							<?php foreach ($result_data as $row): ?>
								<tr>
									<?php foreach ($result_columns as $col): ?>
										<td><?php echo htmlspecialchars($row[$col] ?? 'NULL'); ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php elseif (isset($affected_rows) && $affected_rows !== null): ?>
						<p>Befehl ausgeführt: <code><?php echo htmlspecialchars($sql_query); ?></code></p>
						<p>Betroffene Zeilen: <?php echo $affected_rows; ?></p>
					<?php elseif (!empty($success_message) && !(isset($result_data) && $result_data !== null)): ?>
						<!-- Zeige Erfolgsmeldung, wenn keine Daten zurückkamen aber erfolgreich war -->
						<p><?php echo htmlspecialchars($success_message); ?></p>
						<p>Für Abfrage: <code><?php echo htmlspecialchars($sql_query); ?></code></p>
					<?php elseif (empty($error_message)): ?>
						<p>Die Abfrage lieferte keine Ergebnisse oder betroffenen Zeilen.</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; // Ende SQL Form/Result ?>


	<?php elseif ($action === 'error'): ?>
		<p>Ein Fehler ist aufgetreten. Bitte überprüfen Sie die Konfiguration und die Datenbankdatei.</p>
	<?php else: ?>
		<p>Keine Datenbankverbindung. Überprüfen Sie den Pfad in `DB_FILE` und die Berechtigungen.</p>
	<?php endif; // Ende if $pdo ?>

</div>
</body>
</html>

lten nun eine Oberfläche sehen, die die Tabellen Ihrer Datenbank auflistet (falls vorhanden) und Ihnen erlaubt, durch die Daten zu navigieren, sie zu bearbeiten, zu löschen und neue Zeilen hinzuzufügen sowie SQL auszuführen.