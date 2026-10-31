#!/usr/bin/env python3
"""Checks that every '**File `path`**' code block of the lesson pages is identical to the file of the checkpoint
of that lesson (wiki/NN-*.md -> checkpoints/lesson-NN/path, wiki/A-*.md -> checkpoints/appendix-a/path). Run from
the repository root:
    python3 tools/check-pages.py            # pages in wiki/
    python3 tools/check-pages.py ../caligrafy-quill-course.wiki
Exit code 1 when a block is missing from or differs from its checkpoint."""
import glob, os, re, sys
root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
pages = sys.argv[1] if len(sys.argv) > 1 else os.path.join(root, 'wiki')
ck = os.path.join(root, 'checkpoints')
bad = checked = 0
for page in sorted(glob.glob(os.path.join(pages, '[0-9][0-9]-*.md')) + glob.glob(os.path.join(pages, '[A-Z]-*.md'))):
    name = os.path.basename(page)
    lesson = 'appendix-' + name[0].lower() if name[1] == '-' else 'lesson-' + name[:2]
    text = open(page).read()
    for m in re.finditer(r'\*\*File `([^`]+)`\*\*[^\n]*\n\n```[a-z]*\n(.*?)\n```', text, flags=re.S):
        path, code = m.group(1), m.group(2)
        f = os.path.join(ck, lesson, path)
        if not os.path.exists(f):   # the SPA files may be named relative to public/bookshelf-spa
            f = os.path.join(ck, lesson, 'public/bookshelf-spa', path)
        checked += 1
        if not os.path.exists(f):
            print(f'MISSING {lesson}/{path} ({os.path.basename(page)})'); bad += 1
        elif open(f).read().rstrip('\n') != code:
            print(f'DIFFERS {lesson}/{path} ({os.path.basename(page)})'); bad += 1
print(f'{checked} file blocks checked, {bad} problem(s)')
sys.exit(1 if bad else 0)
