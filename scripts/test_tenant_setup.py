#!/usr/bin/env python3
"""
Provisiona tenants de demo e valida multi-tenancy (banco de dados/*.sqlite)
Executar: python scripts/test_tenant_setup.py
"""
import os
import re
import sqlite3
import sys

try:
    import bcrypt
except ImportError:
    print("Instalando bcrypt...")
    os.system(f'"{sys.executable}" -m pip install bcrypt -q')
    import bcrypt

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_DIR = os.path.join(ROOT, "banco de dados")
ADMIN_DB = os.path.join(ROOT, "database.sqlite")
PASSWORD = "changeme"

SCHEMA = [
    """CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        senha TEXT NOT NULL,
        nivel INTEGER DEFAULT 1
    )""",
    """CREATE TABLE IF NOT EXISTS catalogo (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        tipo TEXT,
        fabricante TEXT,
        codigo TEXT,
        estoque_atual REAL DEFAULT 0,
        localizacao TEXT,
        descricao TEXT,
        specs TEXT,
        imagem TEXT
    )""",
    """CREATE TABLE IF NOT EXISTS ativos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        tag TEXT,
        tipo TEXT,
        pai_id INTEGER,
        imagem TEXT,
        obs TEXT,
        dados_tecnicos TEXT,
        ip INTEGER,
        json_specs TEXT,
        fabricante TEXT,
        modelo TEXT,
        num_serie TEXT,
        status TEXT DEFAULT 'OK',
        user_id INTEGER DEFAULT 1
    )""",
    """CREATE TABLE IF NOT EXISTS ordens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        descricao TEXT,
        responsavel TEXT,
        data_planejada TEXT,
        prioridade TEXT,
        situacao TEXT DEFAULT 'Pendente',
        observacao TEXT,
        ativo_id INTEGER,
        usuarios_id INTEGER,
        last_sync DATETIME,
        obs_exec TEXT,
        conc_percent REAL,
        data_execucao TEXT
    )""",
    """CREATE TABLE IF NOT EXISTS rate_limits (
        ip TEXT PRIMARY KEY,
        count INTEGER NOT NULL DEFAULT 0,
        window_start INTEGER NOT NULL
    )""",
    """CREATE TABLE IF NOT EXISTS mercado_ofertas (
        id INTEGER PRIMARY KEY,
        catalogo_id INTEGER,
        vendor_name TEXT,
        price REAL
    )""",
]


def hash_password(pwd: str) -> str:
    h = bcrypt.hashpw(pwd.encode(), bcrypt.gensalt(rounds=10)).decode()
    return h.replace("$2b$", "$2y$", 1)


def verify_password(pwd: str, hashed: str) -> bool:
    try:
        return bcrypt.checkpw(pwd.encode(), hashed.replace("$2y$", "$2b$", 1).encode())
    except Exception:
        return False


def provision_tenant(slug: str) -> str:
    os.makedirs(DB_DIR, exist_ok=True)
    path = os.path.join(DB_DIR, f"{slug}.sqlite")
    if os.path.exists(path):
        os.remove(path)

    conn = sqlite3.connect(path)
    cur = conn.cursor()
    for q in SCHEMA:
        cur.execute(q)

    pwd_hash = hash_password(PASSWORD)
    cur.execute(
        "INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?,?,?,?)",
        ("Admin", f"admin@{slug}.local", pwd_hash, 3),
    )
    cur.execute(
        "INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?,?,?,?)",
        ("Funcionario", f"funcionario@{slug}.local", pwd_hash, 1),
    )
    cur.execute(
        "INSERT INTO ordens (descricao, responsavel, data_planejada, prioridade, situacao) VALUES (?,?,?,?,?)",
        (f"Lubrificação preventiva — {slug.upper()}", "Equipe Campo", "2026-07-20", "Média", "Pendente"),
    )
    conn.commit()
    conn.close()
    return path


def parse_login(raw: str):
    raw = raw.strip()
    if "." not in raw:
        return None, raw
    parts = raw.split(".", 1)
    slug = re.sub(r"[^a-z0-9-]", "", parts[0].lower())
    return (slug or None), parts[1].strip()


def username_variants(username: str):
    key = username.lower().replace("á", "a").replace("é", "e").replace("í", "i")
    key = key.replace("ó", "o").replace("ú", "u").replace("ã", "a").replace("õ", "o").replace("ç", "c")
    if key in ("admin", "gestor", "supervisor", "dono", "gerente"):
        return list(dict.fromkeys(["Admin", "Gestor", username]))
    if key in ("funcionario", "trabalhador", "operador", "tecnico", "mecanico"):
        return list(dict.fromkeys(["Funcionario", "Trabalhador", username]))
    return [username]


def test_login(slug: str, login: str, password: str, expect_ok: bool) -> bool:
    tenant, user = parse_login(login)
    if tenant != slug:
        print(f"  [FAIL] parse login {login}: tenant={tenant} esperado={slug}")
        return False

    path = os.path.join(DB_DIR, f"{slug}.sqlite")
    if not os.path.isfile(path):
        print(f"  [FAIL] banco não existe: {path}")
        return False

    conn = sqlite3.connect(path)
    cur = conn.cursor()
    found = None
    for v in username_variants(user):
        cur.execute(
            "SELECT nome, senha, nivel FROM usuarios WHERE LOWER(nome)=LOWER(?) OR LOWER(email)=LOWER(?)",
            (v, v),
        )
        row = cur.fetchone()
        if row:
            found = row
            break
    conn.close()

    ok = found is not None and verify_password(password, found[1])
    if ok == expect_ok:
        role = "gestor" if found and found[2] >= 2 else "trabalhador"
        print(f"  [OK] {login} -> tenant={slug}, role={role if found else '-'}")
        return True
    print(f"  [FAIL] {login} expect_ok={expect_ok} got={ok}")
    return False


def test_isolation():
    paths = [os.path.join(DB_DIR, f) for f in os.listdir(DB_DIR) if f.endswith(".sqlite")]
    if len(paths) < 2:
        print("  [SKIP] isolamento (precisa de 2+ tenants)")
        return True
    sizes = {os.path.basename(p): os.path.getsize(p) for p in paths}
    print(f"  [OK] {len(paths)} bancos isolados: {', '.join(sizes.keys())}")
    return True


def main():
    print("=== LUB-TEK — Provisionamento & Testes Multi-Tenant ===\n")
    os.makedirs(DB_DIR, exist_ok=True)

    for slug in ("demo", "cocacola"):
        path = provision_tenant(slug)
        print(f"[OK] Tenant '{slug}' -> {path}")

    print("\n--- Testes de login ---")
    tests = [
        ("demo", "demo.Admin", PASSWORD, True),
        ("demo", "demo.Funcionario", PASSWORD, True),
        ("demo", "demo.Funcionario", "wrong", False),
        ("cocacola", "Cocacola.Admin", PASSWORD, True),
        ("cocacola", "Cocacola.Funcionario", PASSWORD, True),
        ("cocacola", "cocacola.Gestor", PASSWORD, True),
        ("demo", "demo.Inexistente", PASSWORD, False),
    ]
    passed = sum(1 for t in tests if test_login(*t))

    print("\n--- Isolamento ---")
    test_isolation()

    print(f"\n=== Resultado: {passed}/{len(tests)} logins OK ===")
    print("\nCredenciais para testar no navegador:")
    print("  demo.Admin / changeme")
    print("  demo.Funcionario / changeme")
    print("  Cocacola.Admin / changeme")
    print("  Cocacola.Funcionario / changeme")
    print("\nAdmin global (database.sqlite): use developer / password quando PHP estiver ativo.")

    return 0 if passed == len(tests) else 1


if __name__ == "__main__":
    sys.exit(main())
