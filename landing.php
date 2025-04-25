<?php
/**
 * Landing page for Vocabulary Trainer
 * Displays when user is not logged in
 *
 * PHP version 8.0
 */

// Include configuration for APP_NAME constant
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= APP_NAME ?> - Lernen leicht gemacht</title>
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<!-- Bootstrap Icons -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
	<!-- Custom Styles -->
	<style>
        :root {
            --primary-color: #4e73df;
            --primary-dark: #375abd;
            --secondary-color: #f8f9fc;
            --accent-color: #36b9cc;
        }

        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8f9fc;
        }

        .navbar {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.05em;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 6rem 0;
            position: relative;
            overflow: hidden;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -30px;
            left: -30px;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .hero-title {
            font-weight: 800;
            font-size: 3rem;
        }

        .hero-subtitle {
            font-weight: 300;
            margin-bottom: 2rem;
        }

        .hero-cta .btn {
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 50px;
            box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }

        .hero-cta .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
        }

        .feature-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 1rem 3rem rgba(0,0,0,.175);
        }

        .feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 70px;
            width: 70px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            margin-bottom: 1.5rem;
            font-size: 1.75rem;
        }

        .testimonial-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 2rem;
        }

        .testimonial-card .card-body {
            position: relative;
            padding: 2rem;
        }

        .testimonial-card .card-body::before {
            content: "\201C";
            font-family: Georgia, serif;
            font-size: 5rem;
            position: absolute;
            top: -20px;
            left: 10px;
            color: rgba(0,0,0,0.1);
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 2rem;
            text-align: center;
            height: 100%;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .pricing-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0,0,0,.175);
        }

        .pricing-card.highlighted {
            border: 2px solid var(--primary-color);
            transform: scale(1.05);
            position: relative;
            z-index: 1;
        }

        .pricing-card.highlighted:hover {
            transform: scale(1.05) translateY(-5px);
        }

        .pricing-card .card-header {
            background-color: transparent;
            border-bottom: none;
            text-align: center;
            padding-top: 2rem;
            padding-bottom: 0;
        }

        .pricing-card .price {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .pricing-card .price small {
            font-size: 1rem;
            font-weight: 400;
            color: #6c757d;
        }

        .pricing-card ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 2rem;
        }

        .pricing-card ul li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .pricing-card ul li:last-child {
            border-bottom: none;
        }

        .pricing-card.highlighted .card-header {
            color: var(--primary-color);
        }

        .cta-section {
            background-color: var(--primary-color);
            color: white;
            padding: 5rem 0;
            text-align: center;
        }

        .cta-section h2 {
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .login-form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            padding: 2rem;
        }

        .language-icon {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 50%;
            margin: 0.25rem;
            font-weight: bold;
            color: #495057;
        }

        footer {
            background-color: #212529;
            color: rgba(255, 255, 255, 0.6);
            padding: 3rem 0;
        }

        footer a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
        }

        footer a:hover {
            color: white;
        }

        .footer-links {
            list-style: none;
            padding-left: 0;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .social-icons {
            font-size: 1.5rem;
        }

        .social-icons a {
            margin-right: 1rem;
        }

        @media (max-width: 992px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .pricing-card.highlighted {
                transform: none;
                margin-top: 2rem;
                margin-bottom: 2rem;
            }
        }
	</style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-white py-3">
	<div class="container">
		<a class="navbar-brand" href="landing.php">
			<i class="bi bi-journal-text text-primary me-2"></i><?= APP_NAME ?>
		</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="navbarNav">
			<ul class="navbar-nav ms-auto">
				<li class="nav-item">
					<a class="nav-link" href="#features">Funktionen</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="#how-it-works">So funktioniert's</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="#testimonials">Erfahrungen</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="#pricing">Preise</a>
				</li>
				<li class="nav-item ms-lg-3">
					<a class="btn btn-outline-primary px-4" href="#login">Anmelden</a>
				</li>
				<li class="nav-item ms-lg-2">
					<a class="btn btn-primary px-4" href="#register">Registrieren</a>
				</li>
			</ul>
		</div>
	</div>
</nav>

<!-- Hero Section -->
<section class="hero-section">
	<div class="container">
		<div class="row align-items-center">
			<div class="col-lg-6 mb-5 mb-lg-0">
				<h1 class="hero-title mb-3">Vokabeln lernen war noch nie so einfach</h1>
				<p class="hero-subtitle fs-5">Entdecke einen effektiven und motivierenden Weg, neue Sprachen zu lernen. Unser Vokabeltrainer passt sich deinem Lerntempo an und hilft dir, schneller Fortschritte zu erzielen.</p>
				<div class="hero-cta">
					<a href="#register" class="btn btn-light me-3">Jetzt kostenlos starten</a>
					<a href="#demo" class="btn btn-outline-light">Demo ansehen</a>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="login-form-container">
					<h3 class="text-center mb-4">Anmelden</h3>
					<form id="login" action="login.php" method="post">
						<div class="mb-3">
							<label for="email" class="form-label">E-Mail-Adresse</label>
							<input type="email" class="form-control" id="email" name="email" required>
						</div>
						<div class="mb-3">
							<label for="password" class="form-label">Passwort</label>
							<input type="password" class="form-control" id="password" name="password" required>
						</div>
						<div class="mb-3 form-check">
							<input type="checkbox" class="form-check-input" id="remember" name="remember">
							<label class="form-check-label" for="remember">Angemeldet bleiben</label>
						</div>
						<div class="d-grid">
							<button type="submit" class="btn btn-primary">Anmelden</button>
						</div>
						<div class="text-center mt-3">
							<a href="#forgot-password" class="text-decoration-none small">Passwort vergessen?</a>
							<hr>
							<p class="mb-0">Noch kein Konto? <a href="register.php" class="text-decoration-none">Jetzt registrieren</a></p>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Languages Section -->
<section class="py-5 bg-white">
	<div class="container text-center">
		<h2 class="mb-4">Unterstützte Sprachen</h2>
		<div class="d-flex flex-wrap justify-content-center">
			<div class="language-icon">DE</div>
			<div class="language-icon">EN</div>
			<div class="language-icon">FR</div>
			<div class="language-icon">ES</div>
			<div class="language-icon">IT</div>
			<div class="language-icon">NL</div>
			<div class="language-icon">PT</div>
			<div class="language-icon">RU</div>
			<div class="language-icon">PL</div>
			<div class="language-icon">CZ</div>
			<div class="language-icon">JP</div>
			<div class="language-icon">CN</div>
			<div class="language-icon">AR</div>
			<div class="language-icon">TR</div>
			<div class="language-icon">KR</div>
			<div class="language-icon">+</div>
		</div>
		<p class="mt-4 text-muted">Und viele weitere Sprachen – du kannst sogar benutzerdefinierte Listen erstellen!</p>
	</div>
</section>

<!-- Features Section -->
<section id="features" class="py-5">
	<div class="container">
		<div class="text-center mb-5">
			<h2 class="fw-bold">Warum unser Vokabeltrainer?</h2>
			<p class="text-muted">Entdecke unsere einzigartigen Funktionen für ein effektives Lernen</p>
		</div>
		<div class="row g-4">
			<div class="col-md-4">
				<div class="feature-card card p-4">
					<div class="text-center">
						<div class="feature-icon">
							<i class="bi bi-graph-up"></i>
						</div>
						<h4>Intelligente Abfrage</h4>
						<p class="text-muted">Unser System merkt sich deine Fortschritte und fragt schwierige Wörter häufiger ab, um deine Lerneffizienz zu steigern.</p>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="feature-card card p-4">
					<div class="text-center">
						<div class="feature-icon">
							<i class="bi bi-folder-plus"></i>
						</div>
						<h4>Eigene Listen erstellen</h4>
						<p class="text-muted">Erstelle benutzerdefinierte Vokabellisten zu verschiedenen Themen und organisiere deinen Lernfortschritt.</p>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="feature-card card p-4">
					<div class="text-center">
						<div class="feature-icon">
							<i class="bi bi-bar-chart"></i>
						</div>
						<h4>Detaillierte Statistiken</h4>
						<p class="text-muted">Verfolge deinen Fortschritt mit übersichtlichen Diagrammen und erkenne deine Stärken und Schwächen.</p>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="feature-card card p-4">
					<div class="text-center">
						<div class="feature-icon">
							<i class="bi bi-arrows-angle-expand"></i>
						</div>
						<h4>Flexibles Lernen</h4>
						<p class="text-muted">Lerne in beide Richtungen (Fremdsprache → Deutsch und umgekehrt) für ein umfassendes Verständnis.</p>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="feature-card card p-4">
					<div class="text-center">
						<div class="feature-icon">
							<i class="bi bi-card-list"></i>
						</div>
						<h4>Notizen & Beispiele</h4>
						<p class="text-muted">Ergänze Vokabeln mit Beispielsätzen und Notizen für einen besseren Kontext und leichteres Lernen.</p>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="feature-card card p-4">
					<div class="text-center">
						<div class="feature-icon">
							<i class="bi bi-cloud-arrow-up"></i>
						</div>
						<h4>Import & Export</h4>
						<p class="text-muted">Importiere bestehende Vokabellisten oder exportiere deine Listen für die Verwendung in anderen Anwendungen.</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Statistics Section -->
<section class="py-5 bg-white">
	<div class="container">
		<div class="row g-4">
			<div class="col-md-3">
				<div class="stat-card">
					<i class="bi bi-people fs-1 text-primary mb-3"></i>
					<div class="stat-number">10,000+</div>
					<h5>Aktive Nutzer</h5>
				</div>
			</div>
			<div class="col-md-3">
				<div class="stat-card">
					<i class="bi bi-translate fs-1 text-primary mb-3"></i>
					<div class="stat-number">20+</div>
					<h5>Unterstützte Sprachen</h5>
				</div>
			</div>
			<div class="col-md-3">
				<div class="stat-card">
					<i class="bi bi-book fs-1 text-primary mb-3"></i>
					<div class="stat-number">5 Mio+</div>
					<h5>Gelernte Vokabeln</h5>
				</div>
			</div>
			<div class="col-md-3">
				<div class="stat-card">
					<i class="bi bi-stars fs-1 text-primary mb-3"></i>
					<div class="stat-number">4.8/5</div>
					<h5>Durchschnittliche Bewertung</h5>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- How It Works Section -->
<section id="how-it-works" class="py-5">
	<div class="container">
		<div class="text-center mb-5">
			<h2 class="fw-bold">So funktioniert's</h2>
			<p class="text-muted">In nur 3 einfachen Schritten zu deinen persönlichen Vokabellisten</p>
		</div>
		<div class="row align-items-center mb-5">
			<div class="col-lg-6 order-lg-2 mb-4 mb-lg-0">
				<img src="https://via.placeholder.com/600x400" alt="Registrierung" class="img-fluid rounded-3 shadow">
			</div>
			<div class="col-lg-6 order-lg-1">
				<div class="d-flex">
					<div class="me-4">
						<div class="bg-primary text-white rounded-circle p-3 d-inline-block">
							<span class="fw-bold">1</span>
						</div>
					</div>
					<div>
						<h3>Erstelle ein kostenloses Konto</h3>
						<p>Registriere dich in wenigen Sekunden mit deiner E-Mail-Adresse oder über Google/Facebook und erhalte sofortigen Zugriff auf alle Funktionen.</p>
					</div>
				</div>
			</div>
		</div>
		<div class="row align-items-center mb-5">
			<div class="col-lg-6 mb-4 mb-lg-0">
				<img src="https://via.placeholder.com/600x400" alt="Vokabellisten erstellen" class="img-fluid rounded-3 shadow">
			</div>
			<div class="col-lg-6">
				<div class="d-flex">
					<div class="me-4">
						<div class="bg-primary text-white rounded-circle p-3 d-inline-block">
							<span class="fw-bold">2</span>
						</div>
					</div>
					<div>
						<h3>Erstelle deine eigenen Vokabellisten</h3>
						<p>Füge Vokabeln hinzu, importiere bestehende Listen oder nutze unsere vorgefertigten Sammlungen für verschiedene Sprachniveaus und Themen.</p>
					</div>
				</div>
			</div>
		</div>
		<div class="row align-items-center">
			<div class="col-lg-6 order-lg-2 mb-4 mb-lg-0">
				<img src="https://via.placeholder.com/600x400" alt="Vokabeln lernen" class="img-fluid rounded-3 shadow">
			</div>
			<div class="col-lg-6 order-lg-1">
				<div class="d-flex">
					<div class="me-4">
						<div class="bg-primary text-white rounded-circle p-3 d-inline-block">
							<span class="fw-bold">3</span>
						</div>
					</div>
					<div>
						<h3>Starte mit dem Lernen</h3>
						<p>Unser intelligentes System fragt dich gezielt nach den Vokabeln, die du noch nicht beherrschst, und hilft dir, deinen Wortschatz effizient zu erweitern.</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Demo Video Section -->
<section id="demo" class="py-5 bg-light">
	<div class="container">
		<div class="text-center mb-5">
			<h2 class="fw-bold">Sieh unseren Vokabeltrainer in Aktion</h2>
			<p class="text-muted">Eine kurze Demonstration unserer wichtigsten Funktionen</p>
		</div>
		<div class="row justify-content-center">
			<div class="col-lg-10">
				<div class="ratio ratio-16x9 shadow rounded">
					<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="YouTube video" allowfullscreen></iframe>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Testimonials Section -->
<section id="testimonials" class="py-5">
	<div class="container">
		<div class="text-center mb-5">
			<h2 class="fw-bold">Was unsere Nutzer sagen</h2>
			<p class="text-muted">Erfahrungen von Menschen, die ihren Wortschatz mit unserem Tool erweitert haben</p>
		</div>
		<div class="row">
			<div class="col-md-4">
				<div class="testimonial-card card h-100">
					<div class="card-body">
						<p class="card-text">"Der beste Vokabeltrainer, den ich je benutzt habe! Die intelligente Abfrage hilft mir, genau die Wörter zu üben, die ich noch nicht gut kenne. Mein Spanisch hat sich in nur 3 Monaten deutlich verbessert."</p>
						<div class="d-flex align-items-center mt-4">
							<div class="rounded-circle overflow-hidden me-3" style="width: 50px; height: 50px;">
								<img src="https://via.placeholder.com/50" alt="Julia M." class="img-fluid">
							</div>
							<div>
								<h6 class="mb-0">Julia M.</h6>
								<small class="text-muted">Lernende Spanisch</small>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="testimonial-card card h-100">
					<div class="card-body">
						<p class="card-text">"Als Lehrer nutze ich den Vokabeltrainer, um meinen Schülern beim Englischlernen zu helfen. Die Möglichkeit, eigene Listen zu erstellen und den Lernfortschritt zu verfolgen, ist unbezahlbar. Absolut empfehlenswert!"</p>
						<div class="d-flex align-items-center mt-4">
							<div class="rounded-circle overflow-hidden me-3" style="width: 50px; height: 50px;">
								<img src="https://via.placeholder.com/50" alt="Markus K." class="img-fluid">
							</div>
							<div>
								<h6 class="mb-0">Markus K.</h6>
								<small class="text-muted">Englischlehrer</small>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="testimonial-card card h-100">
					<div class="card-body">
						<p class="card-text">"Ich bereite mich auf eine Japanischprüfung vor und der Vokabeltrainer hat mir enorm geholfen. Die Statistiken zeigen mir genau, wo ich noch Schwächen habe, und die Möglichkeit, in beide Richtungen zu lernen, ist super praktisch."</p>
						<div class="d-flex align-items-center mt-4">
							<div class="rounded-circle overflow-hidden me-3" style="width: 50px; height: 50px;">
								<img src="https://via.placeholder.com/50" alt="Simon T." class="img-fluid">
							</div>
							<div>
								<h6 class="mb-0">Simon T.</h6>
								<small class="text-muted">Lernender Japanisch</small>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Pricing Section -->
<section id="pricing" class="py-5 bg-light">
	<div class="container">
		<div class="text-center mb-5">
			<h2 class="fw-bold">Einfache, transparente Preise</h2>
			<p class="text-muted">Wähle den Plan, der zu deinen Bedürfnissen passt</p>
		</div>
		<div class="row">
			<div class="col-lg-4 mb-4">
				<div class="pricing-card card h-100">
					<div class="card-header">
						<h4>Basis</h4>
						<div class="price">Kostenlos</div>
					</div>
					<div class="card-body">