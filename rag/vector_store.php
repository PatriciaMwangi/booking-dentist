<?php

declare(strict_types=1);

const RAG_DATABASE = __DIR__ . '/data/rag.sqlite';

/**
 * Get the SQLite connection.
 */
function getRagDatabase(): PDO
{
    $databaseDirectory = dirname(RAG_DATABASE);

    if (!is_dir($databaseDirectory)) {
        mkdir($databaseDirectory, 0775, true);
    }

    $pdo = new PDO(
        'sqlite:' . RAG_DATABASE
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    return $pdo;
}


/**
 * Create the RAG database tables.
 */
function initializeRagDatabase(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chunks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            chunk_index INTEGER NOT NULL,
            content TEXT NOT NULL,
            embedding TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (document_id)
                REFERENCES documents(id)
                ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_chunks_document
        ON chunks(document_id)
    ");
}


/**
 * Add a document.
 */
function addDocument(
    PDO $pdo,
    string $filename
): int {
    $stmt = $pdo->prepare("
        INSERT INTO documents (filename)
        VALUES (:filename)
    ");

    $stmt->execute([
        ':filename' => $filename
    ]);

    return (int) $pdo->lastInsertId();
}


/**
 * Add a chunk and its embedding.
 */
function addChunk(
    PDO $pdo,
    int $documentId,
    int $chunkIndex,
    string $content,
    array $embedding
): int {
    $stmt = $pdo->prepare("
        INSERT INTO chunks (
            document_id,
            chunk_index,
            content,
            embedding
        )
        VALUES (
            :document_id,
            :chunk_index,
            :content,
            :embedding
        )
    ");

    $stmt->execute([
        ':document_id' => $documentId,
        ':chunk_index' => $chunkIndex,
        ':content' => $content,
        ':embedding' => json_encode(
            $embedding,
            JSON_THROW_ON_ERROR
        )
    ]);

    return (int) $pdo->lastInsertId();
}


/**
 * Retrieve all chunks with their embeddings.
 */
function getAllChunks(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            chunks.id,
            chunks.document_id,
            chunks.chunk_index,
            chunks.content,
            chunks.embedding,
            documents.filename
        FROM chunks
        INNER JOIN documents
            ON documents.id = chunks.document_id
        ORDER BY
            documents.id,
            chunks.chunk_index
    ");

    return $stmt->fetchAll();
}


/**
 * Calculate cosine similarity between two vectors.
 */
function cosineSimilarity(
    array $vectorA,
    array $vectorB
): float {
    if (count($vectorA) !== count($vectorB)) {
        throw new InvalidArgumentException(
            'Vectors must have the same dimensions.'
        );
    }

    $dotProduct = 0.0;
    $magnitudeA = 0.0;
    $magnitudeB = 0.0;

    $count = count($vectorA);

    for ($i = 0; $i < $count; $i++) {
        $a = (float) $vectorA[$i];
        $b = (float) $vectorB[$i];

        $dotProduct += $a * $b;
        $magnitudeA += $a * $a;
        $magnitudeB += $b * $b;
    }

    if ($magnitudeA == 0.0 || $magnitudeB == 0.0) {
        return 0.0;
    }

    return $dotProduct /
        (sqrt($magnitudeA) * sqrt($magnitudeB));
}


/**
 * Search the knowledge base.
 *
 * @return array<int, array<string, mixed>>
 */
function searchChunks(
    PDO $pdo,
    array $queryEmbedding,
    int $limit = 3
): array {
    $rows = getAllChunks($pdo);

    $results = [];

    foreach ($rows as $row) {
        $embedding = json_decode(
            $row['embedding'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $score = cosineSimilarity(
            $queryEmbedding,
            $embedding
        );

        $results[] = [
            'id' => (int) $row['id'],
            'document_id' => (int) $row['document_id'],
            'chunk_index' => (int) $row['chunk_index'],
            'filename' => $row['filename'],
            'content' => $row['content'],
            'score' => $score,
        ];
    }

    usort(
        $results,
        static fn(array $a, array $b): int =>
            $b['score'] <=> $a['score']
    );

    return array_slice($results, 0, $limit);
}
