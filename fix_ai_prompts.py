#!/usr/bin/env python3
"""Fix AI prompts in AiGateway.php"""
import re

filepath = 'workspace-arena-suite/vision-prime/app/Domains/Ai/Services/AiGateway.php'
with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

original = content

# Fix 1: Line 504 - JSON example in single-quoted PHP string has \" which is literal backslash+quote
# In PHP single-quoted strings, \" is NOT a valid escape, so it's literally \" (backslash + double-quote)
# The AI sees: [{\"heading\": \"متن عنوان\"}] which confuses JSON parsing
# Fix: remove the backslashes so AI sees valid JSON: [{"heading": "متن عنوان"}]
# The line in file is: . '[{\"heading\": \"متن عنوان\", \"level\": 2, \"note\": \"توضیح اختیاری - چه محتوایی اینجا برود\"}]\r\n'

# Use a regex to find the line and fix it
def fix_line(line_num, old_pattern, new_text):
    """Replace pattern on specific line"""
    lines = content_fixed.split('\n')
    if line_num <= len(lines):
        old_line = lines[line_num - 1]
        new_line = old_line.replace(old_pattern, new_text)
        if old_line != new_line:
            lines[line_num - 1] = new_line
            print(f'  Fixed line {line_num}')
        else:
            print(f'  Line {line_num}: pattern not found')
        return '\n'.join(lines)
    return content_fixed

content_fixed = content

# Fix 1: JSON example in single-quoted string (line ~504)
# File content: '[{\"heading\": \"متن عنوان\", \"level\": 2, \"note\": \"توضیح...\"}]'
# The backslashes are literal in single-quoted PHP strings, causing AI to output \"
# Fix: remove backslashes before double-quotes in the JSON example
# We need to find this specific pattern in the file
old_json = r"[{\"heading\": \"متن عنوان\", \"level\": 2, \"note\": \"توضیح اختیاری - چه محتوایی اینجا برود\"}]"
new_json = '[{"heading": "\u0645\u062a\u0646 \u0639\u0646\u0648\u0627\u0646", "level": 2, "note": "\u062a\u0648\u0636\u06cc\u062d \u0627\u062e\u062a\u06cc\u0627\u0631\u06cc - \u0686\u0647 \u0645\u062d\u062a\u0648\u0627\u06cc\u06cc \u0627\u06cc\u0646\u062c\u0627 \u0628\u0631\u0648\u062f"}]'

if old_json in content_fixed:
    content_fixed = content_fixed.replace(old_json, new_json)
    print('Fixed JSON example in outline system prompt')
else:
    # Try with raw string variations
    # The file might have actual backslash characters
    lines = content_fixed.split('\n')
    for i, line in enumerate(lines):
        if 'heading' in line and '\u0645\u062a\u0646 \u0639\u0646\u0648\u0627\u0646' in line and i > 490 and i < 510:
            # This is the line. Let's replace \x22 (double quote preceded by backslash) in the JSON part
            # First, find the JSON array portion
            print(f'  Found JSON example at line {i+1}: {repr(line[:80])}')
            # The PHP source has literal \x22 where \" means backslash + quote in single-quoted string
            # We need to remove the backslashes before double-quotes in the JSON example portion
            # Find the JSON array in the line
            import re as regex
            # Match the single-quoted PHP string containing the JSON
            match = regex.search(r"'\[(?:\\\\?\"[^\"]*\\\\?\":\s*\\\\?\"[^\"]*\\\\?\"[, ]*)+\]", line)
            if match:
                json_str = match.group(0)
                # Remove all backslashes that precede double-quotes in the JSON
                fixed_json = json_str.replace('\\"', '"')
                new_line = line[:match.start()] + fixed_json + line[match.end():]
                lines[i] = new_line
                print(f'  Fixed JSON via regex at line {i+1}')
                content_fixed = '\n'.join(lines)
            break

# Fix 2: JSON example in user prompt (double-quoted string, line ~536)
# File has: \\\" which in PHP double-quoted = literal \"
# AI sees: [{\"heading\": ...}] with backslashes
# Fix: change \\\\\" to \\\" in the file
# In double-quoted PHP: \\\" = \ (literal backslash) + " (literal quote)
# We want: \" in the file = " (just quote, no backslash)
lines = content_fixed.split('\n')
for i, line in enumerate(lines):
    if 'JSON array' in line and 'heading' in line and 'level' in line:
        print(f'  Found JSON user example at line {i+1}: {repr(line[:100])}')
        # Replace \\\" with \" in the JSON example portion
        # File has \\\\\" which means in PHP output it's \"
        # We want \\\\\" in file to become \\\" in file
        # Actually: file has 4 backslash-quote sequences
        # Let's just replace the pattern
        if '\\\\\\"' in line:
            # File has 5 backslashes + quote which repr shows as \\\\\\\"
            line = line.replace('\\\\\\"', '\\"')
            lines[i] = line
            print(f'  Fixed user JSON example at line {i+1}')
        content_fixed = '\n'.join(lines)
        break

# Fix 3: Typo خروجjتی → خروجی (lines 818, 848)
content_fixed = content_fixed.replace('\u062e\u0631\u0648\u062cj\u062a\u06cc', '\u062e\u0631\u0648\u062c\u06cc')
content_fixed = content_fixed.replace('\u062e\u0631\u0648\u062cj\u062a\u06cc', '\u062e\u0631\u0648\u062c\u06cc')

# Write if changed
if content_fixed != original:
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content_fixed)
    print(f'\nFile updated successfully')
else:
    print('\nNo changes made')

# Verify
print('\n--- Verification ---')
with open(filepath, 'r', encoding='utf-8') as f:
    lines = f.read().split('\n')
for i, line in enumerate(lines, 1):
    if 'heading' in line and '\u0645\u062a\u0646 \u0639\u0646\u0648\u0627\u0646' in line and 490 < i < 510:
        print(f'Line {i}: {repr(line.strip()[:120])}')
    if 'JSON array' in line and 'heading' in line:
        print(f'Line {i}: {repr(line.strip()[:120])}')
    if '\u062e\u0631\u0648\u062c' in line and '\u062a\u06cc' in line:
        if 810 < i < 850:
            print(f'Line {i} (typo check): {repr(line.strip()[:100])}')
