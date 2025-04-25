<?php
/**
 * Registration page
 *
 * PHP version 8.0
 */

// Include configuration and classes
require_once 'config.php';
require_once 'UserAuthentication.php';

// Get database connection
$db = getDbConnection();
$auth = new UserAuthentication($db);

xlog($_REQUEST);
// Initialize variables
$email = '';
$username = '';
$firstName = '';
$lastName = '';
$errorMessage = '';

// Check if user is already logged in
if ($auth->isLoggedIn()) {
	// Redirect to index page
	header('Location: index.php');
	exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Get form data
	$email = trim($_POST['email'] ?? '');
	$username = trim($_POST['username'] ?? '');
	$firstName = trim($_POST['first_name'] ?? '');
	$lastName = trim($_POST['last_name'] ?? '');
	$password = $_POST['password'] ?? '';
	$passwordConfirm = $_POST['password_confirm'] ?? '';

	// Validate passwords match
	if ($password !== $passwordConfirm) {
		$errorMessage = 'Die Passwörter stimmen nicht überein.';
	} else {
		// Create profile data array
		$profile = [
			'first_name' => $firstName,
			'last_name' => $lastName
		];

		// Attempt to register user
		$userId = $auth->registerUser($email, $username, $password, $profile);

		if ($userId) {
			// Registration successful, redirect to login page
			header('Location: login.php?message=registered');
			exit;
		} else {
			// Registration failed, get error message
			$errorMessage = $auth->getFirstError() ?: 'Ein unbekannter Fehler ist aufgetreten.';
		}
	}
}

// Include header without session check
$noSessionCheck = true;
require_once 'header.php';
?>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card card-hover shadow-lg border-0">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Registrieren</h4>
                </div>
                <div class="card-body p-4">
					<?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
							<?= htmlspecialchars($errorMessage) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
					<?php endif; ?>

                    <form method="post" action="register.php" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="first_name" class="form-label">Vorname</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($firstName) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Nachname</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($lastName) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Benutzername <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>
                            </div>
                            <div class="form-text">Wähle einen eindeutigen Benutzernamen (min. 3 Zeichen).</div>
                            <div class="invalid-feedback">Bitte gib einen Benutzernamen ein.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-Mail-Adresse <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                            </div>
                            <div class="form-text">Deine E-Mail wird für die Kontowiederherstellung verwendet.</div>
                            <div class="invalid-feedback">Bitte gib eine gültige E-Mail-Adresse ein.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Passwort <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password"
                                       required minlength="8" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Mindestens 8 Zeichen.</div>
                            <div class="invalid-feedback">Bitte gib ein sicheres Passwort ein (mind. 8 Zeichen).</div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirm" class="form-label">Passwort bestätigen <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                                       required minlength="8" autocomplete="new-password">
                            </div>
                            <div class="invalid-feedback">Bitte bestätige dein Passwort.</div>
                        </div>

                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                Ich stimme den <a href="#" target="_blank">Nutzungsbedingungen</a> und der <a href="#" target="_blank">Datenschutzerklärung</a> zu.
                            </label>
                            <div class="invalid-feedback">Du musst den Bedingungen zustimmen, um fortzufahren.</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-person-plus me-2"></i>Konto erstellen
                            </button>
                            <a href="login.php" class="btn btn-outline-secondary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Zurück zur Anmeldung
                            </a>
                        </div>
                    </form>
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

		// Password confirmation validation
		document.getElementById('password_confirm').addEventListener('input', function() {
			const password = document.getElementById('password').value;
			const passwordConfirm = this.value;

			if (password !== passwordConfirm) {
				this.setCustomValidity('Die Passwörter stimmen nicht überein.');
			} else {
				this.setCustomValidity('');
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