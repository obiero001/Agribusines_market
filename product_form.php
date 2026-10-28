<?php
session_start();
require_once 'config.php';

// Authorization check: Must be logged in as Farmer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Farmer') {
    header('Location: login.php');
    exit;
}

$farmer_name = $_SESSION['user_name'];

// Fetch categories for selection
$categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $categories_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DanFam Market</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 2.5rem auto;
            background: white;
            border: 1px solid var(--gray-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .form-body {
            padding: 2.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.15);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }
        .help-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.3rem;
        }
    </style>
</head>
<body>
    <header>
        <a href="catalogue.php" class="logo">DanFam Market</a>
        <nav>
            <a href="catalogue.php">Catalogue</a>
            <a href="farmer_dashboard.php">Farmer Portal</a>
            <a href="logout.php">Logout (<?= htmlspecialchars($farmer_name) ?>)</a>
        </nav>
    </header>

    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <h2> List New Farm Produce</h2>
                <p style="opacity: 0.9; margin-top: 0.5rem; font-size: 0.95rem;">Fill out the details below to make your harvest visible to verified buyers across Kenya.</p>
            </div>

            <div class="form-body">
                <form action="process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_produce">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Produce Name</label>
                            <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Fresh Sukuma Wiki / Cabbages">
                            <div class="help-text">Specify crop variety if applicable (e.g Hass Avocados).</div>
                        </div>

                        <div class="form-group">
                            <label for="category_id">Produce Category</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">-- Select Category --</option>
                               <option value="Vegetables" <?= (isset($_POST['category']) && $_POST['category'] === 'Vegetables') ? 'selected' : '' ?>> Fresh Vegetables</option>
                               <option value="Fruits" <?= (isset($_POST['category']) && $_POST['category'] === 'Fruits') ? 'selected' : '' ?>>Fruits & Berries</option>
                              <option value="Cereals & Grains" <?= (isset($_POST['category']) && $_POST['category'] === 'Cereals & Grains') ? 'selected' : '' ?>>Cereals & Grains (Maize, Rice, Beans)</option>
                              <option value="Tubers & Roots" <?= (isset($_POST['category']) && $_POST['category'] === 'Tubers & Roots') ? 'selected' : '' ?>>Tubers & Roots (Potatoes, Cassava)</option>
                             <option value="Dairy & Livestock" <?= (isset($_POST['category']) && $_POST['category'] === 'Dairy & Livestock') ? 'selected' : '' ?>>Dairy & Livestock Products</option>
                             <option value="Poultry & Eggs" <?= (isset($_POST['category']) && $_POST['category'] === 'Poultry & Eggs') ? 'selected' : '' ?>>Poultry & Eggs</option>
                             <option value="Fish & Aquaculture" <?= (isset($_POST['category']) && $_POST['category'] === 'Fish & Aquaculture') ? 'selected' : '' ?>>Fish & Aquaculture</option>
                             <option value="Cash Crops" <?= (isset($_POST['category']) && $_POST['category'] === 'Cash Crops') ? 'selected' : '' ?>>Cash Crops (Tea, Coffee, Sugarcane)</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Choose the most appropriate farming category.</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price per Unit (KSh)</label>
                            <input type="number" step="0.01" id="price" name="price" class="form-control" required placeholder="50.00">
                            <div class="help-text">Enter your expected selling price in Kenyan Shillings.</div>
                        </div>

                        <div class="form-group">
                            <label for="quantity">Available Quantity</label>
                            <input type="number" step="0.1" id="quantity" name="quantity" class="form-control" required placeholder="200">
                            <div class="help-text">Total stock currently ready for dispatch.</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="unit">Unit of Measure</label>
                            <select id="unit" name="unit" class="form-control" required>
                                <option value="kg">Kilogram (kg)</option>
                                <option value="bag">Bag</option>
                                <option value="crate">Crate</option>
                                <option value="piece">Piece</option>
                                <option value="tonne">Tonne</option>
                                
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="location">Farm Location / County</label>
                            <input type="text" id="location" name="location" class="form-control" required placeholder="e.g. Kisii, kisumu, migori">
                            <div class="help-text">Region or county where produce is harvested.</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 0.5rem;">
                        <label for="photo">Produce Image Upload</label>
                        <input type="file" id="photo" name="photo" accept="image/*" class="form-control" style="background: var(--gray-light); padding: 0.6rem;">
                        <div class="help-text">Upload a clear photo (JPG, PNG, WEBP) to attract buyers instantly.</div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2.5rem; border-top: 1px solid var(--gray-border); padding-top: 1.5rem;">
                        <a href="farmer_dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>
                        <button type="submit" class="btn btn-accent" style="padding: 0.75rem 2rem;">Submit Listing for Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 DanFam Market. Farmer Portal.</p>
    </footer>
</body>
</html>