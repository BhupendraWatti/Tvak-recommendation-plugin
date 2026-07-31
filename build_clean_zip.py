import os
import zipfile

src_dir = r"D:\Company Work\Company projects\Plugin php\Tvak"
zip_path = os.path.join(src_dir, "tvak-beauty-kit.zip")

print("== Building Cross-Platform WordPress Plugin ZIP ==")

if os.path.exists(zip_path):
    os.remove(zip_path)

allowed_extensions = ('.php', '.css', '.js', '.png', '.jpg', '.jpeg', '.svg', '.json', '.txt')

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
    for root, dirs, files in os.walk(src_dir):
        # Exclude development/scratch directories
        if any(x in root for x in ['scratch', '.git', '.gemini', '.claude', 'node_modules', 'docs']):
            continue
            
        for file in files:
            if not file.endswith(allowed_extensions):
                continue
            if file == 'tvak-beauty-kit.zip':
                continue
                
            full_path = os.path.join(root, file)
            rel_path = os.path.relpath(full_path, src_dir)
            
            # Use forward slashes (/) for cross-platform ZIP compatibility
            unix_rel_path = rel_path.replace('\\', '/')
            arcname = 'tvak-beauty-kit/' + unix_rel_path
            
            zipf.write(full_path, arcname)
            print(f" Added: {arcname}")

print(f"\nSUCCESS: Built {zip_path} ({round(os.path.getsize(zip_path)/1024, 1)} KB)")
