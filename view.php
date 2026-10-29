<?php
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: admin_login.php');
    exit;
}

include 'config.php';

$search = trim($_GET['search'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

$allowedSorts = [
    'newest' => 'created_at DESC, id DESC',
    'oldest' => 'created_at ASC, id ASC',
    'az'     => 'name ASC, id ASC',
    'za'     => 'name DESC, id DESC'
];

$sort = $_GET['sort'] ?? 'newest';

if (!array_key_exists($sort, $allowedSorts)) {
    $sort = 'newest';
}

// Validate dates
foreach (['fromDate', 'toDate'] as $dateVariable) {
    if ($$dateVariable !== '') {
        $dateParts = explode('-', $$dateVariable);

        if (
            count($dateParts) !== 3 ||
            !checkdate(
                (int)($dateParts[1] ?? 0),
                (int)($dateParts[2] ?? 0),
                (int)($dateParts[0] ?? 0)
            ) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $$dateVariable)
        ) {
            $$dateVariable = '';
        }
    }
}

// Clear reversed date ranges
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    $fromDate = '';
    $toDate = '';
}

// Pagination settings
$allowedLimits = [5, 10, 25, 50];
$limit = (int)($_GET['limit'] ?? 5);

if (!in_array($limit, $allowedLimits, true)) {
    $limit = 5;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$searchTerm = '%' . $search . '%';

$searchActive = $search !== '' ? '1' : '';
$fromActive = $fromDate !== '' ? '1' : '';
$toActive = $toDate !== '' ? '1' : '';

$whereSql = "
    WHERE (? = '' OR name LIKE ? OR email LIKE ?)
      AND (? = '' OR DATE(created_at) >= ?)
      AND (? = '' OR DATE(created_at) <= ?)
";

// Count matching entries
$countSql = "SELECT COUNT(*) AS total FROM entries " . $whereSql;

$countStmt = $conn->prepare($countSql);

$countStmt->bind_param(
    "sssssss",
    $searchActive,
    $searchTerm,
    $searchTerm,
    $fromActive,
    $fromDate,
    $toActive,
    $toDate
);

$countStmt->execute();

$totalRows = (int)$countStmt->get_result()->fetch_assoc()['total'];

$totalPages = max(1, (int)ceil($totalRows / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

// Fetch entries
$sql = "SELECT * FROM entries "
     . $whereSql
     . " ORDER BY " . $allowedSorts[$sort]
     . " LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssssssii",
    $searchActive,
    $searchTerm,
    $searchTerm,
    $fromActive,
    $fromDate,
    $toActive,
    $toDate,
    $limit,
    $offset
);

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>View Entries - Library Management System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F5ECD3;
            color: #173E28;
        }

        .navbar {
            background-color: #173E28;
            padding: 15px 0;
        }

        .navbar-brand,
        .navbar-nav .nav-link {
            color: #F5ECD3;
        }

        .navbar-brand {
            font-weight: 600;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: #D7BC7F;
        }

        .page-header {
            background: linear-gradient(135deg, #F5ECD3, #E9D9B5);
            padding: 45px 20px;
        }

        .section-card {
            background-color: #FFFAF0;
            border: none;
            border-radius: 16px;
        }

        .table thead th {
            background-color: #173E28;
            color: #FFFFFF;
            white-space: nowrap;
        }

        .table {
            vertical-align: middle;
            margin-bottom: 0;
        }

        /* Sortable table headings */
        #entriesTable th.sortable {
            cursor: pointer;
            user-select: none;
            transition: background-color 0.2s ease;
        }

        #entriesTable th.sortable:hover {
            background-color: #2C553B;
            color: #D7BC7F;
        }

        #entriesTable th .sort-icon {
            margin-left: 5px;
            opacity: 0.75;
        }

        /* Hover highlighting */
        #entriesTable tbody tr:hover > * {
            background-color: #F5ECD3;
            transition: background-color 0.2s ease;
        }

        /* Updated row highlight */
        #entriesTable tbody tr.table-success > * {
            background-color: #D1E7DD !important;
        }

        .btn-green {
            background-color: #2C553B;
            color: #FFFFFF;
            border-radius: 8px;
        }

        .btn-green:hover {
            background-color: #173E28;
            color: #FFFFFF;
        }

        .modal-content {
            background-color: #FFFAF0;
            border: none;
            border-radius: 16px;
        }

        .modal-header {
            background-color: #173E28;
            color: #FFFFFF;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Fixed notification that doesn't change scroll position */
        .update-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1090;
            width: min(420px, calc(100vw - 40px));
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }

        footer {
            background-color: #173E28;
            color: #F5ECD3;
            padding: 18px 10px;
        }
    </style>
</head>

<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">

        <a class="navbar-brand" href="index.html">
            <i class="bi bi-book-half me-2"></i>Library Management System
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto">

                <li class="nav-item">
                    <a class="nav-link" href="index.html">
                        <i class="bi bi-house-door me-1"></i>Home
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="form.html">
                        <i class="bi bi-ui-checks me-1"></i>Form
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="about.html">
                        <i class="bi bi-info-circle me-1"></i>About
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="contact.html">
                        <i class="bi bi-envelope me-1"></i>Contact Us
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="admin_logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </li>

            </ul>
        </div>
    </div>
</nav>

<!-- Page Header -->
<section class="page-header text-center">
    <div class="container">
        <i class="bi bi-journal-bookmark-fill fs-1"></i>

        <h1 class="fw-bold mt-3">All Entries</h1>

        <p class="mb-0">
            View the information submitted through the entry form.
        </p>
    </div>
</section>

<!-- Entries Table -->
<main class="container py-5">

    <div class="card section-card shadow-sm p-3 p-md-4"
         id="entries-table">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

            <h4 class="fw-bold mb-0">
                <i class="bi bi-database me-2"></i>Entry Records
            </h4>

            <a href="form.html" class="btn btn-green">
                <i class="bi bi-plus-circle me-2"></i>Add New Entry
            </a>

        </div>

        <!-- Update notification -->
        <div id="updateMessage"
             class="alert d-none update-notification"
             role="alert"
             aria-live="polite"></div>

        <!-- Search and Filters -->
        <form method="GET" action="view.php#entries-table" class="mb-4">

            <div class="row g-3">

                <div class="col-12">
                    <label for="search" class="form-label fw-semibold">
                        Search by name or email
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="form-control"
                        placeholder="Enter a name or email..."
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>

                <div class="col-md-6">
                    <label for="from_date" class="form-label fw-semibold">
                        From date
                    </label>

                    <input
                        type="date"
                        id="from_date"
                        name="from_date"
                        class="form-control"
                        value="<?= htmlspecialchars($fromDate) ?>"
                    >
                </div>

                <div class="col-md-6">
                    <label for="to_date" class="form-label fw-semibold">
                        To date
                    </label>

                    <input
                        type="date"
                        id="to_date"
                        name="to_date"
                        class="form-control"
                        value="<?= htmlspecialchars($toDate) ?>"
                    >
                </div>

                <div class="col-md-6">
                    <label for="sort" class="form-label fw-semibold">
                        Sort entries
                    </label>

                    <select id="sort" name="sort" class="form-select">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                            Newest first
                        </option>

                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>
                            Oldest first
                        </option>

                        <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>
                            Name: A–Z
                        </option>

                        <option value="za" <?= $sort === 'za' ? 'selected' : '' ?>>
                            Name: Z–A
                        </option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="limit" class="form-label fw-semibold">
                        Entries per page
                    </label>

                    <select id="limit" name="limit" class="form-select">
                        <?php foreach ([5, 10, 25, 50] as $optionLimit): ?>
                            <option
                                value="<?= $optionLimit ?>"
                                <?= $limit === $optionLimit ? 'selected' : '' ?>>
                                <?= $optionLimit ?> entries
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">

                    <button type="submit" class="btn btn-green">
                        <i class="bi bi-funnel me-1"></i>Apply Filters
                    </button>

                    <a href="view.php#entries-table"
                       class="btn btn-outline-secondary">
                        Clear Filters
                    </a>

                </div>
            </div>
        </form>

        <?php if ($result && $result->num_rows > 0): ?>

            <!-- Responsive Table -->
            <div class="table-responsive">

                <table class="table table-striped table-hover table-bordered"
                       id="entriesTable">

                    <thead>
                        <tr>
                            <th class="sortable" data-column="0" data-type="number" scope="col">
                                ID <i class="bi bi-arrow-down-up sort-icon"></i>
                            </th>

                            <th class="sortable" data-column="1" data-type="text" scope="col">
                                Name <i class="bi bi-arrow-down-up sort-icon"></i>
                            </th>

                            <th class="sortable" data-column="2" data-type="text" scope="col">
                                Email <i class="bi bi-arrow-down-up sort-icon"></i>
                            </th>

                            <th class="sortable" data-column="3" data-type="text" scope="col">
                                Phone <i class="bi bi-arrow-down-up sort-icon"></i>
                            </th>

                            <th class="sortable" data-column="4" data-type="date" scope="col">
                                Created At <i class="bi bi-arrow-down-up sort-icon"></i>
                            </th>

                            <th scope="col">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$row['id']) ?></td>

                                <td><?= htmlspecialchars($row['name']) ?></td>

                                <td><?= htmlspecialchars($row['email']) ?></td>

                                <td><?= htmlspecialchars($row['phone']) ?></td>

                                <td><?= htmlspecialchars($row['created_at']) ?></td>

                                <td class="text-nowrap">

                                    <!-- Edit Button -->
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm edit-entry-btn"
                                        data-id="<?= (int)$row['id'] ?>"
                                        data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-email="<?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-phone="<?= htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal">
                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                    </button>

                                    <!-- Delete Button -->
                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm delete-entry-btn"
                                        data-id="<?= (int)$row['id'] ?>"
                                        data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteModal">
                                        <i class="bi bi-trash me-1"></i>Delete
                                    </button>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>

                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">

                <span>
                    Page <?= $page ?> of <?= $totalPages ?>
                    (<?= $totalRows ?> entries)
                </span>

                <div class="d-flex gap-2">

                    <?php if ($page > 1): ?>
                        <a class="btn btn-green"
                           href="?search=<?= urlencode($search) ?>&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>&sort=<?= urlencode($sort) ?>&limit=<?= $limit ?>&page=<?= $page - 1 ?>#entries-table">
                            <i class="bi bi-arrow-left me-1"></i>Previous
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-green"
                           href="?search=<?= urlencode($search) ?>&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>&sort=<?= urlencode($sort) ?>&limit=<?= $limit ?>&page=<?= $page + 1 ?>#entries-table">
                            Next<i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    <?php endif; ?>

                </div>
            </div>

        <?php elseif ($result): ?>

            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle me-2"></i>
                No entries found. Submit an entry using the form to see it here.
            </div>

        <?php else: ?>

            <div class="alert alert-danger mb-0">
                Unable to retrieve entries. Please check your database connection.
            </div>

        <?php endif; ?>

    </div>
</main>

<!-- Edit Confirmation Modal -->
<div class="modal fade" id="editModal" tabindex="-1"
     aria-labelledby="editModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Entry
                </h5>

                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <form id="editForm">
                <div class="modal-body">

                    <div id="editFormError"
                         class="alert alert-danger d-none"
                         role="alert"></div>

                    <input type="hidden" id="editEntryId" name="id">

                    <div class="mb-3">
                        <label for="editName" class="form-label">Name</label>

                        <!-- Name -->
                        <input
                            type="text"
                            class="form-control"
                            id="editName"
                            name="name"
                            required
                            minlength="3"
                            maxlength="255"
                            oninput="this.value = this.value.toUpperCase()"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>

                        <input
                            type="email"
                            class="form-control"
                            id="editEmail"
                            name="email"
                            required
                            maxlength="255"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="editPhone" class="form-label">Phone</label>

                        <input
                            type="tel"
                            class="form-control"
                            id="editPhone"
                            name="phone"
                            required
                            inputmode="numeric"
                            pattern="[0-9]{10}"
                            maxlength="10"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
                        >
                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-green"
                            id="saveEditButton">
                        <i class="bi bi-check-circle me-1"></i>Save Changes
                    </button>

                </div>
            </form>

        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1"
     aria-labelledby="deleteModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Confirm Deletion
                </h5>

                <button type="button" class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">
                Are you sure you want to delete
                <strong id="deleteEntryName">this entry</strong>?

                <p class="text-danger small mt-2 mb-0">
                    This action cannot be undone.
                </p>
            </div>

            <div class="modal-footer">

                <button type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                    Cancel
                </button>

                <form method="POST" action="delete.php" id="deleteForm">
                    <input type="hidden" name="id" id="deleteEntryId">

                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Yes, Delete
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="text-center">
    <p class="mb-0">
        Developed by Rukaiya Ramzan - B7 | 2026
    </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Client-side sorting for rows currently displayed
document.querySelectorAll('#entriesTable th.sortable').forEach(function (header) {

    header.dataset.direction = 'asc';
    header.setAttribute('tabindex', '0');
    header.setAttribute('aria-label', 'Sort by ' + header.textContent.trim());

    function sortTable() {
        const table = document.getElementById('entriesTable');
        const tbody = table.querySelector('tbody');
        const column = Number(header.dataset.column);
        const type = header.dataset.type;
        const direction = header.dataset.direction;

        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort(function (rowA, rowB) {
            const valueA = rowA.cells[column].textContent.trim();
            const valueB = rowB.cells[column].textContent.trim();

            let comparison = 0;

            if (type === 'number') {
                comparison = valueA.localeCompare(valueB, undefined, {
                    numeric: true
                });
            } else if (type === 'date') {
                comparison = new Date(valueA).getTime() -
                             new Date(valueB).getTime();
            } else {
                comparison = valueA.localeCompare(valueB, undefined, {
                    numeric: true,
                    sensitivity: 'base'
                });
            }

            return direction === 'asc' ? comparison : -comparison;
        });

        rows.forEach(function (row) {
            tbody.appendChild(row);
        });

        table.querySelectorAll('th.sortable .sort-icon').forEach(function (icon) {
            icon.className = 'bi bi-arrow-down-up sort-icon';
        });

        const icon = header.querySelector('.sort-icon');

        icon.className = direction === 'asc'
            ? 'bi bi-sort-up sort-icon'
            : 'bi bi-sort-down sort-icon';

        header.dataset.direction = direction === 'asc' ? 'desc' : 'asc';
    }

    header.addEventListener('click', sortTable);

    header.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            sortTable();
        }
    });
});

// Fill the Delete modal
document.querySelectorAll('.delete-entry-btn').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('deleteEntryId').value = this.dataset.id;

        document.getElementById('deleteEntryName').textContent =
            this.dataset.name || 'this entry';
    });
});

// Edit modal elements
const editModal = document.getElementById('editModal');
const editForm = document.getElementById('editForm');
const editError = document.getElementById('editFormError');
const saveEditButton = document.getElementById('saveEditButton');
const updateMessage = document.getElementById('updateMessage');

// Fill the Edit modal with the selected record
document.querySelectorAll('.edit-entry-btn').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('editEntryId').value = this.dataset.id;
        document.getElementById('editName').value = this.dataset.name;
        document.getElementById('editEmail').value = this.dataset.email;
        document.getElementById('editPhone').value = this.dataset.phone;

        editError.classList.add('d-none');
        editError.textContent = '';
    });
});

// Submit changes through POST without reloading the page
editForm.addEventListener('submit', async function (event) {
    event.preventDefault();
        const name = document.getElementById('editName');
        const email = document.getElementById('editEmail');
        const phone = document.getElementById('editPhone');

        name.value = name.value.trim().toUpperCase();
        email.value = email.value.trim();
        phone.value = phone.value.trim();

        if (name.value.length < 3) {
            name.setCustomValidity('Name must contain at least 3 characters.');
        } else {
            name.setCustomValidity('');
        }

        if (!email.validity.valid) {
            email.reportValidity();
            return;
        }

        if (!/^[0-9]{10}$/.test(phone.value)) {
            phone.setCustomValidity('Phone number must contain exactly 10 digits.');
            phone.reportValidity();
            phone.setCustomValidity('');
            return;
        }

        if (!editForm.checkValidity()) {
            editForm.reportValidity();
            return;
        }

    saveEditButton.disabled = true;
    saveEditButton.innerHTML =
        '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    editError.classList.add('d-none');
    editError.textContent = '';
    updateMessage.classList.add('d-none');

    try {
        const response = await fetch('update.php', {
            method: 'POST',
            body: new FormData(editForm),
            headers: {
                'Accept': 'application/json'
            }
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            editError.textContent =
                result.message || 'Unable to update this entry.';

            editError.classList.remove('d-none');
            return;
        }

        const entry = result.entry;

        // Locate the matching row
        const rows = document.querySelectorAll('#entriesTable tbody tr');

        const row = Array.from(rows).find(function (currentRow) {
            return currentRow.cells[0].textContent.trim() ===
                   String(entry.id);
        });

        if (row) {
            // Update visible values immediately
            row.cells[1].textContent = entry.name;
            row.cells[2].textContent = entry.email;
            row.cells[3].textContent = entry.phone;

            // Update the Edit button's saved values
            const editButton = row.querySelector('.edit-entry-btn');

            if (editButton) {
                editButton.dataset.name = entry.name;
                editButton.dataset.email = entry.email;
                editButton.dataset.phone = entry.phone;
            }

            // Briefly highlight the changed record
            row.classList.add('table-success');

            setTimeout(function () {
                row.classList.remove('table-success');
            }, 2500);
        }

        // Close modal
        bootstrap.Modal.getOrCreateInstance(editModal).hide();

        // Show success notification without scrolling or reloading
        updateMessage.textContent = result.message;
        updateMessage.className =
            'alert alert-success update-notification';

        // Hide the message after 3 seconds
        setTimeout(function () {
            updateMessage.classList.add('d-none');
        }, 3000);

    } catch (error) {
        editError.textContent =
            'Something went wrong. Please try again.';

        editError.classList.remove('d-none');

    } finally {
        saveEditButton.disabled = false;
        saveEditButton.innerHTML =
            '<i class="bi bi-check-circle me-1"></i>Save Changes';
    }
});
</script>

</body>
</html>

<?php
$stmt->close();
$countStmt->close();
$conn->close();
?>