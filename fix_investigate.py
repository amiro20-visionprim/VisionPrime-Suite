#!/usr/bin/env python3
"""Fix AI prompts in AiGateway.php - write to file to avoid shell escaping"""
import re
import os

fp = os.path.join(os.path.dirname(__file__), 'vision-prime', 'app', 'Domains', 'Ai', 'Services', 'AiGateway.php')
with open(fp, 'r', encoding='utf-8') as f:
    content = f.read()

original = content
changes = []

# === Fix 1: Line 504 - JSON example in single-quoted PHP string ===
# The file literally contains: \" in single-quoted PHP strings
# PHP single-quoted: \" = literal backslash + double-quote (NOT an escape)
# AI receives: [{\"heading\": ...}] with literal backslashes
# Fix: remove backslashes before double-quotes in the JSON array
# 
# Search for the exact line containing the JSON example
json_example_pattern = r'\\[\\{\\\\"heading\\\\": \\\"\\u0645\\u062a\\u0646 \\u0639\\u0646\\u0648\\u0627\\u0646\\\"'

# Actually, let me just read the file bytes and check
lines = content.split('\n')

for i, line in enumerate(lines):
    # Line with JSON example for system prompt
    if i > 490 and i < 510 and '"heading"' in line and '"mfn' in line:
        print(f"Found system JSON at line {i+1}")
        print(f"  Raw: {repr(line.strip()[:150])}")
        break
    # Let me search more broadly
    if i > 490 and i < 510 and 'heading' in line and 'level' in line:
        print(f"Found JSON line at {i+1}: {repr(line.strip()[:150])}")
        break

# Hmm, let me just use a different approach entirely.
# Let me use sed-style replacement on specific byte patterns.

# Read raw bytes to see what's actually in the file
with open(fp, 'rb') as f:
    raw = f.read()

# Find the problematic patterns
# Pattern 1: In the single-quoted system prompt, look for the JSON example
# The bytes will be: [{\x22heading\x22: \x22... but preceded by \x5c (backslash)
# So it's: [{\x5c\x22heading\x5c\x22  which in PHP single-quoted means: \" literally

# Let's find it
idx = raw.find(b'\\x5c\\x22')  # No, this is wrong. Let me just look for the pattern directly.

# Actually in the file, the literal bytes for \" would be: 0x5c 0x22 (backslash + double-quote)
# In a single-quoted PHP string, this is: \x5c\x22 = literal backslash + quote

# Let me search for the heading pattern
search = b'heading'  
positions = []
start = 0
while True:
    pos = raw.find(search, start)
    if pos == -1:
        break
    positions.append(pos)
    start = pos + 1

print(f"\nFound 'heading' at {len(positions)} positions")
for pos in positions:
    context = raw[max(0,pos-20):pos+50]
    # Check if this is in the JSON example (has 'level' nearby)
    if b'level' in raw[pos:pos+100]:
        print(f"  At byte {pos}: {context[:80]}")

# Now let me do the actual fix by replacing byte patterns
# Fix 1: Replace \\x5c\\x22 (literal backslash-quote) in the JSON example within single-quoted string
# We need to find the specific JSON array and remove the backslashes before quotes

# Find the single-quoted JSON array: '[{...}]'  
# It starts with b'[{' and contains b'"heading"' after removing backslashes
json_start = raw.find(b'[{\\x5c\\x22')  # Nope, the backslash is literal 0x5c

# Let me try finding the actual bytes
# The file content at line 504 is: . '[{\"heading\": \"متن عنوان\"...}]'
# In bytes: . ' [{"  heading  " :  "   متن عنوان  " ... }]' with 0x5c before each 0x22
# Let me just find it by looking for the pattern b'[{\\x5c\\x22' in the raw bytes

# Actually, in the raw file, the character sequence [{\"heading is:
# [ { \ " h e a d i n g
# Which in bytes is: 5B 7B 5C 22 68 65 61 64 69 6E 67

test = b'[{\\x22heading'  # This is [{\x22heading in bytes - NO!
# \x5c\x22 in Python literal bytes = two bytes: 0x5c, 0x22
# But b'\\x22' is three bytes: 0x5c, 0x78, 0x32, 0x32

# I need to search for the actual byte sequence
search_bytes = b'[{' + bytes([0x5c, 0x22]) + b'heading'
idx = raw.find(search_bytes)
if idx >= 0:
    print(f"\nFound JSON system prompt pattern at byte {idx}")
    print(f"  Context: {raw[idx:idx+120]}")
else:
    print("\nSearching with different approach...")
    # Maybe the file doesn't have literal backslash before quotes
    # Let me check what's at line 504
    lines_raw = raw.split(b'\n')
    if len(lines_raw) > 503:
        line504 = lines_raw[503]
        print(f"Line 504 raw bytes (first 150): {line504[:150]}")
        print(f"Line 504 as string: {line504.decode('utf-8', errors='replace')[:150]}")

print("\nDone investigating")
