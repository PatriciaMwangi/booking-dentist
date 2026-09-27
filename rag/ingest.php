<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/chunker.php';

use PrinsFrank\PdfParser\PdfParser;

$documentsDirectory = __DIR__ . '/documents';

if (!is_dir($documentsDirectory)) {
    mkdir($documentsDirectory, 0775, true);
}

$pdfFiles = glob($documentsDirectory . '/*.pdf');

if ($pdfFiles === false || count($pdfFiles) === 0) {
    echo "No PDF documents found.\n";
    echo "Put your PDF files in:\n";
    echo $documentsDirectory . "\n";
    exit(0);
}

$parser = new PdfParser();

$totalChunks = 0;

foreach ($pdfFiles as $pdfPath) {
    $filename = basename($pdfPath);

    echo "\n";
    echo "========================================\n";
    echo "Processing: {$filename}\n";
    echo "========================================\n";

    try {
        $document = $parser->parseFile($pdfPath);

        $text = $document->getText();

        if (trim($text) === '') {
            echo "WARNING: No text extracted.\n";
            continue;
        }

        $chunks = chunkText($text);

        echo "Extracted characters: " . mb_strlen($text) . "\n";
        echo "Created chunks: " . count($chunks) . "\n";

        foreach ($chunks as $index => $chunk) {
            echo sprintf(
                "  Chunk %d: %d characters\n",
                $index + 1,
                mb_strlen($chunk)
            );
        }

        $totalChunks += count($chunks);

    } catch (Throwable $e) {
        echo "ERROR processing {$filename}:\n";
        echo $e->getMessage() . "\n";
    }
}

echo "\n";
echo "========================================\n";
echo "INGESTION TEST COMPLETE\n";
echo "Total chunks: {$totalChunks}\n";
echo "========================================\n";
