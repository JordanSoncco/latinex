#!/usr/bin/env bash
set -euo pipefail

# Despliegue automatizado de LATINEX Cloud desde AWS CloudShell
# Región seleccionada: us-east-2 (debe coincidir con AWS Settings > View all projects > Overview)

REGION="${AWS_REGION:-us-east-2}"
REPO_URL="https://github.com/JordanSoncco/latinex.git"
ROLE_NAME="LatinexEC2SSMRole"
PROFILE_NAME="LatinexEC2SSMProfile"
SG_NAME="latinex-web-sg"
LT_NAME="latinex-overleaf-lt"
INSTANCE_NAME="latinex-overleaf-prod"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "================================================================="
echo " LATINEX Cloud — Despliegue EC2 t4g.small + Elastic IP ($REGION)"
echo "================================================================="

echo "[1/9] Verificando proyecto AWS activo y plan..."
IDENTITY_JSON="$(aws sts get-caller-identity --region "$REGION" --output json)"
PROJECT_ID="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["Account"])' "$IDENTITY_JSON")"
PROJECT_ARN="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["Arn"])' "$IDENTITY_JSON")"
echo "  Proyecto ID : $PROJECT_ID"
echo "  Identidad   : $PROJECT_ARN"
echo "  Región      : $REGION"
aws freetier get-account-plan-state --region us-east-1 --output json 2>/dev/null || true

echo "[2/9] Verificando disponibilidad de instancia t4g.small en $REGION..."
OFFERING_COUNT="$(aws ec2 describe-instance-type-offerings \
  --region "$REGION" \
  --location-type region \
  --filters "Name=instance-type,Values=t4g.small" \
  --query "length(InstanceTypeOfferings)" \
  --output text)"
if [[ "${OFFERING_COUNT:-0}" -lt 1 ]]; then
  echo "ERROR: t4g.small no está disponible en $REGION." >&2
  exit 1
fi

echo "[3/9] Configurando Rol IAM y Instance Profile para SSM (sin abrir puerto 22)..."
CREATED_IAM=0
if ! aws iam get-role --role-name "$ROLE_NAME" >/dev/null 2>&1; then
  TRUST_POLICY='{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Principal":{"Service":"ec2.amazonaws.com"},"Action":"sts:AssumeRole"}]}'
  aws iam create-role --role-name "$ROLE_NAME" --assume-role-policy-document "$TRUST_POLICY" >/dev/null
  aws iam attach-role-policy --role-name "$ROLE_NAME" --policy-arn "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
  CREATED_IAM=1
fi

if ! aws iam get-instance-profile --instance-profile-name "$PROFILE_NAME" >/dev/null 2>&1; then
  aws iam create-instance-profile --instance-profile-name "$PROFILE_NAME" >/dev/null
  aws iam add-role-to-instance-profile --instance-profile-name "$PROFILE_NAME" --role-name "$ROLE_NAME"
  CREATED_IAM=1
else
  ATTACHED="$(aws iam get-instance-profile --instance-profile-name "$PROFILE_NAME" --query "InstanceProfile.Roles[?RoleName=='$ROLE_NAME'] | length(@)" --output text)"
  if [[ "${ATTACHED:-0}" -eq 0 ]]; then
    aws iam add-role-to-instance-profile --instance-profile-name "$PROFILE_NAME" --role-name "$ROLE_NAME"
    CREATED_IAM=1
  fi
fi

if [[ "$CREATED_IAM" -eq 1 ]]; then
  echo "  Esperando 12s propagación de IAM Instance Profile..."
  sleep 12
fi

echo "[4/9] Obteniendo VPC por defecto, subred pública y Security Group (puertos 80/443)..."
VPC_ID="$(aws ec2 describe-vpcs --region "$REGION" --filters "Name=isDefault,Values=true" --query "Vpcs[0].VpcId" --output text)"
if [[ -z "$VPC_ID" || "$VPC_ID" == "None" ]]; then
  echo "ERROR: No existe una VPC por defecto en $REGION." >&2
  exit 1
fi

SUBNET_ID="$(aws ec2 describe-subnets --region "$REGION" --filters "Name=vpc-id,Values=$VPC_ID" --query "Subnets[?MapPublicIpOnLaunch==\`true\`]|[0].SubnetId" --output text)"
if [[ -z "$SUBNET_ID" || "$SUBNET_ID" == "None" ]]; then
  SUBNET_ID="$(aws ec2 describe-subnets --region "$REGION" --filters "Name=vpc-id,Values=$VPC_ID" --query "Subnets[0].SubnetId" --output text)"
fi

SG_ID="$(aws ec2 describe-security-groups --region "$REGION" --filters "Name=vpc-id,Values=$VPC_ID" "Name=group-name,Values=$SG_NAME" --query "SecurityGroups[0].GroupId" --output text)"
if [[ -z "$SG_ID" || "$SG_ID" == "None" ]]; then
  SG_ID="$(aws ec2 create-security-group --region "$REGION" --group-name "$SG_NAME" --description "LATINEX Overleaf HTTP/HTTPS sin puerto 22" --vpc-id "$VPC_ID" --query "GroupId" --output text)"
fi
aws ec2 authorize-security-group-ingress --region "$REGION" --group-id "$SG_ID" --protocol tcp --port 80  --cidr 0.0.0.0/0 >/dev/null 2>&1 || true
aws ec2 authorize-security-group-ingress --region "$REGION" --group-id "$SG_ID" --protocol tcp --port 443 --cidr 0.0.0.0/0 >/dev/null 2>&1 || true
echo "  VPC: $VPC_ID | Subnet: $SUBNET_ID | SG: $SG_ID"

echo "[5/9] Resolviendo AMI Amazon Linux 2023 ARM64 y Launch Template..."
AMI_ID="$(aws ssm get-parameter --region "$REGION" --name "/aws/service/ami-amazon-linux-latest/al2023-ami-kernel-default-arm64" --query "Parameter.Value" --output text)"
LT_JSON="$(python3 - "$AMI_ID" "$PROFILE_NAME" "$SG_ID" "$SCRIPT_DIR/cloud-init-docker.sh" <<'PY'
import base64, json, sys
ami_id, profile_name, sg_id, userdata_path = sys.argv[1:5]
with open(userdata_path, "rb") as f:
    ud_b64 = base64.b64encode(f.read().replace(b"\r\n", b"\n")).decode("ascii")
data = {
    "ImageId": ami_id,
    "InstanceType": "t4g.small",
    "IamInstanceProfile": {"Name": profile_name},
    "SecurityGroupIds": [sg_id],
    "MetadataOptions": {
        "HttpEndpoint": "enabled",
        "HttpTokens": "required",
        "HttpPutResponseHopLimit": 2,
    },
    "CreditSpecification": {"CpuCredits": "standard"},
    "BlockDeviceMappings": [{
        "DeviceName": "/dev/xvda",
        "Ebs": {
            "VolumeSize": 20,
            "VolumeType": "gp3",
            "Encrypted": True,
            "DeleteOnTermination": True,
        },
    }],
    "UserData": ud_b64,
    "TagSpecifications": [{
        "ResourceType": "instance",
        "Tags": [
            {"Key": "Name", "Value": "latinex-overleaf-prod"},
            {"Key": "Project", "Value": "LATINEX"},
            {"Key": "managed_by", "Value": "aws-skills"},
        ],
    }],
}
print(json.dumps(data))
PY
)"

if aws ec2 describe-launch-templates --region "$REGION" --launch-template-names "$LT_NAME" >/dev/null 2>&1; then
  LT_VERSION="$(aws ec2 create-launch-template-version --region "$REGION" --launch-template-name "$LT_NAME" --version-description "cloudshell" --launch-template-data "$LT_JSON" --query "LaunchTemplateVersion.VersionNumber" --output text)"
else
  LT_VERSION="$(aws ec2 create-launch-template --region "$REGION" --launch-template-name "$LT_NAME" --version-description "v1" --launch-template-data "$LT_JSON" --query "LaunchTemplate.LatestVersionNumber" --output text)"
fi
echo "  AMI: $AMI_ID | Launch Template: $LT_NAME (v$LT_VERSION)"

echo "[6/9] Buscando o lanzando instancia EC2 t4g.small y asociando Elastic IP..."
INSTANCE_ID="$(aws ec2 describe-instances --region "$REGION" --filters "Name=tag:Name,Values=$INSTANCE_NAME" "Name=instance-state-name,Values=pending,running" --query "Reservations[0].Instances[0].InstanceId" --output text)"
if [[ -z "$INSTANCE_ID" || "$INSTANCE_ID" == "None" ]]; then
  INSTANCE_ID="$(aws ec2 run-instances --region "$REGION" --launch-template "LaunchTemplateName=$LT_NAME,Version=$LT_VERSION" --subnet-id "$SUBNET_ID" --count 1 --query "Instances[0].InstanceId" --output text)"
  echo "  Instancia lanzada: $INSTANCE_ID. Esperando estado running..."
  aws ec2 wait instance-running --region "$REGION" --instance-ids "$INSTANCE_ID"
else
  echo "  Reutilizando instancia existente: $INSTANCE_ID"
fi

ALLOC_ID="$(aws ec2 describe-addresses --region "$REGION" --filters "Name=tag:Project,Values=LATINEX" --query "Addresses[0].AllocationId" --output text)"
if [[ -z "$ALLOC_ID" || "$ALLOC_ID" == "None" ]]; then
  ALLOC_ID="$(aws ec2 allocate-address --region "$REGION" --domain vpc --tag-specifications "ResourceType=elastic-ip,Tags=[{Key=Project,Value=LATINEX},{Key=Name,Value=latinex-eip}]" --query "AllocationId" --output text)"
fi
aws ec2 associate-address --region "$REGION" --instance-id "$INSTANCE_ID" --allocation-id "$ALLOC_ID" --allow-reassociation >/dev/null
ELASTIC_IP="$(aws ec2 describe-addresses --region "$REGION" --allocation-ids "$ALLOC_ID" --query "Addresses[0].PublicIp" --output text)"
echo "  IP Estática (Elastic IP): $ELASTIC_IP ($ALLOC_ID)"

echo "[7/9] Esperando que SSM Agent reporte Online..."
ONLINE=0
for _ in $(seq 1 30); do
  STATUS="$(aws ssm describe-instance-information --region "$REGION" --filters "Key=InstanceIds,Values=$INSTANCE_ID" --query "InstanceInformationList[0].PingStatus" --output text)"
  if [[ "$STATUS" == "Online" ]]; then
    ONLINE=1
    break
  fi
  sleep 10
done
if [[ "$ONLINE" -ne 1 ]]; then
  echo "ERROR: La instancia $INSTANCE_ID no reportó Online en SSM a tiempo." >&2
  exit 1
fi

echo "[8/9] Desplegando código desde GitHub y levantando Docker Compose vía SSM..."
SSM_PARAMS="$(python3 - "$REPO_URL" <<'PY'
import json, sys
repo = sys.argv[1]
cmds = [
    "set -euo pipefail",
    "for i in $(seq 1 60); do [ -f /opt/latinex/.cloud-init-ready ] && break; sleep 5; done",
    "test -f /opt/latinex/.cloud-init-ready",
    "git config --global --add safe.directory /opt/latinex",
    f"if [ ! -d /opt/latinex/.git ]; then rm -rf /tmp/latinex-repo && git clone --depth 1 {repo} /tmp/latinex-repo && cp -a /tmp/latinex-repo/. /opt/latinex/ && rm -rf /tmp/latinex-repo; else git -C /opt/latinex fetch origin main && git -C /opt/latinex reset --hard origin/main; fi",
    "mkdir -p /opt/latinex/storage/proyectos /opt/latinex/temp_uploads",
    "if [ ! -f /opt/latinex/.env ]; then DB_PASS=$(openssl rand -hex 20); APP_SECRET=$(openssl rand -hex 32); printf 'HTTP_PORT=80\\nAPP_ENV=production\\nAPP_SECRET=%s\\nDB_NAME=latinex\\nDB_USER=latinex\\nDB_PASS=%s\\n' \"$APP_SECRET\" \"$DB_PASS\" > /opt/latinex/.env; chmod 600 /opt/latinex/.env; fi",
    "cd /opt/latinex && docker compose up -d --build",
    "for k in $(seq 1 25); do curl -fsS http://127.0.0.1/auth.php?action=me >/dev/null && break; sleep 3; done",
    "cd /opt/latinex && docker compose ps",
    "cd /opt/latinex && docker compose exec -T app php -v",
    "cd /opt/latinex && docker compose exec -T app php test_lexico.php",
    "cd /opt/latinex && docker compose exec -T app php test_frente1.php",
    "cd /opt/latinex && docker compose exec -T app php test_auth_proyectos.php",
]
print(json.dumps({"commands": cmds}))
PY
)"

CMD_ID="$(aws ssm send-command --region "$REGION" --instance-ids "$INSTANCE_ID" --document-name "AWS-RunShellScript" --parameters "$SSM_PARAMS" --query "Command.CommandId" --output text)"
echo "  SSM CommandId: $CMD_ID (esperando build y pruebas en EC2...)"

for _ in $(seq 1 90); do
  sleep 5
  INV_JSON="$(aws ssm get-command-invocation --region "$REGION" --command-id "$CMD_ID" --instance-id "$INSTANCE_ID" --output json 2>/dev/null || true)"
  [[ -z "$INV_JSON" ]] && continue
  INV_STATUS="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1]).get("Status",""))' "$INV_JSON")"
  if [[ "$INV_STATUS" == "Success" ]]; then
    python3 -c 'import json,sys; print(json.loads(sys.argv[1]).get("StandardOutputContent",""))' "$INV_JSON"
    break
  elif [[ "$INV_STATUS" == "Failed" || "$INV_STATUS" == "Cancelled" || "$INV_STATUS" == "TimedOut" ]]; then
    echo "ERROR SSM ($INV_STATUS):" >&2
    python3 -c 'import json,sys; d=json.loads(sys.argv[1]); print(d.get("StandardOutputContent","")); print(d.get("StandardErrorContent",""))' "$INV_JSON" >&2
    exit 1
  fi
done

echo "[9/9] Verificando acceso público desde Internet en http://${ELASTIC_IP}/ ..."
curl -fsS "http://${ELASTIC_IP}/auth.php?action=me"
echo ""
echo "================================================================="
echo " ✔ LATINEX CLOUD DESPLEGADO CON ÉXITO EN AWS EC2 ($REGION)"
echo "   URL pública fija : http://${ELASTIC_IP}/"
echo "   InstanceId       : $INSTANCE_ID"
echo "   Elastic IP Alloc : $ALLOC_ID"
echo "   Para actualizar  : bash /tmp/latinex/deploy/update-ec2-cloudshell.sh"
echo "   Tras el concurso : bash /tmp/latinex/deploy/cleanup-cloudshell.sh"
echo "================================================================="
