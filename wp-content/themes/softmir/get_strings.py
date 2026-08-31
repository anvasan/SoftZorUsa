import re, json, os

files = [
    'single-software.php', 'archive-software.php', 'header.php', 'footer.php',
    'home.php', 'single.php', 'page-compare.php', 'page-login.php',
    'page-profile.php', 'page-register.php', 'page-reset-password.php',
    'page-categories.php', 'functions.php'
]
base_dir = r'd:\laragon\www\SoftZorUsa\wp-content\themes\softmir'

cyrillic_pattern = re.compile(r'[\'"]([^\'"]*[А-Яа-яЁёІіЇїЄє][^\'"]*)[\'"]')
html_pattern = re.compile(r'>([^<]*[А-Яа-яЁёІіЇїЄє][^<]*)<')

strings = set()

for file_name in files:
    path = os.path.join(base_dir, file_name)
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
            for m in cyrillic_pattern.findall(content):
                strings.add(m)
            for m in html_pattern.findall(content):
                strings.add(m.strip())

with open(os.path.join(base_dir, 'strings.json'), 'w', encoding='utf-8') as f:
    json.dump(list(strings), f, ensure_ascii=False, indent=2)
