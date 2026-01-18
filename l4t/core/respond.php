<?php
header('Content-Type: application/json');

// TODO: auth check
$data = json_decode(file_get_contents('php://input'), true);

if (!$data['request_id'] || !$data['text']) {
    echo json_encode(['success' => false]);
    exit;
}

// здесь позже:
// INSERT INTO responses (...)

echo json_encode(['success' => true]);
