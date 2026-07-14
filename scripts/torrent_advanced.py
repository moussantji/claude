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


def cmd_split(args):
    """Split a multi-file torrent into smaller torrents (max size per torrent)."""
    data = load_torrent(args.torrent)
    info = data.get('info', data.get(b'info', {}))

    def decode(v):
        if isinstance(v, bytes):
            return v.decode('utf-8', errors='replace')
        return v

    # Get keys
    name_key = 'name' if 'name' in info else b'name'
    files_key = 'files' if 'files' in info else b'files' if b'files' in info else None
    pieces_key = 'pieces' if 'pieces' in info else b'pieces'
    piece_length_key = 'piece length' if 'piece length' in info else b'piece length'

    torrent_name = decode(info[name_key])
    piece_length = info[piece_length_key]
    all_pieces = info[pieces_key]

    # Max size per split (default 1 GB)
    max_size = int(args.max_size * 1024 * 1024 * 1024)  # Convert GB to bytes

    # Handle single-file torrent
    if not files_key:
        length_key = 'length' if 'length' in info else b'length'
        file_size = info[length_key]
        if file_size <= max_size:
            print(f"Single file torrent ({file_size / (1024**3):.2f} GB) is already under {args.max_size} GB. Nothing to split.")
            return
        else:
            print(f"ERROR: Single-file torrent cannot be split (the file itself is {file_size / (1024**3):.2f} GB).")
            print("Splitting requires a multi-file torrent or the actual data on disk.")
            sys.exit(1)

    # Multi-file torrent: group files into buckets of max_size
    files_list = info[files_key]

    # Build file info with offsets
    file_infos = []
    current_offset = 0
    for f in files_list:
        path_key = 'path' if 'path' in f else b'path'
        length_key = 'length' if 'length' in f else b'length'
        file_path = '/'.join(decode(p) for p in f[path_key])
        file_size = f[length_key]
        file_infos.append({
            'entry': f,
            'path': file_path,
            'size': file_size,
            'offset': current_offset,
        })
        current_offset += file_size

    total_size = current_offset

    # Group files into buckets (greedy: fill each bucket up to max_size)
    buckets = []
    current_bucket = []
    current_bucket_size = 0

    for fi in file_infos:
        # If a single file exceeds max_size, it gets its own bucket
        if fi['size'] > max_size:
            if current_bucket:
                buckets.append(current_bucket)
                current_bucket = []
                current_bucket_size = 0
            buckets.append([fi])
        elif current_bucket_size + fi['size'] > max_size:
            # Start a new bucket
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
        print(f"Total size: {total_size / (1024**3):.2f} GB, Files: {len(file_infos)}")
        return

    # Create output directory
    output_dir = args.output_dir
    os.makedirs(output_dir, exist_ok=True)

    # Get announce/metadata from original torrent
    announce = data.get('announce', data.get(b'announce'))
    announce_list = data.get('announce-list', data.get(b'announce-list'))
    comment = data.get('comment', data.get(b'comment'))
    created_by = data.get('created by', data.get(b'created by'))

    print(f"Original: {torrent_name}")
    print(f"Total size: {total_size / (1024**3):.2f} GB")
    print(f"Total files: {len(file_infos)}")
    print(f"Splitting into {len(buckets)} parts (max {args.max_size} GB each)")
    print(f"Output: {output_dir}")
    print()

    generated_files = []

    for i, bucket in enumerate(buckets, 1):
        bucket_size = sum(fi['size'] for fi in bucket)
        part_name = f"{torrent_name}.part{i}"

        # Calculate pieces for this bucket
        bucket_start = bucket[0]['offset']
        bucket_end = bucket[-1]['offset'] + bucket[-1]['size']

        # Piece indices
        first_piece = bucket_start // piece_length
        last_piece = (bucket_end - 1) // piece_length

        # Extract pieces (20 bytes each)
        extracted_pieces = all_pieces[first_piece * 20:(last_piece + 1) * 20]

        # Build new info dict
        new_info = {
            'name': part_name,
            'piece length': piece_length,
            'pieces': extracted_pieces,
            'files': [fi['entry'] for fi in bucket],
        }

        # Copy optional info fields
        source = info.get('source', info.get(b'source'))
        if source:
            new_info['source'] = source
        private = info.get('private', info.get(b'private'))
        if private:
            new_info['private'] = private

        # Build new torrent
        new_torrent = {'info': new_info}
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
        output_path = os.path.join(output_dir, f"{part_name}.torrent")
        with open(output_path, 'wb') as f:
            f.write(bcoding.bencode(new_torrent))

        new_hash = hashlib.sha1(bcoding.bencode(new_info)).hexdigest()

        print(f"  Part {i}: {part_name}.torrent")
        print(f"    Hash: {new_hash}")
        print(f"    Size: {bucket_size / (1024**3):.2f} GB")
        print(f"    Files ({len(bucket)}):")
        for fi in bucket:
            print(f"      - {fi['path']} ({fi['size'] / (1024**2):.1f} MB)")
        print()

        generated_files.append(output_path)

    print(f"Done! {len(generated_files)} torrents generated in {output_dir}")


def cmd_generate(args):
    """Generate a random .torrent file from scratch."""

    # Random name generation helpers
    adjectives = ['Epic', 'Dark', 'Lost', 'Final', 'Hidden', 'Secret', 'Wild', 'Neon', 'Cosmic', 'Iron',
                  'Golden', 'Silent', 'Frozen', 'Blazing', 'Savage', 'Phantom', 'Eternal', 'Wicked']
    nouns = ['Phoenix', 'Shadow', 'Storm', 'Horizon', 'Legacy', 'Empire', 'Cipher', 'Vortex', 'Titan',
             'Ronin', 'Serpent', 'Eclipse', 'Nebula', 'Raven', 'Inferno', 'Abyss', 'Cascade']
    extensions = ['.mkv', '.mp4', '.avi', '.iso', '.zip', '.tar.gz', '.pdf', '.flac', '.mp3']
    sub_extensions = ['.srt', '.ass', '.nfo', '.txt', '.jpg', '.png']
    clients = ['qBittorrent 4.6.3', 'Transmission 4.0.5', 'Deluge 2.1.1', 'rTorrent 0.9.8',
               'libtorrent 2.0.9', 'uTorrent 3.6.0', 'Vuze 5.7.7.0', 'BiglyBT 3.5']
    trackers_pool = [
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
    sources_pool = ['TorrentLeech', 'IPTorrents', 'TorrentDay', 'FileList', 'HDBits',
                    'PassThePopcorn', 'Empornium', 'GazelleGames', 'Orpheus', 'RED']
    qualities = ['720p', '1080p', '2160p', '4K', 'REMUX', 'WEB-DL', 'BluRay', 'HDRip', 'DVDRip']
    groups = ['SPARKS', 'FGT', 'YIFY', 'RARBG', 'EVO', 'FLUX', 'NTb', 'EDITH', 'NOGRP', 'ION10']

    # Generate random torrent name
    adj = random.choice(adjectives)
    noun = random.choice(nouns)
    quality = random.choice(qualities)
    group = random.choice(groups)
    year = random.randint(2018, 2026)
    torrent_name = f"{adj}.{noun}.{year}.{quality}-{group}"

    # Generate random files
    num_files = args.num_files if args.num_files else random.randint(1, 8)
    piece_length = random.choice([256 * 1024, 512 * 1024, 1024 * 1024, 2 * 1024 * 1024, 4 * 1024 * 1024])

    files = []
    total_length = 0

    # Main file
    main_ext = random.choice(extensions[:4])  # video/iso
    main_size = random.randint(500_000_000, 15_000_000_000)
    files.append({'length': main_size, 'path': [f"{torrent_name}{main_ext}"]})
    total_length += main_size

    # Additional files
    for i in range(num_files - 1):
        sub_ext = random.choice(sub_extensions)
        sub_size = random.randint(100, 5_000_000)
        # Some files in subdirectories
        if random.random() > 0.5:
            subfolder = random.choice(['Subs', 'Sample', 'Extras', 'Covers', 'Docs'])
            filename = f"{torrent_name}{sub_ext}"
            files.append({'length': sub_size, 'path': [subfolder, filename]})
        else:
            filename = f"{torrent_name}{sub_ext}"
            files.append({'length': sub_size, 'path': [filename]})
        total_length += sub_size

    # Calculate number of pieces needed
    num_pieces = (total_length + piece_length - 1) // piece_length
    # Generate random piece hashes (20 bytes each = SHA1)
    pieces = os.urandom(num_pieces * 20)

    # Build info dict
    info = {
        'name': torrent_name,
        'piece length': piece_length,
        'pieces': pieces,
        'files': files,
    }

    # Optionally add private flag
    if args.private or random.random() > 0.6:
        info['private'] = 1

    # Optionally add source
    if args.source:
        info['source'] = args.source
    elif random.random() > 0.5:
        info['source'] = random.choice(sources_pool)

    # Build torrent dict
    num_trackers = random.randint(1, 5)
    selected_trackers = random.sample(trackers_pool, min(num_trackers, len(trackers_pool)))

    torrent_data = {
        'announce': selected_trackers[0],
        'announce-list': [[t] for t in selected_trackers],
        'comment': args.comment if args.comment else f"Generated randomly - {torrent_name}",
        'created by': args.created_by if args.created_by else random.choice(clients),
        'creation date': int(time.time()),
        'info': info,
    }

    # Save
    output_path = args.output
    with open(output_path, 'wb') as f:
        f.write(bcoding.bencode(torrent_data))

    info_hash = hashlib.sha1(bcoding.bencode(info)).hexdigest()
    print(f"Generated: {output_path}")
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

    # generate command
    gen_parser = subparsers.add_parser('generate', help='Generate a random .torrent file')
    gen_parser.add_argument('output', help='Output path for the generated .torrent file')
    gen_parser.add_argument('--num-files', type=int, default=None, help='Number of files (random if not set)')
    gen_parser.add_argument('--private', action='store_true', help='Force private flag')
    gen_parser.add_argument('--source', '-s', default=None, help='Set source field')
    gen_parser.add_argument('--comment', '-c', default=None, help='Set comment')
    gen_parser.add_argument('--created-by', default=None, help='Set "created by" field')

    # split command
    split_parser = subparsers.add_parser('split', help='Split a multi-file torrent into smaller torrents')
    split_parser.add_argument('torrent', help='Path to .torrent file to split')
    split_parser.add_argument('output_dir', help='Output directory for split torrent files')
    split_parser.add_argument('--max-size', type=float, default=1.0,
                             help='Maximum size per torrent in GB (default: 1.0)')

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
    elif args.command == 'generate':
        cmd_generate(args)
    elif args.command == 'split':
        cmd_split(args)


if __name__ == '__main__':
    main()
