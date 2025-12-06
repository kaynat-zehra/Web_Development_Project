<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// --- Database connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "whimsy_shop";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// --- Get the action ---
$action = $_GET['action'] ?? "";

// --- Get POST body ---
$rawData = file_get_contents("php://input");
$input = json_decode($rawData, true);


/* ---------------------------------------------------------
   1. FETCH PRODUCTS
--------------------------------------------------------- */
if ($action === "products") {

    $result = $conn->query("SELECT * FROM products");
    $products = [];

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['image'])) {
            $row['image'] = "images/" . $row['image'];
        }
        $products[] = $row;
    }

    echo json_encode($products);
    exit;
}


/* ---------------------------------------------------------
   2. PLACE ORDER 
--------------------------------------------------------- */
if ($action === "order") {

    if ($input === null) {
        echo json_encode(["error" => "Invalid JSON"]);
        exit;
    }

    $name = $input["customer_name"];
    $email = $input["customer_email"];
    $total = $input["total"];
    $itemsArray = $input["items"];

    $itemsText = "";
    foreach ($itemsArray as $i) {
        $itemsText .= $i["name"] . " x " . $i["quantity"] . " — Rs. " . $i["price"] . ", ";
    }
    $itemsText = rtrim($itemsText, ", ");

    $stmt = $conn->prepare("
        INSERT INTO orders (customer_name, customer_email, items, total)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("sssd", $name, $email, $itemsText, $total);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Order saved!"]);
    } else {
        echo json_encode(["error" => "Failed to save order"]);
    }
    exit;
}


// ---------------------------------------------------------
// 3. SAVE CUSTOM ORDER (robust with debug messages)
// ---------------------------------------------------------
if ($action === "custom_order") {

    if ($input === null) {
        echo json_encode(["error" => "Invalid JSON", "raw_received" => $rawData]);
        exit;
    }

    // Basic validation
    $category = $input['category'] ?? '';
    $item_type = $input['item_type'] ?? '';
    $description = $input['description'] ?? '';
    $email = $input['email'] ?? '';

    if (empty($category) || empty($item_type) || empty($email)) {
        echo json_encode(["error" => "Missing required fields", "fields" => ["category"=>$category,"item_type"=>$item_type,"email"=>$email]]);
        exit;
    }

    // Prepare insert
    $stmt = $conn->prepare("INSERT INTO custom_orders (category, item_type, description, email) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(["error" => "Prepare failed", "mysqli_error" => $conn->error]);
        exit;
    }

    if (!$stmt->bind_param("ssss", $category, $item_type, $description, $email)) {
        echo json_encode(["error" => "Bind failed", "stmt_error" => $stmt->error]);
        exit;
    }

    if (!$stmt->execute()) {
        echo json_encode(["error" => "Execute failed", "stmt_error" => $stmt->error]);
        exit;
    }

    // success
    echo json_encode(["success" => true, "message" => "Custom order saved", "insert_id" => $stmt->insert_id]);
    exit;
}



/* ---------------------------------------------------------
   DEFAULT
--------------------------------------------------------- */
echo json_encode(["message" => "API running"]);
?>
