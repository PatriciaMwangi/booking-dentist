<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/chunker.php';
require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/vector_store.php';

use PrinsFrank\PdfParser\PdfParser;

$documentsDirectory = __DIR__ . '/documents';

if (!is_dir($documentsDirectory)) {
    mkdir($documentsDirectory, 0775, true);
}

$pdfFiles = glob($documentsDirectory . '/*.pdf');

if ($pdfFiles === false || count($pdfFiles) === 0) {
    echo "No PDF documents found.\n";
    exit(0);
}

$pdo = getRagDatabase();

initializeRagDatabase($pdo);

$parser = new PdfParser();

foreach ($pdfFiles as $pdfPath) {

    $filename = basename($pdfPath);

    echo "\n";
    echo "========================================\n";
    echo "Indexing: {$filename}\n";
    echo "========================================\n";

    try {

        $document = $parser->parseFile($pdfPath);

        $text = $document->getText();

        if (trim($text) === '') {
            echo "No text extracted. Skipping.\n";
            continue;
        }

        $chunks = chunkText($text);

        echo "Chunks: " . count($chunks) . "\n";

        $documentId = addDocument(
            $pdo,
            $filename
        );

        foreach ($chunks as $index => $chunk) {

            echo sprintf(
                "Embedding chunk %d/%d...",
                $index + 1,
                count($chunks)
            );

            $embedding = ollamaEmbed($chunk);

            addChunk(
                $pdo,
                $documentId,
                $index + 1,
                $chunk,
                $embedding
            );

            echo " OK\n";
        }

        echo "\nSuccessfully indexed {$filename}.\n";

    } catch (Throwable $e) {

        echo "\nERROR:\n";
        echo $e->getMessage() . "\n";

        exit(1);
    }
}

echo "\n";
echo "========================================\n";
echo "INDEXING COMPLETE\n";
echo "========================================\n";

$count = $pdo
    ->query("SELECT COUNT(*) FROM chunks")
    ->fetchColumn();

echo "Total stored chunks: {$count}\n";
echo "Database: " . RAG_DATABASE . "\n";
