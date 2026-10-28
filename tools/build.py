#!/usr/bin/env python3
"""Build licensed, identity-free Store Kit packages from an explicit dependency lock."""
from __future__ import annotations
import argparse
import hashlib
import json
import os
import shutil
import sys
import urllib.parse
import urllib.request
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
WORK = ROOT / '.work'
VENDOR = WORK / 'vendor'
LOCK = ROOT / 'dependencies.lock.json'
VERSION = '1.0.0'
# Optional private denylist lives outside the deliverable; never embed a client's identity in tooling.
FORBIDDEN = tuple(json.loads(Path(os.environ['STORE_KIT_AUDIT_DENYLIST']).read_text(encoding='utf-8'))) if os.environ.get('STORE_KIT_AUDIT_DENYLIST') else ()


def download(url: str) -> bytes:
    request = urllib.request.Request(url, headers={'User-Agent': 'StoreKitBuilder/1.0 (+WordPress)'} )
    with urllib.request.urlopen(request, timeout=180) as response:
        if not response.url.startswith('https://'):
            raise ValueError('Only HTTPS dependency downloads are allowed')
        return response.read()


def api(url: str) -> dict:
    return json.loads(download(url))


def plugin_info(slug: str) -> dict:
    query = urllib.parse.urlencode({'action': 'plugin_information', 'request[slug]': slug})
    return api('https://api.wordpress.org/plugins/info/1.2/?' + query)


def dependency_versions() -> dict:
    offers = api('https://api.wordpress.org/core/version-check/1.7/')['offers']
    wp = next((o for o in offers if o.get('response') == 'upgrade'), offers[0])
    woo = plugin_info('woocommerce')
    sqlite = plugin_info('sqlite-database-integration')
    blocksy = api('https://api.wordpress.org/themes/info/1.2/?' + urllib.parse.urlencode({'action': 'theme_information', 'request[slug]': 'blocksy'}))
    cli = api('https://api.github.com/repos/wp-cli/wp-cli/releases/latest')
    return {
        'wordpress': {'version': wp.get('version', wp.get('current')), 'url': wp['download'], 'license': 'GPL-2.0-or-later', 'archive': 'wordpress.zip'},
        'woocommerce': {'version': woo['version'], 'url': woo['download_link'], 'license': 'GPL-3.0-or-later', 'archive': 'woocommerce.zip'},
        'blocksy': {'version': blocksy['version'], 'url': blocksy['download_link'], 'license': 'GPL-2.0-or-later', 'archive': 'blocksy.zip'},
        'sqlite-database-integration': {'version': sqlite['version'], 'url': sqlite['download_link'], 'license': 'GPL-2.0-or-later', 'archive': 'sqlite-database-integration.zip'},
        'wp-cli': {'version': cli['tag_name'].lstrip('v'), 'url': 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar', 'license': 'MIT', 'archive': 'wp-cli.phar'},
    }


def fetch_dependencies(refresh: bool = False) -> dict:
    VENDOR.mkdir(parents=True, exist_ok=True)
    if refresh:
        deps = dependency_versions()
        for name, item in deps.items():
            if not str(item['url']).startswith('https://'):
                raise ValueError(f'{name}: dependency must use HTTPS')
            print(f'Downloading {name} {item["version"]}', flush=True)
            content = download(item['url'])
            item['sha256'] = hashlib.sha256(content).hexdigest()
            (VENDOR / item['archive']).write_bytes(content)
        lock = {'format': 1, 'kit_version': VERSION, 'dependencies': deps}
        LOCK.write_text(json.dumps(lock, indent=2) + '\n', encoding='utf-8')
    else:
        if not LOCK.is_file():
            raise ValueError('No dependency lock. Run build.py --refresh-lock --fetch-only first.')
        lock = json.loads(LOCK.read_text(encoding='utf-8'))
        for name, item in lock['dependencies'].items():
            file = VENDOR / item['archive']
            if not file.is_file():
                print(f'Downloading locked {name} {item["version"]}', flush=True)
                file.write_bytes(download(item['url']))
            if hashlib.sha256(file.read_bytes()).hexdigest() != item['sha256']:
                raise ValueError(f'{name}: checksum does not match lock; remove the bad cached file and retry')
    return lock


def extract_zip(file: Path, destination: Path) -> None:
    destination.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(file) as archive:
        for member in archive.infolist():
            relative = Path(member.filename)
            target = (destination / relative).resolve()
            if relative.is_absolute() or not target.is_relative_to(destination.resolve()) or (member.external_attr >> 16) & 0o170000 == 0o120000:
                raise ValueError(f'Unsafe dependency archive member: {member.filename}')
        archive.extractall(destination)


def package_tree(source: Path, target: Path, prefix: str) -> None:
    with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
        for file in sorted(source.rglob('*')):
            if file.is_file() and '__pycache__' not in file.parts and file.suffix != '.pyc':
                relative = file.relative_to(source)
                info = zipfile.ZipInfo((Path(prefix) / relative).as_posix(), (2026, 1, 1, 0, 0, 0))
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = 0o100644 << 16
                archive.writestr(info, file.read_bytes())


def audit_text(root: Path) -> None:
    for file in root.rglob('*'):
        if not file.is_file():
            continue
        if file.name in ('wp-config.php', '.env') or file.suffix in ('.sql', '.sqlite', '.sqlite3'):
            raise ValueError(f'Instance data must not be distributed: {file}')
        if file.suffix.lower() in ('.php', '.json', '.js', '.css', '.md', '.txt', '.html', '.xml', '.svg'):
            text = file.read_text(encoding='utf-8', errors='ignore')
            for value in FORBIDDEN:
                if value.casefold() in text.casefold():
                    raise ValueError(f'Client identity found in distributable: {file} ({value})')


def build(lock: dict) -> None:
    dist = ROOT / 'dist'
    dist.mkdir(exist_ok=True)
    for name, source in (
        ('store-kit', ROOT / 'components/plugins/store-kit'),
        ('store-kit-veterinary', ROOT / 'components/plugins/store-kit-veterinary'),
        ('store-kit-child', ROOT / 'components/themes/store-kit-child'),
    ):
        if not source.is_dir():
            raise ValueError(f'Missing component: {source}')
        audit_text(source)
        package_tree(source, dist / f'{name}-{VERSION}.zip', name)
    stage = WORK / 'template-build'
    if stage.exists():
        shutil.rmtree(stage)
    stage.mkdir(parents=True)
    extract_zip(VENDOR / lock['dependencies']['wordpress']['archive'], stage)
    site = stage / 'wordpress'
    # Default vendor content is not part of the Store Kit foundation.
    for directory in (site / 'wp-content/plugins', site / 'wp-content/themes'):
        if directory.exists():
            shutil.rmtree(directory)
        directory.mkdir(parents=True)
    for name, directory in (('woocommerce', 'plugins'), ('sqlite-database-integration', 'plugins'), ('blocksy', 'themes')):
        extract_zip(VENDOR / lock['dependencies'][name]['archive'], site / 'wp-content' / directory)
    shutil.copytree(ROOT / 'components/plugins', site / 'wp-content/plugins', dirs_exist_ok=True)
    shutil.copytree(ROOT / 'components/themes', site / 'wp-content/themes', dirs_exist_ok=True)
    recipe = site / 'store-kit'
    recipe.mkdir()
    shutil.copytree(ROOT / 'tools', recipe / 'tools', ignore=shutil.ignore_patterns('__pycache__', '*.pyc', 'build.py'))
    shutil.copy2(VENDOR / 'wp-cli.phar', recipe / 'tools/wp-cli.phar')
    shutil.copy2(LOCK, recipe / 'dependencies.lock.json')
    for name in ('README.md', 'STYLE-RULES.md', 'CHANGELOG.md', 'LICENSE', 'THIRD-PARTY-NOTICES.md'):
        if not (ROOT / name).is_file():
            raise ValueError(f'Missing handoff/license document: {name}')
        shutil.copy2(ROOT / name, recipe / name)
    # Only fresh core and vendored code plus declarative seed. Never clone a runtime DB/config/uploads.
    for forbidden in ('wp-config.php', 'wp-content/uploads', 'wp-content/database', '.store-kit-instance.json'):
        if (site / forbidden).exists():
            raise ValueError(f'Unexpected instance data in template: {forbidden}')
    audit_text(site / 'wp-content/plugins/store-kit')
    audit_text(site / 'wp-content/plugins/store-kit-veterinary')
    audit_text(site / 'wp-content/themes/store-kit-child')
    package_tree(site, dist / f'wordpress-store-kit-{VERSION}.zip', 'wordpress-store-kit')
    source_stage = WORK / 'source-build'
    if source_stage.exists():
        shutil.rmtree(source_stage)
    source_stage.mkdir()
    for name in ('components', 'tools'):
        shutil.copytree(ROOT / name, source_stage / name, ignore=shutil.ignore_patterns('__pycache__', '*.pyc'))
    for name in ('README.md', 'STYLE-RULES.md', 'CHANGELOG.md', 'LICENSE', 'THIRD-PARTY-NOTICES.md', 'dependencies.lock.json', '.gitignore'):
        shutil.copy2(ROOT / name, source_stage / name)
    audit_text(source_stage)
    audit_text(recipe)
    package_tree(source_stage, dist / f'store-kit-source-{VERSION}.zip', 'store-kit')
    report = {'kit_version': VERSION, 'dependencies': lock['dependencies'], 'artifacts': {}}
    for file in sorted(dist.glob('*.zip')):
        report['artifacts'][file.name] = {'sha256': hashlib.sha256(file.read_bytes()).hexdigest(), 'bytes': file.stat().st_size}
    (dist / 'manifest.json').write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(f'Built {len(report["artifacts"])} archives in {dist}')


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--refresh-lock', action='store_true', help='Resolve current official dependency versions and replace the lock deliberately')
    parser.add_argument('--fetch-only', action='store_true', help='Download/verify dependencies without building')
    args = parser.parse_args()
    try:
        lock = fetch_dependencies(args.refresh_lock)
        if not args.fetch_only:
            build(lock)
        return 0
    except (OSError, ValueError, KeyError, zipfile.BadZipFile) as error:
        print(f'Build failed: {error}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
