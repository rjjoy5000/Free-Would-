<?php
/**
 * Custom Provider
 * Supports: Dynamic API calls with configurable request/response format
 */

function makeRequest(string $endpoint, array $data, string $apiKey, string $method = 'POST'): array
{
    $ch = curl_init($endpoint);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("cURL error: $error");
    }

    $result = json_decode($response, true);

    if ($httpCode >= 400) {
        $errorMsg = $result['error']['message'] ?? $result['error'] ?? $result['message'] ?? "HTTP $httpCode error";
        throw new Exception($errorMsg);
    }

    return $result ?? [];
}

function mapResponse(array $response, array $fieldMap): array
{
    $mapped = [];
    foreach ($fieldMap as $key => $path) {
        $value = $response;
        $parts = explode('.', $path);
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                $value = null;
                break;
            }
        }
        $mapped[$key] = $value;
    }
    return $mapped;
}

function generateImage(string $prompt, array $options, string $apiKey): array
{
    $endpoint = $options['endpoint'] ?? '';
    if (empty($endpoint)) {
        throw new Exception('No endpoint configured for custom provider');
    }

    $requestTemplate = $options['request_template'] ?? ['prompt' => '{prompt}'];
    $responseMap = $options['response_map'] ?? ['image_url' => 'output'];

    // Replace placeholders in template
    $requestData = json_decode(str_replace(
        ['{prompt}', '{negative_prompt}', '{size}', '{model}'],
        [
            $prompt,
            $options['negative_prompt'] ?? '',
            $options['size'] ?? '1024x1024',
            $options['model'] ?? '',
        ],
        json_encode($requestTemplate)
    ), true);

    $response = makeRequest($endpoint, $requestData, $apiKey);
    return mapResponse($response, $responseMap);
}

function chatCompletion(array $messages, array $options, string $apiKey): array
{
    $endpoint = $options['endpoint'] ?? '';
    if (empty($endpoint)) {
        throw new Exception('No endpoint configured for custom provider');
    }

    $requestTemplate = $options['request_template'] ?? [
        'messages' => '{messages}',
        'model' => '{model}',
        'max_tokens' => '{max_tokens}',
    ];

    $responseMap = $options['response_map'] ?? [
        'content' => 'choices.0.message.content',
        'tokens_used' => 'usage.total_tokens',
    ];

    $requestData = json_decode(str_replace(
        ['{messages}', '{model}', '{max_tokens}', '{temperature}'],
        [
            json_encode($messages),
            $options['model'] ?? '',
            $options['max_tokens'] ?? 2048,
            $options['temperature'] ?? 0.7,
        ],
        json_encode($requestTemplate)
    ), true);

    $response = makeRequest($endpoint, $requestData, $apiKey);
    return mapResponse($response, $responseMap);
}
