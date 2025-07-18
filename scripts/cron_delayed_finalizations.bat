@echo off
REM Script batch pour exécuter le traitement des finalisations différées
REM À exécuter toutes les minutes via le planificateur de tâches Windows

cd /d "d:\trea project\YellowJack\scripts"
php process_delayed_finalizations.php >> delayed_finalizations.log 2>&1

REM Garder seulement les 1000 dernières lignes du log
for /f %%i in ('find /c /v "" delayed_finalizations.log') do set lines=%%i
if %lines% gtr 1000 (
    more +500 delayed_finalizations.log > temp_log.txt
    move temp_log.txt delayed_finalizations.log
)