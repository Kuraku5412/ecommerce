<?php
// Start session
session_start();

// Include database connection
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has admin role
$userID = $_SESSION['user_id'];
$sql = "SELECT Role FROM users WHERE UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    if ($row['Role'] !== 'admin') {
        // Redirect non-admin users
        header("Location: login.php");
        exit();
    }
} else {
    // User not found
    session_destroy();
    header("Location: homepage.php");
    exit();
}

// Check if product id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid product ID.";
    header("Location: products.php");
    exit();
}

$productId = $_GET['id'];

// Get all categories for the dropdown
$categoriesQuery = "SELECT id, category_name FROM categories ORDER BY category_name";
$categories = $conn->query($categoriesQuery);

// Get product details
$productQuery = "SELECT * FROM products WHERE ProductID = ?";
$stmt = $conn->prepare($productQuery);
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    $_SESSION['error_message'] = "Product not found.";
    header("Location: products.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $productName = $_POST['product_name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $sizes = $_POST['sizes'];
    $stock = $_POST['stock'];
    $categoryId = $_POST['category'];
    
    // Prepare the SQL statement for updating product
    $sql = "UPDATE products SET ProductName = ?, Description = ?, Price = ?, sizes = ?, StockQuantity = ?, categories = ? WHERE ProductID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdsiii", $productName, $description, $price, $sizes, $stock, $categoryId, $productId);
    
    if ($stmt->execute()) {
        // Handle image uploads (up to 4 images)
        $uploadDir = 'uploads/products/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Get existing images
        $existingImages = json_decode($product['images'], true) ?: [];
        
        // Process each uploaded file
        for ($i = 1; $i <= 4; $i++) {
            $fileInputName = 'product_image_' . $i;
            
            if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
                $tempFile = $_FILES[$fileInputName]['tmp_name'];
                $fileName = time() . '_' . $i . '_' . basename($_FILES[$fileInputName]['name']);
                $targetFile = $uploadDir . $fileName;
                
                // Check if it's a valid image file
                $imageInfo = getimagesize($tempFile);
                if ($imageInfo !== false) {
                    // Move uploaded file to target directory
                    if (move_uploaded_file($tempFile, $targetFile)) {
                        // If we're replacing an existing image, remove the old one at this position
                        if (isset($existingImages[$i-1]) && file_exists($existingImages[$i-1])) {
                            unlink($existingImages[$i-1]);
                        }
                        
                        // Add or replace the image at this position
                        $existingImages[$i-1] = $targetFile;
                    }
                }
            }
        }
        
        // Update image paths if there were changes
        $imagesJson = json_encode(array_values($existingImages));
        $updateImagesQuery = "UPDATE products SET images = ? WHERE ProductID = ?";
        $imgStmt = $conn->prepare($updateImagesQuery);
        $imgStmt->bind_param("si", $imagesJson, $productId);
        $imgStmt->execute();
        
        $_SESSION['success_message'] = "Product updated successfully!";
        header("Location: products.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating product: " . $conn->error;
    }
}

// Get existing image paths
$productImages = json_decode($product['images'], true) ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
        }
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            min-height: 100vh;
            background: #343a40;
            color: #fff;
            transition: all 0.3s;
        }
        #sidebar.active {
            margin-left: -250px;
        }
        #sidebar .sidebar-header {
            padding: 20px;
            background: #212529;
        }
        #sidebar ul.components {
            padding: 20px 0;
            border-bottom: 1px solid #4b545c;
        }
        #sidebar ul p {
            color: #fff;
            padding: 10px;
        }
        #sidebar ul li a {
            padding: 10px;
            font-size: 1.1em;
            display: block;
            color: #fff;
            text-decoration: none;
        }
        #sidebar ul li a:hover {
            color: #000;
            background: #fff;
        }
        #sidebar ul li.active > a,
        a[aria-expanded="true"] {
            color: #fff;
            background: #6d7fcc;
        }
        ul ul a {
            font-size: 0.9em !important;
            padding-left: 30px !important;
            background: #4b545c;
        }
        .wrapper {
            display: flex;
            width: 100%;
        }
        #content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }
            #sidebar.active {
                margin-left: 0;
            }
            #sidebarCollapse span {
                display: none;
            }
        }
        .image-preview {
            width: 150px;
            height: 150px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            background-color: #f8f9fa;
            position: relative;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
        }
        .delete-image {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="admin.php">Home</a>
                </li>
                <li class="active">
                    <a href="#productSubmenu" data-bs-toggle="collapse" aria-expanded="true" class="dropdown-toggle">Products</a>
                    <ul class="collapse show list-unstyled" id="productSubmenu">
                        <li>
                            <a href="products.php">View All Products</a>
                        </li>
                        <li>
                            <a href="add_product.php">Add New Product</a>
                        </li>
                    </ul>
                </li>
                <li>
                    <a href="#categorySubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">Categories</a>
                    <ul class="collapse list-unstyled" id="categorySubmenu">
                        <li>
                            <a href="categories.php">View All Categories</a>
                        </li>
                        <li>
                    </ul>
                </li>
                <li>
                    <a href="#userSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">Users</a>
                    <ul class="collapse list-unstyled" id="userSubmenu">
                        <li>
                            <a href="users.php">View All Users</a>
                        </li>
                    </ul>
                </li>
                <li>
                    <a href="#orderSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">Orders</a>
                    <ul class="collapse list-unstyled" id="orderSubmenu">
                        <li>
                            <a href="orders.php">View All Orders</a>
                        </li>
                        <li>
                            <a href="order_details.php">Order Details</a>
                        </li>
                    </ul>
                </li>
                <li>
                    <a href="logout.php">Logout</a>
                </li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-dark">
                        <i class="fas fa-align-left"></i>
                        <span>Toggle Sidebar</span>
                    </button>
                    <div>
                        <h4>Welcome, <?php echo $_SESSION['Username'] ?? 'Admin'; ?></h4>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <h2 class="mb-4">Edit Product</h2>
                
                <?php if(isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Product Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="edit_product.php?id=<?php echo $productId; ?>" method="post" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="product_name" class="form-label">Product Name</label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product['ProductName']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo $product['Price']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="stock" class="form-label">Stock Quantity</label>
                                        <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?php echo $product['StockQuantity']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">Select Category</option>
                                            <?php while($category = $categories->fetch_assoc()): ?>
                                                <option value="<?php echo $category['id']; ?>" <?php echo ($product['categories'] == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sizes" class="form-label">Available Sizes (comma separated)</label>
                                        <input type="text" class="form-control" id="sizes" name="sizes" placeholder="S,M,L,XL" value="<?php echo htmlspecialchars($product['sizes']); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($product['Description']); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Product Images (Max 4)</label>
                                        <div class="row">
                                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                                <div class="col-md-6 mb-3">
                                                    <label for="product_image_<?php echo $i; ?>" class="form-label">Image <?php echo $i; ?></label>
                                                    <input type="file" class="form-control" id="product_image_<?php echo $i; ?>" name="product_image_<?php echo $i; ?>" accept="image/*" onchange="previewImage(this, <?php echo $i; ?>)">
                                                    <div class="image-preview mt-2" id="image_preview_<?php echo $i; ?>">
                                                        <?php if (isset($productImages[$i-1]) && !empty($productImages[$i-1])): ?>
                                                            <img src="<?php echo htmlspecialchars($productImages[$i-1]); ?>" alt="Product Image <?php echo $i; ?>">
                                                            <div class="delete-image" onclick="removeImage(<?php echo $i; ?>, <?php echo $productId; ?>)">
                                                                <i class="fas fa-times"></i>
                                                            </div>
                                                        <?php else: ?>
                                                            <img src="#" alt="Preview" style="display: none;">
                                                            <span class="text-muted">No image selected</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="products.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Product</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            document.getElementById('sidebarCollapse').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('active');
            });
        });
        
        // Image preview function
        function previewImage(input, index) {
            const preview = document.querySelector(`#image_preview_${index} img`);
            const previewText = document.querySelector(`#image_preview_${index} span`);
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    if (previewText) {
                        previewText.style.display = 'none';
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Remove image function
        function removeImage(imageIndex, productId) {
            if (confirm('Are you sure you want to remove this image?')) {
                // Send AJAX request to remove the image
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'remove_product_image.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (this.status === 200) {
                        const response = JSON.parse(this.responseText);
                        if (response.success) {
                            // Update the image preview
                            const imagePreview = document.querySelector(`#image_preview_${imageIndex}`);
                            const img = imagePreview.querySelector('img');
                            const deleteIcon = imagePreview.querySelector('.delete-image');
                            
                            img.style.display = 'none';
                            img.src = '#';
                            if (deleteIcon) {
                                deleteIcon.remove();
                            }
                            
                            // Add "No image selected" text
                            if (!imagePreview.querySelector('span')) {
                                const span = document.createElement('span');
                                span.className = 'text-muted';
                                span.textContent = 'No image selected';
                                imagePreview.appendChild(span);
                            } else {
                                imagePreview.querySelector('span').style.display = 'block';
                            }
                            
                            alert('Image removed successfully');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                };
                xhr.send(`product_id=${productId}&image_index=${imageIndex-1}`);
            }
        }
    </script>
</body>
</html>