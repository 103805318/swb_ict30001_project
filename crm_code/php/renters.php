<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize notifications array
$notifications = [];

// Set the number of renters per page
$renters_per_page = 5;

// Get the current page number, default to 1 if not set
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Calculate the offset for the SQL query
$offset = ($page - 1) * $renters_per_page;

// Handle form submission for adding a new renter
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_renter"])) {
    $name = $_POST['name'];
    $date_of_birth = $_POST['date_of_birth'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $gender = $_POST['gender'];
    $phone_number = $_POST['phone_number'];
    $identify_card = $_POST['identify_card'];

    $stmt = $pdo->prepare("INSERT INTO renters (name, date_of_birth, email, address, gender, phone_number, identify_card) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $date_of_birth, $email, $address, $gender, $phone_number, $identify_card]);

    // Set notification for renter added
    $_SESSION['notification'] = "Renter added successfully!";
    header("Location: renters.php");
    exit();
}

// Handle renter update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_renter"])) {
    $renter_id = $_POST['renter_id'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $phone_number = $_POST['phone_number'];

    $stmt = $pdo->prepare("UPDATE renters SET email = ?, address = ?, phone_number = ? WHERE id = ?");
    $stmt->execute([$email, $address, $phone_number, $renter_id]);

    // Set notification for renter updated
    $_SESSION['notification'] = "Renter information updated successfully!";
    header("Location: renters.php");
    exit();
}

// Handle renter deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_renter"])) {
    $renter_id = $_POST['renter_id'];

    // Prepare the query to delete the renter
    $stmt = $pdo->prepare("DELETE FROM renters WHERE id = ?");
    $stmt->execute([$renter_id]);

    // Set notification for renter deletion
    $_SESSION['notification'] = "Renter deleted successfully!";
    header("Location: renters.php");
    exit();
}

// Fetch all renters from the database
$renters = $pdo->query("SELECT * FROM renters")->fetchAll(PDO::FETCH_ASSOC);

// Fetch a specific renter for editing
$renterToEdit = null;
if (isset($_GET['edit_renter'])) {
    $renter_id = $_GET['edit_renter'];
    $renterToEdit = $pdo->prepare("SELECT * FROM renters WHERE id = ?");
    $renterToEdit->execute([$renter_id]);
    $renterToEdit = $renterToEdit->fetch(PDO::FETCH_ASSOC);
}

// Fetch the renters with limit and offset
$renters = $pdo->prepare("SELECT * FROM renters LIMIT :limit OFFSET :offset");
$renters->bindParam(':limit', $renters_per_page, PDO::PARAM_INT);
$renters->bindParam(':offset', $offset, PDO::PARAM_INT);
$renters->execute();
$renters = $renters->fetchAll(PDO::FETCH_ASSOC);

// Fetch total number of renters to calculate total pages
$total_renters = $pdo->query("SELECT COUNT(*) FROM renters")->fetchColumn();
$total_pages = ceil($total_renters / $renters_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renters</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="renters.css">

</head>
<body>
    <div class="container mt-4">
        <div class="clock" id="clock"></div>

        <h1>Renters</h1>

        <!-- Display notifications -->
        <?php if (isset($_SESSION['notification'])): ?>
            <div class="alert alert-success notification" role="alert">
                <?= htmlspecialchars($_SESSION['notification']); ?>
            </div>
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>
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

        <!-- Add Renter Button -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addRenterModal">
            Add New Renter
        </button>

        <!-- Add Renter Modal -->
        <div class="modal fade" id="addRenterModal" tabindex="-1" aria-labelledby="addRenterModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addRenterModalLabel">New Renter</h5>
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
                            <button type="submit" name="add_renter" class="btn btn-primary">Save Renter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table to display renters -->
        <?php if (empty($renters)): ?>
            <div class="alert alert-info" role="alert">
                No renters found. Please add a renter.
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
                    <?php foreach ($renters as $renter): ?>
                        <tr>
                            <td><?= htmlspecialchars($renter['name']); ?></td>
                            <td><?= htmlspecialchars($renter['identify_card']); ?></td>
                            <td><?= htmlspecialchars($renter['date_of_birth']); ?></td>
                            <td><?= htmlspecialchars($renter['email']); ?></td>
                            <td><?= htmlspecialchars($renter['address']); ?></td>
                            <td><?= htmlspecialchars($renter['gender']); ?></td>
                            <td><?= htmlspecialchars($renter['phone_number']); ?></td>
                            <td>
                                <a href="renters.php?edit_renter=<?= $renter['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Pagination -->
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="renters.php?page=<?= $i; ?>"><?= $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>

        <!-- Edit Renter Modal -->
        <?php if ($renterToEdit): ?>
            <div class="modal fade" id="editRenterModal" tabindex="-1" aria-labelledby="editRenterModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editRenterModalLabel">Edit Renter</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="renter_id" value="<?= $renterToEdit['id']; ?>">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($renterToEdit['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($renterToEdit['address']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?= htmlspecialchars($renterToEdit['phone_number']); ?>" required>
                                </div>
                                </div>
                            <div class="modal-footer">
                            <button type="button" class="btn btn-delete" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal"><i class="fas fa-trash-alt"></i></button>
                            <button type="submit" name="update_renter" class="btn btn-primary">Update</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Confirm Delete Modal -->
            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Delete</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to delete this renter? This action cannot be undone.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <form method="POST" action="renters.php" style="display:inline;">
                                <input type="hidden" name="renter_id" value="<?= $renterToEdit['id']; ?>">
                                <button type="submit" name="delete_renter" class="btn btn-danger">Yes, Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // Open the edit modal automatically when loaded
                document.addEventListener('DOMContentLoaded', function() {
                    var editModal = new bootstrap.Modal(document.getElementById('editRenterModal'));
                    editModal.show();
                });
            </script>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="clock.js"></script>
</body>
</html>