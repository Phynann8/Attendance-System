param (
    [string]$JiraDomain = "https://phynann.atlassian.net",
    [string]$UserEmail = "phynann8@gmail.com",
    [string]$ApiToken = "ATATT3xFfGF0nfCwXfPpgik0gFJLZBIPICqdbMj_-3VpRoqAgPYU1X07j6u8tv6yipkFIIqLGhrqIqq5BLotjnB7SniWMDQKRolizBOo2v1pIlOMhM05GBDO4GEtS-vAns9YoBICaK5qUSJmQR77Y9CJ7itadd2LWfaqbAyE8UMFFTZ8SyuguBc=62C2751E",
    [string]$ProjectKey = "ATTEND",
    [string]$ProjectName = "Attendance Management System"
)

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   ATTENDANCE SYSTEM - JIRA CLOUD REST API SYNC TOOL      " -ForegroundColor Yellow
Write-Host "==========================================================" -ForegroundColor Cyan

$JiraBaseUrl = $JiraDomain.Trim('/')
$AuthString = [Convert]::ToBase64String([System.Text.Encoding]::ASCII.GetBytes("${UserEmail}:${ApiToken}"))
$Headers = @{
    "Authorization" = "Basic $AuthString"
    "Accept"        = "application/json"
    "Content-Type"  = "application/json"
}

Write-Host ""
Write-Host "[1/4] Verifying connection to Jira Cloud..." -ForegroundColor Blue
try {
    $Myself = Invoke-RestMethod -Uri "$JiraBaseUrl/rest/api/3/myself" -Headers $Headers -Method Get
    Write-Host "  [OK] Authenticated as: $($Myself.displayName) ($($Myself.emailAddress))" -ForegroundColor Green
    $LeadAccountId = $Myself.accountId
} catch {
    Write-Host "  [ERROR] Failed to authenticate with Jira Cloud: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "[2/4] Checking project '$ProjectKey'..." -ForegroundColor Blue
try {
    $Project = Invoke-RestMethod -Uri "$JiraBaseUrl/rest/api/3/project/$ProjectKey" -Headers $Headers -Method Get
    Write-Host "  [OK] Project '$ProjectKey' found: $($Project.name)" -ForegroundColor Green
} catch {
    Write-Host "  Project '$ProjectKey' does not exist. Creating..." -ForegroundColor Yellow
    $ProjectBody = @{
        key                = $ProjectKey
        name               = $ProjectName
        projectTypeKey     = "software"
        projectTemplateKey = "com.pyxis.greenhopper.jira:gh-simplified-agility-scrum"
        leadAccountId      = $LeadAccountId
        assigneeType       = "PROJECT_LEAD"
    } | ConvertTo-Json

    $NewProject = Invoke-RestMethod -Uri "$JiraBaseUrl/rest/api/3/project" -Headers $Headers -Method Post -Body $ProjectBody
    Write-Host "  [OK] Project '$ProjectKey' created!" -ForegroundColor Green
}

$ExistingMap = @{}
try {
    $Uri = "$JiraBaseUrl/rest/api/3/search/jql?jql=project=$ProjectKey" + [char]38 + "fields=summary,issuetype"
    $SearchRes = Invoke-RestMethod -Uri $Uri -Headers $Headers -Method Get
    if ($SearchRes.issues) {
        foreach ($iss in $SearchRes.issues) {
            $ExistingMap[$iss.fields.summary] = $iss.key
        }
    }
} catch {
    Write-Host "  [NOTE] Could not query existing issues, will attempt standard creation." -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "[3/4] Parsing audit backlog from 'jira_import.csv'..." -ForegroundColor Blue
$CsvPath = Join-Path $PSScriptRoot "..\jira_import.csv"
if (-not (Test-Path $CsvPath)) {
    $CsvPath = "jira_import.csv"
}

$Items = Import-Csv -Path $CsvPath
Write-Host "  [OK] Loaded $($Items.Count) backlog items" -ForegroundColor Green

Write-Host ""
Write-Host "[4/4] Synchronizing Epics and Stories into Jira..." -ForegroundColor Blue

$EpicKeyMap = @{
    "Workflow Automation & Exceptions" = "ATTEND-1"
}

# 1. Epics
$Epics = $Items | Where-Object { $_.'Issue Type' -eq 'Epic' }
foreach ($epic in $Epics) {
    if ($ExistingMap.ContainsKey($epic.Summary)) {
        $key = $ExistingMap[$epic.Summary]
        $EpicKeyMap[$epic.'Epic Name'] = $key
        Write-Host "  [EXISTS] Epic: $($epic.Summary) -> $key" -ForegroundColor DarkCyan
        continue
    }

    Write-Host "  Creating Epic: $($epic.Summary)..." -NoNewline
    
    $AdfDescription = @{
        type = "doc"
        version = 1
        content = @(
            @{
                type = "paragraph"
                content = @(
                    @{ type = "text"; text = $epic.Description }
                )
            }
        )
    }

    $Body = @{
        fields = @{
            project   = @{ key = $ProjectKey }
            summary   = $epic.Summary
            description = $AdfDescription
            issuetype = @{ name = "Epic" }
            priority  = @{ name = $epic.Priority }
            labels    = ($epic.Labels -split ",") | ForEach-Object { $_.Trim() }
        }
    } | ConvertTo-Json -Depth 10

    try {
        $Created = Invoke-RestMethod -Uri "$JiraBaseUrl/rest/api/3/issue" -Headers $Headers -Method Post -Body $Body
        $EpicKeyMap[$epic.'Epic Name'] = $Created.key
        $ExistingMap[$epic.Summary] = $Created.key
        Write-Host " -> $($Created.key)" -ForegroundColor Green
    } catch {
        Write-Host " -> FAILED: $($_.Exception.Message)" -ForegroundColor Red
    }
}

# 2. Stories
$Stories = $Items | Where-Object { $_.'Issue Type' -ne 'Epic' }
foreach ($story in $Stories) {
    if ($ExistingMap.ContainsKey($story.Summary)) {
        Write-Host "  [EXISTS] Story: $($story.Summary) -> $($ExistingMap[$story.Summary])" -ForegroundColor DarkCyan
        continue
    }

    Write-Host "  Creating $($story.'Issue Type'): $($story.Summary)..." -NoNewline

    $DescText = "$($story.Description)`n`nAcceptance Criteria:`n$($story.'Acceptance Criteria')"
    $AdfDescription = @{
        type = "doc"
        version = 1
        content = @(
            @{
                type = "paragraph"
                content = @(
                    @{ type = "text"; text = $DescText }
                )
            }
        )
    }

    $Fields = @{
        project     = @{ key = $ProjectKey }
        summary     = $story.Summary
        description = $AdfDescription
        issuetype   = @{ name = if ($story.'Issue Type' -eq 'Story') { "Story" } else { "Task" } }
        priority    = @{ name = $story.Priority }
        labels      = ($story.Labels -split ",") | ForEach-Object { $_.Trim() }
    }

    if ($story.'Epic Link' -and $EpicKeyMap.ContainsKey($story.'Epic Link')) {
        $Fields["parent"] = @{ key = $EpicKeyMap[$story.'Epic Link'] }
    }

    $Body = @{ fields = $Fields } | ConvertTo-Json -Depth 10

    try {
        $Created = Invoke-RestMethod -Uri "$JiraBaseUrl/rest/api/3/issue" -Headers $Headers -Method Post -Body $Body
        $ExistingMap[$story.Summary] = $Created.key
        Write-Host " -> $($Created.key)" -ForegroundColor Green
    } catch {
        Write-Host " -> FAILED: $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "   SYNC COMPLETE! VIEW YOUR LIVE JIRA BOARD AT:          " -ForegroundColor Green
Write-Host "   $JiraBaseUrl/jira/software/projects/$ProjectKey/boards " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Green
