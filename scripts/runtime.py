#!/usr/bin/env python3
"""Public local-task lifecycle entrypoint"""
import sys
sys.dont_write_bytecode = True
from resource_lifecycle.cli import main

if __name__ == '__main__':
    sys.exit(main())
