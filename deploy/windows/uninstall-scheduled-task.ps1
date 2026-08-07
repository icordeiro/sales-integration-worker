param(
    [string] $TaskName = "NielsenIQ - Exportacao Diaria"
)

$ErrorActionPreference = "Stop"

$Task = Get-ScheduledTask `
    -TaskName $TaskName `
    -ErrorAction SilentlyContinue

if ($null -eq $Task) {
    Write-Host ""
    Write-Host "A tarefa não está instalada:"
    Write-Host $TaskName
    Write-Host ""

    exit 0
}

Unregister-ScheduledTask `
    -TaskName $TaskName `
    -Confirm:$false

Write-Host ""
Write-Host "Tarefa removida com sucesso:"
Write-Host $TaskName
Write-Host ""