$src  = "D:\Company Work\Company projects\Plugin php\Tvak"
$dest = "D:\xampp\htdocs\Tavplugin\wp-content\plugins\tvak-beauty-kit"
$zip  = Join-Path $src "tvak-beauty-kit.zip"

Write-Host "== Syncing files to XAMPP ==" -ForegroundColor Cyan

# Ensure destination subdirs exist
$null = New-Item -ItemType Directory -Force -Path (Join-Path $dest "includes")
$null = New-Item -ItemType Directory -Force -Path (Join-Path $dest "assets")

# Copy root plugin file
Copy-Item -Path (Join-Path $src "tvak-beauty-kit.php") -Destination $dest -Force

# Copy contents (not the folders themselves) — Entry #013 pattern
Copy-Item -Path (Join-Path $src "includes\*") -Destination (Join-Path $dest "includes") -Recurse -Force
Copy-Item -Path (Join-Path $src "assets\*")   -Destination (Join-Path $dest "assets")   -Recurse -Force

Write-Host "== Sync complete ==" -ForegroundColor Green

# Rebuild zip using python for cross-platform forward-slash zip structure
Write-Host "== Building cross-platform ZIP ==" -ForegroundColor Cyan
python (Join-Path $src "build_clean_zip.py")
