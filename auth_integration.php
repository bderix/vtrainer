<?php
/**
 * Authentication Integration
 *
 * Dieses Script sollte am Anfang jeder Seite eingebunden werden,
 * um die Authentifizierung und Zugriffskontrolle zu verwalten.
 *
 * PHP version 8.0
 */

// Session starten, falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// UserAuthentication-Klasse einbinden
require_once 'UserAuthentication.php';

// Datenbankverbindung und Authentifizierungsobjekt erstellen
$auth = new UserAuthentication($db);

// Liste von Seiten, die keine Authentifizierung erfordern
$publicPages = [
	'landing.php',
	'login.php',
	'register.php',
	'password_reset.php',
	'verify_email.php'
];

// Aktuelle Seite ermitteln
$currentPage = basename($_SERVER['SCRIPT_NAME']);

// Prüfen, ob dies eine Seite ist, die keine Authentifizierung für den Zugriff erfordert
$isPublicPage = in_array($currentPage, $publicPages);

// Sicherheitsüberprüfung: Ist der Benutzer auf einer geschützten Seite angemeldet?
if (!$isPublicPage && !$auth->isLoggedIn() && !isset($noSessionCheck)) {
	// Aktuelle URL für Weiterleitung nach Login speichern
	$_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];

	// Zur Login-Seite weiterleiten
	header('Location: login.php?required=1&redirect=' . urlencode($_SERVER['REQUEST_URI']));
	exit;
}

// Zusätzliche Berechtigungsprüfungen für bestimmte Seiten
if ($currentPage === 'admin.php' && !$auth->isAdmin()) {
	// Nur Administratoren dürfen auf die Admin-Seite zugreifen
	header('Location: index.php?error=unauthorized');
	exit;
}

// Wenn wir hier sind, hat der Benutzer Zugriff auf die Seite

// Globale Variablen für die Verwendung in Templates
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$isPremium = $auth->isPremium();

/**
 * Hilfsfunktion zur Anzeige von benutzerspezifischen UI-Elementen
 */
function showUserUI() {
	global $currentUser, $isAdmin, $isPremium;

	if (!$currentUser) {
		return;
	}

	// Benutzer-Badge basierend auf Rolle anzeigen
	$roleClass = 'bg-secondary';
	$roleText = 'Standard';

	if ($isAdmin) {
		$roleClass = 'bg-danger';
		$roleText = 'Admin';
	} elseif ($isPremium) {
		$roleClass = 'bg-success';
		$roleText = 'Premium';
	}

	// Benutzermenü erzeugen
	echo '<div class="dropdown">';
	echo '  <button class="btn btn-light dropdown-toggle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">';
	echo '    <i class="bi bi-person-circle me-1"></i>' . htmlspecialchars($currentUser['username']) . ' <span class="badge ' . $roleClass . ' ms-1">' . $roleText . '</span>';
	echo '  </button>';
	echo '  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuButton">';
	echo '    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Mein Profil</a></li>';

	if ($isAdmin) {
		echo '    <li><a class="dropdown-item" href="admin.php"><i class="bi bi-gear me-2"></i>Administration</a></li>';
		echo '    <li><hr class="dropdown-divider"></li>';
	}

	echo '    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>';
	echo '  </ul>';
	echo '</div>';
}

/**
 * Hilfsfunktion zum Anzeigen der öffentlichen Navigation
 */
function showPublicNav() {
	echo '<div class="d-flex">';
	echo '  <a href="login.php" class="btn btn-outline-primary me-2">Anmelden</a>';
	echo '  <a href="register.php" class="btn btn-primary">Registrieren</a>';
	echo '</div>';
}

/**
 * Hilfsfunktion zur Anzeige eines Premium-Badges, falls Inhalt nur für Premium-Benutzer ist
 */
function showPremiumBadge($featureName = 'Diese Funktion') {
	global $isPremium;

	if (!$isPremium) {
		echo '<div class="alert alert-warning">';
		echo '  <i class="bi bi-star-fill me-2"></i>' . htmlspecialchars($featureName) . ' ist nur für Premium-Nutzer verfügbar.';
		echo '  <a href="upgrade.php" class="btn btn-sm btn-warning ms-3">Upgrade auf Premium</a>';
		echo '</div>';
	}
}

/**
 * Hilfsfunktion zur Überprüfung, ob ein Benutzer Zugriff auf eine Liste hat
 */
function userHasListAccess($listId, $vocabDB, $userId = null) {
	global $isAdmin, $currentUser;

	// Admins haben immer Zugriff
	if ($isAdmin) {
		return true;
	}

	// Wenn keine Benutzer-ID angegeben, verwende aktuelle Session
	if ($userId === null && isset($currentUser)) {
		$userId = $currentUser['id'];
	}

	// Öffentliche Listen oder eigene Listen erlauben Zugriff
	$list = $vocabDB->getListById($listId, $userId);
	return ($list !== false);
}

/**
 * Hilfsfunktion zur Überprüfung, ob ein Benutzer eine Liste bearbeiten darf
 */
function userCanEditList($listId, $vocabDB, $userId = null) {
	global $isAdmin, $currentUser;

	// Admins haben immer Bearbeitungsrechte
	if ($isAdmin) {
		return true;
	}

	// Wenn keine Benutzer-ID angegeben, verwende aktuelle Session
	if ($userId === null && isset($currentUser)) {
		$userId = $currentUser['id'];
	}

	// Nur der Besitzer darf bearbeiten
	return $vocabDB->isListOwner($listId, $userId);
}

/**
 * Hilfsfunktion um Fehlermeldungen auszugeben und zurück zu leiten
 */
function redirectWithError($message, $redirectUrl = 'index.php') {
	$_SESSION['errorMessage'] = $message;
	header('Location: ' . $redirectUrl);
	exit;
}

/**
 * Hilfsfunktion um Erfolgsmeldungen auszugeben und zurück zu leiten
 */
function redirectWithSuccess($message, $redirectUrl = 'index.php') {
	$_SESSION['successMessage'] = $message;
	header('Location: ' . $redirectUrl);
	exit;
}


