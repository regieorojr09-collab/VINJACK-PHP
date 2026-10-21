<#
.SYNOPSIS
    Automated Git Watcher and Push Daemon for VinJack Motorworks
.DESCRIPTION
    Monitors project files for changes using System.IO.FileSystemWatcher.
    Debounces commits by waiting 10 seconds of inactivity before committing
    and pushing to GitHub origin/main.
#>

param (
    [string]$WatchPath = "$PSScriptRoot\..",
    [int]$DebounceSeconds = 10,
    [string]$Branch = "main",
    [string]$Remote = "origin"
)

# Resolve absolute path
$RootPath = (Resolve-Path $WatchPath).Path
Set-Location -Path $RootPath

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   VINJACK MOTORWORKS - GIT AUTO-SYNC DAEMON ACTIVATED    " -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  Monitoring Directory: $RootPath" -ForegroundColor Gray
Write-Host "  Target Remote Branch: $Remote/$Branch" -ForegroundColor Gray
Write-Host "  Debounce Delay      : $DebounceSeconds seconds" -ForegroundColor Gray
Write-Host "  Press Ctrl+C to terminate auto-sync anytime.`n" -ForegroundColor Yellow

# Patterns to strictly exclude from triggering sync
$ExcludedPatterns = @(
    '\.git',
    '\\scratch',
    '\.vscode',
    '\.gemini',
    '\.agents',
    '\.idea',
    '\.log$',
    '\.tmp$',
    '\.temp$',
    '\.swp$',
    '~$'
)

function Test-IsExcluded([string]$FilePath) {
    foreach ($pattern in $ExcludedPatterns) {
        if ($FilePath -match $pattern) {
            return $true
        }
    }
    return $false
}

# State variables
$script:LastEventTime = $null
$script:PendingFiles = [System.Collections.Generic.HashSet[string]]::new()
$script:IsSyncing = $false

# Initialize FileSystemWatcher
$Watcher = New-Object System.IO.FileSystemWatcher
$Watcher.Path = $RootPath
$Watcher.IncludeSubdirectories = $true
$Watcher.EnableRaisingEvents = $true
$Watcher.NotifyFilter = [System.IO.NotifyFilters]'FileName, LastWrite, DirectoryName'

# Event Action ScriptBlock
$OnChange = {
    param($Source, $EventArgs)
    $RelativePath = $EventArgs.FullPath.Replace($RootPath, '').TrimStart('\/')

    if (-not (Test-IsExcluded -FilePath $EventArgs.FullPath)) {
        $script:PendingFiles.Add($RelativePath) | Out-Null
        $script:LastEventTime = [DateTime]::UtcNow
        $timestamp = (Get-Date).ToString("HH:mm:ss")
        Write-Host "[$timestamp] [CHANGE DETECTED] $($EventArgs.ChangeType): $RelativePath" -ForegroundColor DarkCyan
    }
}

# Register Watcher Events
$Handlers = @()
$Handlers += Register-ObjectEvent -InputObject $Watcher -EventName "Changed" -Action $OnChange
$Handlers += Register-ObjectEvent -InputObject $Watcher -EventName "Created" -Action $OnChange
$Handlers += Register-ObjectEvent -InputObject $Watcher -EventName "Deleted" -Action $OnChange
$Handlers += Register-ObjectEvent -InputObject $Watcher -EventName "Renamed" -Action $OnChange

try {
    while ($true) {
        Start-Sleep -Milliseconds 1000

        # Check if debounce window expired
        if ($null -ne $script:LastEventTime -and (-not $script:IsSyncing)) {
            $Elapsed = ([DateTime]::UtcNow - $script:LastEventTime).TotalSeconds

            if ($Elapsed -ge $DebounceSeconds) {
                $script:IsSyncing = $true
                $script:LastEventTime = $null

                $timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
                Write-Host "`n[$((Get-Date).ToString('HH:mm:ss'))] [SYNC TRIGGERED] Debounce window elapsed. Checking Git status..." -ForegroundColor Yellow

                try {
                    # Check status
                    $status = (git status --porcelain 2>&1)
                    if ([string]::IsNullOrWhiteSpace($status)) {
                        Write-Host "[$((Get-Date).ToString('HH:mm:ss'))] [INFO] Working directory is clean. No sync required." -ForegroundColor Gray
                    } else {
                        Write-Host "[$((Get-Date).ToString('HH:mm:ss'))] [STAGING] Running 'git add .'..." -ForegroundColor Cyan
                        git add .

                        $commitMessage = "auto: sync updates $timestamp"
                        Write-Host "[$((Get-Date).ToString('HH:mm:ss'))] [COMMITTING] Running 'git commit -m `"$commitMessage`"'..." -ForegroundColor Cyan
                        git commit -m $commitMessage

                        Write-Host "[$((Get-Date).ToString('HH:mm:ss'))] [PUSHING] Running 'git push $Remote $Branch'..." -ForegroundColor Magenta
                        $pushResult = (git push $Remote $Branch 2>&1)
                        Write-Host $pushResult -ForegroundColor Gray

                        Write-Host "[$((Get-Date).ToString('HH:mm:ss'))] [SUCCESS] All changes successfully synchronized to GitHub!" -ForegroundColor Green
                    }
                } catch {
                    Write-Host "[$((Get-Date).ToString('HH:mm:ss'))] [ERROR] Synchronization error: $_" -ForegroundColor Red
                } finally {
                    $script:PendingFiles.Clear()
                    $script:IsSyncing = $false
                    Write-Host "[$((Get-Date).ToString('HH:mm:ss'))] [WATCHING] Listening for further file modifications...`n" -ForegroundColor DarkGray
                }
            }
        }
    }
} finally {
    # Cleanup event listeners & watcher on exit
    Write-Host "`nTerminating auto-sync watcher..." -ForegroundColor Yellow
    foreach ($handler in $Handlers) {
        Unregister-Event -SourceIdentifier $handler.Name -ErrorAction SilentlyContinue
    }
    $Watcher.EnableRaisingEvents = $false
    $Watcher.Dispose()
    Write-Host "Auto-sync daemon stopped.`n" -ForegroundColor Red
}
