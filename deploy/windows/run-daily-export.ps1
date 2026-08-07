param(
    [Parameter(Mandatory = $true)]
    [string] $PhpPath
)

$ErrorActionPreference = "Stop"

$ProjectRoot = Resolve-Path (
    Join-Path $PSScriptRoot "..\.."
)

$ExportScript = Join-Path `
    $ProjectRoot `
    "bin\export-sales-daily.php"

$LogDirectory = Join-Path `
    $ProjectRoot `
    "storage\logs"

$LogFile = Join-Path `
    $LogDirectory `
    "export-sales-daily.log"

if (-not (Test-Path $PhpPath)) {
    Write-Error "PHP não encontrado em: $PhpPath"
    exit 1
}

if (-not (Test-Path $ExportScript)) {
    Write-Error "Comando de exportação não encontrado em: $ExportScript"
    exit 1
}

if (-not (Test-Path $LogDirectory)) {
    New-Item `
        -ItemType Directory `
        -Path $LogDirectory `
        -Force |
        Out-Null
}

$StartedAt = Get-Date

Add-Content `
    -Path $LogFile `
    -Value ""

Add-Content `
    -Path $LogFile `
    -Value ("=" * 80)

Add-Content `
    -Path $LogFile `
    -Value (
        "Início: {0}" -f $StartedAt.ToString(
            "yyyy-MM-dd HH:mm:ss"
        )
    )

Add-Content `
    -Path $LogFile `
    -Value (
        "PHP: {0}" -f $PhpPath
    )

Add-Content `
    -Path $LogFile `
    -Value (
        "Projeto: {0}" -f $ProjectRoot
    )

Add-Content `
    -Path $LogFile `
    -Value ("-" * 80)

Push-Location $ProjectRoot

try {
    $Output = & $PhpPath $ExportScript 2>&1 |
        Out-String

    $ExitCode = $LASTEXITCODE

    Add-Content `
        -Path $LogFile `
        -Value $Output
}
catch {
    $ExitCode = 1

    Add-Content `
        -Path $LogFile `
        -Value (
            "Falha ao executar rotina: {0}" -f
            $_.Exception.Message
        )
}
finally {
    Pop-Location
}

$FinishedAt = Get-Date

Add-Content `
    -Path $LogFile `
    -Value (
        "Fim: {0}" -f $FinishedAt.ToString(
            "yyyy-MM-dd HH:mm:ss"
        )
    )

Add-Content `
    -Path $LogFile `
    -Value (
        "Exit code: {0}" -f $ExitCode
    )

Add-Content `
    -Path $LogFile `
    -Value ("=" * 80)

exit $ExitCode