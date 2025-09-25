<?php
require_once '../config/db.php';
check_role('shelter');
$page_title = 'Add New Pet';

$success_message = '';
$error_message = '';

// Get shelter info
try {
    $stmt = $pdo->prepare("SELECT * FROM shelters WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $shelter = $stmt->fetch();
    
    if (!$shelter) {
        redirect('../auth/login.php');
    }
} catch(PDOException $e) {
    redirect('../auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $species = sanitize_input($_POST['species']);
    $breed = sanitize_input($_POST['breed']);
    $age = (int)$_POST['age'];
    $gender = sanitize_input($_POST['gender']);
    $size = sanitize_input($_POST['size']);
    $color = sanitize_input($_POST['color']);
    $description = sanitize_input($_POST['description']);
    $medical_history = sanitize_input($_POST['medical_history']);
    $special_needs = sanitize_input($_POST['special_needs']);
    $adoption_fee = (float)$_POST['adoption_fee'];
    
    if (empty($name) || empty($species) || empty($gender) || empty($size) || $age <= 0) {
        $error_message = "Please fill in all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO pets (shelter_id, name, species, breed, age, gender, size, color, description, medical_history, special_needs, adoption_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$shelter['id'], $name, $species, $breed, $age, $gender, $size, $color, $description, $medical_history, $special_needs, $adoption_fee]);
            
            $success_message = "Pet added successfully! You can now add vaccination records if needed.";
            
            // Clear form data
            $_POST = [];
        } catch(PDOException $e) {
            $error_message = "Error adding pet: " . $e->getMessage();
        }
    }
}

include '../common/header.php';
include '../common/navbar_shelter.php';
?>

<main class="main-content">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 class="page-title">Add New Pet</h1>
                <a href="/shelter/viewPets.php" class="btn btn-secondary">View All Pets</a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                    <div style="margin-top: 1rem;">
                        <a href="/shelter/addPet.php" class="btn">Add Another Pet</a>
                        <a href="/shelter/viewPets.php" class="btn btn-secondary">View All Pets</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h2 style="font-size: 1.25rem; color: #1f2937;">Pet Information</h2>
                        <p style="color: #6b7280; margin-top: 0.5rem;">Fill in the details for the new pet</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="addPetForm">
                            <div class="grid grid-cols-2">
                                <div class="form-group">
                                    <label for="name" class="form-label">Pet Name *</label>
                                    <input type="text" id="name" name="name" class="form-input" required
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                           placeholder="Enter pet's name">
                                </div>

                                <div class="form-group">
                                    <label for="species" class="form-label">Species *</label>
                                    <select id="species" name="species" class="form-input form-select" required>
                                        <option value="">Select species</option>
                                        <option value="dog" <?php echo (isset($_POST['species']) && $_POST['species'] === 'dog') ? 'selected' : ''; ?>>Dog</option>
                                        <option value="cat" <?php echo (isset($_POST['species']) && $_POST['species'] === 'cat') ? 'selected' : ''; ?>>Cat</option>
                                        <option value="bird" <?php echo (isset($_POST['species']) && $_POST['species'] === 'bird') ? 'selected' : ''; ?>>Bird</option>
                                        <option value="rabbit" <?php echo (isset($_POST['species']) && $_POST['species'] === 'rabbit') ? 'selected' : ''; ?>>Rabbit</option>
                                        <option value="other" <?php echo (isset($_POST['species']) && $_POST['species'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2">
                                <div class="form-group">
                                    <label for="breed" class="form-label">Breed</label>
                                    <input type="text" id="breed" name="breed" class="form-input"
                                           value="<?php echo isset($_POST['breed']) ? htmlspecialchars($_POST['breed']) : ''; ?>"
                                           placeholder="Enter breed (if known)">
                                </div>

                                <div class="form-group">
                                    <label for="age" class="form-label">Age (years) *</label>
                                    <input type="number" id="age" name="age" class="form-input" required min="0" max="30" step="0.5"
                                           value="<?php echo isset($_POST['age']) ? $_POST['age'] : ''; ?>"
                                           placeholder="Enter age in years">
                                </div>
                            </div>

                            <div class="grid grid-cols-3">
                                <div class="form-group">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select id="gender" name="gender" class="form-input form-select" required>
                                        <option value="">Select gender</option>
                                        <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="size" class="form-label">Size *</label>
                                    <select id="size" name="size" class="form-input form-select" required>
                                        <option value="">Select size</option>
                                        <option value="small" <?php echo (isset($_POST['size']) && $_POST['size'] === 'small') ? 'selected' : ''; ?>>Small</option>
                                        <option value="medium" <?php echo (isset($_POST['size']) && $_POST['size'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                        <option value="large" <?php echo (isset($_POST['size']) && $_POST['size'] === 'large') ? 'selected' : ''; ?>>Large</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="color" class="form-label">Color</label>
                                    <input type="text" id="color" name="color" class="form-input"
                                           value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : ''; ?>"
                                           placeholder="Primary color">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="adoption_fee" class="form-label">Adoption Fee ($)</label>
                                <input type="number" id="adoption_fee" name="adoption_fee" class="form-input" min="0" step="0.01"
                                       value="<?php echo isset($_POST['adoption_fee']) ? $_POST['adoption_fee'] : '0.00'; ?>"
                                       placeholder="0.00">
                            </div>

                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea id="description" name="description" class="form-input form-textarea"
                                          placeholder="Describe the pet's personality, behavior, and any other relevant information..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="medical_history" class="form-label">Medical History</label>
                                <textarea id="medical_history" name="medical_history" class="form-input form-textarea"
                                          placeholder="Include any known medical conditions, treatments, or health concerns..."><?php echo isset($_POST['medical_history']) ? htmlspecialchars($_POST['medical_history']) : ''; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="special_needs" class="form-label">Special Needs</label>
                                <textarea id="special_needs" name="special_needs" class="form-input form-textarea"
                                          placeholder="Any special care requirements, dietary restrictions, or behavioral considerations..."><?php echo isset($_POST['special_needs']) ? htmlspecialchars($_POST['special_needs']) : ''; ?></textarea>
                            </div>

                            <div style="display: flex; gap: 1rem; justify-content: end;">
                                <a href="/shelter/viewPets.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn" onclick="handleSubmit(this)">
                                    Add Pet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function handleSubmit(button) {
    if (validateForm('addPetForm')) {
        showLoading(button);
    }
}
</script>

<?php include '../common/footer.php'; ?>