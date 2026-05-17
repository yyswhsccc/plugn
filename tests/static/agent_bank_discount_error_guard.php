<?php

$path = __DIR__ . '/../../agent/modules/v1/controllers/BankDiscountController.php';
$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "Unable to read BankDiscountController.php\n");
    exit(1);
}

$forbidden = [
    '"message" => $model->errors',
    '"message" => $bank_discount->errors',
    "'message' => \$model->errors",
    "'message' => \$bank_discount->errors",
];

foreach ($forbidden as $needle) {
    if (strpos($source, $needle) !== false) {
        fwrite(STDERR, "Raw bank-discount validation errors are still returned: {$needle}\n");
        exit(1);
    }
}

if (strpos($source, 'bankDiscountErrorResponse') === false || strpos($source, 'Yii::error') === false) {
    fwrite(STDERR, "Bank-discount failures must log details through bankDiscountErrorResponse().\n");
    exit(1);
}

echo "Bank discount error response guard passed.\n";
