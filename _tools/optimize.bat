@echo off
for %%i in (*.png) do (
  echo %%~ni>&2
  optipng -o7 "%%i"
)