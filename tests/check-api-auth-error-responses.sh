#!/bin/sh
set -eu

file="api/modules/v2/controllers/AuthController.php"

for raw_response in \
  "\"message\" => \$model->errors" \
  "\"message\" => \$customer->errors"
do
  if grep -Fq "$raw_response" "$file"; then
    echo "Raw validation errors are still returned to API clients: $raw_response" >&2
    exit 1
  fi
done

for marker in \
  "logAuthValidationErrors('signup'" \
  "logAuthValidationErrors('update-email'" \
  "Unable to create customer account." \
  "Unable to update customer email address."
do
  if ! grep -Fq "$marker" "$file"; then
    echo "Missing API auth hardening marker: $marker" >&2
    exit 1
  fi
done
