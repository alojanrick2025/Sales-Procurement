<?php
$pageTitle = 'Printable Quotation';
require_once __DIR__ . '/../config.php';
requireLogin();

$conn = getDBConnection();
$quoteId = intval($_GET['id'] ?? 0);

if ($quoteId <= 0) {
    header('Location: /quotation/quotation.php');
    exit();
}

// Fetch quotation
$stmt = $conn->prepare("SELECT q.*, u.full_name as creator_name FROM quotations q LEFT JOIN users u ON q.created_by = u.id WHERE q.id = ?");
$stmt->bind_param("i", $quoteId);
$stmt->execute();
$quoteResult = $stmt->get_result();

if ($quoteResult->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Location: /quotation/quotation.php');
    exit();
}

$quote = $quoteResult->fetch_assoc();
$stmt->close();

// Fetch quotation items
$itemStmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC");
$itemStmt->bind_param("i", $quoteId);
$itemStmt->execute();
$itemsResult = $itemStmt->get_result();
$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}
$itemStmt->close();

// Fetch system company information
$sysInfoQry = $conn->query("SELECT meta_field, meta_value FROM system_info");
$sysInfo = [];
while ($row = $sysInfoQry->fetch_assoc()) {
    $sysInfo[$row['meta_field']] = $row['meta_value'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* Base document styling */
    .printable-order-sheet {
        background: #ffffff;
        max-width: 880px;
        margin: 0 auto;
        padding: 35px 45px;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        border: 1px solid #dee2e6;
        color: #000;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
    }

    /* Header layout */
    .order-header-wrap {
        display: flex;
        align-items: center;
        margin-bottom: 18px;
        position: relative;
    }

    .order-logo-box {
        flex: 0 0 95px;
        text-align: center;
    }

    .order-logo-img {
        width: 90px;
        height: 90px;
        object-fit: contain;
    }

    .order-company-info {
        flex: 1;
        text-align: center;
        padding-right: 95px;
        /* balances out logo width to center text perfectly */
    }

    .order-company-title {
        font-weight: 700;
        font-size: 1.15rem;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
        color: #000;
        text-transform: uppercase;
    }

    .order-company-address {
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        margin-bottom: 2px;
        color: #111;
        text-transform: uppercase;
    }

    .order-company-contact {
        font-size: 0.76rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        color: #111;
        text-transform: uppercase;
        margin-bottom: 0;
    }

    /* Meta info grid box */
    .meta-info-box {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
        border: 1.5px solid #000;
    }

    .meta-info-box td {
        padding: 6px 12px;
        border: 1px solid #000;
        font-size: 0.88rem;
        vertical-align: middle;
    }

    .meta-info-box .meta-label {
        font-weight: 700;
        display: inline-block;
        min-width: 75px;
    }

    .meta-info-box .meta-value {
        font-weight: 500;
    }

    /* Title banner */
    .order-banner-title {
        border-left: 1.5px solid #000;
        border-right: 1.5px solid #000;
        border-bottom: 1.5px solid #000;
        text-align: center;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 1.5px;
        padding: 6px;
        text-transform: uppercase;
        background-color: #fafafa;
    }

    /* Items data table */
    .order-items-table {
        width: 100%;
        border-collapse: collapse;
        border: 1.5px solid #000;
        margin-bottom: 0;
    }

    .order-items-table th,
    .order-items-table td {
        border: 1px solid #000;
        padding: 6px 10px;
        font-size: 0.85rem;
        line-height: 1.35;
    }

    .order-items-table th {
        font-weight: 700;
        text-align: center;
        text-transform: capitalize;
        background-color: #f5f5f5;
    }

    .order-items-table td.blank-cell {
        height: 28px;
    }

    .order-items-table tr.total-row td {
        font-weight: 700;
        font-size: 0.9rem;
        border-top: 1.5px solid #000;
        border-bottom: 1.5px solid #000;
    }

    /* Signature section */
    .signature-section {
        margin-top: 35px;
        display: flex;
        justify-content: flex-end;
    }

    .signature-box {
        width: 280px;
        text-align: center;
    }

    .signature-greeting {
        font-style: italic;
        font-size: 0.9rem;
        text-align: right;
        padding-right: 35px;
        margin-bottom: 45px;
    }

    .signature-line {
        border-bottom: 1.5px solid #000;
        margin-bottom: 4px;
    }

    .signature-company {
        font-weight: 700;
        font-size: 0.82rem;
        text-transform: uppercase;
        margin-bottom: 0;
        line-height: 1.2;
    }

    .signature-title {
        font-size: 0.78rem;
        color: #333;
        margin-bottom: 0;
    }

    /* Print specifications */
    @media print {
        @page {
            size: portrait;
            margin: 10mm 12mm;
        }

        html,
        body {
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
            color: #000 !important;
            font-size: 10pt;
        }

        .top-navbar,
        .sidebar,
        .no-print {
            display: none !important;
        }

        .main-content {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .container-fluid,
        .row {
            padding: 0 !important;
            margin: 0 !important;
        }

        .printable-order-sheet {
            box-shadow: none !important;
            border: none !important;
            max-width: 100% !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .order-banner-title,
        .order-items-table th {
            background-color: #f2f2f2 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<!-- Action Bar (Hidden in Print) -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <a href="/quotation/quotation.php" class="btn btn-secondary me-2">
            <i class="ph-bold ph-arrow-left"></i> Back to Quotations
        </a>
        <span class="badge bg-dark fs-6"><?php echo htmlspecialchars($quote['quotation_number']); ?></span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary" onclick="window.print();">
            <i class="ph-bold ph-printer"></i> Print / Save as PDF
        </button>
        <a href="/quotation/edit_quotation.php?id=<?php echo $quote['id']; ?>" class="btn btn-warning">
            <i class="ph-bold ph-pencil-simple"></i> Edit
        </a>
        <a href="/orders/add_customer_po.php?quote_id=<?php echo $quote['id']; ?>&customer_id=<?php echo urlencode($quote['client_id'] ?? ''); ?>&customer_name=<?php echo urlencode($quote['client_name']); ?>"
            class="btn btn-success">
            <i class="ph-bold ph-receipt"></i> Create Customer PO
        </a>
    </div>
</div>

<?php if (isset($_GET['created'])): ?>
    <div class="alert alert-success alert-dismissible fade show no-print mb-4" role="alert">
        <i class="ph-bold ph-check-circle"></i> Quotation
        <strong><?php echo htmlspecialchars($quote['quotation_number']); ?></strong> created successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Printable Document -->
<div class="printable-order-sheet">
    <!-- Header Section -->
    <div class="order-header-wrap">
        <div class="order-logo-box">
            <img src="/uploads/logo.png" alt="Company Logo" class="order-logo-img"
                onerror="this.src='/uploads/jess_logo.png'">
        </div>
        <div class="order-company-info">
            <h1 class="order-company-title">
                <?php echo htmlspecialchars($sysInfo['company_name'] ?? 'JUSTLY ELECTRICAL SUPPLIES AND SERVICES'); ?>
            </h1>
            <p class="order-company-address">
                <?php echo htmlspecialchars($sysInfo['company_address'] ?? 'CAPITAN SABI BRGY. ZONE 4 TALISAY CITY NEG. OCC.'); ?>
            </p>
            <p class="order-company-contact">
                CONTACT NO. <?php echo htmlspecialchars($sysInfo['company_phone'] ?? '09953508617 / 09813515556'); ?>
            </p>
        </div>
    </div>

    <!-- Metadata Grid Table -->
    <table class="meta-info-box">
        <tr>
            <td style="width: 65%;">
                <span class="meta-label">Customer:</span>
                <span class="meta-value"><?php echo htmlspecialchars($quote['client_name']); ?></span>
            </td>
            <td style="width: 35%;">
                <span class="meta-label">Date:</span>
                <span class="meta-value"><?php echo date('F d, Y', strtotime($quote['created_at'])); ?></span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="meta-label">Address:</span>
                <span class="meta-value">
                    <?php echo htmlspecialchars(!empty($quote['client_address']) ? $quote['client_address'] : 'N/A'); ?>
                </span>
            </td>
            <td>
                <span class="meta-label">Quote #</span>
                <span class="meta-value"><?php echo htmlspecialchars($quote['quotation_number']); ?></span>
            </td>
        </tr>
    </table>

    <!-- Banner Bar -->
    <div class="order-banner-title">
        QUOTATION
    </div>

    <!-- Items Grid Table -->
    <table class="order-items-table">
        <thead>
            <tr>
                <th style="width: 8%;">Stock No.</th>
                <th style="width: 46%;">Item Description</th>
                <th style="width: 10%;">Unit</th>
                <th style="width: 10%;">Qty</th>
                <th style="width: 13%;">Unit Cost</th>
                <th style="width: 13%;">Total Cost</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stockNo = 1;
            foreach ($items as $item):
                $cleanItemName = html_entity_decode(str_ireplace('&quot;', '"', $item['item_name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $unitCostWithMarkup = ($item['quantity'] > 0)
                    ? ($item['total_price'] / $item['quantity'])
                    : ($item['unit_price'] * (1 + floatval($item['markup_rate'] ?? 0) / 100));
                ?>
                <tr>
                    <td class="text-center"><?php echo $stockNo++; ?></td>
                    <td>
                        <span><?php echo htmlspecialchars($cleanItemName); ?></span>
                    </td>
                    <td class="text-center"><?php echo htmlspecialchars($item['unit'] ?? 'units'); ?></td>
                    <td class="text-center"><?php echo number_format($item['quantity'], 0); ?></td>
                    <td class="text-end">₱<?php echo number_format($unitCostWithMarkup, 2); ?></td>
                    <td class="text-end">₱<?php echo number_format($item['total_price'], 2); ?></td>
                </tr>
            <?php endforeach; ?>

            <!-- Blank Filler Rows (Extend cells downward so Total is at the bottom) -->
            <?php
            $targetRows = 14;
            $fillerCount = max(2, $targetRows - count($items));
            for ($f = 0; $f < $fillerCount; $f++):
                ?>
                <tr>
                    <td class="blank-cell">&nbsp;</td>
                    <td class="blank-cell">&nbsp;</td>
                    <td class="blank-cell">&nbsp;</td>
                    <td class="blank-cell">&nbsp;</td>
                    <td class="blank-cell">&nbsp;</td>
                    <td class="blank-cell">&nbsp;</td>
                </tr>
            <?php endfor; ?>

            <!-- Total Row at the BOTTOM of the cells (Centered) -->
            <tr class="total-row">
                <td colspan="5" class="text-center fw-bold" style="letter-spacing: 0.5px;">Total</td>
                <td class="text-end fw-bold">
                    ₱<?php echo number_format($quote['grand_total'] ?? $quote['total_amount'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Signature and Sign-off Section -->
    <div class="signature-section">
        <div class="signature-box">
            <p class="signature-greeting">Very Truly Yours,</p>
            <div class="signature-line"></div>
            <p class="signature-company">
                <?php echo htmlspecialchars($sysInfo['company_name'] ?? 'JUSTLY ELECTRICAL SUPPLIES AND SERVICES'); ?>
            </p>
            <p class="signature-title">Owner/Proprietress</p>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>