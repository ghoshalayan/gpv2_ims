<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'Print') ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
            color: #000;
            background: #eee;
            margin: 0;
            padding: 18px;
        }
        table { border-collapse: collapse; width: 100%; }
        th, td { vertical-align: top; }
        .sheet {
            background: #fff;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .sheet-a4 { width: 210mm; min-height: 297mm; padding: 12mm 12mm 12mm 12mm; }
        .sheet-thermal { width: 80mm; padding: 4mm; font-family: 'Consolas', 'Courier New', monospace; font-size: 11px; }
        .no-print {
            position: fixed; top: 10px; right: 10px; z-index: 99;
            background: #fff; padding: 8px; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .no-print button { padding: 6px 12px; margin-left: 4px; cursor: pointer; }
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .sheet { box-shadow: none; margin: 0; }
            .no-print { display: none !important; }
            .sheet-a4 { width: 210mm; min-height: 297mm; }
            .sheet-thermal { width: 80mm; }
            @page { size: A4; margin: 0; }
        }
        @media print and (width: 80mm) {
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">🖨 Print</button>
    <button onclick="window.close()">✕ Close</button>
</div>
<?= $content ?>
<script>
    // Auto-open the print dialog once the document is fully rendered.
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
    });
</script>
</body>
</html>
