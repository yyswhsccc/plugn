#!/bin/sh
set -eu

target="agent/modules/v1/controllers/ItemController.php"

for marker in \
	"itemOperationError" \
	"Agent item operation failed" \
	"Error saving item detail" \
	"Error saving option" \
	"Error saving option value" \
	"Error saving item variant" \
	"Error saving item variant option" \
	"Unable to update item quantity. Please try again." \
	"We've faced a problem deleting the item" \
	"We've faced a problem while status change of item"; do
	if ! grep -Fq "$marker" "$target"; then
		echo "Missing agent item hardening marker: $marker" >&2
		exit 1
	fi
done

if grep -Eq '"message"[[:space:]]*=>[[:space:]]*(sizeof\(\$[^)]*->errors\)|\$[A-Za-z0-9_]+->errors)' "$target"; then
	echo "Agent item controller still returns raw validation errors" >&2
	exit 1
fi

echo "Agent item error response guard passed."
