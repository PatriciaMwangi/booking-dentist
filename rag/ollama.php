<?php

declare(strict_types=1);

const OLLAMA_BASE_URL = 'http://127.0.0.1:11434';

const EMBEDDING_MODEL = 'embeddinggemma:latest';
const CHAT_MODEL = 'qwen3:8b';


/**
 * Generate an embedding vector for text using Ollama.
 *
 * @return array<float>
 */
function ollamaEmbed(string $text): array
{
    $url = OLLAMA_BASE_URL . '/api/embed';

    $payload = [
        'model' => EMBEDDING_MODEL,
        'input' => $text,
    ];

    $response = ollamaRequest($url, $payload);

    if (
        !isset($response['embeddings']) ||
        !is_array($response['embeddings']) ||
        !isset($response['embeddings'][0])
    ) {
        throw new RuntimeException(
            'Ollama did not return an embedding.'
        );
    }

    return $response['embeddings'][0];
}


/**
 * Send a chat request to the local Qwen model.
 */
function ollamaChat(
    string $systemPrompt,
    string $userPrompt
): string {
    $url = OLLAMA_BASE_URL . '/api/chat';

    $payload = [
        'model' => CHAT_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ],
        'stream' => false,
        'options' => [
        'temperature' => 0.2,
    ],
    ];

    $response = ollamaRequest($url, $payload);
//     echo "\nDEBUG OLLAMA RESPONSE:\n";
// echo json_encode(
//     $response,
//     JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
// );
// echo "\nEND DEBUG\n";

  if (!isset($response['message']) || !is_array($response['message'])) {
    throw new RuntimeException(
        'Ollama did not return a valid message: ' .
        json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

$content = $response['message']['content'] ?? '';

if (trim($content) === '') {
    throw new RuntimeException(
        'Ollama returned an empty answer. Full response: ' .
        json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

return $content;
}


/**
 * Generic POST request to Ollama.
 */
function ollamaRequest(
    string $url,
    array $payload
): array {
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException(
            'Could not initialize cURL.'
        );
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 300,
    ]);

    $rawResponse = curl_exec($ch);

    if ($rawResponse === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new RuntimeException(
            'Ollama connection failed: ' . $error
        );
    }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException(
            "Ollama returned HTTP {$httpCode}: {$rawResponse}"
        );
    }

    $decoded = json_decode(
        $rawResponse,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'Invalid JSON returned by Ollama.'
        );
    }

    return $decoded;
}
