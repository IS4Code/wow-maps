@echo off
for %%i in (*.png) do @(
  optipng -o7 "%%i"
)