<?php
/**
 * Vocabulary quiz page - displays quiz questions and processes answers
 *
 * PHP version 8.0
 */

// Include configuration and database class
require_once 'config.php';
require_once 'VocabularyDatabase.php';

xlog($_REQUEST);

// Get database connection
$db = getDbConnection();
require_once 'auth_integration.php';

// Create database handler
$vocabDB = new VocabularyDatabase($db);

// Get quiz parameters from GET or session
$direction = $_GET['direction'] ?? 'source_to_target';
$importance = isset($_GET['importance']) ? array_map('intval', (array)$_GET['importance']) : [1, 2, 3];
$filtered = isset($_GET['filtered']) && $_GET['filtered'] == 1;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$listId = isset($_GET['list_id']) ? intval($_GET['list_id']) : 0;
$vocabId = isset($_GET['vocab_id']) ? intval($_GET['vocab_id']) : 0;
$recentLimit = $vtrequest->getRecentLimit();

// Save quiz parameters in session for self-evaluation redirects
$_SESSION['quiz_importance'] = $importance;
$_SESSION['quiz_search'] = $searchTerm;
$_SESSION['quiz_filtered'] = $filtered;
$_SESSION['quiz_list_id'] = $listId;
$_SESSION['quiz_recent_limit'] = $recentLimit;


// Wenn eine spezifische Vokabel-ID angefordert wird, diese direkt laden
if ($vocabId > 0) {
	// Get specific vocabulary
	$vocabBatch = $vocabDB->getQuizVocabulary($direction, $importance, '', $vocabId, $listId);
	if (!empty($vocabBatch)) {
		$quizVocab = $vocabBatch[0];
	} else {
		$quizVocab = null;
	}
	// Lösche zwischengespeicherte Vokabeln, da eine bestimmte Vokabel angefordert wurde
	unset($_SESSION['quiz_vocab_batch']);
} else {
	// Prüfen, ob wir noch Vokabeln im Batch haben
	if (empty($_SESSION['quiz_vocab_batch']) || count($_SESSION['quiz_vocab_batch']) === 0) {
		// Keine Vokabeln im Batch - neue laden
		$vocabBatch = $vocabDB->getQuizVocabulary($direction, $importance, $searchTerm, 0, $listId, $recentLimit);
        xlog($vocabBatch);
		if (!empty($vocabBatch)) $_SESSION['quiz_vocab_batch'] = $vocabBatch;
	}

	// Wähle eine Vokabel aus dem Batch aus
	if (!empty($_SESSION['quiz_vocab_batch'])) {
		// Zufällige Auswahl aus dem Batch
		$randomIndex = array_rand($_SESSION['quiz_vocab_batch']);
		$quizVocab = $_SESSION['quiz_vocab_batch'][$randomIndex];
		// Entferne die verwendete Vokabel aus dem Batch
		unset($_SESSION['quiz_vocab_batch'][$randomIndex]);
		// Re-indexiere das Array
		$_SESSION['quiz_vocab_batch'] = array_values($_SESSION['quiz_vocab_batch']);
	} else {
		// Keine Vokabeln im Batch und keine neuen gefunden
		$quizVocab = null;
	}
}

// Falls keine passende Vokabel gefunden wurde
if (!$quizVocab) {
	$_SESSION['errorMessage'] = 'Keine passenden Vokabeln für die Abfrage gefunden.';
	header('Location: quiz_select.php');
	exit;
}

// If no vocabulary found, show message and redirect to selection
if (!$quizVocab) {
	$_SESSION['errorMessage'] = 'Keine passenden Vokabeln für die Abfrage gefunden.';
	header('Location: quiz_select.php');
	exit;
}




// Include header
require_once 'header.php';

require_once 'modal_edit.php';
?>
    <style>
        .reveal-btn {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        .reveal-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }
        .reveal-btn:active {
            transform: translateY(0px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
    <script src="vocab_edit.js"></script>

    <div class="row">
        <div class="col-md-8 offset-md-2">


			<?php
            xlog($_SESSION);
            if (isset($_SESSION['quiz_result'])):
                include('quiz_result.php');
			endif;
			?>

			<?php
			// Fügen Sie diesen Code in quiz.php ein, nach der Ergebnisanzeige und vor dem Formular für die neue Abfrage

			// Anzeige der Durchlauf-Statistik, falls vorhanden
			if (isset($_SESSION['quiz_session_stats_current'])):
				$stats = $_SESSION['quiz_session_stats_current'];
				// Formatiere die Dauer in Minuten und Sekunden
				$minutes = floor($stats['duration'] / 60);
				$seconds = $stats['duration'] % 60;
				$durationFormatted = $minutes > 0 ? "$minutes Min. $seconds Sek." : "$seconds Sek.";
				?>
                <div class="card mb-4 border-light">
                    <div class="card-header bg-light text-dark d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-bar-chart-line"></i> Statistik Aktueller Durchlauf
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetSessionStats">
                            <i class="bi bi-arrow-counterclockwise"></i> Zurücksetzen
                        </button>
                    </div>
                    <div class="card-body py-2">
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="small text-muted">Abgefragt</div>
                                <div class="fw-bold"><?= $stats['total'] ?></div>
                            </div>
                            <div class="col-3">
                                <div class="small text-muted">Richtig</div>
                                <div class="fw-bold text-success"><?= $stats['correct'] ?></div>
                            </div>
                            <div class="col-3">
                                <div class="small text-muted">Quote</div>
                                <div class="fw-bold <?= $stats['success_rate'] >= 70 ? 'text-success' : ($stats['success_rate'] >= 50 ? 'text-warning' : 'text-danger') ?>">
									<?= $stats['success_rate'] ?>%
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="small text-muted">Dauer</div>
                                <div class="fw-bold"><?= $durationFormatted ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
					// JavaScript zum Zurücksetzen der Durchlauf-Statistik
					document.getElementById('resetSessionStats').addEventListener('click', function() {
						if (confirm('Möchtest du die Statistik für den aktuellen Durchlauf zurücksetzen?')) {
							fetch('reset_session_stats.php', {
								method: 'POST',
								headers: {
									'X-Requested-With': 'XMLHttpRequest'
								}
							})
								.then(response => response.json())
								.then(data => {
									if (data.success) {
										// Statistikanzeige entfernen oder aktualisieren
										location.reload();
									}
								});
						}
					});
                </script>
			<?php endif; ?>

            <div class="card card-hover">

                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-question-circle"></i> Vokabelabfrage
                    </h5>
                </div>

                <div class="card-body">
                    <!-- Quiz question form -->
                    <form method="post" action="answer.php" id="quizForm">
                        <input type="hidden" name="vocab_id" value="<?= $quizVocab['id'] ?>">
                        <input type="hidden" name="mode" value="typed">
                        <input type="hidden" name="quiz_direction" value="<?= $direction ?>">

                        <div class="text-center mb-4">
                        <?php if (!empty($quizVocab['list_name'])): ?>
                            <span class="badge rounded-pill bg-info fs-6 px-3 py-2">
                                Liste: <?= htmlspecialchars($quizVocab['list_name']) ?>
                            </span>
						<?php endif; ?>

                        <span class="badge rounded-pill bg-<?= Helper::getImportanceBadgeColor($quizVocab['importance']) ?> fs-6 px-3 py-2">
                            Wichtigkeit: <?= $quizVocab['importance'] ?>
                        </span>

                        <?php if ($recentLimit > 0): ?>
                                <span class="badge rounded-pill bg-warning fs-6 px-3 py-2">
                                    <i class="bi bi-clock-history"></i> Nur die <?= $recentLimit ?> neuesten Vokabeln
                                </span>
                        <?php endif; ?>
                        </div>

                        <div class="card bg-light mb-4">
                            <div class="card-body p-4 text-center">
                                <h3 class="card-title mb-0">
									<?php if ($direction === 'source_to_target'): ?>
										<?= htmlspecialchars($quizVocab['word_source']) ?>
                                        <input type="hidden" name="correct_answer" value="<?= htmlspecialchars($quizVocab['word_target']) ?>">

										<?php if (!empty($quizVocab['example_sentence'])): ?>
                                            <div class="mt-2">
                                                <button type="button" id="showExampleBtn" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-eye-fill"></i> Notiz anzeigen
                                                </button>
                                                <div id="exampleSentenceArea" class="text-muted fst-italic mt-1 d-none">
                                                    <small class="fs-6 text-secondary fw-light"><?= nl2br(htmlspecialchars($quizVocab['example_sentence'])) ?></small>
                                                </div>
                                            </div>
										<?php endif; ?>
									<?php else: ?>
										<?= htmlspecialchars($quizVocab['word_target']) ?>
                                        <input type="hidden" name="correct_answer" value="<?= htmlspecialchars($quizVocab['word_source']) ?>">
									<?php endif; ?>
                                </h3>
                            </div>
                        </div>

                        <!-- Selbstbewertungsbuttons (Daumen hoch/runter) -->
                        <div id="selfEvaluationCard">
                                <div class="card-title text-center mb-3">Weißt du die Antwort?</div>


                                <div class="d-grid mb-3">
                                    <button type="button" id="revealAnswerBtn" class="btn btn-lg py-3 d-flex flex-column align-items-center justify-content-center text-white reveal-btn"
                                            style="background: linear-gradient(135deg, #17a2b8, #138496); border: none; border-radius: 12px; width: 100%; transition: all 0.3s ease;">
                                        <i class="bi bi-eye fs-2 mb-1"></i>
                                        <span style="font-weight: 600; letter-spacing: 0.5px;">Antwort aufdecken</span>
                                        <small class="mt-1 text-white-50">Klicken um die Lösung zu sehen</small>
                                    </button>
                                </div>

                                <div id="answerRevealArea" class="text-center my-3 p-3 border rounded bg-light d-none">
                                    <h6>Die richtige Antwort lautet:</h6>
                                    <h3 class="text-primary">
										<?= ($direction === 'source_to_target') ? htmlspecialchars($quizVocab['word_target']) : htmlspecialchars($quizVocab['word_source']) ?>
                                    </h3>
                                    <div class="mt-3">
                                        <a onclick="editVocabulary(<?= $quizVocab['id'] ?>)" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                </div>

                                <div id="selfEvaluationButtons" class="d-flex justify-content-center gap-4 d-none">
                                    <a href="answer.php?vocab_id=<?= $quizVocab['id'] ?>&direction=<?= $direction ?>&&mode=self_known"
                                       class="btn btn-lg btn-success px-4 py-3" id="knownBtn">
                                        <i class="bi bi-hand-thumbs-up-fill fs-2"></i>
                                        <div class="mt-2">Ja, gewusst</div>
                                    </a>
                                    <a href="answer.php?vocab_id=<?= $quizVocab['id'] ?>&direction=<?= $direction ?>&&mode=self_unknown"
                                       class="btn btn-lg btn-danger px-4 py-3" id="notKnownBtn">
                                        <i class="bi bi-hand-thumbs-down-fill fs-2"></i>
                                        <div class="mt-2">Nein, nicht gewusst</div>
                                    </a>
                                </div>
                        </div>

                        <div class="mb-4">
                            <label for="user_answer" class="form-label">Oder gib deine Antwort ein:</label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-lg" id="user_answer" name="user_answer" autocomplete="off">
                                <button type="submit" name="submit_answer" class="btn btn-primary" id="checkAnswerBtn">
                                    <i class="bi bi-check-lg"></i> Prüfen
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="quiz_select.php" class="btn btn-outline-secondary" id="settingsBtn">
                                <i class="bi bi-gear"></i> Einstellungen
                            </a>
                            <button type="button" id="skipButton" class="btn btn-outline-warning"
                                    data-bs-toggle="modal" data-bs-target="#skipModal">
                                <i class="bi bi-skip-forward"></i> Überspringen
                            </button>
                        </div>
                    </form>

                    <!-- Skip Modal -->
                    <div class="modal fade" id="skipModal" tabindex="-1" aria-labelledby="skipModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="skipModalLabel">Vokabel überspringen</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Möchtest du diese Vokabel überspringen und zur nächsten gehen?</p>
                                    <p class="fw-bold">
										<?php if ($direction === 'source_to_target'): ?>
											<?= htmlspecialchars($quizVocab['word_source']) ?> =
											<?= htmlspecialchars($quizVocab['word_target']) ?>
										<?php else: ?>
											<?= htmlspecialchars($quizVocab['word_target']) ?> =
											<?= htmlspecialchars($quizVocab['word_source']) ?>
										<?php endif; ?>
                                    </p>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                                    <a href="<?= 'quiz.php?direction=' . urlencode($direction) .
									(empty($importance) ? '' : '&importance[]=' . implode('&importance[]=', $importance)) .
									(!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') .
									($filtered ? '&filtered=1' : '') .
									($listId > 0 ? '&list_id=' . $listId : '') ?>"
                                       class="btn btn-warning" id="confirmSkipBtn">
                                        Überspringen
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include vocabulary lists row -->
    <div class="row mt-4">

        <div class="col-md-8 offset-md-2">
            <?php $recentlyPracticedEntries = 3; ?>
			<?php include 'recently_practiced.php'; ?>
        </div>
    </div>


    <script>
		// Auto-submit form when pressing Enter
		document.addEventListener('DOMContentLoaded', function() {
			const userAnswerInput = document.getElementById('user_answer');
			if (userAnswerInput) {
				userAnswerInput.addEventListener('keydown', function(event) {
					if (event.key === 'Enter') {
						event.preventDefault();
						document.getElementById('quizForm').submit();
					}
				});
			}

			// Aufdecken-Button-Funktionalität
			const revealAnswerBtn = document.getElementById('revealAnswerBtn');
			if (revealAnswerBtn) {
				revealAnswerBtn.addEventListener('click', function() {
					// Zeige die Antwort
					document.getElementById('answerRevealArea').classList.remove('d-none');
					// Zeige die Selbstevaluierungsbuttons
					document.getElementById('selfEvaluationButtons').classList.remove('d-none');
					// Deaktiviere den Aufdecken-Button
					this.disabled = true;
					this.classList.add('disabled');
					this.innerHTML = '<i class="bi bi-eye-fill fs-2"></i><div class="mt-2">Antwort aufgedeckt</div>';
				});
			}

			// Beispielsatz anzeigen-Button-Funktionalität
			const showExampleBtn = document.getElementById('showExampleBtn');
			if (showExampleBtn) {
				showExampleBtn.addEventListener('click', function() {
					// Zeige den Beispielsatz
					document.getElementById('exampleSentenceArea').classList.remove('d-none');
					// Ändere den Button-Text
					this.innerHTML = '<i class="bi bi-quote"></i> Notiz';
					// Deaktiviere den Button
					this.disabled = true;
					this.classList.add('btn-secondary');
					this.classList.remove('btn-outline-info');
				});
			}
		});


    </script>

<?php

// Include footer
require_once 'footer.php';
?>