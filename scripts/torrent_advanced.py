#!/usr/bin/env python3
"""
Advanced torrent editor script.
Handles operations not supported by torrent-edit CLI:
- Rename internal files/directories
- Alter metadata (comment, created by, creation date, source)
- Change/randomize the info hash (by injecting entropy into info dict)
"""

import argparse
import hashlib
import os
import random
import shutil
import string
import sys
import time

import bcoding


def load_torrent(path):
    """Load and decode a torrent file."""
    with open(path, 'rb') as f:
        return bcoding.bdecode(f.read())


def save_torrent(data, path, backup=True):
    """Save torrent data, creating a .bak backup first."""
    if backup and os.path.exists(path):
        shutil.copy2(path, path + '.bak')
    with open(path, 'wb') as f:
        f.write(bcoding.bencode(data))


def get_info_hash(data):
    """Calculate the info hash of a torrent."""
    info = data.get('info', data.get(b'info'))
    if info is None:
        return None
    return hashlib.sha1(bcoding.bencode(info)).hexdigest()


def cmd_rename_files(args):
    """Rename internal files in the torrent."""
    data = load_torrent(args.torrent)
    info = data.get('info', data.get(b'info', {}))

    # Normalize keys to strings for easier handling
    info_key = 'info' if 'info' in data else b'info'

    renames = {}
    for mapping in args.rename:
        old, new = mapping.split('=', 1)
        renames[old] = new

    # Single file torrent
    name_key = 'name' if 'name' in info else b'name'
    current_name = info[name_key]
    if isinstance(current_name, bytes):
        current_name = current_name.decode('utf-8', errors='replace')

    # Rename the torrent name (top-level directory/file name)
    if current_name in renames:
        new_name = renames[current_name]
        info[name_key] = new_name.encode('utf-8') if isinstance(info[name_key], bytes) else new_name
        print(f"Renamed torrent name: {current_name} -> {new_name}")

    # Multi-file torrent: rename individual files
    files_key = 'files' if 'files' in info else b'files' if b'files' in info else None
    if files_key:
        for file_entry in info[files_key]:
            path_key = 'path' if 'path' in file_entry else b'path'
            path_parts = file_entry[path_key]

            # Build full relative path
            full_path = '/'.join(
                p.decode('utf-8', errors='replace') if isinstance(p, bytes) else p
                for p in path_parts
            )

            if full_path in renames:
                new_path = renames[full_path]
                new_parts = new_path.split('/')
                if isinstance(path_parts[0], bytes):
                    file_entry[path_key] = [p.encode('utf-8') for p in new_parts]
                else:
                    file_entry[path_key] = new_parts
                print(f"Renamed file: {full_path} -> {new_path}")
            else:
                # Also check just the filename (last part)
                filename = path_parts[-1]
                if isinstance(filename, bytes):
                    filename = filename.decode('utf-8', errors='replace')
                if filename in renames:
                    new_filename = renames[filename]
                    if isinstance(path_parts[-1], bytes):
                        file_entry[path_key][-1] = new_filename.encode('utf-8')
                    else:
                        file_entry[path_key][-1] = new_filename
                    print(f"Renamed file: {filename} -> {new_filename}")

    save_torrent(data, args.torrent, backup=not args.no_backup)
    new_hash = get_info_hash(data)
    print(f"New info hash: {new_hash}")


def cmd_metadata(args):
    """Alter torrent metadata fields."""
    data = load_torrent(args.torrent)

    if args.comment is not None:
        if args.comment == '':
            data.pop('comment', None)
            data.pop(b'comment', None)
            print("Removed comment")
        else:
            # Remove old key variants
            data.pop('comment', None)
            data.pop(b'comment', None)
            data['comment'] = args.comment
            print(f"Set comment: {args.comment}")

    if args.created_by is not None:
        if args.created_by == '':
            data.pop('created by', None)
            data.pop(b'created by', None)
            print("Removed 'created by'")
        else:
            data.pop('created by', None)
            data.pop(b'created by', None)
            data['created by'] = args.created_by
            print(f"Set created by: {args.created_by}")

    if args.creation_date is not None:
        if args.creation_date == '0':
            data.pop('creation date', None)
            data.pop(b'creation date', None)
            print("Removed creation date")
        elif args.creation_date == 'now':
            data.pop('creation date', None)
            data.pop(b'creation date', None)
            data['creation date'] = int(time.time())
            print(f"Set creation date to now: {int(time.time())}")
        else:
            data.pop('creation date', None)
            data.pop(b'creation date', None)
            data['creation date'] = int(args.creation_date)
            print(f"Set creation date: {args.creation_date}")

    if args.source is not None:
        info = data.get('info', data.get(b'info', {}))
        if args.source == '':
            info.pop('source', None)
            info.pop(b'source', None)
            print("Removed source")
        else:
            info.pop('source', None)
            info.pop(b'source', None)
            info['source'] = args.source
            print(f"Set source: {args.source}")

    save_torrent(data, args.torrent, backup=not args.no_backup)
    new_hash = get_info_hash(data)
    print(f"Info hash: {new_hash}")


def cmd_change_hash(args):
    """Change the info hash by injecting random entropy into the info dict."""
    data = load_torrent(args.torrent)
    info_key = 'info' if 'info' in data else b'info'
    info = data[info_key]

    old_hash = get_info_hash(data)
    print(f"Old info hash: {old_hash}")

    # Method: add a random unique key to the info dict that doesn't affect the torrent content
    # Common technique: use 'entropy' or 'x_randomize' field
    entropy_key = 'entropy' if isinstance(list(info.keys())[0], str) else b'entropy'
    random_value = ''.join(random.choices(string.ascii_letters + string.digits, k=32))
    if isinstance(entropy_key, bytes):
        info[entropy_key] = random_value.encode('utf-8')
    else:
        info[entropy_key] = random_value

    save_torrent(data, args.torrent, backup=not args.no_backup)
    new_hash = get_info_hash(data)
    print(f"New info hash: {new_hash}")
    print(f"Hash changed: {old_hash != new_hash}")


def cmd_info(args):
    """Display torrent information."""
    data = load_torrent(args.torrent)
    info = data.get('info', data.get(b'info', {}))

    def decode(v):
        if isinstance(v, bytes):
            return v.decode('utf-8', errors='replace')
        return v

    print(f"Info hash: {get_info_hash(data)}")
    print(f"Name: {decode(info.get('name', info.get(b'name', b'N/A')))}")

    announce = data.get('announce', data.get(b'announce'))
    if announce:
        print(f"Announce: {decode(announce)}")

    comment = data.get('comment', data.get(b'comment'))
    if comment:
        print(f"Comment: {decode(comment)}")

    created_by = data.get('created by', data.get(b'created by'))
    if created_by:
        print(f"Created by: {decode(created_by)}")

    creation_date = data.get('creation date', data.get(b'creation date'))
    if creation_date:
        print(f"Creation date: {creation_date}")

    source = info.get('source', info.get(b'source'))
    if source:
        print(f"Source: {decode(source)}")

    private = info.get('private', info.get(b'private'))
    if private:
        print(f"Private: {private}")

    # Files
    files_key = 'files' if 'files' in info else b'files' if b'files' in info else None
    if files_key:
        print(f"\nFiles ({len(info[files_key])}):")
        for f in info[files_key]:
            path_key = 'path' if 'path' in f else b'path'
            length_key = 'length' if 'length' in f else b'length'
            path = '/'.join(decode(p) for p in f[path_key])
            size = f[length_key]
            print(f"  {path} ({size} bytes)")
    else:
        length = info.get('length', info.get(b'length', 0))
        print(f"\nSingle file: {decode(info.get('name', info.get(b'name', b'')))} ({length} bytes)")


def main():
    parser = argparse.ArgumentParser(description='Advanced torrent editor')
    parser.add_argument('--no-backup', action='store_true', help='Skip creating .bak backup')
    subparsers = parser.add_subparsers(dest='command', help='Command to execute')

    # rename command
    rename_parser = subparsers.add_parser('rename', help='Rename internal files')
    rename_parser.add_argument('torrent', help='Path to .torrent file')
    rename_parser.add_argument('--rename', '-r', action='append', required=True,
                              help='Rename mapping: old_name=new_name (can be repeated)')

    # metadata command
    meta_parser = subparsers.add_parser('metadata', help='Alter metadata fields')
    meta_parser.add_argument('torrent', help='Path to .torrent file')
    meta_parser.add_argument('--comment', '-c', default=None, help='Set comment (empty to remove)')
    meta_parser.add_argument('--created-by', default=None, help='Set "created by" (empty to remove)')
    meta_parser.add_argument('--creation-date', default=None,
                            help='Set creation date (unix timestamp, "now", or "0" to remove)')
    meta_parser.add_argument('--source', '-s', default=None, help='Set source in info dict (empty to remove)')

    # hash command
    hash_parser = subparsers.add_parser('hash', help='Change/randomize the info hash')
    hash_parser.add_argument('torrent', help='Path to .torrent file')

    # info command
    info_parser = subparsers.add_parser('info', help='Display torrent information')
    info_parser.add_argument('torrent', help='Path to .torrent file')

    args = parser.parse_args()

    if args.command is None:
        parser.print_help()
        sys.exit(1)

    if args.command == 'rename':
        cmd_rename_files(args)
    elif args.command == 'metadata':
        cmd_metadata(args)
    elif args.command == 'hash':
        cmd_change_hash(args)
    elif args.command == 'info':
        cmd_info(args)


if __name__ == '__main__':
    main()
