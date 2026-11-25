param(
    [string]$Profile = "jordan",
    [string]$Region  = "us-east-2"
)

$ErrorActionPreference = "Stop"

$AwsExe = "C:\Users\Jordan\AppData\Local\Programs\Amazon\AWSCLIV2\aws.exe"
if (-not (Test-Path $AwsExe)) {
    $AwsExe = "aws"
}

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$StateFile   = Join-Path $PSScriptRoot ".ec2-state.json"

function Invoke-AwsJson {
    param([string[]]$ArgsList)
    $out = & $AwsExe @ArgsList --profile $Profile --region $Region --output json 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Error ejecutando AWS CLI ($($ArgsList -join ' ')): $out"
    }
    if ([string]::IsNullOrWhiteSpace(($out -join "`n"))) { return $null }
    return ($out -join "`n") | ConvertFrom-Json
}

Write-Host "[1/8] Verificando identidad y plan del proyecto AWS en Región seleccionada ($Region)..."
$identity = Invoke-AwsJson @("sts", "get-caller-identity")
Write-Host ("  Proyecto ID: {0} | ARN: {1}" -f $identity.Account, $identity.Arn)

try {
    $plan = & $AwsExe freetier get-account-plan-state --profile $Profile --region us-east-1 --output json 2>$null | ConvertFrom-Json
    if ($plan) { Write-Host ("  Plan del proyecto: {0} ({1})" -f $plan.accountPlanType, $plan.accountPlanStatus) }
} catch {
    Write-Host "  (Aviso: no se pudo consultar freetier get-account-plan-state; continuando en $Region)"
}

Write-Host "[2/8] Verificando disponibilidad de instancia t4g.small en $Region..."
$offering = Invoke-AwsJson @("ec2", "describe-instance-type-offerings", "--location-type", "region", "--filters", "Name=instance-type,Values=t4g.small")
if (-not $offering.InstanceTypeOfferings -or $offering.InstanceTypeOfferings.Count -eq 0) {
    throw "t4g.small no está disponible en $Region."
}

Write-Host "[3/8] Creando/verificando Rol IAM y Instance Profile para SSM (sin puerto 22)..."
$roleName    = "LatinexEC2SSMRole"
$profileName = "LatinexEC2SSMProfile"
$trustPolicy = '{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Principal":{"Service":"ec2.amazonaws.com"},"Action":"sts:AssumeRole"}]}'

$roleExists = $true
try {
    & $AwsExe iam get-role --role-name $roleName --profile $Profile --output json *> $null
    if ($LASTEXITCODE -ne 0) { $roleExists = $false }
} catch { $roleExists = $false }

$createdIam = $false
if (-not $roleExists) {
    $trustFile = [System.IO.Path]::GetTempFileName()
    [System.IO.File]::WriteAllText($trustFile, $trustPolicy)
    & $AwsExe iam create-role --role-name $roleName --assume-role-policy-document "file://$trustFile" --profile $Profile --output json | Out-Null
    Remove-Item $trustFile -Force
    & $AwsExe iam attach-role-policy --role-name $roleName --policy-arn "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore" --profile $Profile | Out-Null
    $createdIam = $true
}

$ipExists = $true
try {
    $ipObj = & $AwsExe iam get-instance-profile --instance-profile-name $profileName --profile $Profile --output json 2>$null | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0 -or -not $ipObj) { $ipExists = $false }
} catch { $ipExists = $false }

if (-not $ipExists) {
    & $AwsExe iam create-instance-profile --instance-profile-name $profileName --profile $Profile --output json | Out-Null
    & $AwsExe iam add-role-to-instance-profile --instance-profile-name $profileName --role-name $roleName --profile $Profile | Out-Null
    $createdIam = $true
} elseif (-not ($ipObj.InstanceProfile.Roles | Where-Object { $_.RoleName -eq $roleName })) {
    & $AwsExe iam add-role-to-instance-profile --instance-profile-name $profileName --role-name $roleName --profile $Profile | Out-Null
    $createdIam = $true
}

if ($createdIam) {
    Write-Host "  Esperando 12s propagación de Instance Profile IAM..."
    Start-Sleep -Seconds 12
}

Write-Host "[4/8] Configurando VPC por defecto y Security Group (solo puertos 80/443)..."
$vpcs = Invoke-AwsJson @("ec2", "describe-vpcs", "--filters", "Name=isDefault,Values=true")
if (-not $vpcs.Vpcs -or $vpcs.Vpcs.Count -eq 0) {
    throw "No existe una VPC por defecto en $Region."
}
$vpcId = $vpcs.Vpcs[0].VpcId

$subnets = Invoke-AwsJson @("ec2", "describe-subnets", "--filters", "Name=vpc-id,Values=$vpcId")
$subnetId = $subnets.Subnets[0].SubnetId

$sgName = "latinex-web-sg"
$sgId   = $null
try {
    $sgCheck = & $AwsExe ec2 describe-security-groups --filters "Name=vpc-id,Values=$vpcId" "Name=group-name,Values=$sgName" --profile $Profile --region $Region --output json 2>$null | ConvertFrom-Json
    if ($sgCheck.SecurityGroups.Count -gt 0) {
        $sgId = $sgCheck.SecurityGroups[0].GroupId
    }
} catch {}

if (-not $sgId) {
    $sgCreated = Invoke-AwsJson @("ec2", "create-security-group", "--group-name", $sgName, "--description", "LATINEX Overleaf HTTP/HTTPS sin puerto 22", "--vpc-id", $vpcId)
    $sgId = $sgCreated.GroupId
    & $AwsExe ec2 authorize-security-group-ingress --group-id $sgId --protocol tcp --port 80  --cidr "0.0.0.0/0" --profile $Profile --region $Region | Out-Null
    & $AwsExe ec2 authorize-security-group-ingress --group-id $sgId --protocol tcp --port 443 --cidr "0.0.0.0/0" --profile $Profile --region $Region | Out-Null
}
Write-Host "  VPC: $vpcId | Subnet: $subnetId | SG: $sgId"

Write-Host "[5/8] Resolviendo AMI oficial Amazon Linux 2023 ARM64 y Launch Template..."
$amiParam = Invoke-AwsJson @("ssm", "get-parameter", "--name", "/aws/service/ami-amazon-linux-latest/al2023-ami-kernel-default-arm64")
$amiId    = $amiParam.Parameter.Value
$userDataLf    = ([System.IO.File]::ReadAllText((Join-Path $PSScriptRoot "cloud-init-docker.sh"), [System.Text.Encoding]::UTF8)) -replace "`r`n", "`n"
$userDataBytes = [System.Text.Encoding]::UTF8.GetBytes($userDataLf)
$userDataB64   = [Convert]::ToBase64String($userDataBytes)

$ltName = "latinex-overleaf-lt"
$ltData = @{
    ImageId            = $amiId
    InstanceType       = "t4g.small"
    IamInstanceProfile = @{ Name = $profileName }
    SecurityGroupIds   = @($sgId)
    MetadataOptions    = @{
        HttpEndpoint            = "enabled"
        HttpTokens              = "required"
        HttpPutResponseHopLimit = 2
    }
    CreditSpecification = @{ CpuCredits = "standard" }
    BlockDeviceMappings = @(
        @{
            DeviceName = "/dev/xvda"
            Ebs = @{
                VolumeSize          = 20
                VolumeType          = "gp3"
                Encrypted           = $true
                DeleteOnTermination = $true
            }
        }
    )
    UserData = $userDataB64
    TagSpecifications = @(
        @{
            ResourceType = "instance"
            Tags = @(
                @{ Key = "Name"; Value = "latinex-overleaf-prod" },
                @{ Key = "Project"; Value = "LATINEX" },
                @{ Key = "managed_by"; Value = "aws-skills" }
            )
        }
    )
} | ConvertTo-Json -Depth 10 -Compress

$ltFile = [System.IO.Path]::GetTempFileName()
[System.IO.File]::WriteAllText($ltFile, $ltData)

$existingLt = $null
try {
    $existingLt = & $AwsExe ec2 describe-launch-templates --launch-template-names $ltName --profile $Profile --region $Region --output json 2>$null | ConvertFrom-Json
} catch {}

if (-not $existingLt -or $existingLt.LaunchTemplates.Count -eq 0) {
    Invoke-AwsJson @("ec2", "create-launch-template", "--launch-template-name", $ltName, "--version-description", "v1", "--launch-template-data", "file://$ltFile") | Out-Null
}
Remove-Item $ltFile -Force

Write-Host "[6/8] Buscando o lanzando instancia EC2 t4g.small + asociando Elastic IP..."
$existingInst = Invoke-AwsJson @("ec2", "describe-instances", "--filters", "Name=tag:Name,Values=latinex-overleaf-prod", "Name=instance-state-name,Values=pending,running")
$instanceId = $null
if ($existingInst.Reservations.Count -gt 0 -and $existingInst.Reservations[0].Instances.Count -gt 0) {
    $instanceId = $existingInst.Reservations[0].Instances[0].InstanceId
    Write-Host "  Reutilizando instancia existente: $instanceId"
} else {
    $runOut = Invoke-AwsJson @("ec2", "run-instances", "--launch-template", "LaunchTemplateName=$ltName,Version=1", "--subnet-id", $subnetId, "--count", "1")
    $instanceId = $runOut.Instances[0].InstanceId
    Write-Host "  Instancia lanzada: $instanceId. Esperando estado running..."
    & $AwsExe ec2 wait instance-running --instance-ids $instanceId --profile $Profile --region $Region
}

$eips = Invoke-AwsJson @("ec2", "describe-addresses", "--filters", "Name=tag:Project,Values=LATINEX")
$allocationId = $null
$elasticIp    = $null
if ($eips.Addresses.Count -gt 0) {
    $allocationId = $eips.Addresses[0].AllocationId
    $elasticIp    = $eips.Addresses[0].PublicIp
} else {
    $alloc = Invoke-AwsJson @("ec2", "allocate-address", "--domain", "vpc", "--tag-specifications", "ResourceType=elastic-ip,Tags=[{Key=Project,Value=LATINEX},{Key=Name,Value=latinex-eip}]")
    $allocationId = $alloc.AllocationId
    $elasticIp    = $alloc.PublicIp
}

& $AwsExe ec2 associate-address --instance-id $instanceId --allocation-id $allocationId --allow-reassociation --profile $Profile --region $Region | Out-Null
Write-Host "  IP Estática (Elastic IP) asignada: $elasticIp ($allocationId)"

@{
    region       = $Region
    instanceId   = $instanceId
    allocationId = $allocationId
    elasticIp    = $elasticIp
    securityGroup = $sgId
    launchTemplate = $ltName
} | ConvertTo-Json -Depth 4 | Set-Content -Path $StateFile -Encoding UTF8

Write-Host "[7/8] Esperando que SSM Agent reporte Online..."
$online = $false
for ($i = 0; $i -lt 30; $i++) {
    $ssmInfo = Invoke-AwsJson @("ssm", "describe-instance-information", "--filters", "Key=InstanceIds,Values=$instanceId")
    if ($ssmInfo.InstanceInformationList.Count -gt 0 -and $ssmInfo.InstanceInformationList[0].PingStatus -eq "Online") {
        $online = $true
        break
    }
    Start-Sleep -Seconds 10
}
if (-not $online) {
    throw "La instancia $instanceId no reportó Online en SSM a tiempo."
}

Write-Host "[8/8] Empaquetando LATINEX y desplegando contenedores Docker vía SSM..."
$tarPath = Join-Path $env:TEMP "latinex-deploy.tar.gz"
if (Test-Path $tarPath) { Remove-Item $tarPath -Force }
tar -czf $tarPath -C $ProjectRoot --exclude=".git" --exclude=".env" --exclude="deploy/.ec2-state.json" --exclude="storage/*" --exclude="temp_uploads/*" .

$b64 = [Convert]::ToBase64String([System.IO.File]::ReadAllBytes($tarPath))
Remove-Item $tarPath -Force

function Send-SsmShell {
    param([string[]]$Cmds)
    $paramObj = @{ commands = @($Cmds) } | ConvertTo-Json -Compress
    $paramFile = [System.IO.Path]::GetTempFileName()
    [System.IO.File]::WriteAllText($paramFile, $paramObj)
    $send = Invoke-AwsJson @("ssm", "send-command", "--instance-ids", $instanceId, "--document-name", "AWS-RunShellScript", "--parameters", "file://$paramFile")
    Remove-Item $paramFile -Force
    $cmdId = $send.Command.CommandId
    for ($j = 0; $j -lt 60; $j++) {
        Start-Sleep -Seconds 5
        $inv = & $AwsExe ssm get-command-invocation --command-id $cmdId --instance-id $instanceId --profile $Profile --region $Region --output json 2>$null | ConvertFrom-Json
        if ($inv.Status -in @("Success", "Failed", "Cancelled", "TimedOut")) {
            if ($inv.Status -ne "Success") {
                throw "SSM RunShellScript falló ($($inv.Status)): $($inv.StandardErrorContent)"
            }
            return $inv.StandardOutputContent
        }
    }
    throw "Tiempo de espera agotado esperando comando SSM $cmdId"
}

Send-SsmShell @("while [ ! -f /opt/latinex/.cloud-init-ready ]; do sleep 3; done; rm -f /tmp/latinex.b64") | Out-Null

$chunkSize = 24000
for ($offset = 0; $offset -lt $b64.Length; $offset += $chunkSize) {
    $len   = [Math]::Min($chunkSize, $b64.Length - $offset)
    $chunk = $b64.Substring($offset, $len)
    Send-SsmShell @("printf '%s' '$chunk' >> /tmp/latinex.b64") | Out-Null
}

$deployOutput = Send-SsmShell @(
    'set -e',
    'base64 -d /tmp/latinex.b64 > /tmp/latinex.tgz',
    'rm -f /tmp/latinex.b64',
    'tar -xzf /tmp/latinex.tgz -C /opt/latinex',
    'rm -f /tmp/latinex.tgz',
    'mkdir -p /opt/latinex/storage/proyectos /opt/latinex/temp_uploads',
    'if [ ! -f /opt/latinex/.env ]; then DB_PASS=$(openssl rand -hex 20); APP_SECRET=$(openssl rand -hex 32); printf "HTTP_PORT=80\nAPP_ENV=production\nAPP_SECRET=%s\nDB_NAME=latinex\nDB_USER=latinex\nDB_PASS=%s\n" "$APP_SECRET" "$DB_PASS" > /opt/latinex/.env; fi',
    'cd /opt/latinex && docker compose up -d --build',
    'docker compose ps',
    'sleep 5',
    'curl -fsS http://127.0.0.1/auth.php?action=me'
)

Write-Host $deployOutput
Write-Host ""
Write-Host "✔ DESPLIEGUE COMPLETADO EN AWS EC2 ($Region)"
Write-Host "  URL pública fija (Elastic IP): http://$elasticIp/"
