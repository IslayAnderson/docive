<?php

function paperless_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function paperless_get(string $path): array
{
    $config = paperless_config();
    $ch = curl_init(rtrim($config['paperless_base_url'], '/') . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Token ' . $config['paperless_api_token']]);
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException('Paperless request failed: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

function paperless_post_json(string $path, array $data): array
{
    $config = paperless_config();
    $ch = curl_init(rtrim($config['paperless_base_url'], '/') . $path);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $config['paperless_api_token'],
        'Content-Type: application/json',
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException('Paperless request failed: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

function paperless_post_multipart(string $path, array $fields): array
{
    $config = paperless_config();
    $ch = curl_init(rtrim($config['paperless_base_url'], '/') . $path);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Token ' . $config['paperless_api_token']]);
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException('Paperless request failed: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

function paperless_list_document_types(): array
{
    $names = [];
    $path = '/api/document_types/?page_size=100';

    while ($path) {
        $result = paperless_get($path);
        $data = json_decode($result['body'], true);
        if (!isset($data['results'])) {
            break;
        }
        foreach ($data['results'] as $type) {
            $names[] = $type['name'];
        }
        $next = $data['next'] ?? null;
        $path = $next ? substr($next, strpos($next, '/api/')) : null;
    }

    sort($names);
    return $names;
}

function paperless_get_or_create_document_type(string $name): int
{
    $result = paperless_get('/api/document_types/?name=' . urlencode($name));
    $data = json_decode($result['body'], true);
    foreach ($data['results'] ?? [] as $type) {
        if (strcasecmp($type['name'], $name) === 0) {
            return $type['id'];
        }
    }
    $created = paperless_post_json('/api/document_types/', ['name' => $name]);
    $data = json_decode($created['body'], true);
    if (!isset($data['id'])) {
        throw new RuntimeException('Failed to create Paperless document type: ' . $created['body']);
    }
    return $data['id'];
}

function paperless_upload_document(string $filePath, string $title, ?int $documentTypeId): string
{
    $fields = [
        'document' => new CURLFile($filePath, 'application/pdf', basename($filePath)),
        'title' => $title,
    ];
    if ($documentTypeId) {
        $fields['document_type'] = $documentTypeId;
    }
    $result = paperless_post_multipart('/api/documents/post_document/', $fields);
    if ($result['status'] >= 300) {
        throw new RuntimeException('Paperless upload failed (' . $result['status'] . '): ' . $result['body']);
    }
    return trim($result['body'], "\" \n");
}
