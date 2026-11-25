#!/usr/bin/env bash
set -euo pipefail

REGION="${AWS_REGION:-us-east-2}"
INSTANCE_NAME="latinex-overleaf-prod"

INSTANCE_ID="$(aws ec2 describe-instances --region "$REGION" --filters "Name=tag:Name,Values=$INSTANCE_NAME" "Name=instance-state-name,Values=running" --query "Reservations[0].Instances[0].InstanceId" --output text)"
if [[ -z "$INSTANCE_ID" || "$INSTANCE_ID" == "None" ]]; then
  echo "ERROR: No hay ninguna instancia en ejecución llamada $INSTANCE_NAME en $REGION." >&2
  exit 1
fi

SSM_PARAMS='{"commands":["set -euo pipefail","cd /opt/latinex","git fetch origin main","git reset --hard origin/main","docker compose up -d --build","docker compose ps","curl -fsS http://127.0.0.1/auth.php?action=me"]}'
CMD_ID="$(aws ssm send-command --region "$REGION" --instance-ids "$INSTANCE_ID" --document-name "AWS-RunShellScript" --parameters "$SSM_PARAMS" --query "Command.CommandId" --output text)"

for _ in $(seq 1 60); do
  sleep 4
  INV_JSON="$(aws ssm get-command-invocation --region "$REGION" --command-id "$CMD_ID" --instance-id "$INSTANCE_ID" --output json 2>/dev/null || true)"
  [[ -z "$INV_JSON" ]] && continue
  INV_STATUS="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1]).get("Status",""))' "$INV_JSON")"
  if [[ "$INV_STATUS" == "Success" ]]; then
    python3 -c 'import json,sys; print(json.loads(sys.argv[1]).get("StandardOutputContent",""))' "$INV_JSON"
    exit 0
  elif [[ "$INV_STATUS" == "Failed" || "$INV_STATUS" == "Cancelled" || "$INV_STATUS" == "TimedOut" ]]; then
    python3 -c 'import json,sys; d=json.loads(sys.argv[1]); print(d.get("StandardErrorContent",""))' "$INV_JSON" >&2
    exit 1
  fi
done
echo "Tiempo de espera agotado esperando actualización SSM." >&2
exit 1
