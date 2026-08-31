from deep_translator import GoogleTranslator
translator = GoogleTranslator(source='auto', target='en')
line = '            <div class="sz-kpi-sub">За 30д · За 7д: <?php echo esc_html($last_7d); ?></div>'
print(translator.translate(line))
