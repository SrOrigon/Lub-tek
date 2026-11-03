import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

include_pattern = re.compile(r"""(?:require|require_once|include|include_once)\s*\(?\s*(?:__DIR__\s*\.\s*)?['"]([^'"]+\.php)['"]""", re.IGNORECASE)

case_errors = []
backslash_errors = []
missing_errors = []

for root_dir, dirs, files in os.walk(ROOT):
    if '.git' in root_dir or 'deploy' in root_dir or 'node_modules' in root_dir or 'vendor' in root_dir:
        continue
    for f in files:
        if f.endswith('.php'):
            full_path = os.path.join(root_dir, f)
            with open(full_path, 'r', encoding='utf-8', errors='ignore') as fh:
                content = fh.read()
            matches = include_pattern.findall(content)
            for m in matches:
                if '\\' in m:
                    backslash_errors.append((full_path, m))
                
                # Check resolved target
                m_clean = m.replace('\\', '/')
                if m_clean.startswith('/'):
                    target1 = os.path.normpath(os.path.join(root_dir, '.' + m_clean))
                    target2 = os.path.normpath(os.path.join(ROOT, '.' + m_clean))
                else:
                    target1 = os.path.normpath(os.path.join(root_dir, m_clean))
                    target2 = os.path.normpath(os.path.join(ROOT, m_clean))
                
                target = target1 if os.path.exists(target1) else (target2 if os.path.exists(target2) else None)
                if target:
                    dir_part = os.path.dirname(target)
                    base_part = os.path.basename(target)
                    if os.path.exists(dir_part):
                        actual_files = os.listdir(dir_part)
                        if base_part not in actual_files:
                            matches_case = [x for x in actual_files if x.lower() == base_part.lower()]
                            if matches_case:
                                case_errors.append((os.path.relpath(full_path, ROOT), m, matches_case[0]))
                else:
                    # File does not exist at either path
                    missing_errors.append((os.path.relpath(full_path, ROOT), m))

print(f"=== CASE-SENSITIVITY AUDIT (HOSTINGER LINUX) ===")
print(f"Case errors: {len(case_errors)}")
for f, inc, act in case_errors:
    print(f"  [CASE] {f} -> includes '{inc}' but file on disk is '{act}'")

print(f"\nBackslash errors: {len(backslash_errors)}")
for f, inc in backslash_errors:
    print(f"  [SLASH] {f} -> includes '{inc}' (uses backslash)")

print(f"\nMissing files: {len(missing_errors)}")
for f, inc in missing_errors:
    print(f"  [MISSING] {f} -> cannot resolve '{inc}'")
