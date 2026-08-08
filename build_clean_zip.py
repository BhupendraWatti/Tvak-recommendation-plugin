import os
import zipfile

base_dir = r"D:\Company Work\Company projects\Plugin php\Tvak"
allowed_extensions = ('.php', '.css', '.js', '.png', '.jpg', '.jpeg', '.svg', '.json', '.txt', '.mo', '.po')

plugins = [
    {
        'slug': 'tvak-beauty-kit',
        'dir': os.path.join(base_dir, 'tvak-beauty-kit'),
        'zip': os.path.join(base_dir, 'tvak-beauty-kit.zip'),
        'name': 'TVAK Personalized Beauty Recommendation Engine'
    },
    {
        'slug': 'tvak-custom-hamper-builder',
        'dir': os.path.join(base_dir, 'tvak-custom-hamper-builder'),
        'zip': os.path.join(base_dir, 'tvak-custom-hamper-builder.zip'),
        'name': 'TVAK Custom Hamper Builder'
    }
]

print("==================================================")
print("  BUILDING CROSS-PLATFORM WORDPRESS PLUGIN ZIPS   ")
print("==================================================")

for plugin in plugins:
    plugin_dir = plugin['dir']
    zip_path = plugin['zip']
    slug = plugin['slug']
    name = plugin['name']

    print(f"\nPackaging: {name} ({slug})")

    if os.path.exists(zip_path):
        os.remove(zip_path)

    if not os.path.exists(plugin_dir):
        print(f" ERROR: Directory {plugin_dir} does not exist!")
        continue

    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(plugin_dir):
            if any(x in root for x in ['scratch', '.git', '.gemini', '.claude', '.deploy-backups', 'node_modules']):
                continue

            for file in files:
                if not file.endswith(allowed_extensions):
                    continue

                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, plugin_dir)
                unix_rel_path = rel_path.replace('\\', '/')
                arcname = f"{slug}/{unix_rel_path}"

                zipf.write(full_path, arcname)
                print(f"  + Added: {arcname}")

    size_kb = round(os.path.getsize(zip_path) / 1024, 1)
    print(f"SUCCESS: Built {zip_path} ({size_kb} KB)")

print("\nAll plugin zips built successfully!")
