param (
    [string]$JiraDomain = $env:JIRA_DOMAIN,
    [string]$UserEmail = $env:JIRA_USER_EMAIL,
    [string]$ApiToken = $env:JIRA_API_TOKEN,
    [string]$ProjectKey = "ATTEND"
)

if (-not $UserEmail -or -not $ApiToken -or -not $JiraDomain) {
    Write-Host "ERROR: Missing required Jira credentials." -ForegroundColor Red
    Write-Host "Please set environment variables before running:" -ForegroundColor Yellow
    Write-Host '  $env:JIRA_DOMAIN      = "https://your-domain.atlassian.net"' -ForegroundColor Gray
    Write-Host '  $env:JIRA_USER_EMAIL   = "your-email@example.com"' -ForegroundColor Gray
    Write-Host '  $env:JIRA_API_TOKEN    = "your-jira-api-token"' -ForegroundColor Gray
    exit 1
}

$JiraBaseUrl = $JiraDomain.Trim('/')
$AuthString = [Convert]::ToBase64String([System.Text.Encoding]::ASCII.GetBytes("${UserEmail}:${ApiToken}"))
$Headers = @{
    "Authorization" = "Basic $AuthString"
    "Accept"        = "application/json"
    "Content-Type"  = "application/json"
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   ATTENDANCE SYSTEM - JIRA CLOUD TRANSITION TO DONE      " -ForegroundColor Yellow
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. List of all 31 completed stories in the codebase
$CompletedSummaries = @(
    @{ Pattern = "Admin Attendance Session Reopen & Amendment Workflow"; Comment = "Implemented admin session reopen workflow with mandatory audit reason modal, session status reset, and audit log." },
    @{ Pattern = "Structured Absence Taxonomy with Detail Description Input"; Comment = "Implemented absence category taxonomy dropdown with free-text detail description in parent and admin views." },
    @{ Pattern = "Teacher 1-Click 'Mark All Present' & Quick Toggle Shortcuts"; Comment = "Implemented 1-click 'Mark All Present' button with fa-check-double icon." },
    @{ Pattern = "Interactive Confirmation Modal for Attendance Submission"; Comment = "Implemented interactive submission confirmation modal with live tallies, explicit teacher confirmation, and auto-save draft." },
    @{ Pattern = "Complete Khmer (KH) & English (EN) Localization Wiring"; Comment = "Implemented Khmer (km) and English (en) localization with dynamic language switching wired to topbar 'KH | EN' pill." },
    @{ Pattern = "Inner View Vector Iconography Standardization"; Comment = "Replaced all residual emojis with Font Awesome 6 vector icons across reports, permissions, mark, and student views." },
    @{ Pattern = "Custom Date Range & Multi-Filter Report Generator"; Comment = "Upgraded ReportController to multi-filter query engine supporting custom date ranges, presets (Today, This Week, This Month), class, and status." },
    @{ Pattern = "Eliminate Teacher Dashboard N+1 Query Inefficiency"; Comment = "Refactored DashboardController to eager-load active student counts and daily sessions with single-query grouping." },
    @{ Pattern = "Composite Database Indexing on Attendances Table"; Comment = "Added composite database indexes on attendances table: ['final_status', 'finalized_at'] and ['student_id', 'final_status']." },
    @{ Pattern = "Asynchronous Background Queue for Audit Logging"; Comment = "Implemented LogAuditEventJob implementing ShouldQueue and AuditService::dispatch for asynchronous background queue logging." },
    @{ Pattern = "Two-Factor Authentication (2FA / TOTP) for Administrators"; Comment = "Implemented Google Authenticator TOTP time-based 2FA challenge and verification flow for administrator logins." },
    @{ Pattern = "Evidence File Upload Security & Malware / MIME Verification"; Comment = "Implemented FileUploadSecurityService with strict MIME validation, 5MB limit, and SHA256 randomized storage." },
    @{ Pattern = "30-Minute Inactivity Auto-Timeout & Session Locking"; Comment = "Implemented EnforceInactivityTimeout middleware and client-side countdown modal enforcing 30-minute idle session termination." },
    @{ Pattern = "Automated Telegram Bot & SMS Attendance Notifications"; Comment = "Implemented TelegramNotificationService, SmsNotificationService, and queued SendAttendanceNotificationJob." },
    @{ Pattern = "REST API with Laravel Sanctum for Mobile / Tablet Apps"; Comment = "Implemented RESTful API under /api/v1 authenticated with Laravel Sanctum tokens with feature tests." },
    @{ Pattern = "Extend Student & Classroom Schema for PSIS Compatibility"; Comment = "Added student_code, khmer_name, gender, dob, psis_student_id, and psis_group_id to database schema." },
    @{ Pattern = "PSIS SQL Backup Stream Parser & Artisan Command (psis:import)"; Comment = "Implemented PsisSyncService memory-efficient SQL parser and Artisan command php artisan psis:import." },
    @{ Pattern = "Multi-Campus Tenancy & Data Isolation (CHV, KB, KSR, NR3)"; Comment = "Implemented multi-campus tenancy scoping, campus_user junction, and topbar campus switcher." },
    @{ Pattern = "Login Route Rate Limiting (Brute-Force Protection)"; Comment = "Added throttle:5,1 rate limiting middleware to POST /login and 2FA verify routes in web.php." },
    @{ Pattern = "Hide Manual Create Student & Create Class Buttons from Admin UI"; Comment = "Removed '+ Add Student' and '+ Add Class' buttons from admin views, keeping backend routes as fallbacks." },
    @{ Pattern = "Remove Hardcoded Jira API Token from Sync Script"; Comment = "Refactored Jira synchronization scripts to read credentials strictly from environment variables." },
    @{ Pattern = "Campus-Scope the Audit Log Viewer for Admin Users"; Comment = "Scoped AuditLogController by activeCampusId() and assigned campuses so non-super-admins only view their campus." },
    @{ Pattern = "Increase Minimum Password Length from 6 to 8 Characters"; Comment = "Enforced min:8 password length validation in SuperAdmin UserController on create and update." },
    @{ Pattern = "Log CacheService Tag-Flush Failures Instead of Silently Swallowing"; Comment = "Added Log::warning in CacheService::invalidateDashboardStats catch block." },
    @{ Pattern = "Class Schedules, Substitutions & Attendance Session Schema"; Comment = "Created class_schedules and schedule_substitutions tables and linked attendance_sessions to schedule slots." },
    @{ Pattern = "Schedule-Based Teacher Authorization & Primary Period Escalation"; Comment = "Implemented substitution-aware authorization in ScheduleController and is_primary morning escalation." },
    @{ Pattern = "Teacher Dashboard Today's Period Schedule View"; Comment = "Refactored teacher dashboard to display today's assigned periods chronologically with status buttons." },
    @{ Pattern = "Admin Timetable Bulk CSV Import & Timetable Management"; Comment = "Implemented php artisan schedule:import command and ScheduleImportService for bulk CSV timetable loading." },
    @{ Pattern = "Admin Date-Specific Quick Teacher Substitution Action"; Comment = "Implemented ScheduleSubstitutionController and quick modal action for date-specific teacher substitution." },
    @{ Pattern = "Core Domain Services & Logic Unit Test Suite (tests/Unit)"; Comment = "Built isolated unit test suite covering UserModel, PermissionModel, and ScheduleImportService without DB." },
    @{ Pattern = "Full-Cycle Multi-Role QA Smoke & Regression Test Suite"; Comment = "Implemented php artisan smoke:test and MultiRoleFullCycleSmokeTest covering full 5-role school day workflow." }
)

Write-Host "Querying all issues in Jira Project '$ProjectKey'..." -ForegroundColor Blue
$SearchUri = "$JiraBaseUrl/rest/api/3/search/jql?jql=project=$ProjectKey" + [char]38 + "maxResults=100" + [char]38 + "fields=summary,status,issuetype"
$SearchResult = Invoke-RestMethod -Uri $SearchUri -Headers $Headers -Method Get

$JiraIssues = $SearchResult.issues
Write-Host "Found $($JiraIssues.Count) issues in Jira Cloud." -ForegroundColor Green
Write-Host ""

$TransitionedCount = 0

foreach ($completed in $CompletedSummaries) {
    $match = $JiraIssues | Where-Object { $_.fields.summary -like "*$($completed.Pattern)*" } | Select-Object -First 1

    if (-not $match) {
        Write-Host "  [NOT FOUND IN JIRA] $($completed.Pattern)" -ForegroundColor DarkGray
        continue
    }

    $Key = $match.key
    $CurrentStatus = $match.fields.status.name

    if ($CurrentStatus -match "Done|Closed|Resolved") {
        Write-Host "  [ALREADY DONE] $Key : $($match.fields.summary)" -ForegroundColor DarkGreen
        continue
    }

    # Query available transitions for this issue
    $TransUri = "$JiraBaseUrl/rest/api/3/issue/$Key/transitions"
    $TransitionsRes = Invoke-RestMethod -Uri $TransUri -Headers $Headers -Method Get
    $DoneTransition = $TransitionsRes.transitions | Where-Object { $_.name -match "Done|Closed|Resolve" } | Select-Object -First 1

    if (-not $DoneTransition) {
        Write-Host "  [NO DONE TRANSITION] $Key : Available transitions: $(($TransitionsRes.transitions.name) -join ', ')" -ForegroundColor Yellow
        continue
    }

    try {
        $Body = @{
            transition = @{ id = $DoneTransition.id }
        } | ConvertTo-Json

        Invoke-RestMethod -Uri $TransUri -Headers $Headers -Method Post -Body $Body | Out-Null
        Write-Host "  [TRANSITIONED -> DONE] $Key ($($DoneTransition.name)) : $($match.fields.summary)" -ForegroundColor Green
        $TransitionedCount++

        # Post Resolution Comment
        $CommentBody = @{
            body = @{
                type = "doc"
                version = 1
                content = @(
                    @{
                        type = "paragraph"
                        content = @(
                            @{
                                type = "text"
                                text = "[RESOLVED & TESTED] $($completed.Comment)"
                            }
                        )
                    }
                )
            }
        } | ConvertTo-Json -Depth 10

        Invoke-RestMethod -Uri "$JiraBaseUrl/rest/api/3/issue/$Key/comment" -Headers $Headers -Method Post -Body $CommentBody | Out-Null
    } catch {
        Write-Host "  [ERROR] Could not transition $Key : $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "   SYNC FINISHED! Successfully updated $TransitionedCount issues to Done." -ForegroundColor Green
Write-Host "   View Jira Board: $JiraBaseUrl/jira/software/projects/$ProjectKey/boards" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Green

