<?php
// Start session for managing chat history
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Absolute path safe for local environments and Render container paths
require_once __DIR__ . '/vendor/autoload.php';

use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\PredictRequest;
use Google\Protobuf\Value;
use Google\Protobuf\Struct;

function askGeminiChatbot($userInput) {
    $projectId = 'dentistassistant-500509';
    $location = 'us-central1'; 
    $modelId = 'gemini-1.5-flash'; 

    // Create the client matching version 1.60 conventions
    $client = new PredictionServiceClient();
    
    // Format the official API endpoint string path
    $endpoint = sprintf('projects/%s/locations/%s/publishers/google/models/%s', $projectId, $location, $modelId);

    // Structure the input message text wrapper
    $promptText = "You are a helpful dental clinic assistant. Only answer general dental info. Do not accept personal or health data.\n\nUser: " . $userInput;

    // Correct payload format mapping for predict requests
    $promptStruct = new Struct();
    $promptStruct->setFields([
        'content' => (new Value())->setStringValue($promptText)
    ]);

    $instance = new Value();
    $instance->setStructValue($promptStruct);

    // Build formal V1 predict execution instance
    $request = (new PredictRequest())
        ->getEndpoint($endpoint)
        ->setInstances([$instance]);

    try {
        $response = $client->predict($request);
        $predictions = $response->getPredictions();
        
        return $predictions[0]->getStructValue()->getFields()['content']->getStringValue();
    } catch (Exception $e) {
        return "Sorry, I am having trouble connecting to my system right now.";
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $userMessage = htmlspecialchars(trim($_POST['message']));
    
    // 1. Append user message to session history
    $_SESSION['chat_history'][] = ['sender' => 'user', 'message' => $userMessage];
    
    // 2. Fetch AI response
    $botResponse = askGeminiChatbot($userMessage);
    
    // 3. Append bot response to session history
    $_SESSION['chat_history'][] = ['sender' => 'bot', 'message' => $botResponse];
    
    // Redirect to avoid form re-submission on refresh
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Clear chat history handler
if (isset($_GET['clear'])) {
    unset($_SESSION['chat_history']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Chat Support</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; display: flex; justify-content: center; }
        .chat-card { width: 100%; max-width: 450px; background: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; flex-direction: column; height: 80vh; overflow: hidden; }
        .chat-header { background: #3498db; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .chat-header h3 { margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .chat-header a { color: white; text-decoration: none; font-size: 12px; opacity: 0.8; }
        .chat-header a:hover { opacity: 1; }
        .chat-logs { flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; background: #fafafa; }
        .msg { max-width: 75%; padding: 10px 14px; border-radius: 12px; font-size: 14px; line-height: 1.4; }
        .msg.bot { background: #e2e8f0; color: #334155; align-self: flex-start; border-top-left-radius: 2px; }
        .msg.user { background: #3498db; color: white; align-self: flex-end; border-top-right-radius: 2px; }
        .chat-input-area { padding: 15px; background: white; border-top: 1px solid #e2e8f0; }
        .chat-form { display: flex; gap: 8px; }
        .chat-input { flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; outline: none; }
        .chat-input:focus { border-color: #3498db; }
        .send-btn { background: #3498db; color: white; border: none; padding: 0 16px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .send-btn:hover { background: #2980b9; }
        .back-link { text-align: center; margin-top: 10px; font-size: 12px; }
        .back-link a { color: #64748b; text-decoration: none; }
    </style>
</head>
<body>

<div style="display: flex; flex-direction: column; align-items: center; width: 100%;">
    <div class="chat-card">
        <div class="chat-header">
            <h3>
                <svg style="width: 20px; height: 20px; color: white;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <path d="M 35,30 C 35,22 45,22 45,32 L 45,45 C 45,55 55,55 55,45 L 55,32 C 55,22 65,22 65,30" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
                    <circle cx="35" cy="30" r="4" fill="currentColor"/>
                    <circle cx="65" cy="30" r="4" fill="currentColor"/>
                    <path d="M 50,50 L 50,65" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
                    <rect x="40" y="65" width="20" height="7" rx="2" fill="white" stroke="currentColor" stroke-width="2"/>
                    <circle cx="50" cy="78" r="9" fill="white" stroke="currentColor" stroke-width="3"/>
                </svg>
                Dental Assistant
            </h3>
            <a href="?clear=1">Clear Chat</a>
        </div>

        <div class="chat-logs" id="chatLogs">
            <?php foreach ($_SESSION['chat_history'] as $chat): ?>
                <div class="msg <?php echo $chat['sender']; ?>">
                    <?php echo nl2br($chat['message']); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="chat-input-area">
            <form class="chat-form" method="POST" action="">
                <input type="text" name="message" class="chat-input" placeholder="Ask about treatments, timing..." required autofocus autocomplete="off">
                <button type="submit" class="send-btn">Send</button>
            </form>
        </div>
    </div>
    
    <div class="back-link">
        <a href=" ">← Return to Booking Form</a>
    </div>
</div>

<script>
    // Keep chat scrolled to bottom on load
    const chatLogs = document.getElementById('chatLogs');
    chatLogs.scrollTop = chatLogs.scrollHeight;
</script>

</body>
</html>