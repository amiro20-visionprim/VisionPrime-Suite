import os

path = 'app/Domains/Ai/Services/AiGateway.php'
with open(path, 'rb') as f:
    raw = f.read()

needle = b'{$guardrailRules}";

        $user'
idx = raw.find(needle)
if idx < 0:
    needle = b'{$guardrailRules}";

        $user'
    idx = raw.find(needle)

if idx < 0:
    print('NEEDLE NOT FOUND')
else:
    print(f'Found at index {idx}')
    
    r = b'{$guardrailRules}"
'
    r += b"        . ($customInstructions !== '' ? "

=== Ø¯Ø³ØªÙØ±Ø§Øª ÙÛÚÙ ÙØ§Ø±Ø¨Ø± ===
" . $customInstructions : '')
"
    r += b"        . ($userWordCount > 0 ? "

--- ØªØ¹Ø¯Ø§Ø¯ ÙÙÙØ§Øª ÙÙØ±Ø¯ ÙØ¸Ø±: " . $userWordCount . " ÙÙÙÙ ---" : '')
"
    r += b"        ;

        $user"
    
    new = raw[:idx] + r + raw[idx+len(needle):]
    with open(path, 'wb') as f:
        f.write(new)
    print('Fix 3 applied!')
