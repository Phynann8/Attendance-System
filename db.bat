@echo off
setlocal
cd /d "%~dp0"
REM ============================================================
REM  Open a MySQL shell into the Docker database
REM
REM    db.bat                      -> interactive SQL shell
REM    db.bat "SELECT * FROM users" -> run a single query
REM
REM  Connection: container "mysql" / db: attendance_system
REM ============================================================

if "%~1"=="" (
    docker compose exec mysql mysql -uattendance -psecret attendance_system
) else (
    docker compose exec -T mysql mysql -uattendance -psecret attendance_system -e "%~1"
)
