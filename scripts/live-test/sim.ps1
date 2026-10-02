# Windows wrapper: run a live-test helper inside the disposable test distro.
# Examples:  .\sim.ps1 page    .\sim.ps1 heartbeat "Lidia Sobieska" "Aela the Huntress"
#            .\sim.ps1 -Script env.sh up      .\sim.ps1 -Script run-php-tests.sh tests/state_check.php
[CmdletBinding(PositionalBinding = $false)]
param(
    [string]$Script = "pcvsim.py",
    [Parameter(ValueFromRemainingArguments = $true)][string[]]$Rest
)
$distro = if ($env:PCV_TEST_DISTRO) { $env:PCV_TEST_DISTRO } else { "DwemerAI4Skyrim3-test" }
$windowsPath = (Resolve-Path (Join-Path $PSScriptRoot $Script)).Path
# K:\a\b -> /mnt/k/a/b (passing backslashes through wsl to wslpath loses them)
$linuxPath = "/mnt/" + $windowsPath.Substring(0, 1).ToLower() + ($windowsPath.Substring(2) -replace '\\', '/')
if ($Script -like "*.py") {
    wsl -d $distro -- python3 -u $linuxPath @Rest
} else {
    wsl -d $distro -- bash $linuxPath @Rest
}
