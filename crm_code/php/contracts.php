<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Function to reset client IDs if no contracts exist
function reset_client_ids_if_no_contracts($pdo) {
    $contract_count = $pdo->query("SELECT COUNT(*) FROM contracts")->fetchColumn();
    if ($contract_count == 0) {
        $pdo->query("ALTER TABLE clients AUTO_INCREMENT = 1");
    }
}

// Generate contract ID using client identify card
function generateContractID($client_identify_card) {
    return 'Contract ' . $client_identify_card;
}

// Add a new contract
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_contract'])) {
    $client_id = $_POST['client_id'];
    $renter_id = $_POST['renter_id'];
    $address = $_POST['address'];
    $price = $_POST['price'];
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    $pdf_file = null;

    // Fetch client identify card
    $client_stmt = $pdo->prepare("SELECT identify_card FROM clients WHERE id = ?");
    $client_stmt->execute([$client_id]);
    $client_data = $client_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client_data) {
        $_SESSION['notification'] = "Client not found.";
        header("Location: contracts.php");
        exit();
    }

    $identify_card = $client_data['identify_card'];
    $contract_name = generateContractID($identify_card);

    // Restrict to PDF files only and assign a unique name
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        if (mime_content_type($_FILES['pdf_file']['tmp_name']) === 'application/pdf') {
            $target_dir = "uploads/";
            $pdf_file_name = time() . "_" . basename($_FILES['pdf_file']['name']);
            $target_file = $target_dir . $pdf_file_name;

            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_file)) {
                $pdf_file = $pdf_file_name;
            }
        }
    }

    // Prepare the statement to add a new contract with status set to 'active' if due date is in the future
    $status = (strtotime($due_date) >= strtotime(date('Y-m-d'))) ? 'active' : 'expired';
    $stmt = $pdo->prepare("INSERT INTO contracts (contract_name, client_id, renter_id, address, price, start_date, due_date, status, pdf_file) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$contract_name, $client_id, $renter_id, $address, $price, $start_date, $due_date, $status, $pdf_file]);

    reset_client_ids_if_no_contracts($pdo);

    $_SESSION['notification'] = "Contract added successfully.";
    header("Location: contracts.php");
    exit();
}

// Edit contract (for price adjustment or due date renewal)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_contract'])) {
    $contract_id = $_POST['contract_id'];
    $price = $_POST['price'];
    $due_date = $_POST['due_date'];

    // Fetch old price and due date to save renewal history
    $old_contract = $pdo->prepare("SELECT price, due_date FROM contracts WHERE id = ?");
    $old_contract->execute([$contract_id]);
    $old_data = $old_contract->fetch(PDO::FETCH_ASSOC);

    // Update specific contract with new price and due date
    $stmt = $pdo->prepare("UPDATE contracts SET price = ?, due_date = ?, status = ? WHERE id = ?");
    $status = (strtotime($due_date) >= strtotime(date('Y-m-d'))) ? 'active' : 'expired';
    $stmt->execute([$price, $due_date, $status, $contract_id]);

    // Save renewal history
    $history_stmt = $pdo->prepare("INSERT INTO contract_renewals (contract_id, old_price, old_due_date, new_price, new_due_date) VALUES (?, ?, ?, ?, ?)");
    $history_stmt->execute([$contract_id, $old_data['price'], $old_data['due_date'], $price, $due_date]);

    $_SESSION['notification'] = "Contract renewed successfully.";
    header("Location: contracts.php");
    exit();
}

// Contract liquidation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_liquidate'])) {
    $contract_id = $_POST['contract_id'];

    $stmt = $pdo->prepare("UPDATE contracts SET status = 'liquidated' WHERE id = ?");
    $stmt->execute([$contract_id]);

    $_SESSION['notification'] = "Contract liquidated successfully.";
    header("Location: contracts.php");
    exit();
}

// Remove contract (requires password)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_contract'])) {
    $password = $_POST['password'];
    $contract_id = $_POST['contract_id'];

    if ($password === 'admin123') {
        $stmt = $pdo->prepare("DELETE FROM contracts WHERE id = ?");
        $stmt->execute([$contract_id]);
        $_SESSION['notification'] = "Contract deleted successfully.";
        header("Location: contracts.php");
        exit();
    } else {
        $_SESSION['notification'] = "Incorrect password.";
        header("Location: contracts.php");
        exit();
    }
}

// Check and update contract status based on due dates
$update_status_stmt = $pdo->prepare("UPDATE contracts SET status = CASE 
                                        WHEN due_date < CURDATE() AND status != 'liquidated' THEN 'expired' 
                                        WHEN due_date >= CURDATE() AND status != 'liquidated' THEN 'active' 
                                    END 
                                    WHERE status != 'liquidated'");
$update_status_stmt->execute();

// Fetch contracts with pagination (limit 5 per page)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * 5;
$total_contracts = $pdo->query("SELECT COUNT(*) FROM contracts")->fetchColumn();
$total_pages = ceil($total_contracts / 5);

$contracts = $pdo->prepare("SELECT c.*, cl.name AS client_name, r.name AS renter_name 
                            FROM contracts c 
                            JOIN clients cl ON c.client_id = cl.id 
                            JOIN renters r ON c.renter_id = r.id 
                            LIMIT 5 OFFSET ?");
$contracts->bindParam(1, $offset, PDO::PARAM_INT);
$contracts->execute();
$contracts = $contracts->fetchAll(PDO::FETCH_ASSOC);

// Fetch clients and renters for selection
$clients = $pdo->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);
$renters = $pdo->query("SELECT * FROM renters")->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contracts</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="contracts.css">

</head>
<body>
<div class="container mt-4">
<div class="clock" id="clock"></div>    
<div class="container mt-5">
        <h1>Contracts</h1>
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

        <?php if (isset($_SESSION['notification'])): ?>
            <div class="alert alert-success" role="alert">
                <?= $_SESSION['notification']; ?>
                <?php unset($_SESSION['notification']); ?>
            </div>
        <?php endif; ?>

        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addContractModal">
            Add New Contract
        </button>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Contract ID</th>
                    <th>Client</th>
                    <th>Renter</th>
                    <th>Address</th>
                    <th>Start Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    <?php foreach ($contracts as $contract): ?>
        <tr>
            <td><?= $contract['contract_name']; ?></td> <!-- Displaying Random Contract ID -->
            <td><?= htmlspecialchars($contract['client_name']); ?></td>
            <td><?= htmlspecialchars($contract['renter_name']); ?></td>
            <td><?= htmlspecialchars($contract['address']); ?></td>
            <td><?= htmlspecialchars($contract['start_date']); ?></td>
            <td><?= htmlspecialchars($contract['due_date']); ?></td>
            <td><?= htmlspecialchars($contract['status']); ?></td>
            <td>
                <form action="contracts.php" method="POST" class="d-inline">
                    <input type="hidden" name="contract_id" value="<?= $contract['id']; ?>">
                    <button type="button" class="btn btn-view" data-bs-toggle="modal" data-bs-target="#viewContractModal<?= $contract['id']; ?>">View</button>
                </form>
            </td>
        </tr>
        
        <!-- View Contract Modal -->
        <div class="modal fade" id="viewContractModal<?= $contract['id']; ?>" tabindex="-1" aria-labelledby="viewContractModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Contract Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <h5>Contract ID: <?= htmlspecialchars($contract['contract_name']); ?></h5>
                        <p><strong>Client:</strong> <?= htmlspecialchars($contract['client_name']); ?></p>
                        <p><strong>Renter:</strong> <?= htmlspecialchars($contract['renter_name']); ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($contract['address']); ?></p>

                        <?php
                        // Fetch renewal history for this contract
                        $renewal_history = $pdo->prepare("SELECT * FROM contract_renewals WHERE contract_id = ?");
                        $renewal_history->execute([$contract['id']]);
                        $renewal_history = $renewal_history->fetchAll(PDO::FETCH_ASSOC);

                        // Display the latest renewal history (if any)
                        if (count($renewal_history) > 0) {
                            $latest_renewal = $renewal_history[count($renewal_history) - 1];
                            ?>
                            <p><strong>Old Price:</strong> <?= htmlspecialchars($latest_renewal['old_price']); ?></p>
                            <p><strong>Old Due Date:</strong> <?= htmlspecialchars($latest_renewal['old_due_date']); ?></p>
                            <?php
                        }
                        ?>

                        <p><strong>Current Price:</strong> <?= htmlspecialchars($contract['price']); ?></p>
                        <p><strong>Current Due Date:</strong> <?= htmlspecialchars($contract['due_date']); ?></p>
                        <p><strong>Status:</strong> <?= htmlspecialchars($contract['status']); ?></p>
                        <p><strong>PDF File:</strong> <a href="uploads/<?= htmlspecialchars($contract['pdf_file']); ?>" target="_blank">View PDF</a></p>
                    </div>
                    <div class="modal-footer">
                        <!-- Keeping the Liquidate, Renew, and Delete buttons here -->
                        <form action="contracts.php" method="POST" class="d-inline">
                            <input type="hidden" name="contract_id" value="<?= $contract['id']; ?>">
                            <button type="button" class="btn btn-delete" data-bs-toggle="modal" data-bs-target="#deleteContractModal<?= $contract['id']; ?>"><i class="fas fa-trash-alt"></i></button>
                            <button type="button" class="btn btn-liquidate" data-bs-toggle="modal" data-bs-target="#liquidateContractModal<?= $contract['id']; ?>">Liquidate</button>
                            <button type="button" class="btn btn-renew" data-bs-toggle="modal" data-bs-target="#renewContractModal<?= $contract['id']; ?>">Renew</button>
                        </form>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Renew Contract Modal -->
        <div class="modal fade" id="renewContractModal<?= $contract['id']; ?>" tabindex="-1" aria-labelledby="renewContractModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="contracts.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Renew Contract</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="contract_id" value="<?= $contract['id']; ?>">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (current: <?= htmlspecialchars($contract['price']); ?>)</label>
                                <input type="number" step="0.01" class="form-control" name="price" id="price" required>
                            </div>
                            <div class="mb-3">
                                <label for="due_date" class="form-label">New Due Date</label>
                                <input type="date" class="form-control" name="due_date" id="due_date" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="edit_contract" class="btn btn-renew-confirm">Renew</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Liquidate Contract Modal -->
        <div class="modal fade" id="liquidateContractModal<?= $contract['id']; ?>" tabindex="-1" aria-labelledby="liquidateContractModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="contracts.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Liquidate Contract</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to liquidate this contract?</p>
                            <input type="hidden" name="contract_id" value="<?= $contract['id']; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="confirm_liquidate" class="btn btn-liquidate-confirm">Liquidate</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Contract Modal -->
        <div class="modal fade" id="deleteContractModal<?= $contract['id']; ?>" tabindex="-1" aria-labelledby="deleteContractModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="contracts.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Contract</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this contract?</p>
                            <input type="hidden" name="contract_id" value="<?= $contract['id']; ?>">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" id="password" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="delete_contract" class="btn btn-danger">Delete</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php endforeach; ?>
</tbody>
        </table>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="contracts.php?page=<?= $i; ?>"><?= $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>

    <!-- Add Contract Modal -->
    <div class="modal fade" id="addContractModal" tabindex="-1" aria-labelledby="addContractModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="contracts.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Contract</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="client_id" class="form-label">Client</label>
                            <select class="form-select" name="client_id" required>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id']; ?>"><?= htmlspecialchars($client['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="renter_id" class="form-label">Renter</label>
                            <select class="form-select" name="renter_id" required>
                                <?php foreach ($renters as $renter): ?>
                                    <option value="<?= $renter['id']; ?>"><?= htmlspecialchars($renter['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" id="address" required>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" step="0.01" class="form-control" name="price" id="price" required>
                        </div>
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="start_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date" id="due_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="pdf_file" class="form-label">Upload PDF</label>
                            <input type="file" class="form-control" name="pdf_file" id="pdf_file" accept="application/pdf">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_contract" class="btn btn-primary">Add Contract</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="clock.js"></script>
</body>
</html>