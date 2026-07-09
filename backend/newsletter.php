<?php
require_once 'config.php';
header('Content-Type: application/json');

function logNews($msg)
{
    file_put_contents(__DIR__ . '/newsletter_debug.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

function sendThankYouEmail($email)
{
    $subject = "Welcome to the OMG Family! 🌸";

    // HTML Message
    $message = "
    <html>
    <head>
        <style>
            .email-container { font-family: 'Georgia', serif; color: #1a1a1a; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
            .header { background: #1a1a1a; padding: 40px; text-align: center; }
            .content { padding: 40px; line-height: 1.6; }
            .button { display: inline-block; background: #c5a044; color: white !important; padding: 12px 30px; text-decoration: none; border-radius: 30px; font-weight: bold; margin-top: 20px; }
            .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1 style='color: #c5a044; margin: 0;'>OMG</h1>
                <p style='color: #e5e7eb; font-style: italic; margin: 5px 0 0;'>Oh My Gudness</p>
            </div>
            <div class='content'>
                <h2 style='color: #1a1a1a;'>Thank you for joining us!</h2>
                <p>Hello,</p>
                <p>We're thrilled to have you as part of our community. At <strong>OMG (Oh My Gudness)</strong>, we believe in crafting more than just gifts; we create unforgettable moments.</p>
                <p>As a subscriber, you'll be the first to receive:</p>
                <ul style='color: #4b5563;'>
                    <li>Exclusive access to new collections</li>
                    <li>Member-only offers and floral tips</li>
                    <li>Inspiration for your next big surprise</li>
                </ul>
                <a href='https://mediumspringgreen-loris-371923.hostingersite.com' class='button'>Explore Our Collections</a>
            </div>
            <div class='footer'>
                <p>&copy; 2026 OMG (Oh My Gudness) | Bangalore, Karnataka</p>
                <p>If you have any questions, reply to this email or visit our website.</p>
            </div>
        </div>
    </body>
    </html>";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: krappu203@gmail.com" . "\r\n";
    $headers .= "Reply-To: krappu203@gmail.com" . "\r\n";

    $res = @mail($email, $subject, $message, $headers);
    logNews("Mail to $email sent (HTML): " . ($res ? "Success" : "Failed"));
    return $res;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        logNews("DB Connection failed");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // Auto-create table if not exists (Safety)
    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    logNews("Request Method: " . $_SERVER['REQUEST_METHOD']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        logNews("Input: " . $input);

        $data = json_decode($input, true);
        $email = trim($data['email'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            logNews("Invalid email: $email");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
            exit();
        }

        // Check if already subscribed
        $checkStmt = $db->prepare("SELECT id FROM newsletter_subscribers WHERE email = :email");
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            logNews("Already subscribed: $email");
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'This email is already subscribed.']);
            exit();
        }

        // Insert into database
        $stmt = $db->prepare("INSERT INTO newsletter_subscribers (email) VALUES (:email)");
        $stmt->bindParam(':email', $email);

        if ($stmt->execute()) {
            logNews("Saved successfully: $email");
            sendThankYouEmail($email);
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Thank you for subscribing!']);
        }
        else {
            logNews("Insert failed for: $email");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save subscription.']);
        }
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
