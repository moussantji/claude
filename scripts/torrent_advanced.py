#!/usr/bin/env python3
"""
Advanced torrent editor script.
Handles operations not supported by torrent-edit CLI:
- Rename internal files/directories
- Alter metadata (comment, created by, creation date, source)
- Change/randomize the info hash
- Generate random .torrent files
- Split multi-file torrents into smaller parts (with optional randomization)
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

# ─────────────────────────────────────────────────────────────────────────────
# SHARED HELPERS
# ─────────────────────────────────────────────────────────────────────────────

ADJECTIVES = ['Epic', 'Dark', 'Lost', 'Final', 'Hidden', 'Secret', 'Wild', 'Neon', 'Cosmic', 'Iron',
              'Golden', 'Silent', 'Frozen', 'Blazing', 'Savage', 'Phantom', 'Eternal', 'Wicked']
NOUNS = ['Phoenix', 'Shadow', 'Storm', 'Horizon', 'Legacy', 'Empire', 'Cipher', 'Vortex', 'Titan',
         'Ronin', 'Serpent', 'Eclipse', 'Nebula', 'Raven', 'Inferno', 'Abyss', 'Cascade']
CLIENTS = ['qBittorrent 4.6.3', 'Transmission 4.0.5', 'Deluge 2.1.1', 'rTorrent 0.9.8',
           'libtorrent 2.0.9', 'uTorrent 3.6.0', 'Vuze 5.7.7.0', 'BiglyBT 3.5']
TRACKERS_POOL = [
    'udp://tracker.opentrackr.org:1337/announce',
    'udp://open.tracker.cl:1337/announce',
    'udp://tracker.openbittorrent.com:6969/announce',
    'udp://explodie.org:6969/announce',
    'udp://exodus.desync.com:6969/announce',
    'https://tracker.torrent.eu.org:443/announce',
    'udp://tracker.torrent.eu.org:451/announce',
    'udp://tracker.moeking.me:6969/announce',
    'udp://tracker.dler.org:6969/announce',
    'udp://open.stealth.si:80/announce',
]
SOURCES_POOL = ['TorrentLeech', 'IPTorrents', 'TorrentDay', 'FileList', 'HDBits',
                'PassThePopcorn', 'GazelleGames', 'Orpheus', 'RED', 'BHD', 'Blutopia']
QUALITIES = ['720p', '1080p', '2160p', '4K', 'REMUX', 'WEB-DL', 'BluRay', 'HDRip', 'DVDRip']
GROUPS = ['SPARKS', 'FGT', 'YIFY', 'RARBG', 'EVO', 'FLUX', 'NTb', 'EDITH', 'NOGRP', 'ION10']
EXTENSIONS = ['.mkv', '.mp4', '.avi', '.iso', '.zip', '.tar.gz', '.pdf', '.flac', '.mp3']
SUB_EXTENSIONS = ['.srt', '.ass', '.nfo', '.txt', '.jpg', '.png']


def random_release_name():
    """Generate a random release-style name."""
    adj = random.choice(ADJECTIVES)
    noun = random.choice(NOUNS)
    year = random.randint(2019, 2026)
    quality = random.choice(QUALITIES)
    group = random.choice(GROUPS)
    return f"{adj}.{noun}.{year}.{quality}-{group}"


def decode_val(v):
    """Decode bytes to str."""
    if isinstance(v, bytes):
        return v.decode('utf-8', errors='replace')
    return v


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


# ─────────────────────────────────────────────────────────────────────────────
# COMMANDS
# ─────────────────────────────────────────────────────────────────────────────

def cmd_rename_files(args):
    """Rename internal files in the torrent."""
    data = load_torrent(args.torrent)
    info = data.get('info', data.get(b'info', {}))

    renames = {}
    for mapping in args.rename:
        old, new = mapping.split('=', 1)
        renames[old] = new

    name_key = 'name' if 'name' in info else b'name'
    current_name = decode_val(info[name_key])

    if current_name in renames:
        new_name = renames[current_name]
        info[name_key] = new_name.encode('utf-8') if isinstance(info[name_key], bytes) else new_name
        print(f"Renamed torrent name: {current_name} -> {new_name}")

    files_key = 'files' if 'files' in info else b'files' if b'files' in info else None
    if files_key:
        for file_entry in info[files_key]:
            path_key = 'path' if 'path' in file_entry else b'path'
            path_parts = file_entry[path_key]
            full_path = '/'.join(decode_val(p) for p in path_parts)

            if full_path in renames:
                new_path = renames[full_path]
                new_parts = new_path.split('/')
                if isinstance(path_parts[0], bytes):
                    file_entry[path_key] = [p.encode('utf-8') for p in new_parts]
                else:
                    file_entry[path_key] = new_parts
                print(f"Renamed file: {full_path} -> {new_path}")
            else:
                filename = decode_val(path_parts[-1])
                if filename in renames:
                    new_filename = renames[filename]
                    if isinstance(path_parts[-1], bytes):
                        file_entry[path_key][-1] = new_filename.encode('utf-8')
                    else:
                        file_entry[path_key][-1] = new_filename
                    print(f"Renamed file: {filename} -> {new_filename}")

    save_torrent(data, args.torrent, backup=not args.no_backup)
    print(f"New info hash: {get_info_hash(data)}")


def cmd_metadata(args):
    """Alter torrent metadata fields."""
    data = load_torrent(args.torrent)

    if args.comment is not None:
        if args.comment == '':
            data.pop('comment', None)
            data.pop(b'comment', None)
            print("Removed comment")
        else:
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
    print(f"Info hash: {get_info_hash(data)}")


def cmd_change_hash(args):
    """Change the info hash by injecting random entropy."""
    data = load_torrent(args.torrent)
    info_key = 'info' if 'info' in data else b'info'
    info = data[info_key]

    old_hash = get_info_hash(data)
    print(f"Old info hash: {old_hash}")

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

    print(f"Info hash: {get_info_hash(data)}")
    print(f"Name: {decode_val(info.get('name', info.get(b'name', b'N/A')))}")

    announce = data.get('announce', data.get(b'announce'))
    if announce:
        print(f"Announce: {decode_val(announce)}")

    comment = data.get('comment', data.get(b'comment'))
    if comment:
        print(f"Comment: {decode_val(comment)}")

    created_by = data.get('created by', data.get(b'created by'))
    if created_by:
        print(f"Created by: {decode_val(created_by)}")

    creation_date = data.get('creation date', data.get(b'creation date'))
    if creation_date:
        print(f"Creation date: {creation_date}")

    source = info.get('source', info.get(b'source'))
    if source:
        print(f"Source: {decode_val(source)}")

    private = info.get('private', info.get(b'private'))
    if private:
        print(f"Private: {private}")

    files_key = 'files' if 'files' in info else b'files' if b'files' in info else None
    if files_key:
        print(f"\nFiles ({len(info[files_key])}):")
        for f in info[files_key]:
            path_key = 'path' if 'path' in f else b'path'
            length_key = 'length' if 'length' in f else b'length'
            path = '/'.join(decode_val(p) for p in f[path_key])
            print(f"  {path} ({f[length_key]} bytes)")
    else:
        length = info.get('length', info.get(b'length', 0))
        print(f"\nSingle file: {decode_val(info.get('name', info.get(b'name', b'')))} ({length} bytes)")


def cmd_split(args):
    """Split a multi-file torrent into smaller torrents (with optional randomization)."""
    data = load_torrent(args.torrent)
    info = data.get('info', data.get(b'info', {}))

    name_key = 'name' if 'name' in info else b'name'
    files_key = 'files' if 'files' in info else b'files' if b'files' in info else None
    pieces_key = 'pieces' if 'pieces' in info else b'pieces'
    piece_length_key = 'piece length' if 'piece length' in info else b'piece length'

    torrent_name = decode_val(info[name_key])
    piece_length = info[piece_length_key]
    all_pieces = info[pieces_key]
    max_size = args.max_size * 1024 * 1024 * 1024
    randomize = args.randomize

    # Single-file check
    if not files_key:
        length_key = 'length' if 'length' in info else b'length'
        file_size = info[length_key]
        if file_size <= max_size:
            print(f"Single file ({file_size / (1024**3):.2f} GB) already under {args.max_size} GB.")
        else:
            print(f"ERROR: Cannot split a single-file torrent ({file_size / (1024**3):.2f} GB).")
            sys.exit(1)
        return

    # Build file info list with byte offsets
    files_list = info[files_key]
    file_infos = []
    current_offset = 0
    for f in files_list:
        path_key = 'path' if 'path' in f else b'path'
        length_key = 'length' if 'length' in f else b'length'
        file_path = '/'.join(decode_val(p) for p in f[path_key])
        file_size = f[length_key]
        file_infos.append({
            'entry': f,
            'path': file_path,
            'size': file_size,
            'offset': current_offset,
        })
        current_offset += file_size

    total_size = current_offset

    # Group files into buckets <= max_size
    buckets = []
    current_bucket = []
    current_bucket_size = 0

    for fi in file_infos:
        if fi['size'] > max_size:
            if current_bucket:
                buckets.append(current_bucket)
                current_bucket = []
                current_bucket_size = 0
            buckets.append([fi])
        elif current_bucket_size + fi['size'] > max_size:
            buckets.append(current_bucket)
            current_bucket = [fi]
            current_bucket_size = fi['size']
        else:
            current_bucket.append(fi)
            current_bucket_size += fi['size']

    if current_bucket:
        buckets.append(current_bucket)

    if len(buckets) <= 1:
        print(f"All files fit within {args.max_size} GB. No split needed.")
        return

    # Create output directory
    output_dir = args.output_dir
    os.makedirs(output_dir, exist_ok=True)

    # Original metadata
    announce = data.get('announce', data.get(b'announce'))
    announce_list = data.get('announce-list', data.get(b'announce-list'))
    comment = data.get('comment', data.get(b'comment'))
    created_by = data.get('created by', data.get(b'created by'))

    print(f"Original: {torrent_name}")
    print(f"Total size: {total_size / (1024**3):.2f} GB")
    print(f"Total files: {len(file_infos)}")
    print(f"Splitting into {len(buckets)} parts (max {args.max_size} GB each)")
    if randomize:
        print(f"Randomization: ENABLED")
    print(f"Output: {output_dir}")
    print()

    generated_files = []

    for i, bucket in enumerate(buckets, 1):
        bucket_size = sum(fi['size'] for fi in bucket)

        # Part name
        if randomize:
            part_name = random_release_name()
        else:
            part_name = f"{torrent_name}.part{i}"

        # Extract relevant pieces
        bucket_start = bucket[0]['offset']
        bucket_end = bucket[-1]['offset'] + bucket[-1]['size']
        first_piece = bucket_start // piece_length
        last_piece = (bucket_end - 1) // piece_length
        extracted_pieces = all_pieces[first_piece * 20:(last_piece + 1) * 20]

        # Build file list
        if randomize:
            new_files = []
            for fi in bucket:
                entry = fi['entry']
                path_key = 'path' if 'path' in entry else b'path'
                length_key = 'length' if 'length' in entry else b'length'
                path_parts = entry[path_key]

                # Get original extension
                last_part = decode_val(path_parts[-1])
                ext = os.path.splitext(last_part)[-1]

                # Generate random path
                new_filename = f"{random_release_name()}{ext}"
                new_path_parts = []
                if len(path_parts) > 1:
                    folders = ['Season' + str(random.randint(1, 8)), 'Extras', 'Subs',
                               'Bonus', 'Disc' + str(random.randint(1, 4)), 'Specials', 'Featurettes']
                    for _ in path_parts[:-1]:
                        new_path_parts.append(random.choice(folders))
                new_path_parts.append(new_filename)

                if isinstance(path_parts[0], bytes):
                    new_path_parts = [p.encode('utf-8') for p in new_path_parts]

                new_entry = {}
                new_entry[path_key] = new_path_parts
                new_entry[length_key] = entry[length_key]
                new_files.append(new_entry)
        else:
            new_files = [fi['entry'] for fi in bucket]

        # Build info dict
        new_info = {
            'name': part_name,
            'piece length': piece_length,
            'pieces': extracted_pieces,
            'files': new_files,
        }

        if randomize:
            new_info['source'] = random.choice(SOURCES_POOL)
            new_info['entropy'] = ''.join(random.choices(string.ascii_letters + string.digits, k=32))
            if random.random() > 0.4:
                new_info['private'] = 1
        else:
            source = info.get('source', info.get(b'source'))
            if source:
                new_info['source'] = source
            private = info.get('private', info.get(b'private'))
            if private:
                new_info['private'] = private

        # Build torrent dict
        new_torrent = {'info': new_info}

        if randomize:
            num_trackers = random.randint(1, 4)
            selected = random.sample(TRACKERS_POOL, num_trackers)
            new_torrent['announce'] = selected[0]
            new_torrent['announce-list'] = [[t] for t in selected]
            new_torrent['comment'] = f"{random_release_name()} - uploaded"
            new_torrent['created by'] = random.choice(CLIENTS)
            new_torrent['creation date'] = int(time.time()) - random.randint(0, 86400 * 60)
        else:
            if announce:
                new_torrent['announce'] = announce
            if announce_list:
                new_torrent['announce-list'] = announce_list
            if comment:
                new_torrent['comment'] = comment
            if created_by:
                new_torrent['created by'] = created_by
            new_torrent['creation date'] = int(time.time())

        # Save
        output_filename = f"{part_name}.torrent"
        output_path = os.path.join(output_dir, output_filename)
        with open(output_path, 'wb') as f:
            f.write(bcoding.bencode(new_torrent))

        new_hash = hashlib.sha1(bcoding.bencode(new_info)).hexdigest()

        print(f"  Part {i}: {output_filename}")
        print(f"    Hash: {new_hash}")
        print(f"    Name: {part_name}")
        print(f"    Size: {bucket_size / (1024**3):.2f} GB")
        print(f"    Files ({len(bucket)}):")
        for idx, fi in enumerate(bucket):
            if randomize:
                pk = 'path' if 'path' in new_files[idx] else b'path'
                new_path = '/'.join(decode_val(p) for p in new_files[idx][pk])
                print(f"      {fi['path']}  ->  {new_path}")
            else:
                print(f"      - {fi['path']} ({fi['size'] / (1024**2):.1f} MB)")
        if randomize:
            print(f"    Source: {new_info.get('source')}")
            print(f"    Client: {new_torrent.get('created by')}")
        print()

        generated_files.append(output_path)

    print(f"Done! {len(generated_files)} torrents generated in {output_dir}")


def cmd_generate(args):
    """Generate a random .torrent file from scratch."""

    num_files = args.num_files if args.num_files else random.randint(1, 8)
    piece_length = random.choice([256 * 1024, 512 * 1024, 1024 * 1024, 2 * 1024 * 1024, 4 * 1024 * 1024])
    torrent_name = random_release_name()

    files = []
    total_length = 0

    # Main file
    main_ext = random.choice(EXTENSIONS[:4])
    main_size = random.randint(500_000_000, 15_000_000_000)
    files.append({'length': main_size, 'path': [f"{torrent_name}{main_ext}"]})
    total_length += main_size

    # Additional files
    for _ in range(num_files - 1):
        sub_ext = random.choice(SUB_EXTENSIONS)
        sub_size = random.randint(100, 5_000_000)
        if random.random() > 0.5:
            subfolder = random.choice(['Subs', 'Sample', 'Extras', 'Covers', 'Docs'])
            files.append({'length': sub_size, 'path': [subfolder, f"{torrent_name}{sub_ext}"]})
        else:
            files.append({'length': sub_size, 'path': [f"{torrent_name}{sub_ext}"]})
        total_length += sub_size

    num_pieces = (total_length + piece_length - 1) // piece_length
    pieces = os.urandom(num_pieces * 20)

    info = {
        'name': torrent_name,
        'piece length': piece_length,
        'pieces': pieces,
        'files': files,
    }

    if args.private or random.random() > 0.6:
        info['private'] = 1
    if args.source:
        info['source'] = args.source
    elif random.random() > 0.5:
        info['source'] = random.choice(SOURCES_POOL)

    num_trackers = random.randint(1, 5)
    selected_trackers = random.sample(TRACKERS_POOL, min(num_trackers, len(TRACKERS_POOL)))

    torrent_data = {
        'announce': selected_trackers[0],
        'announce-list': [[t] for t in selected_trackers],
        'comment': args.comment if args.comment else f"Generated randomly - {torrent_name}",
        'created by': args.created_by if args.created_by else random.choice(CLIENTS),
        'creation date': int(time.time()),
        'info': info,
    }

    with open(args.output, 'wb') as f:
        f.write(bcoding.bencode(torrent_data))

    info_hash = hashlib.sha1(bcoding.bencode(info)).hexdigest()
    print(f"Generated: {args.output}")
    print(f"Name: {torrent_name}")
    print(f"Info hash: {info_hash}")
    print(f"Files: {len(files)}")
    print(f"Total size: {total_length} bytes ({total_length / (1024**3):.2f} GB)")
    print(f"Pieces: {num_pieces} x {piece_length // 1024} KB")
    print(f"Trackers: {len(selected_trackers)}")
    print(f"Private: {'yes' if info.get('private') else 'no'}")
    if info.get('source'):
        print(f"Source: {info['source']}")
    print(f"Created by: {torrent_data['created by']}")


# ─────────────────────────────────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Advanced torrent editor')
    parser.add_argument('--no-backup', action='store_true', help='Skip creating .bak backup')
    subparsers = parser.add_subparsers(dest='command', help='Command to execute')

    # rename
    p = subparsers.add_parser('rename', help='Rename internal files')
    p.add_argument('torrent', help='Path to .torrent file')
    p.add_argument('--rename', '-r', action='append', required=True,
                   help='Rename mapping: old_name=new_name (repeatable)')

    # metadata
    p = subparsers.add_parser('metadata', help='Alter metadata fields')
    p.add_argument('torrent', help='Path to .torrent file')
    p.add_argument('--comment', '-c', default=None)
    p.add_argument('--created-by', default=None)
    p.add_argument('--creation-date', default=None)
    p.add_argument('--source', '-s', default=None)

    # hash
    p = subparsers.add_parser('hash', help='Randomize info hash')
    p.add_argument('torrent', help='Path to .torrent file')

    # info
    p = subparsers.add_parser('info', help='Display torrent info')
    p.add_argument('torrent', help='Path to .torrent file')

    # generate
    p = subparsers.add_parser('generate', help='Generate a random .torrent')
    p.add_argument('output', help='Output path')
    p.add_argument('--num-files', type=int, default=None)
    p.add_argument('--private', action='store_true')
    p.add_argument('--source', '-s', default=None)
    p.add_argument('--comment', '-c', default=None)
    p.add_argument('--created-by', default=None)

    # split
    p = subparsers.add_parser('split', help='Split torrent into smaller parts')
    p.add_argument('torrent', help='Path to .torrent file')
    p.add_argument('output_dir', help='Output directory')
    p.add_argument('--max-size', type=float, default=1.0, help='Max GB per part (default: 1.0)')
    p.add_argument('--randomize', action='store_true',
                   help='Randomize all metadata: file names, trackers, comment, source, hash')

    args = parser.parse_args()

    if args.command is None:
        parser.print_help()
        sys.exit(1)

    commands = {
        'rename': cmd_rename_files,
        'metadata': cmd_metadata,
        'hash': cmd_change_hash,
        'info': cmd_info,
        'generate': cmd_generate,
        'split': cmd_split,
    }
    commands[args.command](args)


if __name__ == '__main__':
    main()
