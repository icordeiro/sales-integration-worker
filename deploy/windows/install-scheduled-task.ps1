param(
    [string] $PhpPath = "",
    [string] $TaskName = "NielsenIQ - Exportacao Diaria",
    [string] $ScheduleTime = "14:00"
)

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "Instalador NielsenIQ - Exportacao Diaria"
Write-Host ("-" * 60)

$ProjectRoot = Resolve-Path (
    Join-Path $PSScriptRoot "..\.."
)

$RunnerScript = Resolve-Path (
    Join-Path `
        $PSScriptRoot `
        "run-daily-export.ps1"
)

#
# Localiza o PHP.
#
if ([string]::IsNullOrWhiteSpace($PhpPath)) {

    $PhpCommand = Get-Command `
        php.exe `
        -ErrorAction SilentlyContinue

    if ($null -ne $PhpCommand) {
        $PhpPath = $PhpCommand.Source
    }
}

#
# Fallback comum para XAMPP.
#
if (
    [string]::IsNullOrWhiteSpace($PhpPath) `
    -and `
    (Test-Path "C:\xampp\php\php.exe")
) {
    $PhpPath = "C:\xampp\php\php.exe"
}

if (
    [string]::IsNullOrWhiteSpace($PhpPath) `
    -or `
    -not (Test-Path $PhpPath)
) {
    throw @"
PHP não foi encontrado.

Execute novamente informando o caminho:

powershell -ExecutionPolicy Bypass -File install-scheduled-task.ps1 -PhpPath "C:\caminho\php.exe"
"@
}

$PhpPath = (
    Resolve-Path $PhpPath
).Path

#
# Valida horário.
#
try {
    $TriggerTime = [DateTime]::ParseExact(
        $ScheduleTime,
        "HH:mm",
        [System.Globalization.CultureInfo]::InvariantCulture
    )
}
catch {
    throw "Horário inválido. Utilize HH:mm. Exemplo: 14:00"
}

Write-Host "Projeto : $ProjectRoot"
Write-Host "PHP     : $PhpPath"
Write-Host "Horario : $ScheduleTime"
Write-Host ""

#
# PowerShell que será chamado pelo Task Scheduler.
#
$Arguments = @(
    "-NoProfile"
    "-ExecutionPolicy"
    "Bypass"
    "-File"
    "`"$RunnerScript`""
    "-PhpPath"
    "`"$PhpPath`""
) -join " "

$Action = New-ScheduledTaskAction `
    -Execute "powershell.exe" `
    -Argument $Arguments `
    -WorkingDirectory $ProjectRoot

$Trigger = New-ScheduledTaskTrigger `
    -Daily `
    -At $TriggerTime

#
# IgnoreNew é uma terceira proteção.
#
# Mesmo assim mantemos nosso FileProcessLock,
# porque a aplicação não deve depender do SO.
#
$Settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -RunOnlyIfNetworkAvailable `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (
        New-TimeSpan -Hours 1
    )

#
# SYSTEM é adequado aqui porque:
#
# - PostgreSQL usa credenciais próprias do .env;
# - SFTP usa credenciais próprias do .env;
# - não dependemos das credenciais Windows do usuário;
# - pode funcionar sem ninguém logado.
#
$Principal = New-ScheduledTaskPrincipal `
    -UserId "SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

$Task = New-ScheduledTask `
    -Action $Action `
    -Trigger $Trigger `
    -Settings $Settings `
    -Principal $Principal `
    -Description "Exporta diariamente as vendas D-1 para NielsenIQ."

Register-ScheduledTask `
    -TaskName $TaskName `
    -InputObject $Task `
    -Force |
    Out-Null

Write-Host ("-" * 60)
Write-Host "Tarefa instalada com sucesso."
Write-Host ""
Write-Host "Nome    : $TaskName"
Write-Host "Horario : $ScheduleTime"
Write-Host "Usuario : SYSTEM"
Write-Host ""
Write-Host "Para testar:"
Write-Host "Start-ScheduledTask -TaskName `"$TaskName`""
Write-Host ""