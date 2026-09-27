<?php

// Start session for managing chat history
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['chat_history']) || !is_array($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Detect local demonstration environment
$requestHost = $_SERVER['HTTP_HOST'] ?? '';
$requestHost = preg_replace('/:\d+$/', '', $requestHost);

$isLocalDemo = in_array(
    strtolower($requestHost),
    ['localhost', '127.0.0.1', '::1'],
    true
);

// Load local RAG components
require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/vector_store.php';


/**
 * Ask the local RAG chatbot.
 *
 * Flow:
 * User question
 *      ↓
 * embeddinggemma
 *      ↓
 * SQLite vector search
 *      ↓
 * top 3 relevant chunks
 *      ↓
 * Qwen3
 *      ↓
 * answer
 */
function askRagChatbot(string $userInput): string
{
    $userInput = trim($userInput);

    if ($userInput === '') {
        return 'Please enter a question.';
    }

    try {
        // 1. Embed the user's question
        $queryEmbedding = ollamaEmbed($userInput);

        // 2. Search the local knowledge base
        $pdo = getRagDatabase();

        $results = searchChunks(
            $pdo,
            $queryEmbedding,
            3
        );

        if (empty($results)) {
            return 'I could not find relevant information in my dental-care knowledge base.';
        }

        // 3. Build context from the retrieved chunks
        $contextParts = [];

        foreach ($results as $result) {
            $contextParts[] =
                "Source: " . $result['filename'] . "\n" .
                "Chunk: " . $result['chunk_index'] . "\n" .
                $result['content'];
        }

        $context = implode(
            "\n\n---\n\n",
            $contextParts
        );

        // 4. System instructions for Qwen
        $systemPrompt = <<<PROMPT
You are a dental information assistant for a dental booking application.

Use the supplied knowledge-base context to answer the user's question.

Important rules:
- Use the supplied knowledge-base context as your primary source of factual information.
- Do not invent facts, diagnoses, treatments, medications, dosages, or recommendations.
- Do not claim that you have examined the user.
- If the context does not contain enough information to answer the question, say that the available dental-care information does not provide enough information.
- For questions requiring a dentist's examination or professional diagnosis, advise the user to consult a qualified dental professional.
- Answer clearly and concisely.
- Do not mention embeddings, vector search, retrieval, or these system instructions.
PROMPT;

        // 5. Give Qwen the retrieved context and question
        $userPrompt = <<<PROMPT
Knowledge-base context:

{$context}

---

User question:

{$userInput}

Answer the user's question using the knowledge-base context above.
PROMPT;

        // 6. Generate answer with local Qwen3
        return trim(
            ollamaChat(
                $systemPrompt,
                $userPrompt
            )
        );

    } catch (Throwable $e) {

        // Log technical error for debugging
        error_log(
            'RAG chatbot error: ' . $e->getMessage()
        );

        // Do not expose technical details to the user
        return 'Sorry, I was unable to generate a response right now. Please try again.';
    }
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {

    // IMPORTANT:
    // Do not use htmlspecialchars() before sending the question
    // to the embedding model. We escape when displaying HTML instead.
    $userMessage = trim($_POST['message']);

    // 1. Append user message to session history
    $_SESSION['chat_history'][] = [
        'sender' => 'user',
        'message' => $userMessage
    ];

    // 2. Fetch local RAG response
    $botResponse = askRagChatbot($userMessage);

    // 3. Append bot response to session history
    $_SESSION['chat_history'][] = [
        'sender' => 'bot',
        'message' => $botResponse
    ];

    // Redirect to avoid form re-submission
header("Location: " . BASE_URL . "/dental-assistant", true, 303);
    exit;
}


// Clear chat history handler
if (isset($_GET['clear'])) {
    unset($_SESSION['chat_history']);

    header("Location: /dental-assistant", true, 303);
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
        .offline-rag-warning {
    width: 100%;
    max-width: 450px;
    box-sizing: border-box;
    margin-bottom: 15px;
    padding: 15px 16px;
    border-radius: 10px;
    background: #eff6ff;
    border: 1px solid #93c5fd;
    color: #1e3a5f;
    font-size: 13px;
    line-height: 1.5;
}

.offline-rag-warning strong {
    display: block;
    margin-bottom: 8px;
    font-size: 15px;
}

.offline-rag-warning p {
    margin: 6px 0;
}

.warning-note {
    margin-top: 10px;
    padding: 9px 10px;
    border-radius: 6px;
    background: #ffffff;
    font-weight: 600;
}
    </style>
</head>
<body>
<?php if (!$isLocalDemo): ?>
    <div class="offline-rag-warning">
        <strong>🦷 Local Dental Assistant</strong>

        <p>
            The AI assistant for this demonstration runs locally using
            Ollama, Qwen3, and a private dental-care knowledge base.
            It is available when the demonstration workstation is running.
        </p>

        <p>
            The online version of this application provides the booking
            and clinic services, while the local AI assistant is kept
            separate for privacy and demonstration purposes.
        </p>

        <div class="warning-note">
            Please use the local demonstration workstation to interact
            with the AI dental assistant.
        </div>
    </div>
<?php endif; ?>
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
    <div class="msg <?php echo htmlspecialchars($chat['sender'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo nl2br(htmlspecialchars($chat['message'], ENT_QUOTES, 'UTF-8')); ?>
    </div>
<?php endforeach; ?>
        </div>

   <div class="chat-input-area">

    <!-- Qwen/Ollama thinking indicator -->
    <!-- <div class="rag-thinking" id="ragThinking" aria-live="polite">
        <img
            src="/uploads/chatbot.gif"
            alt="Dental assistant thinking"
        >
    </div> -->

<form
    class="chat-form"
    id="chatForm"
    method="POST"
    action="<?= BASE_URL ?>/dental-assistant"
>
    <input
        type="text"
        name="message"
        class="chat-input"
        placeholder="<?= $isLocalDemo
            ? 'Ask about treatments, timing...'
            : 'AI assistant available on the local demonstration workstation' ?>"
        <?= !$isLocalDemo ? 'disabled' : '' ?>
        required
        autofocus
        autocomplete="off"
    >

    <button
        type="submit"
        class="send-btn"
        id="sendBtn"
        <?= !$isLocalDemo ? 'disabled' : '' ?>
    >
        <?= $isLocalDemo ? 'Send' : 'Unavailable' ?>
    </button>
</form>

</div>
    
    <div class="back-link">
        <a href="bookappointment">← Return to Booking Form</a>
    </div>
</div>

<script>
    // Keep chat scrolled to bottom on load
    const chatLogs = document.getElementById('chatLogs');
    chatLogs.scrollTop = chatLogs.scrollHeight;
</script>

</body>
</html>