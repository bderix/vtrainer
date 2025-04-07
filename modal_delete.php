<!-- Delete Modal Template -->
<div class="modal fade" id="deleteModalTemplate" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header bg-danger text-white">
				<h5 class="modal-title">
					<i class="bi bi-trash"></i> Vokabel l�schen
				</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p>M�chtest du die Vokabel <strong id="deleteVocabName"></strong> wirklich l�schen?</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
				<a href="#" id="deleteVocabButton" class="btn btn-danger">L�schen</a>
			</div>
		</div>
	</div>
</div>

<script>
	// Funktion zum �ffnen des L�sch-Modals
	function confirmDeleteVocab(id, source, target) {
		const modal = new bootstrap.Modal(document.getElementById('deleteModalTemplate'));
		document.getElementById('deleteVocabName').textContent = source + ' - ' + target;
		document.getElementById('deleteVocabButton').href = 'delete.php?id=' + id;
		modal.show();
	}
</script>