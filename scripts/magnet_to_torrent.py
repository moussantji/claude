#!/usr/bin/env python3
"""
Resolve a magnet link to a .torrent file using libtorrent.
Connects to DHT/peers, downloads metadata, saves as .torrent.

Usage:
    python3 magnet_to_torrent.py "magnet:?xt=urn:btih:..." /path/output.torrent [timeout_seconds]
"""

import sys
import time
import os

import libtorrent as lt


def magnet_to_torrent(magnet_uri: str, output_path: str, timeout: int = 120) -> bool:
    """
    Resolve a magnet link and save the .torrent file.
    
    Args:
        magnet_uri: The magnet link
        output_path: Where to save the .torrent file
        timeout: Max seconds to wait for metadata (default 120)
    
    Returns:
        True if successful, False if timed out
    """
    # Create session with DHT enabled
    settings = {
        'enable_dht': True,
        'enable_lsd': True,
        'enable_natpmp': True,
        'enable_upnp': True,
        'listen_interfaces': '0.0.0.0:6881',
        'alert_mask': lt.alert.category_t.status_notification,
        'dht_bootstrap_nodes': 'router.bittorrent.com:6881,dht.transmissionbt.com:6881,router.utorrent.com:6881,dht.aelitis.com:6881',
    }

    ses = lt.session(settings)

    # Parse magnet link
    params = lt.parse_magnet_uri(magnet_uri)
    params.save_path = '/tmp'  # Won't actually download data
    params.flags |= lt.torrent_flags.upload_mode  # Don't download anything

    # Add torrent
    handle = ses.add_torrent(params)

    print(f"Resolving magnet link...", flush=True)
    print(f"Info hash: {handle.info_hash()}", flush=True)

    # Wait for metadata
    start_time = time.time()
    while not handle.status().has_metadata:
        elapsed = time.time() - start_time
        if elapsed > timeout:
            print(f"\nERROR: Timeout after {timeout}s. Could not resolve metadata.", flush=True)
            ses.remove_torrent(handle)
            return False

        # Print progress
        s = handle.status()
        print(f"\r  Waiting for metadata... {int(elapsed)}s (peers: {s.num_peers}, DHT nodes: {ses.status().dht_nodes})", end='', flush=True)
        time.sleep(1)

    print(f"\n  Metadata received!", flush=True)

    # Get torrent info
    torrent_info = handle.torrent_file()

    # Create .torrent file
    ct = lt.create_torrent(torrent_info)
    entry = ct.generate()

    # Add trackers from magnet link
    for tracker in handle.trackers():
        entry[b'announce'] = tracker['url'].encode()
        break

    # Save to file
    os.makedirs(os.path.dirname(output_path) or '.', exist_ok=True)
    with open(output_path, 'wb') as f:
        f.write(lt.bencode(entry))

    # Print info
    print(f"  Name: {torrent_info.name()}", flush=True)
    print(f"  Size: {torrent_info.total_size()} bytes ({torrent_info.total_size() / (1024**3):.2f} GB)", flush=True)
    print(f"  Files: {torrent_info.num_files()}", flush=True)
    print(f"  Saved to: {output_path}", flush=True)

    # Cleanup
    ses.remove_torrent(handle)
    return True


def main():
    if len(sys.argv) < 3:
        print("Usage: magnet_to_torrent.py <magnet_uri> <output_path> [timeout]")
        sys.exit(1)

    magnet_uri = sys.argv[1]
    output_path = sys.argv[2]
    timeout = int(sys.argv[3]) if len(sys.argv) > 3 else 120

    if not magnet_uri.startswith('magnet:'):
        print("ERROR: Not a valid magnet link")
        sys.exit(1)

    success = magnet_to_torrent(magnet_uri, output_path, timeout)
    sys.exit(0 if success else 1)


if __name__ == '__main__':
    main()
