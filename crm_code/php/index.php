<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_renters = $pdo->query("SELECT COUNT(*) FROM renters")->fetchColumn();
$total_contracts = $pdo->query("SELECT COUNT(*) FROM contracts")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CRM Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <div class="container mt-5">
        <div class="clock" id="clock"></div>

        <h1>CRM Dashboard</h1>

        <!-- Navigation bar -->
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">CRM</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="clients.php">Clients</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="renters.php">Renters</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contracts.php">Contracts</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="search.php">Search</a>
                        </li>
                    </ul>
                    <form class="d-flex ms-auto" action="logout.php" method="POST">
                        <button class="btn btn-outline-danger" type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </nav>

        <!-- Cards for totals -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Clients</h5>
                        <p class="card-text"><?= htmlspecialchars($total_clients); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Renters</h5>
                        <p class="card-text"><?= htmlspecialchars($total_renters); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Contracts</h5>
                        <p class="card-text"><?= htmlspecialchars($total_contracts); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="clock.js"></script>
</body>
</html>
