<?php
$pageTitle = "Create New User";
require "../config/database.php";
require "../config/header.php";

// Initialize variables
$username = $password = $full_name = $role_id = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $role_id = intval($_POST['role_id']);

    if (empty($username) || empty($password) || empty($role_id)) {
        $error = "Username, password, and role are required.";
    } else {
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert into the database
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role_id) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$username, $password_hash, $full_name, $role_id]);
            $success = "User created successfully!";
            // Clear form values
            $username = $password = $full_name = $role_id = '';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                $error = "Username already exists.";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}
?>

<h1>Create User</h1>

<?php if ($error): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<?php if ($success): ?>
    <p style="color:green;"><?php echo htmlspecialchars($success); ?></p>
<?php endif; ?>

<form method="post" action="">
    <label>Username:<br>
        <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
    </label><br><br>

    <label>Password:<br>
        <input type="password" name="password" required>
    </label><br><br>

    <label>Full Name:<br>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>">
    </label><br><br>

    <label>Role ID:<br>
        <input type="number" name="role_id" value="<?php echo htmlspecialchars($role_id); ?>" required>
    </label><br><br>

    <button class="btn btn-primary" type="submit">Create User</button>
</form>

<?php require "../config/footer.php"; ?>