import os
import re

def strip_comments(text):
    # Remove single-line comments // and #
    text = re.sub(r'//.*', '', text)
    text = re.sub(r'#.*', '', text)
    # Remove multi-line comments /* ... */
    text = re.sub(r'/\*.*?\*/', '', text, flags=re.DOTALL)
    return text

def check_references():
    root = '.'
    php_files = []
    for dirpath, dirs, filenames in os.walk(root):
        if any(x in dirpath for x in ['.git', 'node_modules', 'vendor']):
            continue
        for f in filenames:
            if f.endswith('.php'):
                php_files.append(os.path.join(dirpath, f))

    print(f"Scanning {len(php_files)} PHP files for includes/requires...")
    include_re = re.compile(r'(?:require|include)(?:_once)?\s*(?:\(?\s*[\'"]([^\'"]+)[\'"]|\s*__DIR__\s*\.\s*[\'"]([^\'"]+)[\'"])')

    missing_includes = []
    total_includes = 0

    for path in php_files:
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            raw_content = f.read()
        
        content = strip_comments(raw_content)
        matches = include_re.findall(content)
        for m1, m2 in matches:
            target = m1 or m2
            if not target or target.startswith(('http://', 'https://')):
                continue
            total_includes += 1
            src_dir = os.path.dirname(path)
            # handle leading slash or relative
            if target.startswith('/'):
                resolved = os.path.normpath(os.path.join(src_dir, '.' + target))
            else:
                resolved = os.path.normpath(os.path.join(src_dir, target))
            
            if not os.path.exists(resolved):
                missing_includes.append((path, target, resolved))

    print(f"Total static includes checked: {total_includes}")
    if missing_includes:
        print(f"Missing includes: {len(missing_includes)}")
        for src, tgt, res in missing_includes:
            print(f"  {src} -> {tgt} (not found at {res})")
    else:
        print("ALL static includes resolved successfully!")

    # Check JS script tags and CSS link tags in index.php and includes/header.php
    html_files = ['index.php', 'login.php', 'includes/header.php', 'includes/footer.php']
    src_re = re.compile(r'(?:src|href)=[\'"]([^\'"]+)[\'"]')
    missing_assets = []
    
    print("\nChecking asset tags in core templates...")
    for hf in html_files:
        if not os.path.exists(hf):
            continue
        with open(hf, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        for ref in src_re.findall(content):
            # ignore external urls, anchors, php tags, mailto
            if ref.startswith(('http://', 'https://', '#', 'mailto:', 'tel:', 'data:', 'javascript:')):
                continue
            if '<?' in ref:
                # clean query params like ?v=<?php echo ... ?>
                ref_clean = ref.split('?')[0]
                if not ref_clean or '<?' in ref_clean:
                    continue
            else:
                ref_clean = ref.split('?')[0]
            
            if not ref_clean or ref_clean in ['', '/', './']:
                continue

            resolved = os.path.normpath(os.path.join('.', ref_clean.lstrip('/')))
            if not os.path.exists(resolved):
                missing_assets.append((hf, ref, resolved))

    if missing_assets:
        print(f"Missing referenced assets: {len(missing_assets)}")
        for src, ref, res in missing_assets:
            print(f"  {src} -> {ref} (not found at {res})")
    else:
        print("ALL referenced scripts, styles, and assets in core templates exist!")

if __name__ == '__main__':
    check_references()
