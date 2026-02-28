<?php
$pageTitle = "Module Test Catalogue";
require "../config/database.php";
require "../config/header.php";

// ---- Fetch products for filter ----
$productsStmt = $pdo->query("
    SELECT id, product_code, description
    FROM products
    WHERE active = 1
    AND type = 'module'
    ORDER BY product_code
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Capture filters ----
$filter_product = $_GET['product_id'] ?? '';
$search = trim($_GET['search'] ?? '');

// ---- Build query dynamically ----
$sql = "
SELECT 
    mt.id,
    mt.product_id,
    p.product_code,
    p.description AS product_desc,
    mt.test_name,
    mt.test_order,
    mt.min_value,
    mt.max_value,
    mt.unit,
    mt.required,
    mt.created_at
FROM module_tests mt
JOIN products p ON mt.product_id = p.id
WHERE 1=1
";

$params = [];

// Product filter
if ($filter_product !== '') {
    $sql .= " AND mt.product_id = ?";
    $params[] = $filter_product;
}

// Search filter (test name)
if ($search !== '') {
    $sql .= " AND mt.test_name LIKE ?";
    $params[] = "%" . $search . "%";
}

$sql .= " ORDER BY p.product_code, mt.test_order ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Module Test Catalogue</h3>
        <a href="module_test_create.php" class="btn btn-primary">+ Create New Test</a>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">
            Test created successfully.
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card p-3 mb-3">
        <form method="get" class="row g-3 align-items-end">

            <div class="col-md-4">
                <label class="form-label">Filter by Product</label>
                <select name="product_id" class="form-select">
                    <option value="">All Products</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"
                            <?= ($filter_product == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['product_code']) ?>
                            <?= $p['description'] ? " — " . htmlspecialchars($p['description']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Search Test Name</label>
                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="e.g. IV, EL, Insulation..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">Apply</button>
            </div>

            <div class="col-md-2">
                <a href="module_test_list.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>

        </form>
    </div>

    <!-- Results Table -->
    <div class="card p-3">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Order</th>
                    <th>Test Name</th>
                    <th>Limits</th>
                    <th>Unit</th>
                    <th>Required</th>
                    <th>Created</th>
                    <th style="width: 160px;">Actions</th>
                </tr>
            </thead>
            <tbody>

            <?php if (empty($tests)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        No tests found for this filter.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tests as $t): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($t['product_code']) ?></strong><br>
                            <small class="text-muted">
                                <?= htmlspecialchars($t['product_desc'] ?? '') ?>
                            </small>
                        </td>

                        <td><?= (int)$t['test_order'] ?></td>

                        <td><?= htmlspecialchars($t['test_name']) ?></td>

                        <td>
                            <?php
                            if ($t['min_value'] !== null || $t['max_value'] !== null) {
                                $min = $t['min_value'] !== null ? $t['min_value'] : "—";
                                $max = $t['max_value'] !== null ? $t['max_value'] : "—";
                                echo htmlspecialchars($min) . " → " . htmlspecialchars($max);
                            } else {
                                echo "<span class='text-muted'>No limits</span>";
                            }
                            ?>
                        </td>

                        <td><?= htmlspecialchars($t['unit'] ?? '—') ?></td>

                        <td>
                            <?php if ($t['required']): ?>
                                <span class="badge bg-success">Required</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Optional</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= date("Y-m-d", strtotime($t['created_at'])) ?>
                        </td>

                        <td>
                            <a href="module_test_edit.php?id=<?= $t['id'] ?>"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>

                            <a href="module_test_view.php?id=<?= $t['id'] ?>"
                               class="btn btn-sm btn-outline-secondary">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>

            </tbody>
        </table>
    </div>

</div>

<?php require "../config/footer.php"; ?>