<?php

declare(strict_types=1);

require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/vector_store.php';

if ($argc < 2) {
    echo "Usage:\n";
    echo "php rag/search.php \"your question\"\n";
    exit(1);
}

$question = $argv[1];

echo "Question:\n";
echo $question . "\n\n";

$pdo = getRagDatabase();

$queryEmbedding = ollamaEmbed($question);

$results = searchChunks(
    $pdo,
    $queryEmbedding,
    3
);

echo "Top results:\n";
echo "========================================\n";

foreach ($results as $index => $result) {

    echo "\n";
    echo "RESULT " . ($index + 1) . "\n";
    echo "Score: " . number_format(
        $result['score'],
        4
    ) . "\n";

    echo "Source: ";
    echo $result['filename'] . "\n";

    echo "Chunk: ";
    echo $result['chunk_index'] . "\n";

    echo "\n";
    echo $result['content'] . "\n";

    echo "\n----------------------------------------\n";
}
