<?php
// Scan a quotation image (Create Quotation page). Returns JSON:
// {ok: true, extraction: {...}, match: {client_id, items: [catalog id or null, ...]}}
// or {ok: false, error: "..."}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/quotation_scan.php';

header('Content-Type: application/json');

function scanResponse($status, $body) {
    http_response_code($status);
    echo json_encode($body);
    exit();
}

if (!isLoggedIn()) {
    scanResponse(401, ['ok' => false, 'error' => 'Your session has expired. Please log in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken()) {
    scanResponse(403, ['ok' => false, 'error' => 'Invalid request. Please reload the page and try again.']);
}
if (!isQuotationScanEnabled()) {
    scanResponse(503, ['ok' => false, 'error' => 'Quotation scanning is not set up on this server.']);
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    scanResponse(400, ['ok' => false, 'error' => 'Please choose an image of the quotation.']);
}
if ($file['size'] > QUOTATION_SCAN_MAX_BYTES) {
    scanResponse(400, ['ok' => false, 'error' => 'The image is larger than 5 MB.']);
}
$mediaType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mediaType, QUOTATION_SCAN_TYPES, true)) {
    scanResponse(400, ['ok' => false, 'error' => 'Please upload a JPG, PNG, WEBP or GIF image.']);
}

// The session is not needed while waiting for the scan; release it so other tabs stay responsive
session_write_close();
set_time_limit(180);

try {
    $extraction = extractQuotationFromImage(file_get_contents($file['tmp_name']), $mediaType);
} catch (RuntimeException $e) {
    scanResponse(502, ['ok' => false, 'error' => $e->getMessage()]);
}

scanResponse(200, [
    'ok' => true,
    'extraction' => $extraction,
    'match' => matchScannedQuotation(getDBConnection(), $extraction),
]);
