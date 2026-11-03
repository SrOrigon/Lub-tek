import sqlite3, os
from PIL import Image

os.makedirs('uploads/ambev', exist_ok=True)

conn = sqlite3.connect('banco de dados/ambev.sqlite')
cur = conn.cursor()

images_needed = set()
for q in [
    "SELECT imagem FROM ativos WHERE imagem LIKE 'uploads/%'",
    "SELECT imagem FROM catalogo WHERE imagem LIKE 'uploads/%'"
]:
    cur.execute(q)
    for row in cur.fetchall():
        if row[0]:
            images_needed.add(row[0])

print(f"Total images needed in ambev: {len(images_needed)}")

created = 0
for img_path in images_needed:
    if not os.path.exists(img_path):
        os.makedirs(os.path.dirname(img_path), exist_ok=True)
        im = Image.new('RGB', (240, 240), color=(241, 245, 249))
        im.save(img_path, 'WEBP', quality=65)
        created += 1

print(f"Created {created} lightweight webp images in uploads/ambev")
conn.close()

