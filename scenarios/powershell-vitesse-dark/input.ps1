# Greet users from a list
function Get-Greeting {
    param(
        [Parameter(Mandatory)]
        [string]$Name = "World"
    )
    return "Hello, $Name!"
}

$numbers = 1..5
$total = ($numbers | Measure-Object -Sum).Sum

$config = @{
    Debug   = $true
    Retries = 3
}

foreach ($n in $numbers) {
    if ($n % 2 -eq 0) {
        Write-Output "even: $n"
    }
}

Write-Host (Get-Greeting -Name "PowerShell")
Write-Host "Total = $total"
