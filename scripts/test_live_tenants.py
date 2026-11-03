import requests
import re
import json

BASE_URL = 'https://casadolancheonline-com-159289.hostingersite.com'
HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    'X-Requested-With': 'XMLHttpRequest'
}

def test_tenants():
    s = requests.Session()
    s.headers.update(HEADERS)

    r_login = s.get(f"{BASE_URL}/login.php")
    csrf_m = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', r_login.text)
    csrf_token = csrf_m.group(1) if csrf_m else ''

    test_users = [
        ('demo.Admin', 'changeme'),
        ('demo.Funcionario', 'changeme'),
        ('Cocacola.Admin', 'changeme'),
        ('Cocacola.Funcionario', 'changeme'),
        ('Ambev.Admin', 'changeme'),
        ('Admin', 'password'),
    ]

    print("--- Testando Autenticação Multi-Tenant no Servidor ao Vivo ---")
    for u, p in test_users:
        r = s.post(f"{BASE_URL}/login_process.php", data={'username': u, 'password': p, 'csrf_token': csrf_token})
        try:
            data = r.json()
            ok = data.get('success', False)
            print(f"  {u}: Sucesso={ok} | Role={data.get('user', {}).get('role', '-')} | Tenant={data.get('user', {}).get('tenant', '-')}")
        except Exception:
            print(f"  {u}: HTTP {r.status_code}")

if __name__ == '__main__':
    test_tenants()

