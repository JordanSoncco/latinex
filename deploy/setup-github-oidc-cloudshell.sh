#!/usr/bin/env bash
set -euo pipefail

# Configura OIDC de GitHub Actions en tu proyecto AWS (us-east-2)
# Permite que solo pushes a main en JordanSoncco/latinex actualicen la EC2 vía SSM sin llaves permanentes.

ACCOUNT_ID="$(aws sts get-caller-identity --query Account --output text)"
REGION="us-east-2"
ROLE_NAME="LatinexGitHubDeployRole"
OIDC_URL="https://token.actions.githubusercontent.com"
OIDC_ARN="arn:aws:iam::${ACCOUNT_ID}:oidc-provider/token.actions.githubusercontent.com"

echo "[1/3] Verificando/creando proveedor OIDC de GitHub en proyecto ${ACCOUNT_ID}..."
if ! aws iam get-open-id-connect-provider --open-id-connect-provider-arn "$OIDC_ARN" >/dev/null 2>&1; then
  aws iam create-open-id-connect-provider \
    --url "$OIDC_URL" \
    --client-id-list "sts.amazonaws.com" >/dev/null
  echo "  Proveedor OIDC creado: $OIDC_ARN"
else
  echo "  Proveedor OIDC ya existe: $OIDC_ARN"
fi

echo "[2/3] Creando/actualizando rol IAM ${ROLE_NAME} (solo rama main de JordanSoncco/latinex)..."
TRUST_POLICY="$(cat <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "${OIDC_ARN}"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com",
          "token.actions.githubusercontent.com:sub": "repo:JordanSoncco/latinex:ref:refs/heads/main"
        }
      }
    }
  ]
}
EOF
)"

if aws iam get-role --role-name "$ROLE_NAME" >/dev/null 2>&1; then
  aws iam update-assume-role-policy --role-name "$ROLE_NAME" --policy-document "$TRUST_POLICY"
else
  aws iam create-role --role-name "$ROLE_NAME" --assume-role-policy-document "$TRUST_POLICY" >/dev/null
fi

echo "[3/3] Adjuntando política de mínimo privilegio (solo SSM sobre latinex-overleaf-prod)..."
PERM_POLICY="$(cat <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "DescribeTargetInstance",
      "Effect": "Allow",
      "Action": "ec2:DescribeInstances",
      "Resource": "*"
    },
    {
      "Sid": "SendSsmCommandToLatinexEc2",
      "Effect": "Allow",
      "Action": "ssm:SendCommand",
      "Resource": [
        "arn:aws:ssm:${REGION}::document/AWS-RunShellScript",
        "arn:aws:ec2:${REGION}:${ACCOUNT_ID}:instance/*"
      ]
    },
    {
      "Sid": "ReadSsmCommandResult",
      "Effect": "Allow",
      "Action": "ssm:GetCommandInvocation",
      "Resource": "*"
    }
  ]
}
EOF
)"

aws iam put-role-policy \
  --role-name "$ROLE_NAME" \
  --policy-name "LatinexGitHubSSMDeployPolicy" \
  --policy-document "$PERM_POLICY"

echo "✔ OIDC y Rol ${ROLE_NAME} listos. Cada push a main desplegará automáticamente en http://3.151.208.72/."
