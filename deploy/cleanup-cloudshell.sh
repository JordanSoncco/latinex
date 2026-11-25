#!/usr/bin/env bash
set -euo pipefail

REGION="${AWS_REGION:-us-east-2}"
echo "Limpiando instancia EC2 y Elastic IP de LATINEX en $REGION..."

ALLOC_IDS="$(aws ec2 describe-addresses --region "$REGION" --filters "Name=tag:Project,Values=LATINEX" --query "Addresses[].AllocationId" --output text)"
for ALLOC_ID in $ALLOC_IDS; do
  ASSOC_ID="$(aws ec2 describe-addresses --region "$REGION" --allocation-ids "$ALLOC_ID" --query "Addresses[0].AssociationId" --output text)"
  if [[ -n "$ASSOC_ID" && "$ASSOC_ID" != "None" ]]; then
    aws ec2 disassociate-address --region "$REGION" --association-id "$ASSOC_ID"
  fi
  aws ec2 release-address --region "$REGION" --allocation-id "$ALLOC_ID"
  echo "  Elastic IP liberada: $ALLOC_ID"
done

INSTANCE_IDS="$(aws ec2 describe-instances --region "$REGION" --filters "Name=tag:Name,Values=latinex-overleaf-prod" "Name=instance-state-name,Values=pending,running,stopping,stopped" --query "Reservations[].Instances[].InstanceId" --output text)"
if [[ -n "$INSTANCE_IDS" && "$INSTANCE_IDS" != "None" ]]; then
  aws ec2 terminate-instances --region "$REGION" --instance-ids $INSTANCE_IDS >/dev/null
  aws ec2 wait instance-terminated --region "$REGION" --instance-ids $INSTANCE_IDS
  echo "  Instancia(s) terminada(s): $INSTANCE_IDS"
fi

echo "✔ Recursos EC2 y Elastic IP de LATINEX liberados."
