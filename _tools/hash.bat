@echo off
mkdir hashed
(
  echo {
  for %%i in (map*.png) do (
    for /f "delims=" %%h in ('magick identify -format "%%#" "%%i"') do (
      cp %%i "hashed/%%h.png"
      echo %%~ni %%h>&2
      echo "%%~ni":"%%h",
    )
  )
  echo "":""}
) > hashes.json