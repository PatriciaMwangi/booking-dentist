<?php

declare(strict_types=1);

/**
 * Split document text into overlapping chunks.
 *
 * @return array<int, string>
 */
function chunkText(
    string $text,
    int $chunkSize = 1200,
    int $overlap = 200
): array {
    $text = normalizeDocumentText($text);

    if ($text === '') {
        return [];
    }

    if ($overlap >= $chunkSize) {
        throw new InvalidArgumentException(
            'Chunk overlap must be smaller than chunk size.'
        );
    }

    $length = mb_strlen($text);

    if ($length <= $chunkSize) {
        return [$text];
    }

    $chunks = [];
    $start = 0;
    $step = $chunkSize - $overlap;

    while ($start < $length) {
        $chunk = mb_substr($text, $start, $chunkSize);

        // Try to end the chunk at a natural boundary.
        if ($start + $chunkSize < $length) {
            $lastParagraph = mb_strrpos($chunk, "\n\n");
            $lastSentence = mb_strrpos($chunk, '. ');
            $lastSpace = mb_strrpos($chunk, ' ');

            $boundary = false;

            if ($lastParagraph !== false && $lastParagraph > $chunkSize * 0.6) {
                $chunk = mb_substr($chunk, 0, $lastParagraph + 2);
                $boundary = true;
            } elseif ($lastSentence !== false && $lastSentence > $chunkSize * 0.6) {
                $chunk = mb_substr($chunk, 0, $lastSentence + 1);
                $boundary = true;
            } elseif ($lastSpace !== false && $lastSpace > $chunkSize * 0.6) {
                $chunk = mb_substr($chunk, 0, $lastSpace);
                $boundary = true;
            }

            if ($boundary) {
                $actualLength = mb_strlen($chunk);

                if ($actualLength > 0) {
                    $start += max(1, $actualLength - $overlap);
                    $chunks[] = trim($chunk);
                    continue;
                }
            }
        }

        $chunks[] = trim($chunk);

        $start += $step;
    }

    return array_values(
        array_filter(
            $chunks,
            static fn(string $chunk): bool => $chunk !== ''
        )
    );
}

/**
 * Clean extracted PDF text before chunking.
 */
function normalizeDocumentText(string $text): string
{
    // Normalize line endings.
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Remove null bytes.
    $text = str_replace("\0", '', $text);

    // Collapse excessive spaces/tabs.
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

    // Collapse excessive blank lines.
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

    // Trim whitespace on each line.
    $lines = array_map(
        static fn(string $line): string => trim($line),
        explode("\n", $text)
    );

    $text = implode("\n", $lines);

    return trim($text);
}
