<?php

declare(strict_types=1);

require_once __DIR__ . '/ollama.php';

$text = 'Dental health is important for maintaining healthy teeth and gums.';

echo "Sending text to Ollama...\n";
echo "Model: " . EMBEDDING_MODEL . "\n\n";

try {
    $embedding = ollamaEmbed($text);

    echo "SUCCESS!\n";
    echo "Embedding dimensions: " . count($embedding) . "\n";

    echo "\nFirst 10 values:\n";

    foreach (array_slice($embedding, 0, 10) as $value) {
        echo $value . "\n";
    }

} catch (Throwable $e) {
    echo "\nERROR:\n";
    echo $e->getMessage() . "\n";

    exit(1);
}
