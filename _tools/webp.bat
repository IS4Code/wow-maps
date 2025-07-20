@echo off
for %%i in (*.png) do (
  echo %%~ni>&2
  if not exist "%%~ni.webp" (
    magick "%%i" -quality 100 -define webp:alpha-compression=0 -define webp:lossless=true -define webp:method=6 -define webp:partitions=3 -define webp:partition-limit=0 "%%~ni.webp"
  )
)