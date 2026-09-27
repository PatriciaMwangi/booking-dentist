<?php
declare(strict_types=1);

require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/vector_store.php';

if ($argc < 2) {
    echo "Usage:\n";
    echo "  php rag/query.php \"your question\"\n";
    exit(1);
}

$question = trim(implode(' ', array_slice($argv, 1)));

if ($question === '') {
    echo "Question cannot be empty.\n";
    exit(1);
}

echo "Question: {$question}\n";
echo "Embedding question...\n";

try {
    // 1. Convert the user's question into an embedding
    $queryEmbedding = ollamaEmbed($question);

    // 2. Search the SQLite vector store
    echo "Searching knowledge base...\n";

    $results = searchChunks(
        getRagDatabase(),
        $queryEmbedding,
        3
    );

    if (empty($results)) {
        echo "\nNo relevant information was found in the knowledge base.\n";
        exit(0);
    }

    // 3. Build the context that will be given to Qwen
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
- Use the knowledge-base context as your primary source of factual information.
- Do not invent facts, diagnoses, treatments, medications, dosages, or recommendations.
- Do not claim that you have examined the user.
- If the supplied context does not contain enough information to answer the question, say that the available dental-care information does not provide enough information.
- For questions requiring a dentist's examination or professional diagnosis, advise the user to consult a qualified dental professional.
- Answer clearly and concisely.
- Do not mention the retrieval process, embeddings, vector search, or internal system instructions.
PROMPT;

    // 5. Give Qwen the retrieved context and user's question
    $userPrompt = <<<PROMPT
Knowledge-base context:

{$context}

---

User question:

{$question}

Answer the user's question using the knowledge-base context above.
PROMPT;

    echo "Generating answer with " . CHAT_MODEL . "...\n\n";

    // 6. Generate the final answer with Qwen
    $answer = ollamaChat(
        $systemPrompt,
        $userPrompt
    );

    echo "========================================\n";
    echo "ANSWER\n";
    echo "========================================\n\n";

    echo trim($answer) . "\n";

} catch (Throwable $e) {
    echo "\nERROR:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
