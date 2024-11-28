<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables for search results
$clients = [];
$renters = [];
$contracts = [];
$total_price = 0;
$warning_message = '';

// Handle search logic
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search_query'])) {
    $search_query = $_GET['search_query'];
    
    if (empty($search_query)) {
        // Show a warning if search query is empty
        $warning_message = 'Please enter a search term.';
    } else {
        $search_query = '%' . $search_query . '%';

        // Search clients
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE name LIKE ? OR id LIKE ?");
        $stmt->execute([$search_query, $search_query]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Search renters
        $stmt = $pdo->prepare("SELECT * FROM renters WHERE name LIKE ? OR id LIKE ?");
        $stmt->execute([$search_query, $search_query]);
        $renters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Search contracts related to clients or renters
        $stmt = $pdo->prepare("SELECT c.*, cl.name AS client_name, r.name AS renter_name FROM contracts c
                               JOIN clients cl ON c.client_id = cl.id
                               JOIN renters r ON c.renter_id = r.id
                               WHERE cl.name LIKE ? OR r.name LIKE ?");
        $stmt->execute([$search_query, $search_query]);
        $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total price for contracts found
        foreach ($contracts as $contract) {
            if (in_array($contract['status'], ['active', 'expired'])) {
                $total_price += $contract['price'];
            }
        }

        // Show a warning if no results are found
        if (empty($clients) && empty($renters) && empty($contracts)) {
            $warning_message = 'No results found matching your search.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="search.css">
</head>
<body>
<div class="container mt-4">
    <div class="clock" id="clock"></div>
    <div class="container mt-5">
        <h1>Search Results</h1>

        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">CRM</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="clients.php">Clients</a></li>
                        <li class="nav-item"><a class="nav-link" href="renters.php">Renters</a></li>
                        <li class="nav-item"><a class="nav-link" href="contracts.php">Contracts</a></li>
                        <li class="nav-item"><a class="nav-link" href="search.php">Search</a></li>
                    </ul>
                    <form class="d-flex ms-auto" action="logout.php" method="POST">
                        <button class="btn btn-outline-danger" type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </nav>

        <form class="mb-4" action="search.php" method="GET">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search clients or renters..." name="search_query" value="<?= isset($_GET['search_query']) ? htmlspecialchars($_GET['search_query']) : '' ?>">
                <button class="btn btn-search" type="submit">Search</button>
            </div>
        </form>

        <!-- Show warning message if no input or no results found -->
        <?php if ($warning_message): ?>
            <div class="alert alert-warning" role="alert">
                <?= htmlspecialchars($warning_message) ?>
            </div>
        <?php endif; ?>

        <!-- Clients Results -->
        <?php if (!empty($clients)): ?>
            <h4>Clients Found</h4>
            <table class="table table-bordered mb-4">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Total Active/Expired Contracts Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?= htmlspecialchars($client['id']) ?></td>
                            <td><?= htmlspecialchars($client['name']) ?></td>
                            <td>
                                <?php
                                    $client_contracts = $pdo->prepare("SELECT * FROM contracts WHERE client_id = ?");
                                    $client_contracts->execute([$client['id']]);
                                    $total_value = 0;

                                    foreach ($client_contracts as $contract) {
                                        if (in_array($contract['status'], ['active', 'expired'])) {
                                            $total_value += $contract['price'];
                                        }
                                    }
                                    echo '$' . number_format($total_value, 2);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Renters Results -->
        <?php if (!empty($renters)): ?>
            <h4>Renters Found</h4>
            <table class="table table-bordered mb-4">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Total Active/Expired Contracts Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($renters as $renter): ?>
                        <tr>
                            <td><?= htmlspecialchars($renter['id']) ?></td>
                            <td><?= htmlspecialchars($renter['name']) ?></td>
                            <td>
                                <?php
                                    $renter_contracts = $pdo->prepare("SELECT * FROM contracts WHERE renter_id = ?");
                                    $renter_contracts->execute([$renter['id']]);
                                    $total_value = 0;

                                    foreach ($renter_contracts as $contract) {
                                        if (in_array($contract['status'], ['active', 'expired'])) {
                                            $total_value += $contract['price'];
                                        }
                                    }
                                    echo '$' . number_format($total_value, 2);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Contracts Results -->
        <?php if (!empty($contracts)): ?>
            <h4>Contracts Found</h4>
            <table class="table table-bordered mb-4">
                <thead>
                    <tr>
                        <th>Contract ID</th>
                        <th>Client</th>
                        <th>Renter</th>
                        <th>Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td><?= htmlspecialchars($contract['contract_name']) ?></td>
                            <td><?= htmlspecialchars($contract['client_name']) ?></td>
                            <td><?= htmlspecialchars($contract['renter_name']) ?></td>
                            <td>$<?= number_format($contract['price'], 2) ?></td>
                            <td><?= htmlspecialchars($contract['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h5>Total Value of All Active/Expired Contracts: $<?= number_format($total_price, 2) ?></h5>
        <?php endif; ?>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="clock.js"></script>
</body>
</html>
