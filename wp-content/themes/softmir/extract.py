import os, re

files = [
    'single-software.php', 'archive-software.php', 'header.php', 'footer.php',
    'home.php', 'single.php', 'page-compare.php', 'page-login.php',
    'page-profile.php', 'page-register.php', 'page-reset-password.php',
    'page-categories.php', 'functions.php'
]
base_dir = r'd:\laragon\www\SoftZorUsa\wp-content\themes\softmir'

# Find content in quotes, HTML tags, // comments, and /* */ comments that contains Cyrillic characters
cyrillic_pattern = re.compile(r'([\'"][^\'"]*?[А-Яа-яЁёІіЇїЄє][^\'"]*?[\'"]|\>[^\<]*?[А-Яа-яЁёІіЇїЄє][^\<]*?\<|\/\/[^\n]*?[А-Яа-яЁёІіЇїЄє][^\n]*|\/\*.*?[А-Яа-яЁёІіЇїЄє].*?\*\/)', re.DOTALL)

found_strings = set()

for file_name in files:
    path = os.path.join(base_dir, file_name)
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
            matches = cyrillic_pattern.findall(content)
            for m in matches:
                found_strings.add(m.strip())

with open(os.path.join(base_dir, 'unique_cyrillic.txt'), 'w', encoding='utf-8') as f:
    for s in sorted(list(found_strings)):
        f.write(s + '\n')
