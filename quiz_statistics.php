<!-- Quiz statistics -->
<div class="card bg-light mb-4">
	<div class="card-body">
		<h6 class="card-title">
			<i class="bi bi-info-circle"></i> Statistik für diese Auswahl
		</h6>
		<div class="row g-2 text-center">
			<div class="col-md-3">
				<div class="border rounded p-2">
					<div class="small text-muted">Vokabeln</div>
					<div class="fw-bold" id="statsTotal"><?= $quizStats['total_count'] ?></div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="border rounded p-2">
					<div class="small text-muted">Abfragen</div>
					<div class="fw-bold" id="statsAttempts"><?= $quizStats['attempt_count'] ?></div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="border rounded p-2">
					<div class="small text-muted">Richtig</div>
					<div class="fw-bold" id="statsCorrect"><?= $quizStats['correct_count'] ?></div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="border rounded p-2">
					<div class="small text-muted">Erfolgsrate</div>
					<div class="fw-bold" id="statsSuccessRate"><?= $quizStats['success_rate'] ?>%</div>
				</div>
			</div>
		</div>
	</div>
</div>