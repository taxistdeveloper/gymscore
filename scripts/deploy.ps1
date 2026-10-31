param(
    [Parameter(Mandatory = $true)][string]$LocalRoot,
    [Parameter(Mandatory = $true)][string]$SshHost,
    [Parameter(Mandatory = $true)][string]$SshUser,
    [Parameter(Mandatory = $true)][string]$RemotePath,
    [Parameter(Mandatory = $true)][string]$Include,
    [Parameter(Mandatory = $true)][string]$Exclude,
    [string]$IdentityFile = ''
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $LocalRoot)) {
    Write-Error "Local root not found: $LocalRoot"
    exit 1
}

Set-Location -LiteralPath $LocalRoot

$includeItems = @($Include -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' })
$excludeItems = @($Exclude -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' })

if ($includeItems.Count -eq 0) {
    Write-Error 'Include list is empty'
    exit 1
}

foreach ($item in $includeItems) {
    if (-not (Test-Path -LiteralPath (Join-Path $LocalRoot $item))) {
        Write-Error "Missing include path: $item"
        exit 1
    }
}

$password = [string]$env:DEPLOY_SSH_PASSWORD
if ($password -eq '') {
    Write-Error 'SSH password is empty. Enter it in the admin form.'
    exit 1
}

$null = Get-Command tar.exe -ErrorAction Stop
$null = Get-Command ssh.exe -ErrorAction Stop

$askpass = Join-Path $LocalRoot 'scripts\deploy-askpass.cmd'
if (-not (Test-Path -LiteralPath $askpass)) {
    Write-Error "Askpass helper not found: $askpass"
    exit 1
}

$env:SSH_ASKPASS = $askpass
$env:SSH_ASKPASS_REQUIRE = 'force'
$env:DISPLAY = 'localhost:0'

$sshArgs = @(
    '-o', 'PreferredAuthentications=password',
    '-o', 'PubkeyAuthentication=no',
    '-o', 'NumberOfPasswordPrompts=1',
    '-o', 'StrictHostKeyChecking=accept-new'
)
if ($IdentityFile -ne '' -and (Test-Path -LiteralPath $IdentityFile)) {
    # optional; password auth is preferred above
    $sshArgs += @('-i', $IdentityFile)
}

$target = "$SshUser@$SshHost"
$remoteQuoted = "'" + ($RemotePath -replace "'", "'\''") + "'"
$remoteCmd = "cd $remoteQuoted && tar -xf -"

function Quote-CmdArg([string]$value) {
    if ($value -match '[\s"]') {
        return '"' + ($value -replace '"', '\"') + '"'
    }
    return $value
}

$excludeArgs = ($excludeItems | ForEach-Object { Quote-CmdArg("--exclude=$_") }) -join ' '
$includeArgs = ($includeItems | ForEach-Object { Quote-CmdArg($_) }) -join ' '
$sshOptStr = ($sshArgs | ForEach-Object { Quote-CmdArg($_) }) -join ' '

$cmdLine = "tar.exe -cf - $excludeArgs $includeArgs | ssh.exe $sshOptStr $target `"$remoteCmd`""

Write-Host "Deploy: $LocalRoot -> ${target}:$RemotePath"
Write-Host ("Include: " + ($includeItems -join ', '))
Write-Host ("Exclude: " + ($excludeItems -join ', '))

$outFile = Join-Path $LocalRoot '.deploy-out.txt'
$errFile = Join-Path $LocalRoot '.deploy-err.txt'
Remove-Item -LiteralPath $outFile, $errFile -ErrorAction SilentlyContinue

try {
    $p = Start-Process -FilePath 'cmd.exe' -ArgumentList @('/c', $cmdLine) `
        -WorkingDirectory $LocalRoot -NoNewWindow -Wait -PassThru `
        -RedirectStandardOutput $outFile -RedirectStandardError $errFile

    $out = if (Test-Path -LiteralPath $outFile) { Get-Content -Raw -LiteralPath $outFile } else { '' }
    $err = if (Test-Path -LiteralPath $errFile) { Get-Content -Raw -LiteralPath $errFile } else { '' }

    if ($out) { Write-Host $out.TrimEnd() }
    if ($err) { Write-Host $err.TrimEnd() }

    if ($p.ExitCode -ne 0) {
        Write-Error "Deploy failed with exit code $($p.ExitCode)"
        exit $p.ExitCode
    }
}
finally {
    Remove-Item -LiteralPath $outFile, $errFile -ErrorAction SilentlyContinue
    Remove-Item Env:DEPLOY_SSH_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:SSH_ASKPASS -ErrorAction SilentlyContinue
    Remove-Item Env:SSH_ASKPASS_REQUIRE -ErrorAction SilentlyContinue
}

Write-Host 'Deploy OK'
exit 0
