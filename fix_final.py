#!/usr/bin/env python3
"""Fix remaining issues in AiGateway.php"""
import os

fp = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'vision-prime', 'app', 'Domains', 'Ai', 'Services', 'AiGateway.php')

with open(fp, 'rb') as f:
    raw = f.read()

changes = []

# Fix 1: Line 536 - User prompt JSON example
# File has: \\\" (0x5C 0x5C 0x22) in the JSON example within double-quoted PHP string
# PHP double-quoted: \\\" = literal \" (backslash + quote) 
# AI sees backslashes in JSON example
# Fix: change \\\\\" to \\\\\" in the JSON portion only (0x5C 0x5C 0x22 -> 0x5C 0x22)
# But we need to be careful to only fix the JSON portion, not other parts of the line

# Find the line with "JSON array" and "heading" and "level"
lines_raw = raw.split(b'\n')
for i, line in enumerate(lines_raw):
    if b'JSON array' in line and b'heading' in line and b'level' in line:
        print(f"Found target line {i+1} (length {len(line)})")
        # Find the JSON array portion: [{...}]
        # It starts with b'[{'
        json_start = line.find(b'[{')
        if json_start >= 0:
            # Find the end: }]
            json_end = line.find(b'}]', json_start)
            if json_end >= 0:
                json_end += 2
                json_part = line[json_start:json_end]
                print(f"  JSON portion: {json_part}")
                
                # Replace \\\\\" (0x5C 0x5C 0x22) with \\\\\" (0x5C 0x22)
                # In the JSON portion, we want to remove one backslash before each double-quote
                fixed_json = bytearray()
                j = 0
                while j < len(json_part):
                    if (j + 2 < len(json_part) and 
                        json_part[j] == 0x5C and 
                        json_part[j+1] == 0x5C and 
                        json_part[j+2] == 0x22):
                        # \\\\\" -> \\\"
                        fixed_json.append(0x5C)
                        fixed_json.append(0x22)
                        j += 3
                    elif (j + 1 < len(json_part) and 
                          json_part[j] == 0x5C and 
                          json_part[j+1] == 0x22):
                        # Already just \\\", keep as is
                        fixed_json.append(0x5C)
                        fixed_json.append(0x22)
                        j += 2
                    else:
                        fixed_json.append(json_part[j])
                        j += 1
                
                fixed_json = bytes(fixed_json)
                print(f"  Fixed JSON: {fixed_json}")
                
                new_line = line[:json_start] + fixed_json + line[json_end:]
                lines_raw[i] = new_line
                changes.append(f"Fixed user prompt JSON escaping (line {i+1})")
        break

raw = b'\n'.join(lines_raw)

# Fix 2: Typo
typo_bytes = 'خروجjتی'.encode('utf-8')
fix_bytes = 'خروجی'.encode('utf-8')
count = raw.count(typo_bytes)
if count > 0:
    raw = raw.replace(typo_bytes, fix_bytes)
    changes.append(f"Fixed typo ({count} occurrences)")

# Write
with open(fp, 'wb') as f:
    f.write(raw)

print(f"\nChanges: {len(changes)}")
for c in changes:
    print(f"  {c}")

# Verify
with open(fp, 'r', encoding='utf-8') as f:
    content = f.read()
lines = content.split('\n')
print("\nVerification:")
for i, line in enumerate(lines):
    if 530 < i < 540 and 'JSON' in line and 'heading' in line:
        print(f"  Line {i+1}: {line.strip()[:120]}")
    if 815 < i < 850 and chr(0x062e) in line:
        print(f"  Line {i+1}: {line.strip()[:100]}")
