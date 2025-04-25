<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom styles -->
    <style>
        .importance-1 { background-color: #f8d7da; }
        .importance-2 { background-color: #fff; }
        .importance-3 { background-color: #d1e7dd; }

        /* Animation für den Antwort aufdecken Button */
        .reveal-btn {
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .reveal-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.1);
            transition: all 0.5s ease;
            z-index: -1;
        }

        .reveal-btn:hover:before {
            left: 0;
        }

        .reveal-btn:hover i {
            transform: rotate(-10deg) scale(1.1);
            transition: transform 0.3s ease;
        }

        /* User avatar */
        .avatar-container {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 8px;
            display: inline-block;
        }

        .avatar-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-menu .dropdown-toggle::after {
            margin-left: 0.5em;
            vertical-align: middle;
        }

        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php"><?= APP_NAME ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add.php"><i class="bi bi-plus-circle"></i> Vokabel hinzufügen</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lists.php"><i class="bi bi-folder"></i> Listen</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="list.php"><i class="bi bi-card-list"></i> Vokabelliste</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="quiz_select.php"><i class="bi bi-question-circle"></i> Vokabelabfrage</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="statistics.php"><i class="bi bi-bar-chart"></i> Statistiken</a>
                </li>
            </ul>

            <!-- Benutzerbereich (rechts in der Navbar) -->
            <div class="navbar-nav">
				<?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Benutzer ist angemeldet - zeige Benutzermenü -->
                    <div class="nav-item dropdown user-menu">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar-container bg-secondary d-flex align-items-center justify-content-center text-white">
								<?php
								// Wenn kein Avatar vorhanden, zeige Initialen
								if (isset($currentUser) && isset($currentUser['avatar']) && !empty($currentUser['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" alt="Avatar">
								<?php else: ?>
                                    <i class="bi bi-person-fill"></i>
								<?php endif; ?>
                            </div>
                            <span class="d-none d-md-inline ms-1">
                                    <?= htmlspecialchars($_SESSION['username']) ?>
                                </span>

							<?php if (isset($_SESSION['role_id'])): ?>
								<?php
								$roleBadgeClass = 'bg-secondary';
								$roleText = 'Standard';

								if ($_SESSION['role_id'] == 1) {
									$roleBadgeClass = 'bg-danger';
									$roleText = 'Admin';
								} elseif ($_SESSION['role_id'] == 2) {
									$roleBadgeClass = 'bg-success';
									$roleText = 'Premium';
								}
								?>
                                <span class="badge <?= $roleBadgeClass ?> ms-1 d-none d-md-inline"><?= $roleText ?></span>
							<?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Mein Profil</a></li>
							<?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1): ?>
                                <li><a class="dropdown-item" href="admin.php"><i class="bi bi-gear me-2"></i>Administration</a></li>
                                <li><hr class="dropdown-divider"></li>
							<?php endif; ?>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>
                        </ul>
                    </div>
				<?php else: ?>
                    <!-- Benutzer ist nicht angemeldet - zeige Login/Register-Buttons -->
                    <div class="d-flex">
                        <a href="login.php" class="btn btn-outline-light me-2">Anmelden</a>
                        <a href="register.php" class="btn btn-light">Registrieren</a>
                    </div>
				<?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container mb-4">
	<?php if (isset($dbInitialized) && $dbInitialized): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Datenbank wurde erfolgreich initialisiert!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
	<?php endif; ?>

	<?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
			<?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
	<?php endif; ?>

	<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
		<?= htmlspecialchars($errorMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>