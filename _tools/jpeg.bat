@echo off
for %%i in (*.png) do (
  echo %%~ni>&2
  if not exist "%%~ni.jpg" (
    magick "%%i" -quality 80 -sampling-factor 1x1 "%%~ni.jpg"
  )
)