param(
    [string]$Profile = "jordan",
    [string]$Region  = "us-east-2"
)

$ErrorActionPreference = "Stop"

$AwsExe = "C:\Users\Jordan\AppData\Local\Programs\Amazon\AWSCLIV2\aws.exe"
if (-not (Test-Path $AwsExe)) { $AwsExe = "aws" }

Write-Host "Limpiando recursos EC2 y Elastic IP de LATINEX en $Region..."

$eips = & $AwsExe ec2 describe-addresses --filters "Name=tag:Project,Values=LATINEX" --profile $Profile --region $Region --output json | ConvertFrom-Json
foreach ($eip in $eips.Addresses) {
    if ($eip.AssociationId) {
        Write-Host "  Desasociando Elastic IP $($eip.PublicIp)..."
        & $AwsExe ec2 disassociate-address --association-id $eip.AssociationId --profile $Profile --region $Region | Out-Null
    }
    Write-Host "  Liberando Elastic IP $($eip.PublicIp) ($($eip.AllocationId))..."
    & $AwsExe ec2 release-address --allocation-id $eip.AllocationId --profile $Profile --region $Region | Out-Null
}

$inst = & $AwsExe ec2 describe-instances --filters "Name=tag:Name,Values=latinex-overleaf-prod" "Name=instance-state-name,Values=pending,running,stopping,stopped" --profile $Profile --region $Region --output json | ConvertFrom-Json
$ids = @()
foreach ($res in $inst.Reservations) {
    foreach ($i in $res.Instances) { $ids += $i.InstanceId }
}

if ($ids.Count -gt 0) {
    Write-Host ("  Terminando instancia(s): {0}..." -f ($ids -join ", "))
    & $AwsExe ec2 terminate-instances --instance-ids $ids --profile $Profile --region $Region | Out-Null
    & $AwsExe ec2 wait instance-terminated --instance-ids $ids --profile $Profile --region $Region
}

$stateFile = Join-Path $PSScriptRoot ".ec2-state.json"
if (Test-Path $stateFile) { Remove-Item $stateFile -Force }

Write-Host "✔ Recursos de cómputo y Elastic IP liberados."
