<?php
// فعال کردن نمایش تمام خطاها برای عیب‌یابی
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background: #111; color: #eee; padding: 20px; direction: ltr; text-align: left; font-family: monospace;'>";
echo "<h1>Avatar Fetching Debugger</h1>";

require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/database.php';

session_start();

// تابع کمکی (برای اینکه اسکریپت مستقل باشد)
function get_discord_avatar_url_from_bot($discord_id) {
    echo "--- Attempting to call bot for discord_id: " . htmlspecialchars($discord_id) . " ---\n";
    if (empty($discord_id)) {
        echo "Function received an empty discord_id. Aborting.\n";
        return null;
    }
    $webhook_url = 'http://83.149.95.39:1030/get-avatar-url?discord_id=' . urlencode($discord_id);
    $secret_token = '4a97dd86-4388-4cc0-a54f-65ebbf51649d';
    
    echo "Webhook URL: " . htmlspecialchars($webhook_url) . "\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhook_url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret_token]
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    echo "Bot responded with HTTP Code: " . $http_code . "\n";
    if ($curl_error) echo "cURL Error: " . htmlspecialchars($curl_error) . "\n";
    echo "Bot Response Body: " . htmlspecialchars($response) . "\n";

    if ($http_code == 200) {
        $data = json_decode($response, true);
        $avatar_url = $data['avatar_url'] ?? null;
        echo "Successfully parsed avatar URL: " . ($avatar_url ? htmlspecialchars($avatar_url) : "Not Found") . "\n";
        return $avatar_url;
    }
    echo "--- Bot call failed or returned non-200 code. ---\n";
    return null;
}

echo "<h2>Step 1: Checking Session</h2>";
if (!isset($_SESSION['user_id'])) {
    die("FAILURE: User is not logged in. Session 'user_id' not set.");
}
echo "Session check passed. Session data:\n";
print_r($_SESSION);
echo "\n";

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'user';
$is_staff = ($user_type === 'staff');

echo "<h2>Step 2: Checking User Type</h2>";
if (!$is_staff) {
    die("STOP: User is not a staff member. (user_type is '$user_type')");
}
echo "SUCCESS: User is a staff member.\n\n";


echo "<h2>Step 3: Fetching Data from 'staff-manage' Table</h2>";
try {
    $conn = getDbConnection();
    echo "Database connection successful.\n";

    $stmt = $conn->prepare("SELECT discord_conn, discord_id2 FROM `staff-manage` WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        die("FAILURE: Failed to prepare statement. DB Error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "Query executed for user_id: " . htmlspecialchars($user_id) . "\n";

    if ($staff_info = $result->fetch_assoc()) {
        echo "SUCCESS: Found a record for this user in staff-manage table.\n";
        echo "Database Record:\n";
        print_r($staff_info);
        echo "\n";

        echo "<h2>Step 4: Checking 'discord_id2'</h2>";
        if (!empty($staff_info['discord_id2'])) {
            echo "SUCCESS: 'discord_id2' is not empty. Value: " . htmlspecialchars($staff_info['discord_id2']) . "\n\n";
            
            echo "<h2>Step 5: Calling the Bot</h2>";
            $avatar_url = get_discord_avatar_url_from_bot($staff_info['discord_id2']);

            if($avatar_url) {
                echo "\n<h2>Final Result: SUCCESS</h2>";
                echo "Final proxy URL would be: admin/image_proxy.php?url=" . htmlspecialchars(urlencode($avatar_url));
            } else {
                 echo "\n<h2>Final Result: FAILURE</h2>";
                 echo "Could not retrieve avatar URL from bot.";
            }

        } else {
            die("FAILURE: The 'discord_id2' column is empty or NULL in the database for this user. The bot will not be called.");
        }
    } else {
        die("FAILURE: No record found in 'staff-manage' table for user_id: " . htmlspecialchars($user_id));
    }
    $stmt->close();
} catch (Exception $e) {
    die("FAILURE: A database exception occurred: " . $e->getMessage());
}

echo "</pre>";