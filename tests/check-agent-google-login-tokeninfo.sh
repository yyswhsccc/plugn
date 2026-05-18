#!/bin/sh
set -eu

file="agent/modules/v1/controllers/AuthController.php"

assert_contains() {
	pattern="$1"
	description="$2"

	if ! grep -Fq "$pattern" "$file"; then
		echo "Missing ${description}" >&2
		exit 1
	fi
}

assert_absent() {
	pattern="$1"
	description="$2"

	if grep -Fq "$pattern" "$file"; then
		echo "Found forbidden ${description}" >&2
		exit 1
	fi
}

assert_absent "tokeninfo?id_token=\" . \$token" "raw Google tokeninfo URL concatenation"
assert_contains "if (!is_string(\$token) || trim(\$token) === '')" "empty/non-string token guard"
assert_contains "http_build_query(['id_token' => \$token], '', '&', PHP_QUERY_RFC3986)" "RFC3986 tokeninfo query construction"
assert_contains "if (\$ch === false)" "curl initialization failure guard"
assert_contains "curl_exec(\$ch)" "Google tokeninfo request execution"
assert_contains "curl_close(\$ch)" "curl handle cleanup"
assert_contains "if (\$body === false || \$body === '')" "empty/failed response guard"
assert_contains "json_last_error() !== JSON_ERROR_NONE" "invalid JSON guard"
assert_contains "CURLOPT_CONNECTTIMEOUT" "bounded Google connect timeout"
assert_contains "CURLOPT_TIMEOUT" "bounded Google request timeout"
assert_contains "return \$this->invalidGoogleAccessTokenResponse();" "shared invalid-token response usage"
assert_contains "private function invalidGoogleAccessTokenResponse()" "shared invalid-token response helper"
assert_contains "isGoogleTokenAudienceValid(\$response)" "Google audience validation before agent lookup"
assert_contains "empty(\$response->aud)" "missing Google audience rejection"
assert_contains "googleOAuthClientId" "configured Google OAuth client ID lookup"
assert_contains "GOOGLE_OAUTH_CLIENT_ID" "Google OAuth client ID environment fallback"
assert_contains "GOOGLE_CLIENT_ID" "Google client ID environment fallback"
