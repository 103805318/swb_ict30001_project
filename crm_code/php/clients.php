<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize notifications array
$notifications = [];
// Set the number of clients per page
$clients_per_page = 5;

// Get the current page number, default to 1 if not set
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Calculate the offset for the SQL query
$offset = ($page - 1) * $clients_per_page;

// Handle form submission for adding a new client
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_client"])) {
    $name = $_POST['name'];
    $date_of_birth = $_POST['date_of_birth'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $gender = $_POST['gender'];
    $phone_number = $_POST['phone_number'];
    $identify_card = $_POST['identify_card'];

    $stmt = $pdo->prepare("INSERT INTO clients (name, date_of_birth, email, address, gender, phone_number, identify_card) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $date_of_birth, $email, $address, $gender, $phone_number, $identify_card]);

    // Set notification for client added
    $_SESSION['notification'] = "Client added successfully!";
    header("Location: clients.php");
    exit();
}

// Handle client update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_client"])) {
    $client_id = $_POST['client_id'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $phone_number = $_POST['phone_number'];

    $stmt = $pdo->prepare("UPDATE clients SET email = ?, address = ?, phone_number = ? WHERE id = ?");
    $stmt->execute([$email, $address, $phone_number, $client_id]);

    // Set notification for client updated
    $_SESSION['notification'] = "Client information updated successfully!";
    header("Location: clients.php");
    exit();
}
// Handle client deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_client"])) {
    $client_id = $_POST['client_id'];

    // Prepare the query to delete the client
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);

    // Set notification for client deletion
    $_SESSION['notification'] = "Client deleted successfully!";
    header("Location: clients.php");
    exit();
}


// Fetch all clients from the database
$clients = $pdo->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);

// Fetch a specific client for editing
$clientToEdit = null;
if (isset($_GET['edit_client'])) {
    $client_id = $_GET['edit_client'];
    $clientToEdit = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $clientToEdit->execute([$client_id]);
    $clientToEdit = $clientToEdit->fetch(PDO::FETCH_ASSOC);
}
// Fetch the clients with limit and offset
$clients = $pdo->prepare("SELECT * FROM clients LIMIT :limit OFFSET :offset");
$clients->bindParam(':limit', $clients_per_page, PDO::PARAM_INT);
$clients->bindParam(':offset', $offset, PDO::PARAM_INT);
$clients->execute();
$clients = $clients->fetchAll(PDO::FETCH_ASSOC);

// Fetch total number of clients to calculate total pages
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_pages = ceil($total_clients / $clients_per_page);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="clients.css">

</head>
<body>
    <div class="container mt-4">
        <div class="clock" id="clock"></div>

        <h1>Clients</h1>

        <!-- Display notifications -->
        <?php if (isset($_SESSION['notification'])): ?>
            <div class="alert alert-success notification" role="alert">
                <?= htmlspecialchars($_SESSION['notification']); ?>
            </div>
            <?php unset($_SESSION['notification']); // Clear the notification after displaying ?>
        <?php endif; ?>

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

        <!-- Add Client Button -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addClientModal">
            Add New Client
        </button>

        <!-- Add Client Modal -->
        <div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addClientModalLabel">New Client</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="identify_card" class="form-label">Identification Card</label>
                                <input type="text" class="form-control" id="identify_card" name="identify_card" required>
                            </div>
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" required>
                            </div>
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" required pattern="\d+" title="Only numbers are allowed">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="add_client" class="btn btn-primary">Save Client</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

<!-- Edit Client Modal -->
<?php if ($clientToEdit): ?>
<div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editClientModalLabel">Edit Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($clientToEdit['id']); ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($clientToEdit['name']); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="identify_card" class="form-label">Identification Card</label>
                        <input type="text" class="form-control" id="identify_card" name="identify_card" value="<?= htmlspecialchars($clientToEdit['identify_card']); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($clientToEdit['date_of_birth']); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($clientToEdit['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($clientToEdit['address']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?= htmlspecialchars($clientToEdit['phone_number']); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                <button type="button" class="btn btn-delete" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"><i class="fas fa-trash-alt"></i></button>
                <button type="submit" name="update_client" class="btn btn-primary">Update Client</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this client?</p>
                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($clientToEdit['id']); ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_client" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Automatically show edit modal when a client is selected for editing
    window.onload = function() {
        new bootstrap.Modal(document.getElementById('editClientModal')).show();
    }
</script>
<?php endif; ?>

<?php if (empty($clients)): ?>
            <div class="alert alert-info" role="alert">
                No clients found. Please add a client.
            </div>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>ID Card</th>
                        <th>Date of Birth</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Gender</th>
                        <th>Phone Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?= htmlspecialchars($client['name']); ?></td>
                            <td><?= htmlspecialchars($client['identify_card']); ?></td>
                            <td><?= htmlspecialchars($client['date_of_birth']); ?></td>
                            <td><?= htmlspecialchars($client['email']); ?></td>
                            <td><?= htmlspecialchars($client['address']); ?></td>
                            <td><?= htmlspecialchars($client['gender']); ?></td>
                            <td><?= htmlspecialchars($client['phone_number']); ?></td>
                            <td>
                                <a href="clients.php?edit_client=<?= $client['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Pagination Controls -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="clients.php?page=<?= $page - 1; ?>" class="btn btn-secondary btn-sm">Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="clients.php?page=<?= $i; ?>" class="btn btn-light btn-sm <?= $i == $page ? 'active' : ''; ?>">
                    <?= $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="clients.php?page=<?= $page + 1; ?>" class="btn btn-secondary btn-sm">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="clock.js"></script>
</body>
</html>
