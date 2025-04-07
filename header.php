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
                <ul class="navbar-nav">
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