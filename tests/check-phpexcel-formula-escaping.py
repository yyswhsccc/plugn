#!/usr/bin/env python3
import re
from pathlib import Path

repo_root = Path(__file__).resolve().parents[1]
source = (repo_root / "common/components/PhpExcel.php").read_text()

required_patterns = [
    r"use\s+PhpOffice\\PhpSpreadsheet\\Cell\\DataType\s*;",
    r"private\s+function\s+setExportCellValue\s*\(",
    r"preg_match\s*\(\s*['\"]/\^\[=\+\\-@\\t\\r\]/['\"]\s*,\s*\$value\s*\)",
    r"\$activeSheet->setCellValueExplicit\s*\(\s*\$cell\s*,\s*['\"]'['\"]\s*\.\s*\$value\s*,\s*DataType::TYPE_STRING\s*\)",
    r"\$this->setExportCellValue\s*\(\s*\$activeSheet\s*,\s*\$col\s*\.\s*\$row\s*,\s*\$header\s*\)",
    r"\$this->setExportCellValue\s*\(\s*\$activeSheet\s*,\s*\$col\s*\.\s*\$row\s*,\s*\$column_value\s*\)",
]

missing = [pattern for pattern in required_patterns if not re.search(pattern, source)]
if missing:
    raise SystemExit("PhpExcel formula escaping guard is missing: " + ", ".join(missing))

if re.search(r"->setCellValue\s*\(\s*\$col\s*\.\s*\$row\s*,\s*\$header\s*\)", source):
    raise SystemExit("header writes still bypass formula escaping")

if re.search(r"->setCellValue\s*\(\s*\$col\s*\.\s*\$row\s*,\s*\$column_value\s*\)", source):
    raise SystemExit("data writes still bypass formula escaping")

print("PhpExcel export formula escaping guard is present.")
