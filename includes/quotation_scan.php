<?php
/*
 * "Scan quotation": Claude reads a photo or screenshot of a quotation and returns its
 * details as JSON, which the Create Quotation page uses to fill in the form.
 * Requires ANTHROPIC_API_KEY (environment variable) and the Composer packages
 * (composer install).
 */

require_once __DIR__ . '/../config.php';

define('QUOTATION_SCAN_MODEL', 'claude-opus-5-5');
define('QUOTATION_SCAN_MAX_BYTES', 5 * 1024 * 1024); // the API's limit for one image
define('QUOTATION_SCAN_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

function isQuotationScanEnabled() {
    return trim(getenv('ANTHROPIC_API_KEY') ?: '') !== '' && file_exists(__DIR__ . '/../vendor/autoload.php');
}

function quotationScanPrompt() {
    return <<<'PROMPT'
Role: You are an expert OCR and data-extraction system for a Sales & Procurement platform.

Your task is to analyze the uploaded quotation image, extract the required information, and return structured JSON that will be used to automatically fill the quotation form.

INSTRUCTIONS:

1. Carefully analyze the entire quotation image.
2. Extract only information that is clearly visible in the quotation.
3. Do not guess, assume, calculate, or invent missing information.
4. If a field is missing, unreadable, obscured, or not visible, return null.
5. Extract every populated item row from the quotation table.
6. Ignore completely blank table rows.
7. Preserve item descriptions exactly as shown whenever possible.
8. Return quantities and monetary values as numbers.
9. Remove currency symbols such as ₱ and remove commas from monetary values.
10. Convert clearly readable dates to YYYY-MM-DD format.
11. Use the exact total shown in the quotation.

FIELD MAPPING:

- "customer" = value beside "Customer:"
- "address" = value beside "Address:"
- "date" = value beside "Date:"
- "quote_number" = value beside "Quote #"
- "stock_no" = value under "Stock No."
- "item_description" = value under "Item Description"
- "unit" = value under "Unit"
- "quantity" = value under "Qty"
- "unit_cost" = value under "Unit Cost"
- "total_cost" = value under "Total Cost"
- "total" = final quotation total shown at the bottom

IMPORTANT VALIDATION RULES:

- Only create an item object for a row containing actual item information.
- Do not create item objects for blank rows.
- Do not calculate "total_cost" from quantity × unit_cost when the value is missing. Return null instead.
- Do not calculate the final "total" if it is missing. Return null.
- Keep product measurements, specifications, symbols, and model information in "item_description".
- Do not confuse Stock No. with quantity.
- Do not confuse Unit Cost with Total Cost.
- The JSON keys must always remain exactly the same because they are mapped directly to form fields in the system.
PROMPT;
}

// The response must match this schema exactly (structured outputs), so the keys never change
function quotationScanSchema() {
    $nullable = function ($type) {
        return ['anyOf' => [['type' => $type], ['type' => 'null']]];
    };
    $item = [
        'type' => 'object',
        'properties' => [
            'stock_no' => ['anyOf' => [['type' => 'string'], ['type' => 'number'], ['type' => 'null']]],
            'item_description' => $nullable('string'),
            'unit' => $nullable('string'),
            'quantity' => $nullable('number'),
            'unit_cost' => $nullable('number'),
            'total_cost' => $nullable('number'),
        ],
        'required' => ['stock_no', 'item_description', 'unit', 'quantity', 'unit_cost', 'total_cost'],
        'additionalProperties' => false,
    ];
    return [
        'type' => 'object',
        'properties' => [
            'customer' => $nullable('string'),
            'address' => $nullable('string'),
            'date' => $nullable('string'),
            'quote_number' => $nullable('string'),
            'items' => ['type' => 'array', 'items' => $item],
            'total' => $nullable('number'),
        ],
        'required' => ['customer', 'address', 'date', 'quote_number', 'items', 'total'],
        'additionalProperties' => false,
    ];
}

/**
 * Send the image to Claude and return the extracted quotation as an array.
 * Throws RuntimeException with a message that can be shown to the user.
 */
function extractQuotationFromImage($imageBytes, $mediaType) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $client = new Anthropic\Client(apiKey: trim(getenv('ANTHROPIC_API_KEY') ?: ''));

    try {
        $message = $client->beta->messages->create(
            model: QUOTATION_SCAN_MODEL,
            maxTokens: 16000,
            system: quotationScanPrompt(),
            messages: [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mediaType, 'data' => base64_encode($imageBytes)]],
                    ['type' => 'text', 'text' => 'Extract this quotation.'],
                ],
            ]],
            outputConfig: [
                'effort' => 'medium',
                'format' => ['type' => 'json_schema', 'schema' => quotationScanSchema()],
            ],
            // If a safety check declines the request, the API retries it on a suitable model
            betas: ['server-side-fallback-2026-07-01'],
            fallbacks: 'default',
        );
    } catch (Anthropic\Core\Exceptions\AuthenticationException | Anthropic\Core\Exceptions\PermissionDeniedException $e) {
        throw new RuntimeException('The Anthropic API key was rejected. Check ANTHROPIC_API_KEY in the server settings.');
    } catch (Anthropic\Core\Exceptions\RateLimitException $e) {
        throw new RuntimeException('The scanning service is busy. Please try again in a minute.');
    } catch (Anthropic\Core\Exceptions\BadRequestException $e) {
        error_log('Quotation scan rejected: ' . $e->getMessage());
        throw new RuntimeException('The image could not be processed. Try a clearer photo or a smaller file.');
    } catch (Anthropic\Core\Exceptions\APIStatusException $e) {
        error_log('Quotation scan failed: ' . $e->getMessage());
        throw new RuntimeException('The scanning service returned an error. Please try again.');
    } catch (Anthropic\Core\Exceptions\APIConnectionException $e) {
        error_log('Quotation scan connection error: ' . $e->getMessage());
        throw new RuntimeException('Could not reach the scanning service. Please try again.');
    }

    if ($message->stopReason === 'refusal') {
        throw new RuntimeException('This image could not be scanned. Please enter the quotation manually.');
    }
    if ($message->stopReason === 'max_tokens') {
        throw new RuntimeException('The quotation has too many items to scan at once.');
    }
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $data = json_decode($block->text, true);
            if (is_array($data)) {
                return $data;
            }
        }
    }
    throw new RuntimeException('The scan did not return any quotation details.');
}

// Upper-case, decode HTML entities and fraction symbols, and collapse spacing so that
// 'BOLT, MACHINE 5/8&QUOT; X 8&QUOT;' and 'Bolt, machine 5/8" x 8"' compare equal
function normalizeScanText($text) {
    $text = html_entity_decode(str_ireplace('&quot;', '"', (string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strtr($text, ['¼' => '1/4', '½' => '1/2', '¾' => '3/4', '“' => '"', '”' => '"', '’' => "'", '′' => "'", '″' => '"']);
    $text = mb_strtoupper($text, 'UTF-8');
    $text = preg_replace('/\s*([,\/"\'#()-])\s*/u', '$1', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text, " \t\n\r\0\x0B,.");
}

/**
 * Match the scanned customer and items to Business Partners and Inventory.
 * Only exact matches (after normalizing case, spacing, quotes and fractions) are used,
 * plus a catalog name with a trailing "(METERS)"-style note, so a 5/8" x 8" bolt is
 * never taken for a 5/8" x 10" one. Unmatched items are left for the user to pick.
 */
function matchScannedQuotation($conn, $data) {
    $result = ['client_id' => null, 'items' => []];

    $customer = normalizeScanText($data['customer'] ?? '');
    if ($customer !== '') {
        $res = $conn->query("SELECT id, name FROM clients WHERE partner_type = 'customer'");
        while ($row = $res->fetch_assoc()) {
            if (normalizeScanText($row['name']) === $customer) {
                $result['client_id'] = (int) $row['id'];
                break;
            }
        }
    }

    $byName = [];
    $byCode = [];
    $res = $conn->query("SELECT id, name, description FROM item_list WHERE status = 1");
    while ($row = $res->fetch_assoc()) {
        $name = normalizeScanText($row['name']);
        $byName[$name] = $byName[$name] ?? (int) $row['id'];
        $short = normalizeScanText(preg_replace('/\s*\([^()]*\)\s*$/', '', $row['name']));
        $byName[$short] = $byName[$short] ?? (int) $row['id'];
        $byCode[normalizeScanText($row['description'])] = (int) $row['id'];
    }
    foreach ($data['items'] ?? [] as $item) {
        $name = normalizeScanText($item['item_description'] ?? '');
        $code = normalizeScanText(is_scalar($item['stock_no'] ?? null) ? (string) $item['stock_no'] : '');
        $result['items'][] = $byName[$name] ?? ($code !== '' ? ($byCode[$code] ?? null) : null);
    }
    return $result;
}
