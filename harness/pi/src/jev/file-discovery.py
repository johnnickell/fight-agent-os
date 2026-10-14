"""Enumerate a held macOS directory descriptor without shell or source execution"""
import fnmatch
import json
import os
import stat
import sys


def matches(parts, pattern):
    states = {0}

    def expand():
        for index in range(len(pattern)):
            if index in states and pattern[index] == '**':
                states.add(index + 1)

    expand()
    for part in parts:
        following = set()
        for index in states:
            if index < len(pattern):
                if pattern[index] == '**':
                    following.add(index)
                elif fnmatch.fnmatchcase(part, pattern[index]):
                    following.add(index + 1)
        states = following
        expand()
    return len(pattern) in states


def enumerate_files(request):
    root = request['root']
    pattern = request['pattern']
    candidates = []
    skips = {}
    visited = 0
    complete = True
    reason = None
    stack = []

    def skip(code):
        nonlocal complete
        skips[code] = skips.get(code, 0) + 1
        complete = False

    def excluded(path):
        components = path.split('/')
        name = components[-1].lower()
        return (any(path == p or path.startswith(p.rstrip('/') + '/') for p in request['excludedPaths'])
                or any(p in request['excludedNames'] or p in ['.git', '.ssh', '.aws', '.gnupg'] for p in components)
                or name == '.env' or name.startswith('.env.') or name == 'auth.json'
                or any(name.endswith(suffix) for suffix in ['.pem', '.key', '.p12', '.pfx'])
                or any(name == prefix or name.startswith(prefix + '.') for prefix in ['id_rsa', 'id_ed25519']))

    def push(fd, trail):
        # os.scandir(fd) uses fdopendir; it duplicates the supplied held descriptor.
        try:
            iterator = os.scandir(fd)
        except OSError:
            os.close(fd)
            raise
        stack.append((fd, iterator, trail))

    try:
        push(os.dup(3), [])
        while stack:
            if visited >= 10000 or len(candidates) >= 50:
                complete = False
                reason = 'enumeration-limit' if visited >= 10000 else 'file-count-limit'
                break
            fd, iterator, trail = stack[-1]
            try:
                entry = next(iterator)
            except StopIteration:
                iterator.close()
                os.close(fd)
                stack.pop()
                continue
            visited += 1
            child_trail = trail + [entry.name]
            path = os.path.join(root, *child_trail)
            if entry.is_symlink():
                skip('symlink-denied')
                continue
            if entry.is_dir(follow_symlinks=False):
                if excluded(path):
                    skip('sensitive-file')
                elif len(stack) >= 128:
                    skip('directory-depth-limit')
                elif '**' in pattern or len(child_trail) < len(pattern):
                    try:
                        # Child lookup stays relative to its held parent and never follows a symlink.
                        child = os.open(entry.name, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=fd)
                        push(child, child_trail)
                    except OSError:
                        skip('enumeration-unavailable')
            elif matches(child_trail, pattern):
                if excluded(path):
                    skip('sensitive-file')
                elif len(os.fsencode(path)) > 4096:
                    skip('path-display-limit')
                else:
                    candidates.append(path)
    except OSError:
        complete = False
        reason = 'enumeration-unavailable'
    finally:
        for fd, iterator, _trail in stack:
            iterator.close()
            os.close(fd)
    return {'paths': candidates, 'visitedEntries': visited, 'skips': skips, 'complete': complete, 'reason': reason}


if __name__ == '__main__':
    payload = sys.stdin.buffer.read(65537)
    if len(payload) > 65536 or not stat.S_ISDIR(os.fstat(3).st_mode):
        sys.exit(1)
    result = enumerate_files(json.loads(payload))
    encoded = json.dumps(result, ensure_ascii=True).encode('utf-8')
    if len(encoded) > 65536:
        sys.exit(1)
    sys.stdout.buffer.write(encoded)
