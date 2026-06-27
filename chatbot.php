<?php
require 'vendor/autoload.php';

use Google\Cloud\AIPlatform\V1\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient as PredictionClient;
use Google\Protobuf\Value;

function askGeminiChatbot($userInput) {
    // Project configuration
    $projectId = 'dentistassistant-500509';
    $location = 'us-central1'; // Or your preferred region
    $modelId = 'gemini-1.5-flash'; // Quick and cost-effective for chat

    // Initialize the client (It automatically picks up the Render secret file)
    $client = new PredictionServiceClient();
    
    $endpoint = PredictionClient::endpointName($projectId, $location, $modelId);

    // Structure the prompt with your safety rules integrated
    $systemInstruction = "You are a helpful dental clinic assistant. Only answer general dental info. Do not accept personal or health data.";
    $fullPrompt = $systemInstruction . "\n\nUser: " . $userInput;

    // Format the payload structure required by Vertex AI
    $instance = new Value();
    $instance->setStructValue(new \Google\Protobuf\Struct([
        'fields' => [
            'content' => (new Value())->setStringValue($fullPrompt)
        ]
    ]));

    try {
        $response = $client->predict($endpoint, [$instance], []);
        $predictions = $response->getPredictions();
        
        // Extract the text response out of the Google Protobuf object
        return $predictions[0]->getStructValue()->getFields()['content']->getStringValue();
    } catch (Exception $e) {
        return "Sorry, I'm having trouble connecting right now.";
    }
}