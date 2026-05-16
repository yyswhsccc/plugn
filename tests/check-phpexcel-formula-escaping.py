#!/usr/bin/env python3
from pathlib import Path

source = Path("common/components/PhpExcel.php").read_text()

required_snippets = [
    "use PhpOffice\\PhpSpreadsheet\\Cell\\DataType;",
    "private function setExportCellValue",
    "preg_match('/^[=+\\-@\\t\\r]/', $value)",
    "$activeSheet->setCellValueExplicit($cell, \"'\" . $value, DataType::TYPE_STRING);",
    "$this->setExportCellValue($activeSheet, $col . $row, $header);",
    "$this->setExportCellValue($activeSheet, $col . $row, $column_value);",
]

missing = [snippet for snippet in required_snippets if snippet not in source]
if missing:
    raise SystemExit("PhpExcel formula escaping guard is missing: " + ", ".join(missing))

if "setCellValue($col . $row, $header)" in source:
    raise SystemExit("header writes still bypass formula escaping")

if "setCellValue($col . $row, $column_value)" in source:
    raise SystemExit("data writes still bypass formula escaping")

print("PhpExcel export formula escaping guard is present.")
