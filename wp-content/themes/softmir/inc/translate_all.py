import re
import os
import subprocess
from deep_translator import GoogleTranslator
import time

def translate_cyrillic_in_file(filepath):
    translator = GoogleTranslator(source='auto', target='en')
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        print(f"Error reading {filepath}: {e}")
        return

    pattern = re.compile(r'[А-Яа-яЁёІіЇїЄєҐґ][А-Яа-яЁёІіЇїЄєҐґa-zA-Z0-9\s\.,!?:;\-\(\)«»/]+[А-Яа-яЁёІіЇїЄєҐґa-zA-Z0-9]')
    
    matches = set(pattern.findall(content))
    
    if not matches:
        single_char_pattern = re.compile(r'[А-Яа-яЁёІіЇїЄєҐґ]+')
        matches = set(single_char_pattern.findall(content))
        if not matches:
            return

    print(f"Translating {len(matches)} strings in {filepath}")
    
    new_content = content
    matches = sorted(list(matches), key=len, reverse=True)
    
    for match in matches:
        original = match.strip()
        if not original:
            continue
        try:
            translated = translator.translate(original)
            time.sleep(0.05)
            
            # Very basic check: if translated has single quote but original didn't, 
            # we might break PHP syntax if it was inside ''.
            # Let's escape it for safety.
            if "'" in translated and "'" not in original:
                translated = translated.replace("'", "\\'")
                
            new_content = new_content.replace(match, translated)
        except Exception as e:
            print(f"Failed to translate '{original}': {e}")
            
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)
        
    # Check PHP syntax
    php_exe = r"d:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
    result = subprocess.run([php_exe, "-l", filepath], capture_output=True, text=True)
    if result.returncode != 0:
        print(f"SYNTAX ERROR in {filepath}:\n{result.stdout}\n{result.stderr}")
    else:
        print(f"Done with {filepath}, syntax OK")

files = [
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\admin-analytics.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\admin-autofill.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\admin-category-ai.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\admin-group-buying.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\admin-partner-requests.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\ai-autopost.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\ai-background-enricher.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\ai-content-pipeline.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\ai-inbox-admin.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\ai-translate-admin.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\api-category-features.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\key-functions.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\prompt-templates.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\quiz-frontend.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\quiz-functions.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\quiz-rest-api.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\schema.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\send-software-info.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\shortcodes.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\smtp.php",
    r"d:\laragon\www\SoftZorUsa\wp-content\themes\softmir\inc\template-helpers.php"
]

for f in files:
    translate_cyrillic_in_file(f)
