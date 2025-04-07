<?php
/**
 * Main dashboard page for the Vocabulary Trainer
 *
 * PHP version 8.0
 */

// Include configuration
require_once 'config.php';

// Get database connection
$db = getDbConnection();

// Get vocabulary count
$stmt = $db->query('SELECT COUNT(*) FROM vocabulary');
$vocabCount = $stmt->fetchColumn();

// Get quiz attempts count
$stmt = $db->query('SELECT COUNT(*) FROM quiz_attempts');
$quizCount = $stmt->fetchColumn();

// Get success rate
if ($quizCount > 0) {
	$stmt = $db->query('SELECT SUM(is_correct) FROM quiz_attempts');
	$correctCount = $stmt->fetchColumn();
	$successRate = round(($correctCount / $quizCount) * 100, 1);
} else {
	$successRate = 0;
}

// Include header
require_once 'header.php';
?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100 card-hover">
            <div class="card-body text-center">
                <h5 class="card-title"><i class="bi bi-book text-primary"></i> Vokabeln</h5>
                <p class="display-4"><?= $vocabCount ?></p>
                <a href="list.php" class="btn btn-outline-primary">Alle anzeigen</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100 card-hover">
            <div class="card-body text-center">
                <h5 class="card-title"><i class="bi bi-check2-circle text-success"></i> Abfragen</h5>
                <p class="display-4"><?= $quizCount ?></p>
                <a href="quiz.php" class="btn btn-outline-success">Üben</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100 card-hover">
            <div class="card-body text-center">
                <h5 class="card-title"><i class="bi bi-graph-up text-info"></i> Erfolgsrate</h5>
                <p class="display-4"><?= $successRate ?>%</p>
                <a href="statistics.php" class="btn btn-outline-info">Statistiken</a>
            </div>
        </div>
    </div>
</div>

<!-- Include vocabulary lists row -->
<div class="row mt-4">
    <div class="col-md-6 mb-4">
    <!-- Include recently added vocabulary section -->
	<?php include 'recently_added.php'; ?>
    </div>

    <div class="col-md-6 mb-4">
    <!-- Include recently practiced vocabulary section -->
	<?php include 'recently_practiced.php'; ?>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card card-hover">
            <div class="card-header bg-dark text-white">
                <h5 class="card-title mb-0"><i class="bi bi-lightning-charge"></i> Schnellzugriff</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 col-md-3 mb-3">
                        <a href="add.php" class="btn btn-lg btn-outline-primary w-100 py-3">
                            <i class="bi bi-plus-circle d-block mb-2" style="font-size: 2rem;"></i>
                            Vokabel hinzufügen
                        </a>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <a href="list.php" class="btn btn-lg btn-outline-secondary w-100 py-3">
                            <i class="bi bi-card-list d-block mb-2" style="font-size: 2rem;"></i>
                            Vokabelliste
                        </a>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <a href="quiz.php" class="btn btn-lg btn-outline-success w-100 py-3">
                            <i class="bi bi-question-circle d-block mb-2" style="font-size: 2rem;"></i>
                            Vokabelabfrage
                        </a>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <a href="statistics.php" class="btn btn-lg btn-outline-info w-100 py-3">
                            <i class="bi bi-bar-chart d-block mb-2" style="font-size: 2rem;"></i>
                            Statistiken
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include modals and footer
require_once 'modal_edit.php';
require_once 'modal_delete.php';
require_once 'footer.php';
?>

<script src="vocab_edit.js"></script>