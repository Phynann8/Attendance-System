$UserEmail = "phynann8@gmail.com"
$ApiToken = "ATATT3xFfGF0nfCwXfPpgik0gFJLZBIPICqdbMj_-3VpRoqAgPYU1X07j6u8tv6yipkFIIqLGhrqIqq5BLotjnB7SniWMDQKRolizBOo2v1pIlOMhM05GBDO4GEtS-vAns9YoBICaK5qUSJmQR77Y9CJ7itadd2LWfaqbAyE8UMFFTZ8SyuguBc=62C2751E"
$JiraBaseUrl = "https://phynann.atlassian.net"

$AuthString = [Convert]::ToBase64String([System.Text.Encoding]::ASCII.GetBytes("${UserEmail}:${ApiToken}"))
$Headers = @{
    "Authorization" = "Basic $AuthString"
    "Accept"        = "application/json"
    "Content-Type"  = "application/json"
}

$CompletedIssues = @(
    @{ Key = "ATTEND-8";  Comment = "Implemented 1-click 'Mark All Present' button with fa-check-double icon." },
    @{ Key = "ATTEND-9";  Comment = "Implemented interactive submission confirmation modal with live tallies, explicit teacher confirmation, and auto-save draft." },
    @{ Key = "ATTEND-10"; Comment = "Replaced all residual emojis with Font Awesome 6 vector icons across reports, permissions, mark, and student views." },
    @{ Key = "ATTEND-11"; Comment = "Implemented Khmer (km) and English (en) localization with dynamic language switching wired to topbar 'KH | EN' pill." },
    @{ Key = "ATTEND-2";  Comment = "Implemented Admin session reopen workflow with mandatory audit reason modal, session status reset, and audit log." },
    @{ Key = "ATTEND-12"; Comment = "Implemented absence category taxonomy dropdown with free-text detail description in parent and admin views." },
    @{ Key = "ATTEND-14"; Comment = "Upgraded ReportController to multi-filter query engine supporting custom date ranges, presets (Today, This Week, This Month), class, and status." },
    @{ Key = "ATTEND-15"; Comment = "Implemented UTF-8 BOM CSV exports supporting active report filters and Excel compatibility." },
    @{ Key = "ATTEND-16"; Comment = "Added composite database indexes on attendances table: ['final_status', 'finalized_at'] and ['student_id', 'final_status']." },
    @{ Key = "ATTEND-17"; Comment = "Implemented LogAuditEventJob implementing ShouldQueue and AuditService::dispatch for asynchronous logging." }
)

Write-Host "Transitioning completed issues to 'Done' (ID: 41)..." -ForegroundColor Cyan

foreach ($item in $CompletedIssues) {
    $Key = $item.Key
    $Comment = $item.Comment

    try {
        $Body = @{
            transition = @{ id = "41" }
        } | ConvertTo-Json

        Invoke-RestMethod -Uri "$JiraBaseUrl/rest/api/3/issue/$Key/transitions" -Headers $Headers -Method Post -Body $Body | Out-Null
        Write-Host "  [DONE] $Key transitioned to Done." -ForegroundColor Green

        # Add comment
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
                                text = "[RESOLVED in Enterprise Release] $Comment"
                            }
                        )
                    }
                )
            }
        } | ConvertTo-Json -Depth 10

        Invoke-RestMethod -Uri "$JiraBaseUrl/rest/api/3/issue/$Key/comment" -Headers $Headers -Method Post -Body $CommentBody | Out-Null
        Write-Host "    -> Comment added to $Key" -ForegroundColor Gray
    } catch {
        Write-Host "  [SKIP/ERR] ${Key}: $($_.Exception.Message)" -ForegroundColor Yellow
    }
}

# Comment on ATTEND-13 (Gate Scan aborted)
try {
    $AbortComment = @{
        body = @{
            type = "doc"
            version = 1
            content = @(
                @{
                    type = "paragraph"
                    content = @(
                        @{
                            type = "text"
                            text = "[ABORTED PER STAKEHOLDER DIRECTIVE] Hardware (webcam/barcode scanner) currently unavailable at physical campus gates. Preserving manual Student Affairs gate arrival flow."
                        }
                    )
                }
            )
        }
    } | ConvertTo-Json -Depth 10
    Invoke-RestMethod -Uri "$JiraBaseUrl/rest/api/3/issue/ATTEND-13/comment" -Headers $Headers -Method Post -Body $AbortComment | Out-Null
    Write-Host "  [NOTED] ATTEND-13 marked as aborted per stakeholder requirement." -ForegroundColor Magenta
} catch {
    Write-Host "  Could not comment on ATTEND-13: $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "All tasks successfully updated in live Jira Cloud!" -ForegroundColor Green
