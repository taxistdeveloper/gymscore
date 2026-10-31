@echo off
REM OpenSSH askpass helper: prints password from env (set only for deploy run).
powershell.exe -NoProfile -Command "[Console]::Out.Write([Environment]::GetEnvironmentVariable('DEPLOY_SSH_PASSWORD','Process'))"
