<?php
/**
 * Login page
 *
 * PHP version 8.0
 */

// Include configuration and classes
require_once 'config.php';
require_once 'UserAuthentication.php';

// Get database connection
$db = getDbConnection();
$auth = new UserAuthentication($db);

// Initialize variables
$email = '';
$errorMessage = '';
$successMessage = '';

// Check if user is already logged in
if ($auth->isLoggedIn()) {
	// Redirect to index page
	header('Location: index.php');
	exit;
}

xlog($_REQUEST);
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Get form data
	$email = trim($_POST['email'] ?? '');
	$password = $_POST['password'] ?? '';
	$remember = isset($_POST['remember']) && $_POST['remember'] === 'on';

	// Attempt to login
	if ($auth->login($email, $password, $remember)) {
		// Redirect to index page or intended destination
		$redirect = $_SESSION['intended_url'] ?? 'index.php';
		unset($_SESSION['intended_url']);

		header('Location: ' . $redirect);
		exit;
	} else {
		// Login failed, get error message
		$errorMessage = $auth->getFirstError() ?: 'Ein unbekannter Fehler ist aufgetreten.';
	}
}

// Check if there's a message in the URL (e.g., after registration)
if (isset($_GET['message']) && $_GET['message'] === 'registered') {
	$successMessage = 'Registrierung erfolgreich! Bitte melde dich jetzt an.';
}

// Check if user was redirected from a protected page
if (isset($_GET['required']) && $_GET['required'] === '1') {
	$_SESSION['intended_url'] = $_GET['redirect'] ?? 'index.php';
	$errorMessage = 'Bitte melde dich an, um auf diese Seite zuzugreifen.';
}

// Include header without session check
$noSessionCheck = true;
require_once 'header.php';
?>

	<div class="row justify-content-center">
		<div class="col-md-6 col-lg-5">
			<div class="card card-hover shadow-lg border-0">
				<div class="card-header bg-primary text-white text-center py-4">
					<h4 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i>Anmelden</h4>
				</div>
				<div class="card-body p-4">
					<?php if (!empty($errorMessage)): ?>
						<div class="alert alert-danger alert-dismissible fade show" role="alert">
							<?= htmlspecialchars($errorMessage) ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						</div>
					<?php endif; ?>

					<?php if (!empty($successMessage)): ?>
						<div class="alert alert-success alert-dismissible fade show" role="alert">
							<?= htmlspecialchars($successMessage) ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						</div>
					<?php endif; ?>

					<form method="post" action="login.php" class="needs-validation" novalidate>
						<div class="mb-3">
							<label for="email" class="form-label">E-Mail-Adresse / Login</label>
							<div class="input-group">
								<span class="input-group-text"><i class="bi bi-envelope"></i></span>
								<input class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
							</div>
							<div class="invalid-feedback">Bitte gib deine E-Mail-Adresse ein.</div>
						</div>

						<div class="mb-3">
							<label for="password" class="form-label">Passwort</label>
							<div class="input-group">
								<span class="input-group-text"><i class="bi bi-lock"></i></span>
								<input type="password" class="form-control" id="password" name="password" required>
								<button class="btn btn-outline-secondary" type="button" id="togglePassword">
									<i class="bi bi-eye"></i>
								</button>
							</div>
							<div class="invalid-feedback">Bitte gib dein Passwort ein.</div>
						</div>

						<div class="mb-4 form-check">
							<input type="checkbox" class="form-check-input" id="remember" name="remember">
							<label class="form-check-label" for="remember">Angemeldet bleiben</label>
						</div>

						<div class="d-grid gap-2">
							<button type="submit" class="btn btn-primary btn-lg">
								<i class="bi bi-box-arrow-in-right me-2"></i>Anmelden
							</button>
							<a href="register.php" class="btn btn-outline-secondary">
								<i class="bi bi-person-plus me-2"></i>Neues Konto erstellen
							</a>
						</div>
					</form>

					<div class="text-center mt-4">
						<a href="password_reset.php" class="text-decoration-none">Passwort vergessen?</a>
					</div>
				</div>
				<div class="card-footer bg-light py-3 text-center">
					<p class="mb-0">
						<a href="landing.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Zurück zur Startseite</a>
					</p>
				</div>
			</div>
		</div>
	</div>

	<script>
		// Toggle password visibility
		document.getElementById('togglePassword').addEventListener('click', function() {
			const passwordInput = document.getElementById('password');
			const icon = this.querySelector('i');

			if (passwordInput.type === 'password') {
				passwordInput.type = 'text';
				icon.classList.remove('bi-eye');
				icon.classList.add('bi-eye-slash');
			} else {
				passwordInput.type = 'password';
				icon.classList.remove('bi-eye-slash');
				icon.classList.add('bi-eye');
			}
		});

		// Form validation
		(function() {
			'use strict';

			const forms = document.querySelectorAll('.needs-validation');

			Array.from(forms).forEach(function(form) {
				form.addEventListener('submit', function(event) {
					if (!form.checkValidity()) {
						event.preventDefault();
						event.stopPropagation();
					}

					form.classList.add('was-validated');
				}, false);
			});
		})();
	</script>

<?php
// Include footer
require_once 'footer.php';
?>