
<!-- Einmaliges Lösch-Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Vokabel löschen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Möchtest du die Vokabel <strong id="modal-vocab-display"></strong> wirklich löschen?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <!-- Der href wird per JavaScript gesetzt -->
                <a href="#" id="confirmDeleteButton" class="btn btn-danger">Löschen</a>
            </div>
        </div>
    </div>
</div>


<script>
	// Sicherstellen, dass das DOM geladen ist (optional, wenn das Skript am Ende des Body steht)
	document.addEventListener('DOMContentLoaded', (event) => {

		var confirmDeleteModal = document.getElementById('confirmDeleteModal');
		if (confirmDeleteModal) {
			confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
				// Button, der das Modal ausgelöst hat
				var button = event.relatedTarget;

				// Extrahiere Informationen aus data-*-Attributen
				var vocabId = button.getAttribute('data-vocab-id');
				var vocabSource = button.getAttribute('data-vocab-source');

				// Modal-Inhalt aktualisieren
				var modalVocabDisplay = confirmDeleteModal.querySelector('#modal-vocab-display');
				var confirmDeleteButton = confirmDeleteModal.querySelector('#confirmDeleteButton');

				// Text im Modal-Body setzen (sicherstellen, dass HTML korrekt interpretiert wird)
				modalVocabDisplay.innerHTML = '<strong>' + vocabSource + '</strong>';

				// Den Link für den Löschen-Button im Modal setzen
				confirmDeleteButton.setAttribute('href', 'delete.php?id=' + vocabId);
			});
		} else {
			console.error("Modal with ID 'confirmDeleteModal' not found.");
		}
	});
</script>




<!-- Delete Modal Template -->
<div class="modal fade" id="deleteModalTemplate" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header bg-danger text-white">
				<h5 class="modal-title">
					<i class="bi bi-trash"></i> Vokabel löschen
				</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p>Möchtest du die Vokabel <strong id="deleteVocabName"></strong> wirklich löschen?</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
				<a href="#" id="deleteVocabButton" class="btn btn-danger">Löschen</a>
			</div>
		</div>
	</div>
</div>

<script>
	// Funktion zum Öffnen des Lösch-Modals
	function confirmDeleteVocab(id, source, target) {
		const modal = new bootstrap.Modal(document.getElementById('deleteModalTemplate'));
		document.getElementById('deleteVocabName').textContent = source + ' - ' + target;
		document.getElementById('deleteVocabButton').href = 'delete.php?id=' + id;
		modal.show();
	}
</script>