@echo off
setlocal enabledelayedexpansion
for %%i in (*.png *.webp *.jpg *.jpeg) do (
  echo %%~ni
  set name=%%~ni
  set prefix=!name:~0,2!
  mkdir "!prefix!" 2>NUL
  move "%%i" "!prefix!\%%~nxi" 1>NUL 2>NUL
)