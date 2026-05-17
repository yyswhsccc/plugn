#!/usr/bin/env sh
set -eu

controller="agent/modules/v1/controllers/CampaignController.php"

require_pattern() {
	pattern="$1"
	description="$2"

	if ! grep -Eq "$pattern" "$controller"; then
		echo "Missing expected guard: $description" >&2
		exit 1
	fi
}

# shellcheck disable=SC2016
if grep -Eq '"message"[[:space:]]*=>[[:space:]]*\$model->errors|'\''message'\''[[:space:]]*=>[[:space:]]*\$model->errors' "$controller"; then
	echo "CampaignController still returns raw model errors in API messages" >&2
	exit 1
fi

require_pattern 'function campaignSaveFailedResponse' 'shared campaign failure response helper'
require_pattern 'Yii::error\(' 'server-side validation logging'
require_pattern 'campaign_uuid' 'campaign UUID in diagnostic context'
require_pattern 'restaurant_uuid' 'restaurant UUID in diagnostic context'
require_pattern "We've faced a problem creating the campaign" 'generic create failure message'
require_pattern "We've faced a problem updating the campaign" 'generic update failure message'
require_pattern "We've faced a problem deleting the campaign" 'generic delete failure message'
require_pattern "We've faced a problem tracking the campaign click" 'generic click tracking failure message'
# shellcheck disable=SC2016
require_pattern '\$model === null' 'missing campaign click guard'
require_pattern 'NotFoundHttpException' '404 for missing campaign click target'

echo "Agent campaign error response guard passed."
