@echo off
title Antigravity Multi-Agent Dispatcher (Port 4000)
cd /d "%~dp0"
echo ========================================================
echo  Antigravity Multi-Agent Dispatcher Baslatiliyor...
echo  TaskManager Webhook Hedefi: http://localhost:4000/webhook
echo ========================================================
python dispatcher.py
pause
