#!/usr/bin/env python3
"""Fix AI prompts in AiGateway.php"""
import os

fp = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'vision-prime', 'app', 'Domains', 'Ai', 'Services', 'AiGateway.php')
with open(fp, 'rb') as f:
    raw = f.read()

changes = []

# Fix 1: Line 504 - Single-quoted PHP string JSON example
# File has: [{\"heading\": \"متن عنوان\", ...}]
# The 0x5C before each 0x22 makes the AI see backslashes in JSON
# Fix: remove 0x5C before 0x22 in the JSON array within single-quoted string
# Find the pattern: single-quote, [, {, 0x5C 0x22, heading
single_quote_json = b"[{\\x22heading"  # No, need actual bytes

# Search for the pattern in raw bytes
pattern1 = b"[{\\x22"  # No, this is [ { \ x 2 2
# The actual bytes for the pattern are: [ { 0x5C 0x22 h e a d i n g
pattern1 = bytes([0x5B, 0x7B, 0x5C, 0x22, 0x68, 0x65, 0x61, 0x64, 0x69, 0x6E, 0x67])
idx1 = raw.find(pattern1)

if idx1 >= 0:
    # Found it. Now find the end of this JSON array: }] followed by single-quote
    # Search for }]  0x5C 0x22 0x5D 0x7D
    end_pattern = bytes([0x5C, 0x22, 0x5D, 0x7D])
    end_idx = raw.find(end_pattern, idx1)
    if end_idx >= 0:
        end_idx += 4  # Include the }] 
        json_section = raw[idx1:end_idx]
        # Remove all 0x5C (backslash) that precede 0x22 (double-quote)
        fixed = json_section.replace(b'\\x5c\\x22', b'\\x22')
        # No wait, that's wrong. Let me think in bytes.
        # json_section contains bytes like: [{\\x22heading\\x22: \\x22text\\x22, ...}]
        # I want to remove the 0x5C bytes that are before 0x22
        # Simple approach: replace 0x5C 0x22 with just 0x22
        fixed = b''
        i = 0
        while i < len(json_section):
            if json_section[i] == 0x5C and i + 1 < len(json_section) and json_section[i+1] == 0x22:
                # Skip the backslash
                fixed += b'\x22'
                i += 2
            else:
                fixed += bytes([json_section[i]])
                i += 1
        
        raw = raw[:idx1] + fixed + raw[end_idx:]
        changes.append(f"Fixed system prompt JSON (byte {idx1})")
    else:
        print("Could not find end of JSON array for fix 1")
else:
    print("Pattern 1 not found, checking bytes...")
    # Show what's around byte 19565 (from investigation)
    if len(raw) > 19565:
        print(f"  Around 19565: {raw[19560:19620]}")

# Fix 2: Line 536 - Double-quoted PHP string JSON example
# File has: [{\\\"heading\\\": \\\"...\\\", ...}]
# In bytes: [{ 0x5C 0x5C 0x22 heading 0x5C 0x5C 0x22 : ...}]
# PHP double-quoted: \\\" → \" (literal backslash + quote)
# AI sees: [{\"heading\": ...}] with backslashes
# Fix: change \\\\\" to \\\\\" → \\\\\" (remove one backslash)
# Actually: file has 0x5C 0x5C 0x22 → PHP outputs 0x5C 0x22 → AI sees \"
# We want file to have: 0x5C 0x22 → PHP outputs 0x22 → AI sees "
# So remove one 0x5C before each pair of 0x5C 0x22 in the JSON section

# Search for the JSON array in the double-quoted user prompt
pattern2 = b'[{\\x5c\\x5c\\x22heading'  # Still wrong...

# The bytes for \\\\\\\" in the file are: 0x5C 0x5C 0x22
pattern2 = bytes([0x5B, 0x7B, 0x5C, 0x5C, 0x22, 0x68, 0x65, 0x61, 0x64, 0x69, 0x6E, 0x67])
idx2 = raw.find(pattern2)

if idx2 >= 0:
    # Find the end of this JSON array: \\\"}] in bytes is 0x5C 0x5C 0x22 0x5D 0x7D
    end_pattern2 = bytes([0x5C, 0x5C, 0x22, 0x5D, 0x7D])
    end_idx2 = raw.find(end_pattern2, idx2)
    if end_idx2 >= 0:
        end_idx2 += 5
        json_section2 = raw[idx2:end_idx2]
        # Replace \\x5c\\x22 (backslash+backslash+quote = 3 bytes) with \\x22 (backslash+quote = 2 bytes)
        # Only replace when it's \\x5c\\x5c\\x22 (the pattern for escaped quote in d-quoted PHP)
        fixed2 = b''
        i = 0
        while i < len(json_section2):
            if (json_section2[i] == 0x5C and 
                i + 2 < len(json_section2) and 
                json_section2[i+1] == 0x5C and 
                json_section2[i+2] == 0x22):
                # Replace \\\\\" with \\\\\"
                fixed2 += bytes([0x5C, 0x22])
                i += 3
            else:
                fixed2 += bytes([json_section2[i]])
                i += 1
        
        raw = raw[:idx2] + fixed2 + raw[end_idx2:]
        changes.append(f"Fixed user prompt JSON (byte {idx2})")
    else:
        print("Could not find end of JSON array for fix 2")
else:
    print("Pattern 2 not found")

# Fix 3: Typo
typo = 'خروجjتی'.encode('utf-8')
fix = 'خروجی'.encode('utf-8')
count = raw.count(typo)
if count > 0:
    raw = raw.replace(typo, fix)
    changes.append(f"Fixed typo ({count} occurrences)")

# Write
with open(fp, 'wb') as f:
    f.write(raw)

print(f"\nChanges applied: {len(changes)}")
for c in changes:
    print(f"  - {c}")

# Verify
with open(fp, 'r', encoding='utf-8') as f:
    content = f.read()
lines = content.split('\n')
print("\nVerification:")
for i, line in enumerate(lines):
    if 500 < i < 510 and 'heading' in line and 'level' in line:
        print(f"  Line {i+1}: {line.strip()[:120]}")
    if 530 < i < 540 and 'JSON' in line and 'heading' in line:
        print(f"  Line {i+1}: {line.strip()[:120]}")
    if 815 < i < 850 and 'خروج' in line:
        print(f"  Line {i+1}: {line.strip()[:100]}")
